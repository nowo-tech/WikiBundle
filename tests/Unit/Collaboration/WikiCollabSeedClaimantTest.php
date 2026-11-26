<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use LogicException;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaimant;
use PHPUnit\Framework\TestCase;

/**
 * 2.0.1 (Beacon 207 T030): the seed claimant is a keyed hash of the identifier, never the identifier.
 */
final class WikiCollabSeedClaimantTest extends TestCase
{
    public function testTheClaimantIsAKeyedHashWithTheTab(): void
    {
        $user = WikiCollabSeedClaimant::user('ana@corp.example', 'secret-a');

        self::assertMatchesRegularExpression('/^h1:[0-9a-f]{64}$/', $user);
        self::assertStringNotContainsString('ana', $user);
        self::assertSame($user, WikiCollabSeedClaimant::user('ana@corp.example', 'secret-a'), 'stable for equality');
        self::assertNotSame($user, WikiCollabSeedClaimant::user('ana@corp.example', 'secret-b'), 'keyed');
        self::assertNotSame($user, WikiCollabSeedClaimant::user('Ana@corp.example', 'secret-a'), 'byte for byte');
        self::assertNotSame(WikiCollabSeedClaimant::PREFIX . hash('sha256', 'ana@corp.example'), $user, 'not a plain hash');

        self::assertSame($user . '#123', WikiCollabSeedClaimant::of('ana@corp.example', '123', 'secret-a'));
        self::assertSame($user, WikiCollabSeedClaimant::of('ana@corp.example', '', 'secret-a'));
        self::assertSame($user, WikiCollabSeedClaimant::of('ana@corp.example', 'x1', 'secret-a'), 'an invalid tab id is ignored');
        self::assertSame($user, WikiCollabSeedClaimant::of('ana@corp.example', "123\n", 'secret-a'));
        self::assertSame($user, WikiCollabSeedClaimant::of('ana@corp.example', '12345678901234567', 'secret-a'), 'at most 16 digits');
    }

    public function testAnEmptySecretIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('kernel.secret');

        WikiCollabSeedClaimant::user('ana@corp.example', '');
    }

    public function testHashedValuesAndOwnership(): void
    {
        $user = WikiCollabSeedClaimant::user('ana@corp.example', 'secret-a');

        self::assertTrue(WikiCollabSeedClaimant::isHashed($user));
        self::assertTrue(WikiCollabSeedClaimant::isHashed($user . '#42'));
        self::assertFalse(WikiCollabSeedClaimant::isHashed('ana@corp.example'));
        self::assertFalse(WikiCollabSeedClaimant::isHashed('ana@corp.example#42'));
        self::assertFalse(WikiCollabSeedClaimant::isHashed($user . "\n"));
        self::assertFalse(WikiCollabSeedClaimant::isHashed('h1:bob'));
        self::assertFalse(WikiCollabSeedClaimant::isHashed(strtoupper($user)));

        self::assertTrue(WikiCollabSeedClaimant::belongsTo($user, $user));
        self::assertTrue(WikiCollabSeedClaimant::belongsTo($user . '#42', $user));
        self::assertFalse(WikiCollabSeedClaimant::belongsTo($user . '#x', $user));
        self::assertFalse(WikiCollabSeedClaimant::belongsTo($user . '#', $user));
        self::assertTrue(WikiCollabSeedClaimant::belongsTo('ana@corp.example#7', 'ana@corp.example'), 'a 2.0.0 raw claim');
        self::assertFalse(WikiCollabSeedClaimant::belongsTo('ana@corp.example.org', 'ana@corp.example'));
        self::assertFalse(WikiCollabSeedClaimant::belongsTo('ANA@corp.example', 'ana@corp.example'), 'byte for byte');
        self::assertFalse(WikiCollabSeedClaimant::belongsTo('ana#7#8', 'ana'));
    }
}
