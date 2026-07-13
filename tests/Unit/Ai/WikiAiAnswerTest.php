<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ai;

use Nowo\WikiBundle\Ai\WikiAiAnswer;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use PHPUnit\Framework\TestCase;

final class WikiAiAnswerTest extends TestCase
{
    public function testStoresAnswerAndSources(): void
    {
        $space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page   = new WikiPage($space, 'deploy', 'Deploy');
        $answer = new WikiAiAnswer('Use the runbook.', [
            ['page' => $page, 'space' => $space, 'excerpt' => 'deploy steps'],
        ]);

        self::assertSame('Use the runbook.', $answer->answer);
        self::assertCount(1, $answer->sources);
        self::assertSame('deploy', $answer->sources[0]['page']->getSlug());
    }
}
