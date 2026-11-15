<?php

declare(strict_types=1);

/*
 * 1.37 (Beacon spec 239) worker of LoadReadinessConcurrencyTest: one process of a race on a real server. It waits for
 * the common start time, then runs its writes; the statement named by the scenario is held back a little (a logger of
 * the SQL sleeps before it), so the two processes overlap between their read and their write. Prints one line per
 * write: `ok`, `refused <class>` or `error <class>`.
 *
 * Usage: php load-worker.php <scenario> <start at, unix float> <writes> <args...>
 *   save   <page id> <author id>                       last-write-wins saves of one page
 *   link   <page id> <author id>                       links of one page to the objects task/1..n
 *   reply  <space id> <page id> <thread id> <author id> replies to one thread
 *   react  <space id> <page id> <comment id> <author id> the same reaction
 *   upload <space id> <page id> <directory> <quota>      uploads of 10 bytes into a space with a quota
 */

use Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Psr\Log\AbstractLogger;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

[, $scenario, $startAt, $writes] = $argv;
$args                            = array_slice($argv, 4);

$holdBefore = [
    'save'   => 'INSERT INTO wiki_page_revisions',
    'link'   => 'INSERT INTO wiki_page_links',
    'reply'  => 'INSERT INTO wiki_comments',
    'react'  => 'INSERT INTO wiki_comment_reactions',
    'upload' => 'INSERT INTO wiki_attachments',
][$scenario] ?? null;
if ($holdBefore === null) {
    fwrite(\STDERR, "unknown scenario\n");
    exit(2);
}

$logger = new class($holdBefore) extends AbstractLogger {
    public function __construct(private readonly string $holdBefore)
    {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (str_starts_with((string) ($context['sql'] ?? ''), $this->holdBefore)) {
            usleep(30_000);
        }
    }
};

$em     = WikiTestDatabase::connect($logger);
$author = $em->find(TestUserEntity::class, $scenario === 'upload' ? 'u-race' : $args[array_key_last($args)]);
$wait   = (float) $startAt - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

for ($i = 1; $i <= (int) $writes; ++$i) {
    try {
        switch ($scenario) {
            case 'save':
                $page = $em->find(WikiPage::class, $args[0]);
                LoadReadinessFactory::pages($em)->saveRevision($page, 'Runbook', 'pid ' . getmypid() . ' #' . $i, $author, WikiPageRevision::FORMAT_MARKDOWN);
                break;
            case 'link':
                LoadReadinessFactory::links($em)->link($em->find(WikiPage::class, $args[0]), 'task', (string) $i, $author);
                break;
            case 'reply':
                $space = $em->find(WikiSpace::class, $args[0]);
                LoadReadinessFactory::comments($em, $space)->reply($author, $space, $em->find(WikiPage::class, $args[1]), $args[2], 'reply ' . getmypid() . ' #' . $i);
                break;
            case 'react':
                $space = $em->find(WikiSpace::class, $args[0]);
                LoadReadinessFactory::comments($em, $space)->react($author, $space, $em->find(WikiPage::class, $args[1]), $args[2], '👍', true);
                break;
            case 'upload':
                $file = tempnam(sys_get_temp_dir(), 'wiki-upload');
                file_put_contents($file, '0123456789');
                LoadReadinessFactory::attachments($em, $args[2], (int) $args[3])->upload($em->find(WikiPage::class, $args[1]), $file, 'note.txt', null);
                @unlink($file);
                break;
        }
        echo "ok\n";
    } catch (WikiAttachmentRejectedException|WikiCommentException|WikiRevisionCollisionException $refused) {
        echo 'refused ' . $refused::class . "\n";
    } catch (Throwable $failure) {
        echo 'error ' . $failure::class . "\n";
    }
    if (!$em->isOpen()) {
        // A failed flush closed the manager (1.36): the next write gets a fresh one, as the next request would.
        $em     = WikiTestDatabase::connect($logger);
        $author = $em->find(TestUserEntity::class, $scenario === 'upload' ? 'u-race' : $args[array_key_last($args)]);
    }
    $em->clear();
    $author = $em->find(TestUserEntity::class, $scenario === 'upload' ? 'u-race' : $args[array_key_last($args)]);
}
