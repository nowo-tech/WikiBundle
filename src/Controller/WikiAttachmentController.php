<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException;
use Nowo\WikiBundle\Attachment\WikiAttachmentService;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use function fclose;
use function fpassthru;
use function iconv;
use function preg_replace;

/**
 * Upload and download of page attachments (1.9). Uploads ask {@see WikiAction::Edit} on the page; downloads ask
 * {@see WikiAction::View} on the attachment's own page and answer 404 to every denial (missing, other space,
 * forbidden, archived page for a non-manager), so an attachment URL reveals nothing.
 */
#[AsController]
final class WikiAttachmentController extends AbstractController
{
    /**
     * @param WikiSpaceGuard $spaceGuard space resolution and access
     * @param WikiPageRepositoryInterface $pageRepository page lookups
     * @param WikiAttachmentRepositoryInterface $attachmentRepository attachment lookups
     * @param WikiAttachmentService $attachmentService storage of the files
     * @param array<string, array{path: string, name: string}> $routes route name/path per action
     * @param array{enabled: bool} $attachments attachment options
     * @param WikiTokenGuard|null $tokenGuard firewall-trust check of the security token
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiAttachmentRepositoryInterface $attachmentRepository,
        private readonly WikiAttachmentService $attachmentService,
        private readonly array $routes,
        private readonly array $attachments = ['enabled' => false],
        private readonly ?WikiTokenGuard $tokenGuard = null,
    ) {
    }

    /**
     * CSRF token id of the uploads to a page (sent as the {@code X-CSRF-Token} header or the {@code _token} field).
     */
    public static function csrfTokenId(WikiPage $page): string
    {
        return 'wiki_attachment_' . $page->getId();
    }

    /**
     * Stores the uploaded {@code file} for the page and answers its JSON description; a refused file answers 422
     * with {@code {"error": "<reason>"}} and stores nothing.
     *
     * @param Request $request multipart request with the {@code file} field
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     *
     * @throws NotFoundHttpException when attachments are disabled or the page is missing / not readable
     * @throws AccessDeniedHttpException when the user may not edit the page or the CSRF token is invalid
     *
     * @return JsonResponse the attachment ({@code id}, {@code url}, {@code name}, {@code mime}, {@code size}, {@code image})
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/attachments', name: 'nowo_wiki_attachment_upload', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'attachment_upload'], methods: ['POST'])]
    public function upload(Request $request, string $spaceSlug, string $pageSlug): JsonResponse
    {
        $this->denyUnlessEnabled();
        $user  = $this->requireUser();
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $page  = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }

        $this->spaceGuard->requireGranted($user, WikiAction::View, $space, $page);
        $this->spaceGuard->requireGranted($user, WikiAction::Edit, $space, $page);

        $token = $request->headers->get('X-CSRF-Token') ?? $request->request->getString('_token');
        if (!$this->isCsrfTokenValid(self::csrfTokenId($page), $token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(['error' => WikiAttachmentRejectedException::EMPTY], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $attachment = $this->attachmentService->upload($page, $file->getPathname(), $file->getClientOriginalName(), $user);
        } catch (WikiAttachmentRejectedException $exception) {
            return new JsonResponse(['error' => $exception->reason], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'id'    => $attachment->getId(),
            'url'   => $this->generateUrl($this->routes['attachment_download']['name'], ['spaceSlug' => $space->getSlug(), 'attachmentId' => $attachment->getId()]),
            'name'  => $attachment->getOriginalName(),
            'mime'  => $attachment->getMimeType(),
            'size'  => $attachment->getSize(),
            'image' => $attachment->isInlineImage(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Streams an attachment to a user who may read its page; 404 for every denial.
     *
     * @param string $spaceSlug space slug from the route
     * @param string $attachmentId attachment UUID from the route
     *
     * @throws NotFoundHttpException for every missing or forbidden case alike
     *
     * @return StreamedResponse the file (inline for raster images, attachment otherwise), never cached
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/attachments/{attachmentId}', name: 'nowo_wiki_attachment_download', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'attachmentId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'attachment_download'], methods: ['GET'])]
    public function download(string $spaceSlug, string $attachmentId): StreamedResponse
    {
        $this->denyUnlessEnabled();
        $user       = $this->requireUser();
        $space      = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $attachment = $this->attachmentRepository->findById($attachmentId);
        if (!$attachment instanceof WikiAttachment || $attachment->getSpace()->getId() !== $space->getId()) {
            throw new NotFoundHttpException('Attachment not found.');
        }

        $page = $attachment->getPage();
        if (
            !$this->spaceGuard->isGranted($user, WikiAction::View, $space, $page)
            || ($page->isArchived() && !$this->spaceGuard->isGranted($user, WikiAction::Manage, $space, $page))
        ) {
            throw new NotFoundHttpException('Attachment not found.');
        }

        $stream   = $this->attachmentService->openStream($attachment);
        $response = new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });

        $name     = $attachment->getOriginalName();
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: 'file');
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('Content-Length', (string) $attachment->getSize());
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $attachment->isInlineImage() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $name,
            $fallback === '' ? 'file' : $fallback,
        ));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox");
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function denyUnlessEnabled(): void
    {
        if (!$this->attachments['enabled']) {
            throw new NotFoundHttpException('Attachments are disabled.');
        }
    }

    private function requireUser(): UserInterface
    {
        $user = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }
}
