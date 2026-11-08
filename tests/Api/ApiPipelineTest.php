<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

/**
 * 1.24 (spec 218): what every request goes through before its endpoint, and the shape of every answer.
 */
final class ApiPipelineTest extends ApiTestCase
{
    public function testAValidTokenGetsThroughWithTheHeadersOfEveryAnswer(): void
    {
        $response = $this->call('GET', '/wikis/eng');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('100', $response->headers->get('X-RateLimit-Limit'));
        self::assertSame('99', $response->headers->get('X-RateLimit-Remaining'));
        self::assertSame('1800000000', $response->headers->get('X-RateLimit-Reset'));
        self::assertNull($response->headers->get('Set-Cookie'));
    }

    public function testABareTokenWorksAndTheQueryStringNeverCarriesOne(): void
    {
        $this->client->request('GET', '/api/wiki/v1/wikis/eng', [], [], ['HTTP_AUTHORIZATION' => self::ANA]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->assertRefusal($this->call('GET', '/wikis/eng?access_token=' . self::ANA, null), 401, 'OAUTH_017');
    }

    public function testBadTokensAreOneAnswerAndCountAsFailures(): void
    {
        $this->assertRefusal($this->call('GET', '/wikis/eng', 'unknown'), 401, 'OAUTH_019');
        self::assertCount(1, ApiTestHost::$failedIps);

        ApiTestHost::$tokens['revoked'] = 'invalid';
        $this->assertRefusal($this->call('GET', '/wikis/eng', 'revoked'), 401, 'OAUTH_019');
        self::assertCount(2, ApiTestHost::$failedIps);
        self::assertSame(['err' => 'Token not valid', 'ECODE' => 'OAUTH_019'], $this->json($this->call('GET', '/wikis/eng', 'revoked')));
    }

    public function testSwitchedOffNoWikiLevelAndLimits(): void
    {
        ApiTestHost::$tokens['off'] = 'disabled';
        $this->assertRefusal($this->call('GET', '/wikis/eng', 'off'), 404, 'API_001');

        $this->token('tasks-only', 'u-ana', null);
        $this->assertRefusal($this->call('GET', '/wikis/eng', 'tasks-only'), 403, 'TOKEN_001');

        ApiTestHost::$rateLimited = true;
        $limited                  = $this->call('GET', '/wikis/eng');
        $this->assertRefusal($limited, 429, 'RATE_001');
        self::assertSame('0', $limited->headers->get('X-RateLimit-Remaining'));

        ApiTestHost::$ipBlocked = true;
        $this->assertRefusal($this->call('GET', '/wikis/eng', 'unknown'), 429, 'RATE_002');
        self::assertCount(0, ApiTestHost::$failedIps);
    }

    public function testUnknownPathsAndMethodsUnderThePrefixAnswerInTheApisShape(): void
    {
        $this->assertRefusal($this->call('GET', '/nothing-here'), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('DELETE', '/wikis/eng'), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('GET', '/wikis/NOT_A_SLUG'), 404, 'ACCESS_001');
    }

    public function testNoCredentialAtAll(): void
    {
        $this->assertRefusal($this->call('GET', '/wikis', null), 401, 'OAUTH_017');
    }
}
