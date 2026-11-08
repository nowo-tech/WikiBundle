<?php

declare(strict_types=1);

/*
 * Spec 218 SC-003 worker: loads the page, waits for the common start time, then saves from base revision 1. Reading the
 * next revision number is slowed down (inside the page lock), so both workers overlap. Prints `ok <n>`, `conflict <n>`
 * or `collision`.
 *
 * Usage: php save-worker.php <page id> <author id> <body> <start at, unix float>
 */

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use Symfony\Component\EventDispatcher\EventDispatcher;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

[, $pageId, $authorId, $body, $startAt] = $argv;

$em     = WikiTestDatabase::connect();
$page   = $em->find(WikiPage::class, $pageId);
$author = $em->find(TestUserEntity::class, $authorId);
if (!$page instanceof WikiPage || !$author instanceof TestUserEntity) {
    fwrite(\STDERR, "fixture missing\n");
    exit(1);
}

$inner     = new DoctrineOrmWikiPageRevisionRepository($em);
$revisions = new class($inner) implements WikiPageRevisionRepositoryInterface {
    public function __construct(private readonly WikiPageRevisionRepositoryInterface $inner)
    {
    }

    public function save(WikiPageRevision $revision): void
    {
        $this->inner->save($revision);
    }

    public function findById(string $id): ?WikiPageRevision
    {
        return $this->inner->findById($id);
    }

    public function findByPage(WikiPage $page, ?int $limit = null, int $offset = 0): array
    {
        return $this->inner->findByPage($page, $limit, $offset);
    }

    public function findByPageAndNumber(WikiPage $page, int $number): ?WikiPageRevision
    {
        return $this->inner->findByPageAndNumber($page, $number);
    }

    public function getNextRevisionNumber(WikiPage $page): int
    {
        $next = $this->inner->getNextRevisionNumber($page);
        // Hold the critical section open, so the other worker arrives while this one is inside it.
        usleep(400_000);

        return $next;
    }
};

$service = new WikiPageService(new DoctrineOrmWikiPageRepository($em), $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());

$wait = (float) $startAt - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $revision = $service->saveRevision($page, 'Runbook', $body, $author, WikiPageRevision::FORMAT_MARKDOWN, 1);
    echo 'ok ' . $revision->getRevisionNumber() . "\n";
} catch (WikiRevisionConflictException $conflict) {
    echo 'conflict ' . $conflict->currentRevision . "\n";
} catch (WikiRevisionCollisionException) {
    echo "collision\n";
}
