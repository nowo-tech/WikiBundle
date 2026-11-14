<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Port;

use InvalidArgumentException;
use Nowo\WikiBundle\Api\Value\WikiApiDownload;
use Nowo\WikiBundle\Api\Value\WikiApiUpload;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Collaboration\WikiCollabCookie;
use Nowo\WikiBundle\Collaboration\WikiCollabCookieListener;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\WikiRequestContexts;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\User\WikiRefUser;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function array_diff_key;
use function array_keys;
use function fopen;
use function get_object_vars;
use function ksort;
use function ob_get_clean;
use function ob_start;

final class WikiPortValuesTest extends TestCase
{
    public function testARefUserStandsForItsReference(): void
    {
        $ref  = WikiUserRef::ofId(TestUserEntity::class, '7', 'alice', ['ROLE_USER']);
        $user = new WikiRefUser($ref);

        self::assertSame($ref, $user->ref());
        self::assertSame('7', $user->getId());
        self::assertSame(TestUserEntity::class, $user->getUserClass());
        self::assertSame('alice', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        $user->eraseCredentials();
        self::assertSame('7', (new WikiRefUser(WikiUserRef::ofId('App\\User', '7')))->getUserIdentifier());
        self::assertSame("\0nowo-wiki-anonymous", (new WikiRefUser(new WikiUserRef('App\\User', null)))->getUserIdentifier());

        self::assertSame($ref, WikiUserRefs::fromUser($user), 'a reference comes back unchanged');
        self::assertInstanceOf(WikiRefUser::class, WikiUserRefs::securityUser($ref));
        $security = new TestUser();
        self::assertSame($security, WikiUserRefs::securityUser(WikiUserRefs::fromUser($security)));
        self::assertSame($security, WikiUserRefs::object(WikiUserRefs::fromUser($security)));
        $plain = new stdClass();
        self::assertInstanceOf(WikiRefUser::class, WikiUserRefs::securityUser(WikiUserRefs::fromUser($plain)));
        self::assertInstanceOf(WikiRefUser::class, WikiUserRefs::object($ref));
    }

    public function testTheClassPolicyAcceptsAReferenceToUserClassOrAListedClassExactly(): void
    {
        $policy = new WikiUserClassPolicy(TestUserEntity::class, ['\\App\\Admin']);

        self::assertTrue($policy->isAccepted(new WikiRefUser(WikiUserRef::ofId(TestUserEntity::class, '1'))));
        self::assertTrue($policy->isAccepted(new WikiRefUser(WikiUserRef::ofId('App\\Admin', '1'))));
        self::assertFalse($policy->isAccepted(new WikiRefUser(WikiUserRef::ofId('App\\Other', '1'))));
        self::assertFalse($policy->isAccepted(new WikiRefUser(WikiUserRef::ofId('app\\admin', '1'))));
        self::assertSame('1', $policy->idOf(new WikiRefUser(WikiUserRef::ofId(TestUserEntity::class, '1'))));
    }

    /**
     * Audit OX-L3: a request context carries an allow-list of plain headers only (no credential, infrastructure token,
     * session or CSRF header, whatever its spelling), never the query string, and a referrer without its query or
     * fragment; the REST API credential only as `$credential`.
     */
    public function testRequestContextsCarryAnAllowListOfHeadersAndNoQuery(): void
    {
        $request = Request::create('/p?access_token=t1&token=t2&code=c&state=s&q=v&a[]=x', 'PUT', server: [
            'HTTP_AUTHORIZATION'            => 'Bearer s',
            'REDIRECT_HTTP_AUTHORIZATION'   => 'Bearer r',
            'HTTP_PROXY_AUTHORIZATION'      => 'Basic x',
            'HTTP_COOKIE'                   => 'c=1',
            'HTTP_X_API_KEY'                => 'k',
            'PHP_AUTH_USER'                 => 'u',
            'PHP_AUTH_PW'                   => 'p',
            'PHP_AUTH_DIGEST'               => 'd',
            'HTTP_CF_ACCESS_JWT_ASSERTION'  => 'jwt',
            'HTTP_X_AMZN_OIDC_ACCESSTOKEN'  => 'oidc',
            'HTTP_X_AMZN_OIDC_DATA'         => 'oidc-data',
            'HTTP_X_GOOG_IAP_JWT_ASSERTION' => 'iap',
            'HTTP_X_FORWARDED_ACCESS_TOKEN' => 'fwd',
            'HTTP_X_AUTH_TOKEN'             => 'auth',
            'HTTP_X_CSRF_TOKEN'             => 'csrf',
            'HTTP_X_TRACE'                  => 't',
            'HTTP_ACCEPT'                   => 'application/json',
            'HTTP_ACCEPT_LANGUAGE'          => 'es',
            'HTTP_USER_AGENT'               => 'ua',
            'HTTP_ORIGIN'                   => 'https://app.test',
            'HTTP_REFERER'                  => 'https://app.test/back?code=secret#frag',
            'HTTP_X_REQUEST_ID'             => 'req-1',
            'HTTP_X_FORWARDED_FOR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO'        => 'https',
        ]);
        $context = WikiRequestContexts::fromRequest($request);

        self::assertNull($context->credential);
        $headers = $context->headers;
        ksort($headers);
        self::assertSame([
            'accept'            => 'application/json',
            'accept-language'   => 'es',
            'content-type'      => 'application/x-www-form-urlencoded',
            'origin'            => 'https://app.test',
            'referer'           => 'https://app.test/back',
            'user-agent'        => 'ua',
            'x-forwarded-for'   => '10.0.0.1',
            'x-forwarded-proto' => 'https',
            'x-request-id'      => 'req-1',
        ], array_diff_key($headers, ['host' => true]));
        self::assertSame('localhost', $context->header('Host'));
        self::assertNull($context->header('X-Trace'), 'not on the allow-list');
        foreach (['authorization', 'proxy-authorization', 'cookie', 'x-api-key', 'php-auth-user', 'php-auth-pw', 'php-auth-digest', 'cf-access-jwt-assertion', 'x-amzn-oidc-accesstoken', 'x-amzn-oidc-data', 'x-goog-iap-jwt-assertion', 'x-forwarded-access-token', 'x-auth-token', 'x-csrf-token'] as $header) {
            self::assertNull($context->header($header), $header);
        }
        self::assertSame('PUT', $context->method);
        self::assertSame('/p', $context->path);
        self::assertNull($context->route);
        self::assertSame('s', WikiRequestContexts::fromRequest($request, true)->credential);
        self::assertSame(['method', 'path', 'route', 'clientIp', 'credential', 'headers'], array_keys(get_object_vars($context)), 'no query string');
    }

    public function testARefererThatIsNotAUrlIsDropped(): void
    {
        $context = WikiRequestContexts::fromRequest(Request::create('/p', server: ['HTTP_REFERER' => '/relative?x=1']));
        self::assertNull($context->header('referer'));
        self::assertSame('https://a.test', WikiRequestContexts::fromRequest(Request::create('/p', server: ['HTTP_REFERER' => 'https://user:pw@a.test']))->header('referer'), 'userinfo dropped');
    }

    public function testDownloadsAreAttachmentsFromAPathOrAStream(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertNotFalse($stream);
        fwrite($stream, 'body');
        rewind($stream);
        $response = WikiRequestContexts::response(new WikiApiDownload($stream, 'a "b".txt', 'text/plain', 4));
        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('4', $response->headers->get('Content-Length'));
        self::assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        ob_start();
        $response->sendContent();
        self::assertSame('body', ob_get_clean());

        $closed = fopen('php://memory', 'r');
        self::assertNotFalse($closed);
        $unsized = WikiRequestContexts::response(new WikiApiDownload($closed, 'x.bin'));
        self::assertFalse($unsized->headers->has('Content-Length'));
        fclose($closed);
        ob_start();
        $unsized->sendContent();
        self::assertSame('', ob_get_clean());

        $this->expectException(InvalidArgumentException::class);
        new WikiApiDownload(42, 'x');
    }

    public function testAnUploadOpensItsFile(): void
    {
        $upload = new WikiApiUpload(__FILE__, 'a.php', null, 1);
        $stream = $upload->open();
        self::assertIsResource($stream);
        fclose($stream);

        $this->expectException(RuntimeException::class);
        (new WikiApiUpload('/nonexistent/wiki-upload', 'gone.txt', 'text/plain', 0))->open();
    }

    public function testTheCookieListenerSetsTheQueuedCookiesOnce(): void
    {
        $request = Request::create('/');
        WikiCollabCookieListener::queue($request, [new WikiCollabCookie('hub', 'jwt')]);
        WikiCollabCookieListener::queue($request, [new WikiCollabCookie('two', 'v', null, '/w', 'example.test', false, false, 'lax'), new WikiCollabCookie('three', 'v', null, '/', null, true, true, 'NONE')]);
        $request->attributes->set(WikiCollabCookieListener::ATTRIBUTE, [...(array) $request->attributes->get(WikiCollabCookieListener::ATTRIBUTE), 'junk']);
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $response = new Response();
        $listener = new WikiCollabCookieListener();

        $listener->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }
        self::assertCount(3, $cookies);
        self::assertTrue($cookies['hub']->isSecure());
        self::assertSame('strict', $cookies['hub']->getSameSite());
        self::assertSame('lax', $cookies['two']->getSameSite());
        self::assertSame('example.test', $cookies['two']->getDomain());
        self::assertSame('none', $cookies['three']->getSameSite());
        self::assertFalse($request->attributes->has(WikiCollabCookieListener::ATTRIBUTE));

        $empty = new Response();
        $listener->onResponse(new ResponseEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $empty));
        self::assertSame([], $empty->headers->getCookies());
    }

    public function testApiWikisCarryTheirSpaceId(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't');

        self::assertSame($space->getId(), (new WikiApiWiki('docs', $space, 'Docs'))->spaceId);
        self::assertSame('s-1', (new WikiApiWiki('docs', null, 'Docs', spaceId: 's-1'))->spaceId);
        self::assertSame($space->getId(), (new WikiApiWiki('docs', $space, 'Docs', spaceId: $space->getId()))->spaceId);
        self::assertSame($space->getId(), (new WikiApiWiki('docs', null, 'Docs'))->withSpace($space)->spaceId);

        $this->expectException(InvalidArgumentException::class);
        new WikiApiWiki('docs', $space, 'Docs', spaceId: 'other');
    }

    public function testPrincipalsForAReference(): void
    {
        $ref       = WikiUserRef::ofId(TestUserEntity::class, '5', 'alice');
        $principal = WikiApiPrincipal::of($ref, WikiApiLevel::Edit, WikiApiWhere::everything(), 'tok', 'name', 'mcp');

        self::assertInstanceOf(WikiRefUser::class, $principal->user);
        self::assertSame($ref, $principal->userRef());
        self::assertSame('mcp', $principal->channel);
        $security = new TestUser('u-1', 'bob');
        self::assertSame($security, WikiApiPrincipal::of(WikiUserRefs::fromUser($security), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n')->user);
        self::assertSame('bob', (new WikiApiPrincipal($security, WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n'))->userRef()->identifier);
    }
}
