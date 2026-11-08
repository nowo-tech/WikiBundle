<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Nowo\WikiBundle\Api\WikiApiInput;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use PHPUnit\Framework\TestCase;

final class WikiApiInputTest extends TestCase
{
    public function testTypesAndBounds(): void
    {
        $input = new WikiApiInput(['a' => 'x', 'b' => true, 'n' => 5, 'z' => "a\0b", 'big' => 99, 'odd' => 1.5, 's' => 'abcdef']);

        self::assertTrue($input->has('a'));
        self::assertSame('x', $input->string('a', true));
        self::assertTrue($input->bool('b'));
        self::assertSame(5, $input->int('n', 0, 10));
        self::assertNull($input->int('missing'));
        foreach ([
            static fn () => $input->string('z'),
            static fn () => $input->string('missing', true),
            static fn () => $input->string('s', false, 3),
            static fn () => $input->bool('a'),
            static fn () => $input->int('big', 0, 10),
            static fn () => $input->int('odd'),
        ] as $call) {
            try {
                $call();
                self::fail('Expected a refusal');
            } catch (WikiApiRefusal $refusal) {
                self::assertSame('INPUT_002', $refusal->ecode);
            }
        }
        self::assertSame([], $input->warnings());
    }
}
