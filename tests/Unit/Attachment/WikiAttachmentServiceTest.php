<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Attachment;

use DateTimeImmutable;
use Nowo\WikiBundle\Attachment\ConfiguredWikiAttachmentLimitsProvider;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException;
use Nowo\WikiBundle\Attachment\WikiAttachmentScannerInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class WikiAttachmentServiceTest extends TestCase
{
    /** 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private string $dir;

    private WikiPage $page;

    private InMemoryAttachmentRepository $repo;

    /** @var list<string> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->dir  = sys_get_temp_dir() . '/wiki-att-' . bin2hex(random_bytes(4));
        $this->page = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $this->repo = new InMemoryAttachmentRepository();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testAnImageIsStoredWithItsSniffedTypeAndHash(): void
    {
        $file       = $this->file(base64_decode(self::PNG, true));
        $attachment = $this->service()->upload($this->page, $file, '../../Screen shot.png', new TestUser());

        self::assertSame('Screen shot.png', $attachment->getOriginalName());
        self::assertSame('image/png', $attachment->getMimeType());
        self::assertTrue($attachment->isInlineImage());
        self::assertSame(hash_file('sha256', $file), $attachment->getSha256());
        self::assertMatchesRegularExpression('#^' . $this->page->getSpace()->getId() . '/[0-9a-f]{32}$#', $attachment->getStorageKey());
        self::assertFileExists($this->dir . '/' . $attachment->getStorageKey());
        self::assertSame(['uploaded'], $this->events);

        $stream = $this->service()->openStream($attachment);
        self::assertSame(base64_decode(self::PNG, true), stream_get_contents($stream));
        fclose($stream);

        $this->service()->delete($attachment);
        self::assertFileDoesNotExist($this->dir . '/' . $attachment->getStorageKey());
        self::assertSame([], $this->repo->rows);
    }

    /**
     * @return iterable<string, array{string, string, string, int, int}>
     */
    public static function refusals(): iterable
    {
        yield 'empty' => ['', 'a.txt', WikiAttachmentRejectedException::EMPTY, 100, 1000];
        yield 'too large' => ['hello world', 'a.txt', WikiAttachmentRejectedException::TOO_LARGE, 5, 1000];
        yield 'quota' => ['hello world', 'a.txt', WikiAttachmentRejectedException::QUOTA_EXCEEDED, 100, 10];
        yield 'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'a.svg', WikiAttachmentRejectedException::TYPE_NOT_ALLOWED, 1000, 10000];
        yield 'html as txt' => ['<!DOCTYPE html><html><body><script>x</script></body></html>', 'a.txt', WikiAttachmentRejectedException::TYPE_MISMATCH, 1000, 10000];
        yield 'text as png' => ['plain text', 'a.png', WikiAttachmentRejectedException::TYPE_MISMATCH, 1000, 10000];
    }

    #[DataProvider('refusals')]
    public function testRefusedFilesStoreNothing(string $content, string $name, string $reason, int $maxFile, int $maxSpace): void
    {
        try {
            $this->service(maxFile: $maxFile, maxSpace: $maxSpace)->upload($this->page, $this->file($content), $name);
            self::fail('The upload must be refused.');
        } catch (WikiAttachmentRejectedException $exception) {
            self::assertSame($reason, $exception->reason);
        }

        self::assertSame([], $this->repo->rows);
        self::assertDirectoryDoesNotExist($this->dir);
    }

    public function testTheScannerCanRefuseAFile(): void
    {
        $scanner = new class implements WikiAttachmentScannerInterface {
            public function scan(string $path, string $mimeType, string $originalName): string
            {
                return 'EICAR';
            }
        };

        try {
            $this->service(scanner: $scanner)->upload($this->page, $this->file('hello'), 'a.txt');
            self::fail('The upload must be refused.');
        } catch (WikiAttachmentRejectedException $exception) {
            self::assertSame(WikiAttachmentRejectedException::SCANNER, $exception->reason);
            self::assertSame('EICAR', $exception->detail);
        }

        self::assertSame([], $this->repo->rows);
    }

    public function testAFailedSaveRemovesTheStoredFile(): void
    {
        $this->repo->failOnSave = true;

        try {
            $this->service()->upload($this->page, $this->file('hello'), 'a.txt');
            self::fail('The save must fail.');
        } catch (RuntimeException) {
        }

        self::assertSame([], glob($this->dir . '/*/*') ?: []);
    }

    public function testOrphansAndStrayFilesArePurged(): void
    {
        $service                = $this->service();
        $referenced             = $service->upload($this->page, $this->file('one'), 'one.txt');
        $orphan                 = $service->upload($this->page, $this->file('two'), 'two.txt');
        $this->repo->referenced = [$referenced->getId()];
        mkdir($this->dir . '/stray', 0o700, true);
        file_put_contents($this->dir . '/stray/file', 'x');

        self::assertSame(0, $service->purgeOrphans(new DateTimeImmutable('-1 day')));
        self::assertSame(1, $service->purgeOrphans(new DateTimeImmutable('+1 minute')));
        self::assertSame([$referenced], array_values($this->repo->rows));
        self::assertSame(1, $service->purgeStrayFiles());
        self::assertFileDoesNotExist($this->dir . '/stray/file');
        self::assertFileExists($this->dir . '/' . $referenced->getStorageKey());
        self::assertFileDoesNotExist($this->dir . '/' . $orphan->getStorageKey());
    }

    public function testNamesAreSanitized(): void
    {
        self::assertSame('file', WikiAttachmentService::sanitizeName('  '));
        self::assertSame('évidence.pdf', WikiAttachmentService::sanitizeName("C:\\temp\\évi\x01dence.pdf"));
        self::assertSame(255, mb_strlen(WikiAttachmentService::sanitizeName(str_repeat('a', 300))));
    }

    private function service(int $maxFile = 1_000_000, int $maxSpace = 10_000_000, ?WikiAttachmentScannerInterface $scanner = null): WikiAttachmentService
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::ATTACHMENT_UPLOADED, function (WikiAttachmentEvent $event): void {
            $this->events[] = 'uploaded';
        });

        return new WikiAttachmentService(
            $this->repo,
            new LocalWikiAttachmentStorage($this->dir),
            $scanner ?? new NullWikiAttachmentScanner(),
            new ConfiguredWikiAttachmentLimitsProvider($maxFile, $maxSpace),
            $dispatcher,
        );
    }

    private function file(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wiki-up-');
        file_put_contents($path, $content);

        return $path;
    }
}
