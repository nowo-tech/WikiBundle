<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Routing;

use Nowo\WikiBundle\Routing\WikiRouteLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WikiRouteLoaderDoubleLoadTest extends TestCase
{
    public function testThrowsWhenLoadedTwice(): void
    {
        $loader = new WikiRouteLoader(['index' => ['path' => '/wiki', 'name' => 'nowo_wiki_index']], '');
        $loader->load('nowo_wiki', 'nowo_wiki');

        $this->expectException(RuntimeException::class);
        $loader->load('nowo_wiki', 'nowo_wiki');
    }
}
