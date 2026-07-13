<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Dto;

use Nowo\WikiBundle\Dto\WikiImportReport;
use PHPUnit\Framework\TestCase;

final class WikiImportReportTest extends TestCase
{
    public function testAddMessageAppendsToMessages(): void
    {
        $report = new WikiImportReport();
        $report->addMessage('Imported intro.md');

        self::assertSame(['Imported intro.md'], $report->messages);
    }
}
