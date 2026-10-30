<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Attachment;

use InvalidArgumentException;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalWikiAttachmentStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wiki-storage-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            exec('rm -rf ' . escapeshellarg($this->dir));
        }
    }

    public function testWriteReadListAndDelete(): void
    {
        $storage = new LocalWikiAttachmentStorage($this->dir);
        self::assertSame([], iterator_to_array($storage->keys(), false));

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'hello');
        rewind($stream);
        $storage->write('space-1/abc', $stream);
        fclose($stream);
        file_put_contents($this->dir . '/space-1/ignored.part', 'x');

        $read = $storage->readStream('space-1/abc');
        self::assertSame('hello', stream_get_contents($read));
        fclose($read);
        self::assertSame(['space-1/abc'], iterator_to_array($storage->keys(), false));

        $storage->delete('space-1/abc');
        $storage->delete('space-1/abc');
        self::assertSame([], iterator_to_array($storage->keys(), false));
    }

    public function testMissingFileCannotBeRead(): void
    {
        $this->expectException(RuntimeException::class);
        (new LocalWikiAttachmentStorage($this->dir))->readStream('nope/missing');
    }

    public function testKeysWithUserInputArePathTraversalSafe(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LocalWikiAttachmentStorage($this->dir))->delete('../etc/passwd');
    }

    public function testAnExistingFileIsNeverOverwritten(): void
    {
        $storage = new LocalWikiAttachmentStorage($this->dir);
        mkdir($this->dir . '/s', 0o700, true);
        file_put_contents($this->dir . '/s/k.part', 'busy');
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $this->expectException(RuntimeException::class);
        $storage->write('s/k', $stream);
    }

    public function testUnwritableDirectoryFails(): void
    {
        file_put_contents($this->dir, 'a file where the directory should be');
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        try {
            $this->expectException(RuntimeException::class);
            (new LocalWikiAttachmentStorage($this->dir))->write('s/k', $stream);
        } finally {
            unlink($this->dir);
        }
    }
}
