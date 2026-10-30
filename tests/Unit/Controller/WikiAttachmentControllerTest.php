<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Nowo\WikiBundle\Attachment\ConfiguredWikiAttachmentLimitsProvider;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentService;
use Nowo\WikiBundle\Controller\WikiAttachmentController;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class WikiAttachmentControllerTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private WikiSpace $space;

    private WikiSpace $other;

    private WikiPage $page;

    private InMemoryAttachmentRepository $repo;

    private string $dir;

    protected function setUp(): void
    {
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->other = new WikiSpace('hr', 'HR', WikiSpaceOwnerScope::Team, 't');
        $this->page  = new WikiPage($this->space, 'runbook', 'Runbook');
        $this->repo  = new InMemoryAttachmentRepository();
        $this->dir   = sys_get_temp_dir() . '/wiki-ctrl-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testUploadStoresTheFileAndAnswersItsDescription(): void
    {
        [$controller, $container] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit));
        $request                  = $this->uploadRequest($container, base64_decode(self::PNG, true), 'shot.png');

        $response = $controller->upload($request, 'eng', 'runbook');
        $data     = json_decode((string) $response->getContent(), true);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('/generated/nowo_wiki_attachment_download', $data['url']);
        self::assertSame('shot.png', $data['name']);
        self::assertTrue($data['image']);
        self::assertCount(1, $this->repo->rows);
    }

    public function testRefusedAndMissingFilesAnswer422(): void
    {
        [$controller, $container] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit));

        $svg = $controller->upload($this->uploadRequest($container, '<svg xmlns="http://www.w3.org/2000/svg"/>', 'x.svg'), 'eng', 'runbook');
        self::assertSame(422, $svg->getStatusCode());
        self::assertSame('{"error":"type_not_allowed"}', $svg->getContent());

        $request = Request::create('/upload', 'POST', ['_token' => WikiControllerContainerBuilder::csrfToken($container, WikiAttachmentController::csrfTokenId($this->page))]);
        self::assertSame('{"error":"empty"}', $controller->upload($request, 'eng', 'runbook')->getContent());
        self::assertSame([], $this->repo->rows);
    }

    public function testUploadDenials(): void
    {
        [$readOnly, $container] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));
        $this->assertThrows(AccessDeniedHttpException::class, fn () => $readOnly->upload($this->uploadRequest($container, 'x', 'a.txt'), 'eng', 'runbook'));

        [$editor] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit));
        $this->assertThrows(AccessDeniedHttpException::class, static fn () => $editor->upload(Request::create('/u', 'POST', ['_token' => 'forged']), 'eng', 'runbook'));
        $this->assertThrows(NotFoundHttpException::class, static fn () => $editor->upload(Request::create('/u', 'POST'), 'eng', 'missing'));
        $this->assertThrows(NotFoundHttpException::class, static fn () => $editor->upload(Request::create('/u', 'POST'), 'nope', 'runbook'));

        [$stranger] = $this->controller(CallbackSpaceAccessChecker::allowing());
        $this->assertThrows(NotFoundHttpException::class, static fn () => $stranger->upload(Request::create('/u', 'POST'), 'eng', 'runbook'));

        [$pageHidden] = $this->controller(new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => !$p instanceof WikiPage));
        $this->assertThrows(NotFoundHttpException::class, static fn () => $pageHidden->upload(Request::create('/u', 'POST'), 'eng', 'runbook'));

        [$disabled] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit), enabled: false);
        $this->assertThrows(NotFoundHttpException::class, static fn () => $disabled->upload(Request::create('/u', 'POST'), 'eng', 'runbook'));

        [$anonymous] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View), user: false);
        $this->assertThrows(AccessDeniedHttpException::class, static fn () => $anonymous->download('eng', 'x'));

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/no-firewall'));
        $tokenGuard  = new WikiTokenGuard($requestStack);
        [$untrusted] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View), tokenGuard: $tokenGuard);
        $this->assertThrows(AccessDeniedHttpException::class, static fn () => $untrusted->download('eng', 'x'));
    }

    public function testDownloadStreamsWithPrivateHeaders(): void
    {
        $image        = $this->stored('shot.png', base64_decode(self::PNG, true));
        $pdf          = $this->stored('Informe año.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));

        $response = $controller->download('eng', $image->getId());
        ob_start();
        $response->sendContent();
        self::assertSame(base64_decode(self::PNG, true), ob_get_clean());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $download = $controller->download('eng', $pdf->getId());
        self::assertStringStartsWith('attachment; filename="Informe a', (string) $download->headers->get('Content-Disposition'));
        self::assertStringContainsString("filename*=utf-8''Informe%20a%C3%B1o.pdf", (string) $download->headers->get('Content-Disposition'));
    }

    public function testEveryDownloadDenialIsNotFound(): void
    {
        $file = $this->stored('a.txt', 'hello');

        [$reader] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));
        $this->assertThrows(NotFoundHttpException::class, static fn () => $reader->download('eng', '0b9c9a9e-1111-4222-8333-444455556666'));
        $this->assertThrows(NotFoundHttpException::class, static fn () => $reader->download('hr', $file->getId()));

        [$pageHidden] = $this->controller(new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => !$p instanceof WikiPage));
        $this->assertThrows(NotFoundHttpException::class, static fn () => $pageHidden->download('eng', $file->getId()));

        $this->page->archive();
        $this->assertThrows(NotFoundHttpException::class, static fn () => $reader->download('eng', $file->getId()));

        [$manager] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Manage));
        self::assertSame(200, $manager->download('eng', $file->getId())->getStatusCode());
    }

    /**
     * @param class-string<Throwable> $class
     */
    private function assertThrows(string $class, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $class);
        } catch (Throwable $exception) {
            self::assertInstanceOf($class, $exception);
        }
    }

    private function stored(string $name, string $content): WikiAttachment
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wiki-dl-');
        file_put_contents($path, $content);

        return $this->service()->upload($this->page, $path, $name);
    }

    private function service(): WikiAttachmentService
    {
        return new WikiAttachmentService(
            $this->repo,
            new LocalWikiAttachmentStorage($this->dir),
            new NullWikiAttachmentScanner(),
            new ConfiguredWikiAttachmentLimitsProvider(),
            new EventDispatcher(),
        );
    }

    private function uploadRequest(Container $container, string $content, string $name): Request
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wiki-up-');
        file_put_contents($path, $content);
        $request = Request::create('/upload', 'POST', [], [], ['file' => new UploadedFile($path, $name, null, null, true)]);
        $request->headers->set('X-CSRF-Token', WikiControllerContainerBuilder::csrfToken($container, WikiAttachmentController::csrfTokenId($this->page)));

        return $request;
    }

    /**
     * @return array{0: WikiAttachmentController, 1: Container}
     */
    private function controller(CallbackSpaceAccessChecker $checker, bool $enabled = true, bool $user = true, ?WikiTokenGuard $tokenGuard = null): array
    {
        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('findBySlug')->willReturnCallback(fn (WikiSpace $space, string $slug): ?WikiPage => $slug === 'runbook' ? $this->page : null);

        $controller = new WikiAttachmentController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space, $this->other), $checker),
            $pages,
            $this->repo,
            $this->service(),
            ['attachment_download' => ['path' => '/x', 'name' => 'nowo_wiki_attachment_download']],
            ['enabled' => $enabled],
            $tokenGuard,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user ? new TestUser() : null);

        return [$controller, $container];
    }
}
