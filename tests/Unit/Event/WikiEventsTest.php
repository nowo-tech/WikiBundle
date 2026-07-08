<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Event;

use Nowo\WikiBundle\Event\WikiEvents;
use PHPUnit\Framework\TestCase;

final class WikiEventsTest extends TestCase
{
    public function testEventNames(): void
    {
        self::assertSame('nowo_wiki.page.saved', WikiEvents::PAGE_SAVED);
        self::assertSame('nowo_wiki.page.archived', WikiEvents::PAGE_ARCHIVED);
    }
}
