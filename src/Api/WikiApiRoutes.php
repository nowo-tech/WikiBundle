<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Nowo\WikiBundle\Api\Controller\WikiApiCommentController;
use Nowo\WikiBundle\Api\Controller\WikiApiDelegatedController;
use Nowo\WikiBundle\Api\Controller\WikiApiReadController;
use Nowo\WikiBundle\Api\Controller\WikiApiWikiController;
use Nowo\WikiBundle\Api\Controller\WikiApiWriteController;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Routing\WikiRouteLoader;

/**
 * 1.24 (spec 218): the API's routes, added by {@see WikiRouteLoader} under `api.prefix` only
 * with `api.enabled`. Never copied under tenant paths.
 */
final class WikiApiRoutes
{
    /** Route name prefix. */
    public const NAME_PREFIX = 'nowo_wiki_api_';

    /**
     * name => [path after the prefix, controller service, method, HTTP method].
     *
     * @var array<string, array{0: string, 1: class-string, 2: string, 3: string}>
     */
    public const ROUTES = [
        'wikis'          => ['/wikis', WikiApiWikiController::class, 'list', 'GET'],
        'wiki'           => ['/wikis/{wiki}', WikiApiWikiController::class, 'get', 'GET'],
        'pages'          => ['/wikis/{wiki}/pages', WikiApiReadController::class, 'pages', 'GET'],
        'page'           => ['/wikis/{wiki}/pages/{page}', WikiApiReadController::class, 'page', 'GET'],
        'revisions'      => ['/wikis/{wiki}/pages/{page}/revisions', WikiApiReadController::class, 'revisions', 'GET'],
        'revision'       => ['/wikis/{wiki}/pages/{page}/revisions/{number}', WikiApiReadController::class, 'revision', 'GET'],
        'diff'           => ['/wikis/{wiki}/pages/{page}/revisions/{number}/diff', WikiApiReadController::class, 'diff', 'GET'],
        'threads'        => ['/wikis/{wiki}/pages/{page}/threads', WikiApiReadController::class, 'threads', 'GET'],
        'thread'         => ['/wikis/{wiki}/pages/{page}/threads/{thread}', WikiApiReadController::class, 'thread', 'GET'],
        'search'         => ['/search', WikiApiReadController::class, 'search', 'GET'],
        'page_create'    => ['/wikis/{wiki}/pages', WikiApiWriteController::class, 'create', 'POST'],
        'page_update'    => ['/wikis/{wiki}/pages/{page}', WikiApiWriteController::class, 'update', 'PUT'],
        'page_move'      => ['/wikis/{wiki}/pages/{page}/move', WikiApiWriteController::class, 'move', 'POST'],
        'page_archive'   => ['/wikis/{wiki}/pages/{page}/archive', WikiApiWriteController::class, 'archive', 'POST'],
        'page_unarchive' => ['/wikis/{wiki}/pages/{page}/unarchive', WikiApiWriteController::class, 'unarchive', 'POST'],
        'thread_create'  => ['/wikis/{wiki}/pages/{page}/threads', WikiApiCommentController::class, 'create', 'POST'],
        'thread_reply'   => ['/wikis/{wiki}/pages/{page}/threads/{thread}/comments', WikiApiCommentController::class, 'reply', 'POST'],
        'thread_resolve' => ['/wikis/{wiki}/pages/{page}/threads/{thread}', WikiApiCommentController::class, 'resolve', 'PATCH'],
        'thread_delete'  => ['/wikis/{wiki}/pages/{page}/threads/{thread}', WikiApiCommentController::class, 'deleteThread', 'DELETE'],
        'comment_edit'   => ['/wikis/{wiki}/pages/{page}/comments/{comment}', WikiApiCommentController::class, 'edit', 'PATCH'],
        'comment_delete' => ['/wikis/{wiki}/pages/{page}/comments/{comment}', WikiApiCommentController::class, 'delete', 'DELETE'],
        'wiki_create'    => ['/wikis', WikiApiDelegatedController::class, 'createWiki', 'POST'],
        'wiki_update'    => ['/wikis/{wiki}', WikiApiDelegatedController::class, 'updateWiki', 'PATCH'],
        'wiki_archive'   => ['/wikis/{wiki}/archive', WikiApiDelegatedController::class, 'archiveWiki', 'POST'],
        'wiki_unarchive' => ['/wikis/{wiki}/unarchive', WikiApiDelegatedController::class, 'unarchiveWiki', 'POST'],
        'page_files'     => ['/wikis/{wiki}/pages/{page}/files', WikiApiDelegatedController::class, 'files', 'GET'],
        'file_upload'    => ['/wikis/{wiki}/files', WikiApiDelegatedController::class, 'upload', 'POST'],
        'file_download'  => ['/wikis/{wiki}/files/{file}', WikiApiDelegatedController::class, 'download', 'GET'],
        'page_restore'   => ['/wikis/{wiki}/pages/{page}/revisions/{number}/restore', WikiApiWriteController::class, 'restore', 'POST'],
    ];

    /**
     * Placeholder requirements: anything else never reaches a controller (404 in the API's shape).
     *
     * @var array<string, string>
     */
    public const REQUIREMENTS = [
        'wiki'    => WikiApiGuard::SLUG_ROUTE,
        'page'    => WikiApiGuard::UUID,
        'file'    => WikiApiGuard::UUID,
        'number'  => '[1-9]\d{0,8}',
        'against' => '[1-9]\d{0,8}',
        'thread'  => '[A-Za-z0-9_-]{1,64}',
        'comment' => '[A-Za-z0-9_-]{1,64}',
    ];
}
