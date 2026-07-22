<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\User\UserInterface;

final class WikiMetadataListenerTest extends TestCase
{
    public function testAppliesTableNamesAndAuthorMapping(): void
    {
        $metadata                                = new ClassMetadata(WikiPageRevision::class);
        $metadata->table                         = ['name' => 'wiki_page_revisions'];
        $metadata->associationMappings['author'] = [
            'fieldName'    => 'author',
            'targetEntity' => UserInterface::class,
        ];

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        (new WikiMetadataListener('custom_spaces', 'custom_pages', 'custom_revisions', 'App\\Entity\\User'))
            ->loadClassMetadata($args);

        self::assertSame('custom_revisions', $metadata->table['name']);
        self::assertSame('App\\Entity\\User', $metadata->associationMappings['author']['targetEntity']);
    }

    public function testAppliesSpaceTable(): void
    {
        $metadata        = new ClassMetadata(WikiSpace::class);
        $metadata->table = ['name' => 'wiki_spaces'];
        $args            = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        (new WikiMetadataListener('s', 'p', 'r', 'App\\Entity\\User'))->loadClassMetadata($args);

        self::assertSame('s', $metadata->table['name']);
    }

    public function testAppliesPageTable(): void
    {
        $metadata        = new ClassMetadata(WikiPage::class);
        $metadata->table = ['name' => 'wiki_pages'];
        $args            = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        (new WikiMetadataListener('s', 'p', 'r', 'App\\Entity\\User'))->loadClassMetadata($args);

        self::assertSame('p', $metadata->table['name']);
    }

    public function testIgnoresOtherEntities(): void
    {
        $metadata        = new ClassMetadata(stdClass::class);
        $metadata->table = ['name' => 'std'];
        $args            = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        (new WikiMetadataListener('s', 'p', 'r', 'App\\Entity\\User'))->loadClassMetadata($args);

        self::assertSame('std', $metadata->table['name']);
    }

    public function testRevisionWithoutAuthorMappingIsNoOp(): void
    {
        $metadata        = new ClassMetadata(WikiPageRevision::class);
        $metadata->table = ['name' => 'wiki_page_revisions'];
        $args            = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        (new WikiMetadataListener('s', 'p', 'custom_revisions', 'App\\Entity\\User'))->loadClassMetadata($args);

        self::assertSame('custom_revisions', $metadata->table['name']);
    }
}
