<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Nowo\WikiBundle\Api\Operation\WikiApiComments;
use Nowo\WikiBundle\Api\Serializer\WikiApiContent;
use Nowo\WikiBundle\Api\Serializer\WikiApiPeople;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Comment\WikiCommentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * 1.24 (spec 218): the comment service's refusals in the API's words, and comments switched off as 404.
 */
final class WikiApiCommentsTest extends TestCase
{
    public function testEveryRefusalOfTheServiceMaps(): void
    {
        $comments = $this->comments();
        foreach ([
            [403, 'forbidden', 403, 'ACCESS_002'],
            [404, 'not_found', 404, 'ACCESS_001'],
            [413, 'too_large', 413, 'INPUT_003'],
            [429, 'rate_limited', 429, 'RATE_003'],
            [422, 'limit_reached', 422, 'COMMENT_001'],
            [409, 'stale', 409, 'COMMENT_002'],
            [422, 'invalid', 400, 'INPUT_002'],
            [422, 'invalid_anchor', 400, 'INPUT_002'],
        ] as [$status, $reason, $answer, $ecode]) {
            try {
                $comments->call(static fn (): never => throw new WikiCommentException($status, $reason));
                self::fail($reason);
            } catch (WikiApiRefusal $refusal) {
                self::assertSame([$answer, $ecode], [$refusal->status, $refusal->ecode], $reason);
            }
        }
        self::assertSame('ok', $comments->call(static fn (): string => 'ok'));
    }

    public function testCommentsSwitchedOffAreNotFound(): void
    {
        $this->expectException(WikiApiRefusal::class);
        $this->comments()->service();
    }

    private function comments(): WikiApiComments
    {
        $people  = (new ReflectionClass(WikiApiPeople::class))->newInstanceWithoutConstructor();
        $content = (new ReflectionClass(WikiApiContent::class))->newInstanceWithoutConstructor();

        return new WikiApiComments(null, $people, $content);
    }
}
