<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Util;

use InvalidArgumentException;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;

final class WikiSluggerInvalidTest extends TestCase
{
    public function testSlugifyRejectsEmptyResult(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WikiSlugger())->slugify('!!!');
    }
}
