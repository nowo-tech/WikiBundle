# Concurrency (REQ-RUNTIME-003)

How the wiki's writes behave when two writers meet the same rows: which transaction each write runs in, what it locks
and in which order, what the loser gets, which risks are accepted, and which tests prove it on which databases. The
details of the runner and the host entity manager are in [HIGH-LOAD.md](HIGH-LOAD.md); worker mode (kernel not reset)
in [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md); the REST API's answers in [API.md](API.md).

## Table of contents

- [Databases tested](#databases-tested)
- [Rules every write follows](#rules-every-write-follows)
- [Write paths](#write-paths)
- [Lock order](#lock-order)
- [Conflict answers](#conflict-answers)
- [Retries and replays](#retries-and-replays)
- [Host options](#host-options)
- [Residual risks](#residual-risks)
- [Tests](#tests)

## Databases tested

CI runs the database and REST API suites on SQLite (in memory, one connection), **MySQL 8.4**, **MariaDB 11.4** (since
1.48) and **PostgreSQL 16**. The `Load readiness` job runs the race tests on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16,
each on DBAL 4 and DBAL 3. Tests that need two connections or two processes run only on a server and are skipped on
SQLite; the `Load readiness` job runs its two race files with `--fail-on-skipped`, so a server that cannot run them
fails the job.

Until 1.47 only a few focused tests ran on MariaDB (the look-alike owner, the user id reference, favourites, recents
and backlinks); the CI note that the rest was "not yet green on MariaDB" no longer holds: the whole database and REST
API suites and both race files pass on MariaDB 11.4 with no code change.

## Rules every write follows

- **One unit of work per write** on the bundle's runner `nowo_wiki.transaction_runner` (`WikiTransactionRunner`, built
  on nowo-tech/transaction-doctrine, implementing nowo-tech/transaction-contract's `TransactionRunnerInterface`). Inside
  a transaction of the application the unit is a savepoint. After any failure the levels the unit opened are rolled
  back; when that rollback fails (MySQL / MariaDB drop the savepoints on a deadlock: error 1305) the connection is
  closed, so the worker's next request never inherits a nested connection. A lost savepoint is never taken as success.
- **Lock, then decide on the locked row.** Input and rights are checked before the unit. Inside it the rows the write
  depends on are locked (`SELECT … FOR UPDATE`, Doctrine `PESSIMISTIC_WRITE`; none on SQLite, where a writing
  transaction locks the whole database) and every rule a concurrent writer could break (the current revision, the
  archived / trashed state, a cap, a quota, a position) is decided again on what was locked.
- **Unique keys plus a catch for "only one" rules** (a favourite, a visit, a reaction, a link, an idempotency claim, a
  slug). A check-then-insert is never the only control.
- **Events after the unit.** Page, comment, link, attachment and trash events, the co-editing bookkeeping and Mercure
  updates are held by `WikiEventDeferral` and dispatched once the unit is done (after the commit when the unit is the
  outermost transaction). A listener that fails afterwards is logged; it does not report a stored write as failed.
- **The host's entity manager is never flushed, reset or cleared.** The wiki writes through its private manager (the
  only mode since 2.0) and never touches the application's manager.
- **No silent swallowing.** A deadlock or lock wait inside a listener, the backlink index or a page visit that runs in a
  transaction of the application is rethrown. Outside such a transaction only the read-side paths named below log it.
- **Bounded work under locks.** Files are written before the unit and deleted after a refused unit; mails and HTTP
  calls are never made under a row lock. Batch commands run one small transaction per batch.

## Write paths

| Write path | Transaction | Concurrency control (rule 4) | Loser |
|---|---|---|---|
| Page create (UI, REST API, import) | One unit: page, first revision, the page's revision pointer | Space row locked (tree position, sibling order; 1.50: its archived state read by the locking statement); unique slug per space | Unique slug: 409 `WIKI_003` / `WIKI_CONFLICT`; lock conflict: [rule 6](#conflict-answers) |
| Page save (edit form, REST API, live session, import) | One unit: revision + page pointer | Page row locked and read again: the next revision number follows the locked current one; `base_revision` compared on the locked row; archived / trashed refused | Stale base: 409 `WIKI_REVISION_CONFLICT` (form shown again with the text); REST API lock conflict: rule 6 (since 1.48; was `WIKI_SAVE_COLLISION`); another collision: 409 `WIKI_SAVE_COLLISION` + `Retry-After`; edit form: shown again with the text and a "try again" message |
| Archive / unarchive, page icon | One unit | Page row re-read with `PESSIMISTIC_WRITE` (`lockEntity()`); judged on the locked row | Already in that state: answered as done, announced once; gone: 404 |
| Tree move, reorder | One unit | Space row locked; parent, depth and cycle checked under the lock | 409 `WIKI_MOVE_001` (stale move or cycle) / 422 `WIKI_MOVE_002` |
| Trash, restore, delete for good | One unit per page subtree | Space row, then page rows | Gone meanwhile: 404; lock conflict: rule 6 |
| Page links | One unit | Page row locked; the link is looked up again under the lock; unique key on (page, object) | A concurrent identical link is stored and announced once |
| Comment thread, reply, resolve, reaction, edit, delete | One unit | Page row (new thread), then thread row; position and per-thread cap decided under the thread lock; unique key on reactions | Stale anchor or thread: 409 `COMMENT_002`; edited / deleted by someone else: 403 / 404 |
| Attachment upload | File stored first, then one unit | Space row locked; quota re-judged with a locking read on MySQL / MariaDB | Over the quota: refused, the stored file deleted |
| Co-editing log (live sessions) | Connection-level unit through DBAL, nothing flushed | Document row `page_collab_documents` locked first | Lost race: nothing written, the client resyncs |
| Favourite toggle / removal (1.45) | One unit through DBAL | Unique key `(user_id, page_id)`; a concurrent add is answered as done; `favourites.max` read in the unit | Rule 6 (`WikiConflictAnswer`) |
| Page visit (recents, 1.45) | One unit through DBAL; at most 50 stale rows pruned by primary key | Unique key `(user_id, page_id)`: a concurrent first visit becomes an update | None (a read): logged, the page is shown |
| Backlink index after a save (1.45) | One unit per page | Source page row locked, its current revision read under the lock: the last writer indexes the latest revision | None (the save is already committed): logged; the next save or `wiki:backlinks:rebuild` fixes it |
| REST API idempotency claim | Single statements | Unique key `(scope, key_hash)`; an abandoned claim is taken over with a conditional `UPDATE … WHERE created_at = ?` | 409 `IDEMP_001` + `Retry-After`; mismatching body 409 `IDEMP_002` |
| Space create by id (1.55: `createSpaceFrom()`, `createSpace()`) | One unit on the wiki's connection, the row written by one `INSERT` (no flush) | Slug looked up, then the unique key `(owner_scope_type, owner_scope_id, slug)` decides (owner id compared exactly) | Taken meanwhile: `WikiSlugTakenException` (the insert waited on the other row; nothing written, no manager closed); lock wait timeout: rule 6 `CONFLICT_001`; inside a host transaction: a savepoint, committed or rolled back with it |
| Space create (1.27 entity port `WikiSpaceServiceInterface::create()`), settings | One flush of one row | Unique slug | 409 `WIKI_003` |
| Space rename, archive, unarchive (1.50) | One unit | Space row re-read with `PESSIMISTIC_WRITE`; optional `expectedVersion` compared on the locked row; the new slug written by statement (unique key per owner) before the flush | Stale version: `WikiSpaceVersionConflictException`; slug taken meanwhile: `WikiSlugTakenException` (no flush failed, the manager stays open); already in that state: answered as done; lock conflict: rule 6 `CONFLICT_001` |
| Space purge (1.50) | One unit to mark the space, then one unit per batch of 100 pages, then one for the space row | Space row locked in each unit; every wiki table cleared by statement, the removed rows forgotten by the wiki's manager | Lock conflict before this call committed anything: `CONFLICT_001`; after: `ConcurrencyConflict` with `committed: true` (`CONFLICT_002`); any failure leaves complete batches only, and the next call resumes; refused inside an open transaction; only the call that deleted the space row dispatches `SPACE_PURGED` |
| Space erasure (1.55: `eraseSpace()`) | The purge's units, after the host's erasure committed | As the purge; a space already gone answers done (`found: false`); 1.55.1: only a lowercase UUID id, and the expected owner compared byte for byte under the space lock of the first unit | As the purge; refused inside a transaction; a second call (retry, replayed message) answers `finished: true`; another owner: `WikiSpaceOwnerMismatchException`, nothing written |
| `wiki:trash:purge`, `wiki:recents:purge`, `wiki:collab:purge`, `wiki:history:thin`, erasure commands | One transaction per batch / per person | Rows by primary key | The command fails; run it again (each batch is complete or absent) |
| `wiki:search:backfill-text`, `wiki:backlinks:rebuild` | One transaction per batch / per page; keyset batches | One `UPDATE` per changed text / the index lock above | A page with lock conflicts is tried 3 times, then skipped and reported (exit FAILURE, `--after=<id>` resumes) |

The 1.51 read ports (`WikiSpaceQueryInterface`, `WikiPageQueryInterface`) and `WikiPageAccessInterface` never write and
take no lock: each statement reads committed rows; a list and its count are two statements, so a page of results and
its total can disagree by the writes committed in between (as in any offset paging).

Every `flush()` in `src/` is inside a runner unit (the repositories' `WikiFlusher::flush()` is called from the services'
units) except the single-row space save of the 1.27 entity port (`WikiSpaceServiceInterface::create()`), which Doctrine
wraps in its own transaction; the 1.55 id operations write the space row by statement inside a unit.

## Lock order

Co-editing document row (`page_collab_documents`), then space row, then page row(s) in ascending id, then comment
thread, then comment. No unit of the wiki takes them the other way round. The co-editing bookkeeping of a save runs
after the page's unit, never under the page lock.

One inversion can come from the application: a page save wrapped in the application's own transaction while co-editing
is on holds the page row until that transaction commits, and the bookkeeping then takes the document row. A concurrent
live-session save takes document, then page. The server breaks the deadlock and one side gets the rule 6 answer.

## Conflict answers

A lock conflict is a deadlock, a lock wait timeout, a serialization failure or a lost savepoint (MySQL 1213 / 1205,
MariaDB 1020, PostgreSQL 40001 / 40P01 / 55P03): the runner's `TransientStorageFailure`, a DBAL `RetryableException`,
or transaction-doctrine's `ConcurrencyConflict`. On the wiki's routes it answers (REQ-RUNTIME-003 rule 6):

| Code | When | Status | Headers |
|---|---|---|---|
| `CONFLICT_001` | Nothing of the request was committed: retrying is safe | the policy's (409; 503 opt-in) | `X-Conflict-Code: CONFLICT_001`, `Retry-After` |
| `CONFLICT_002` | Part of the request was committed (the failed unit, an earlier unit, the host or another bundle in the same request) | the same | `X-Conflict-Code: CONFLICT_002`, no `Retry-After` |

The code is decided for the whole request by `ConflictCode::forRequest()` (the commit watermark transaction-doctrine
takes at `kernel.request` on the main request, ≥ 1.6). Shapes:

| Route | Body |
|---|---|
| REST API | `{"err": "…", "ECODE": "CONFLICT_001" \| "CONFLICT_002"}` (since 1.48; `SERVER_002` before) |
| Internal JSON endpoints | `{"error": "unavailable", "code": "CONFLICT_00x"}` (favourite routes: `{"error": "conflict", "code": …}`) |
| HTML pages | `HttpException` with the policy's status and headers, rendered by the application's error page |
| Routes listed in `nowo_transaction_doctrine.controller_routes` | The library's responder, shaped by `WikiStorageFailureConflictFormatter` as above |

`WikiStorageFailureSubscriber` maps these on every wiki route; `WikiConflictAnswer` answers the favourite routes
directly. Domain conflicts keep their own code and carry no `X-Conflict-Code`: a unique key that refused a concurrent
write (`WIKI_CONFLICT`, `WIKI_003`, `{"error": "conflict"}`), a stale `base_revision` (`WIKI_REVISION_CONFLICT`), a
save collision (`WIKI_SAVE_COLLISION`), a stale move or thread. Lost connections and other database errors are not
conflicts and keep their normal error path.

## Retries and replays

- HTTP writes make one attempt; the client retries on `CONFLICT_001`. `runRetrying()` (outermost transaction only, three
  attempts, full-jitter backoff) is for replayable background units; no wiki HTTP path calls it.
- REST writes with an `Idempotency-Key` store their answer. A `CONFLICT_002` answer keeps the claim, so a retry with the
  same key replays the stored 409 (with `X-Conflict-Code: CONFLICT_002`) instead of writing twice; a `CONFLICT_001`
  answer releases it, so the retry runs.
- A replayed unit re-reads its rows by id under the lock; request memos hold ids, and principals are resolved again for
  each request.

## Host options

| Option | Effect |
|---|---|
| `nowo_transaction_doctrine.conflict_response.status` | `409` (default) or `503` for every rule 6 answer |
| `nowo_transaction_doctrine.conflict_response.retry_after` | The `Retry-After` used when the failure carries no hint |
| `nowo_transaction_doctrine.commit_detection` | The request watermark behind `CONFLICT_002` for earlier commits of the request |
| `doctrine.dbal.use_savepoints: true` (DBAL 3) | Required so that a wiki failure inside the application's transaction rolls back only the wiki's savepoint |
| `innodb_lock_wait_timeout` / `lock_timeout` | Keep below the request timeout: a timeout becomes `CONFLICT_001`, never a stuck worker |

## Residual risks

Accepted and documented:

- **Inside an application transaction on MySQL / MariaDB (REPEATABLE READ)** a plain read after a row lock uses the
  transaction's snapshot: two replies at once can share a position or pass the reply cap by one, and a concurrent
  identical link ends on the unique key (409, never a duplicate). PostgreSQL's READ COMMITTED and calls outside the
  application's transaction have the full guarantee.
- **Events inside an application transaction** are dispatched when the wiki's savepoint is released, before the
  application commits; a later rollback leaves listeners told of rows that do not exist.
- **Favourites:** two concurrent adds of one person can pass `favourites.max` by one; recents can keep one visit more
  than `recents.keep` until the next visit.
- **Backlinks:** a lock conflict while indexing after a committed save leaves the index one save behind until the next
  save or `wiki:backlinks:rebuild`.
- **The edit form, the restore button and the tree's rename** answer a save that lost a lock conflict in their own way
  (the form again with the person's text and a "try again" message, a redirect with a flash, a `409 {"error":
  "conflict"}`), without `X-Conflict-Code`: nothing was written and the text is never lost, which matters more on these
  screens than the platform's generic "try again" page.
- **Space archive vs. an in-flight page save:** a save that read the space before an archive committed may still land
  (as if it had committed just before); every later write is refused. A purge clears what such a save wrote. Page
  creates are not affected: they read the space state under its row lock.
- **Space erasure (1.55):** the erasure runs after the host's commit, in the wiki's own units. Between that commit and
  the first unit the wiki's space is not archived by the wiki: the host hides it with its own mark, written inside its
  erasure transaction. A process that dies before the first unit leaves the space whole until the erasure runs again
  (it is idempotent: run it from a retried message or a sweep).
- **Space purge files:** attachment files are removed after each batch commits; a process that dies in between leaves
  orphan files for the attachment maintenance.
- **Commit detection** sees DBAL writes on DoctrineBundle connections only; a commit on another connection of the same
  request is not seen and its conflict answers `CONFLICT_001`.

## Tests

| Test | What | Databases |
|---|---|---|
| `tests/Database/LoadReadinessTest.php` | The races replayed deterministically (a second entity manager plays the other request): saves, archive, links, replies, reactions, quota, co-editing log | SQLite, MySQL, MariaDB, PostgreSQL |
| `tests/Database/LoadReadinessConcurrencyTest.php` (+ `Race/load-worker.php`) | Two processes saving one page, linking one object, replying to one thread, reacting twice, uploading into a full space | MySQL, MariaDB, PostgreSQL |
| `tests/Database/WikiQuickAccessRaceTest.php` | Two connections: lock conflict on a favourite (`CONFLICT_001` / `CONFLICT_002`), unique-key races on favourites and visits | MySQL, MariaDB, PostgreSQL |
| `tests/Database/LoadReadinessQnTest.php`, `WikiPageRevisionRaceTest.php`, `WikiPageExpectedRevisionTest.php` | The shared-manager failure paths (QN), two saves racing for one revision number, stale `base_revision` | SQLite, MySQL, MariaDB, PostgreSQL |
| `tests/Database/WikiSpaceLifecycleTest.php` | Space rename / archive / purge: a locked space row (`CONFLICT_001`, nothing written), a slug taken between the check and the write, a failure and a lock conflict in the middle of a purge (`CONFLICT_002`, resumed by the next call) | SQLite, MySQL, MariaDB, PostgreSQL |
| `tests/Database/WikiBeaconIdOperationsTest.php` (+ `Race/space-worker.php`) | 1.55 space create by id: a slug taken between the lookup and the insert; a second connection holding the same slug (commits: refused; rolls back: created; past the lock timeout: `CONFLICT_001`); inside a host transaction; the host's pending changes untouched; look-alike owners; the erasure forced, complete, idempotent, refused inside a transaction | SQLite (deterministic cases), MySQL, MariaDB, PostgreSQL |
| `tests/Api/BeaconIdOperations/*` | The same operations from the container, shared and private entity manager | all |
| `tests/Unit/Transaction/WikiTransactionRunnerTest.php` | Nested units, lost savepoints, the closed-manager path, events after the unit | — |
| `tests/Api/ApiIdempotencyTest.php` | A committed conflict keeps the claim and replays `CONFLICT_002`; an uncommitted one releases it | all |
| `tests/Unit/Transaction/*` | The rule 6 mapping: deadlock, lock wait, serialization, committed and request-level `CONFLICT_002`, unique violations and lost connections not mapped | — |

Run them on a server with `WIKI_TEST_DATABASE_URL`:

```bash
WIKI_TEST_DATABASE_URL='mysql://root@127.0.0.1:3306/wiki_test?serverVersion=11.4.0-MariaDB&charset=utf8mb4' \
  vendor/bin/phpunit --testsuite database,api
```
