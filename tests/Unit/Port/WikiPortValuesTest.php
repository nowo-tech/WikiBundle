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

use function fopen;
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

    public function testRequestContextsDropCredentials(): void
    {
        $request = Request::create('/p?q=v&a[]=x', 'PUT', server: [
            'HTTP_AUTHORIZATION'       => 'Bearer s',
            'HTTP_PROXY_AUTHORIZATION' => 'Basic x',
            'HTTP_COOKIE'              => 'c=1',
            'HTTP_X_API_KEY'           => 'k',
            'PHP_AUTH_USER'            => 'u',
            'PHP_AUTH_PW'              => 'p',
            'HTTP_X_TRACE'             => 't',
        ]);
        $context = WikiRequestContexts::fromRequest($request);

        self::assertNull($context->credential);
        self::assertSame('t', $context->header('X-Trace'));
        foreach (['authorization', 'proxy-authorization', 'cookie', 'x-api-key', 'php-auth-user', 'php-auth-pw'] as $header) {
            self::assertNull($context->header($header), $header);
        }
        self::assertSame(['q' => 'v'], $context->query);
        self::assertNull($context->route);
        self::assertSame('s', WikiRequestContexts::fromRequest($request, true)->credential);
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
