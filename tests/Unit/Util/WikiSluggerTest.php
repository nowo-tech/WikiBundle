<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Util;

use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;

final class WikiSluggerTest extends TestCase
{
    private WikiSlugger $slugger;

    protected function setUp(): void
    {
        $this->slugger = new WikiSlugger();
    }

    public function testSlugify(): void
    {
        self::assertSame('runbook-deploy', $this->slugger->slugify('Runbook Deploy'));
    }

    public function testIsValid(): void
    {
        self::assertTrue($this->slugger->isValid('adr-001'));
        self::assertFalse($this->slugger->isValid('Bad Slug'));
    }
}
