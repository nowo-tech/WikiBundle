# High load

Since 1.37.0 (Beacon spec 239) the wiki is built for many concurrent requests on long-running workers: FrankenPHP
worker mode with the kernel **not** reset between requests, RoadRunner, Messenger consumers.

## Table of contents

- [One unit of work per write](#one-unit-of-work-per-write)
- [Your entity manager is yours](#your-entity-manager-is-yours)
- [Transient failures and conflicts: 409](#transient-failures-and-conflicts-409)
- [Favourites, recents and backlinks (1.45)](#favourites-recents-and-backlinks-145)
- [Races that no longer happen](#races-that-no-longer-happen)
- [One lock order](#one-lock-order)
- [Exact ids](#exact-ids)
- [Database settings](#database-settings)
- [Workers](#workers)
- [Tests](#tests)

## One unit of work per write

Every wiki write runs on the bundle's own runner, `nowo_wiki.transaction_runner`
(`Nowo\WikiBundle\Transaction\WikiTransactionRunner`, built on nowo-tech/transaction-doctrine and implementing
nowo-tech/transaction-contract's `TransactionRunnerInterface`):

- one transaction per unit of work (a savepoint inside a transaction your code opened). The wiki's manager is flushed
  inside it and the runner commits; the bundle never flushes inside a transaction it did not open;
- after any failure no transaction of the bundle stays open. The levels it opened are rolled back, and the connection
  is closed only when that rollback fails (after a MySQL / MariaDB deadlock inside a nested flush the server dropped the
  savepoints, so `ROLLBACK TO SAVEPOINT` fails with 1305). The worker's next request never inherits a nested connection;
- side effects follow the unit: page, comment, link, attachment and trash events, co-editing bookkeeping
  (`nowo_wiki.page.written`), Mercure updates. A unit that fails announces nothing; a unit that succeeds is announced
  once (a listener that fails afterwards is logged and does not undo it, through `WikiEventDeferral`). When the unit
  is the outermost transaction, that is after the commit. **Inside a transaction of yours** the wiki's unit is a
  savepoint, and its events are dispatched when that savepoint is released, not when you commit: if you then roll back,
  listeners have heard of rows that do not exist (and an uploaded file stays in storage). Call the wiki outside your
  transaction, or hold its events yourself until you commit (audit QN-L5; the same as in 1.36).

HTTP writes make one attempt: a transient failure answers 409 + `Retry-After` (503 with the policy opt-in) and the client retries. `runRetrying()` (outermost
transaction only, three attempts, full-jitter backoff) is there for replayable background units of your own; no wiki
service calls it.

## Your entity manager is yours

The wiki never runs its writes through a runner your application binds to `TransactionRunnerInterface`
(DoctrineDeadlockRetryBundle's adapter, your own): such a runner drives your manager. It is injected into the wiki
services by its class.

- **Private entity manager** (`database.private_entity_manager: true`, the only mode in 2.0): the wiki's writes never
  touch your manager at all. After a failure the wiki forgets what its unit loaded or wrote; a manager closed by a
  failed flush is created again on its next call.
- **Shared manager** (the default, `database.entity_manager`): the unit runs in a connection-level transaction. After
  a failure only the wiki rows the unit added, wrote, changed or scheduled for deletion are forgotten; your entities
  stay managed and your pending changes stay pending. A failed flush closes the manager (Doctrine does): it **stays
  closed for the rest of the request** (your next flush fails loudly; a wiki write answers 409) and is replaced at the
  next request, message or command boundary. Until 1.36 the wiki reset it in the middle of the request, which detached
  every entity you had loaded. In this mode a wiki unit still writes what you left pending in the shared manager, as in
  1.36; the co-editing log and the search backfill no longer do (they write through DBAL). When a wiki unit that
  flushed changes of yours then fails (a later deadlock or lock wait, a failed COMMIT, a `postFlush` listener that
  throws), the database rolled them back while Doctrine still takes them as written: the wiki **closes the manager**
  (audit QN-M1), so your next flush fails loudly instead of silently writing nothing.

The connection is shared: when your code opens a transaction around a wiki call, the wiki's unit nests in it as a
savepoint, and a wiki failure rolls back to that savepoint only. A transient failure inside your transaction is not
replayed by the wiki (the server may have rolled back your work too): it reaches you as `TransientStorageFailure`
(or, from a page save, as `WikiRevisionCollisionException` with it as previous); retry your whole transaction.

## Transient failures and conflicts: 409

On the wiki's routes (a controller of the bundle, or a route of the REST API), `WikiStorageFailureSubscriber`
(`kernel.exception`) maps:

| Failure | REST API | Internal JSON endpoints | Pages |
|---------|----------|-------------------------|-------|
| `TransientStorageFailure`, or a DBAL `RetryableException` (deadlock, lock wait timeout) | `409` `{"err", "ECODE": "SERVER_002"}` + `Retry-After` | `409` `{"error": "unavailable"}` + `Retry-After` | `HttpException` 409 (your error page) |
| A unique key refused a concurrent write (a page or space slug taken meanwhile) | `409` `{"ECODE": "WIKI_CONFLICT"}` | `409` `{"error": "conflict"}` | `ConflictHttpException` |

Since 1.42 the status is the shared transaction-doctrine policy's (409; `nowo_transaction_doctrine.conflict_response.status: 503` keeps 503), and a conflict that already committed (`ConcurrencyConflict`, `committed: true`) gets no `Retry-After`. The REST API and the pages then carry the reload text ("Part of your change may have been saved. Reload before trying again."); the internal JSON endpoints keep `{"error": "unavailable"}` byte for byte, so the missing `Retry-After` is their only signal. A REST write sent with an `Idempotency-Key` keeps its claim in that case: a retry replays the answer instead of writing again. The storage error stays the previous exception, for your logs: no SQL or parameter reaches the client. A page save
that loses a race keeps its 1.22+ answer (the form again with the text, or `409 WIKI_SAVE_COLLISION` + `Retry-After`
in the API). An idempotent API write whose store failed answers `503 IDEMP_004` with `Retry-After`; a 503 releases its
`Idempotency-Key` claim, so the retry runs.

The subscriber looks down the exception chain, so a decorator of a wiki service that wraps the failure keeps the
answer. Your own controllers that call the wiki services map `TransientStorageFailure` themselves.

## Favourites, recents and backlinks (1.45)

The write paths added in 1.45 follow REQ-RUNTIME-003 rule 6 with the conflict codes of transaction-doctrine 1.6:

| Write path | Transaction | Control | Conflict answer |
|------------|-------------|---------|-----------------|
| Favourite toggle (`POST …/favourite`) | One runner unit on the connection (DBAL; nothing flushed) | Unique key `(user_id, page_id)`: a concurrent add of the same person is answered as done (`unchanged`), never a conflict; the `favourites.max` count is read in the unit | Lock conflict: `409` + `X-Conflict-Code: CONFLICT_001` + `Retry-After` (nothing committed), or `409` + `CONFLICT_002` without `Retry-After` (an earlier unit of the same request committed: request watermark). JSON `{"error": "conflict", "code": …}`, else plain text |
| Page visit (page view, `recents.enabled`) | One runner unit on the connection; at most 50 stale visits deleted per visit | Unique key `(user_id, page_id)`: a concurrent first visit becomes an update | None: the page view is a read. A lock conflict is logged and the page is shown (inside a transaction of yours it propagates) |
| Backlink index (after a save, `backlinks.enabled`; `wiki:backlinks:rebuild`) | One runner unit on the connection per page | The source page row is locked (`SELECT … FOR UPDATE`) and its current revision read under the lock: the last writer indexes the latest revision | None for the save (already committed): a lock conflict is logged; the next save or a rebuild fixes the index (inside a transaction of yours it propagates) |

Unique violations and lost connections are never mapped to a conflict code. Lock order: the index unit takes only the
page row; favourite and visit units take only their own rows. Residual: two concurrent adds of one person can pass
`favourites.max` by one (the count is not locked); two concurrent visits can leave one visit more than `recents.keep`
until the next visit.

## Races that no longer happen

Each one has a test that fails on 1.36.0 (see [Tests](#tests)).

| Before 1.37 | Since 1.37 |
|-------------|------------|
| Two saves of one page without a base revision (the edit form, imports) took the same next number: one failed with a collision, or the page was left pointing at the older revision | The save locks the page row and reads it again; the number follows the current revision; the revision and the page's pointer commit together |
| A page archived or trashed by another request after this one loaded it was still edited | The save reads the page again under the lock and refuses it |
| Two requests archiving (or bringing back) one page both announced it | Judged on the locked row: written and announced once |
| A failure between a new page and its first revision left a page without a revision (its slug taken for good) | The page, its revision and the pointer are one unit |
| The same link sent twice at once failed on the unique key (500) and was announced twice | Looked up again under the page lock: stored and announced once |
| Two replies to one thread got the same position (PostgreSQL) or deadlocked (MySQL); the per-thread cap could be passed | The position and the cap are judged under the thread lock |
| Two resolves of one thread both wrote and announced it; an anchor moved meanwhile was overwritten | Judged on the thread read again under its lock (`stale` for the anchor) |
| The same reaction sent twice at once (a double click) failed on the unique key | Looked up again under the thread lock |
| A comment edited or deleted by someone else meanwhile, or a page gone meanwhile, was written anyway | Read again under the lock: 403 / 404 |
| Two uploads into the room left for one were both accepted (space quota passed) | The quota is judged again under the space row lock, with a locking read on MySQL / MariaDB (also inside a transaction of yours); the refused file is deleted |
| Every co-editing update flushed what the application had left pending in the shared manager; a lost savepoint after a MySQL deadlock could leave a nested connection | The log is written through DBAL in a connection-level unit; no manager is flushed |
| `wiki:search:backfill-text` flushed and cleared the application's manager | Plain rows and one `UPDATE` per changed text, a transaction per batch |

**Inside a transaction of yours (MySQL / MariaDB REPEATABLE READ):** the rows are still locked, but a plain read after
the lock uses the snapshot your transaction took at its first read. The attachment quota reads with a lock and holds.
The reply position, the reply cap and the link lookup do not: two replies at once can share a position or pass the
cap by one, and a concurrent identical link ends on the unique key (409, never a duplicate link). Call the wiki outside your transaction, or use READ COMMITTED, for
the full guarantee (audit QN-L3). PostgreSQL's default READ COMMITTED sees the latest commits in every statement.
Since 1.38 the entity re-read after a row lock (`WikiTransactionRunner::lockEntity()`: archive / unarchive, comment
edits, the page icon) is a locking read (`PESSIMISTIC_WRITE`), so it sees the latest commit inside your transaction
too (audit QY-L3).

## One lock order

Co-editing log row (`page_collab_documents`), then space row (tree moves, trash, attachment quota), then page row
(saves, restores, archiving, links, new comment threads), then comment thread, then comment. No unit takes them the
other way round. The co-editing bookkeeping of a save (`nowo_wiki.page.written`) runs after the page's unit, never
under the page lock. The row locks are `SELECT … FOR UPDATE` (none on SQLite, where a writing transaction locks the
whole database).

The wiki's own units never take them the other way round. **One inversion comes from your side:** if your code wraps
a page save in its own transaction while co-editing is on, the page row stays locked until your commit, and the save's
co-editing bookkeeping then takes the log row: page, then log. A concurrent live-session save takes log, then page, so
the two can deadlock; the server breaks it and one of them answers 409 / 503 (audit QN-I1). Keep such transactions
short, or save outside them.

## Exact ids

Identifier columns compare exactly (house rule LQ, audit QL-M3): the binary **NO PAD** collation (`utf8mb4_0900_bin`
on MySQL 8, `utf8mb4_nopad_bin` on MariaDB) for the space owner, the user references and, since 1.37, the host object
of a page link (`object_type`, `object_id`). Every lookup by such an id is re-checked with `===` in PHP, and no lookup
puts a `LIMIT` before that check, so a look-alike row (`ABC` against `abc`, `42 ` against `42`) never takes the
exact one's place. The API keeps its id-with-class rules (audit OX-L1).

## Database settings

- **DBAL 3:** set `doctrine.dbal.use_savepoints: true` (DBAL 4 always nests with savepoints). Without it a wiki
  failure inside your transaction marks the whole transaction rollback-only.
- **Lock waits:** a unit waits for a locked row as long as the server lets it (`innodb_lock_wait_timeout`, 50 s by
  default on MySQL; `lock_timeout` on PostgreSQL, unlimited by default). Keep it below your request timeout (5–10 s
  for web workers): a timeout becomes a 409 + `Retry-After` (503 with the policy opt-in), never a stuck worker.
- **Connection poolers:** PgBouncer in transaction pooling mode is fine (the wiki holds no session state between
  units); statement pooling is not supported.

## Workers

The private manager is replaced on its next call when a failure closed it; a closed shared manager is replaced at the
next boundary by the runner (`WikiRequestBoundaryResetter`: start and end of every main request, Messenger message
and console command, and `kernel.reset`). The runner and the subscriber hold no state.

## Tests

- `tests/Database/LoadReadinessTest.php`: the races replayed deterministically (a second entity manager on the same
  connection plays the other request). SQLite by default, MySQL / PostgreSQL with `WIKI_TEST_DATABASE_URL`.
- `tests/Database/LoadReadinessConcurrencyTest.php` (+ `Race/load-worker.php`): two processes saving one page,
  linking one object, replying to one thread, reacting twice and uploading into a full space, on a real server.
  Skipped on SQLite.
- `tests/Support/LoadReadinessFactory.php` builds the services by argument name, so both files also run against 1.36.0
  (no runner) and show the races there.
- CI job `Load readiness`: MySQL 8.4 and PostgreSQL 16, DBAL 4 and DBAL 3.

```bash
WIKI_TEST_DATABASE_URL='mysql://root@127.0.0.1:3306/wiki_test?serverVersion=8.4.0&charset=utf8mb4' \
  vendor/bin/phpunit tests/Database/LoadReadinessTest.php tests/Database/LoadReadinessConcurrencyTest.php
```
