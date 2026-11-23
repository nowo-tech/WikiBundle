<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\HttpFoundation\Response;

use function fwrite;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function strlen;

use const STDERR;

/**
 * Lazy dialogs (owner rule 2026-11-23, "every dialog loads its content on demand"): a space with 50 pages and every
 * dialog option on renders no dialog body and no dialog form; each dialog's own page serves its fragment for
 * `X-Nowo-Dialog: 1` and the full page without it, with the same access decision as the action it serves.
 */
final class LazyDialogsWebTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'tree'          => ['enabled' => true, 'context_menu' => true],
        'trash'         => ['enabled' => true],
        'import_export' => ['enabled' => true],
        'page_icon'     => ['form_field' => true],
        'web_ui'        => ['new_page_dialog' => true, 'import_export' => 'dialog'],
    ];

    protected static bool $slugLocator = true;

    public function testASpaceOfFiftyPagesRendersNoDialogBody(): void
    {
        $parent = null;
        for ($i = 1; $i <= 50; ++$i) {
            $page   = $this->page($this->eng, 'Page ' . $i, 'Body ' . $i, $i % 5 === 0 ? null : $parent);
            $parent = $i % 5 === 0 ? null : $page;
        }
        $html = $this->signedInPage($this->ana, '/tools/wiki/spaces/eng');
        // Before 1.53: 88,544 bytes, 4 forms (search, new page, change icon, move to) and 5 dialogs with their bodies.
        fwrite(STDERR, sprintf("\n[lazy-dialogs] wiki space, 50 pages: %d bytes, %d <form, %d <dialog\n", strlen($html), preg_match_all('/<form\b/', $html), preg_match_all('/<dialog\b/', $html)));

        // The search form only: no new-page, icon, move or import form, and no form token of theirs.
        self::assertSame(1, preg_match_all('/<form\b/', $html));
        self::assertStringNotContainsString('wiki_page_form', $html);
        self::assertStringNotContainsString('name="form[icon]"', $html);
        self::assertStringNotContainsString('name="form[parent]"', $html);
        self::assertStringNotContainsString('<template data-wiki-tree-icon-template>', $html);
        self::assertStringNotContainsString('id="nowo-wiki-import-export-form"', $html);
        // The dialogs are empty shells (the bare Archive / Delete confirm has no form; JS writes its sentence).
        foreach (['nowo-wiki-new-page-dialog', 'nowo-wiki-import-export-dialog'] as $id) {
            self::assertSame(1, preg_match('#<dialog id="' . $id . '".*?</dialog>#s', $html, $dialog), $id);
            self::assertMatchesRegularExpression('#data-nowo-ui-dialog-body></div>#', $dialog[0], $id . ' is empty');
        }
        self::assertStringContainsString('data-wiki-tree-icon-src="/tools/wiki/spaces/eng?dialog=tree-icon"', $html);
        self::assertStringContainsString('data-wiki-tree-move-src="/tools/wiki/spaces/eng?dialog=tree-move"', $html);
        self::assertStringContainsString('data-wiki-new-page-url="/tools/wiki/spaces/eng/pages/new"', $html);
        self::assertStringContainsString('href="/tools/wiki/spaces/eng/import" data-testid="wiki-import-export-open" data-nowo-ui-dialog-form', $html);
        // 50 rows, still one dialog of each kind.
        self::assertSame(5, preg_match_all('/<dialog\b/', $html));
    }

    public function testEachDialogServesItsFragmentAndTheFullPageWithoutTheHeader(): void
    {
        $home = $this->page($this->eng, 'Home', 'x');
        $this->client->loginUser($this->ana, 'main');
        foreach ([
            '/tools/wiki/spaces/eng/pages/new?place=child:' . $home->getId() => 'id="nowo-wiki-new-page-form"',
            '/tools/wiki/spaces/eng/import'                                  => 'data-testid="wiki-export-outline"',
            '/tools/wiki/spaces/eng?dialog=tree-icon'                        => 'name="form[icon]"',
            '/tools/wiki/spaces/eng?dialog=tree-move'                        => 'name="form[parent]"',
        ] as $path => $marker) {
            $fragment = $this->dialog($path);
            self::assertSame(200, $fragment->getStatusCode(), $path);
            self::assertContains('X-Nowo-Dialog', $fragment->getVary(), $path);
            self::assertStringNotContainsString('<html', (string) $fragment->getContent(), $path);
            self::assertStringContainsString($marker, (string) $fragment->getContent(), $path);
        }
        self::assertStringContainsString('Inside “Home”', (string) $this->dialog('/tools/wiki/spaces/eng/pages/new?place=child:' . $home->getId())->getContent());
        // A place that is not an intent (or a page the user may not read) gets no line: nothing about another page.
        self::assertStringNotContainsString('data-wiki-new-page-context', (string) $this->dialog('/tools/wiki/spaces/eng/pages/new?place=garbage')->getContent());
        self::assertStringNotContainsString('data-wiki-new-page-context', (string) $this->dialog('/tools/wiki/spaces/eng/pages/new?place=child:00000000-0000-0000-0000-000000000000')->getContent());
        // Without the header: the full pages (the new-page form, the import & export page, the space).
        foreach (['/tools/wiki/spaces/eng/pages/new', '/tools/wiki/spaces/eng/import', '/tools/wiki/spaces/eng?dialog=tree-icon'] as $path) {
            $this->client->request('GET', $path);
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), $path);
            self::assertStringContainsString('<html', (string) $this->client->getResponse()->getContent(), $path);
        }
    }

    public function testTheNewPageDialogPostsInPlace(): void
    {
        $this->client->loginUser($this->ana, 'main');
        $token   = $this->formToken((string) $this->dialog('/tools/wiki/spaces/eng/pages/new')->getContent());
        $invalid = $this->dialog('/tools/wiki/spaces/eng/pages/new', 'POST', ['wiki_page_form' => ['_token' => $token, 'title' => '', 'content' => 'x']]);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringNotContainsString('<html', (string) $invalid->getContent());
        self::assertStringContainsString('role="alert"', (string) $invalid->getContent(), 'the refusal is in the fragment');
        $created = $this->dialog('/tools/wiki/spaces/eng/pages/new', 'POST', ['wiki_page_form' => ['_token' => $token, 'title' => 'Made in the dialog', 'content' => 'Body']]);
        self::assertSame(204, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame('/tools/wiki/spaces/eng/pages/made-in-the-dialog', $created->headers->get('X-Nowo-Redirect'));
        // The same form without the dialog: the redirect as before.
        $this->client->request('POST', '/tools/wiki/spaces/eng/pages/new', ['wiki_page_form' => ['_token' => $token, 'title' => 'Made on the page', 'content' => 'Body']]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
    }

    public function testAccessIsTheDecisionOfTheActionsTheyServe(): void
    {
        $this->page($this->eng, 'Home', 'x');
        // A reader of eng: no create, edit, import or export there — the same refusals as the actions.
        $this->client->loginUser($this->bob, 'main');
        foreach (['/tools/wiki/spaces/eng/pages/new', '/tools/wiki/spaces/eng/import', '/tools/wiki/spaces/eng?dialog=tree-icon', '/tools/wiki/spaces/eng?dialog=tree-move'] as $path) {
            self::assertSame(403, $this->dialog($path)->getStatusCode(), $path);
        }
        $this->client->request('GET', '/tools/wiki/spaces/eng/pages/new');
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'the full page refuses alike');
        // A space bob may not read: 404, as the space page.
        foreach (['/tools/wiki/spaces/ops/pages/new', '/tools/wiki/spaces/ops/import', '/tools/wiki/spaces/ops?dialog=tree-icon'] as $path) {
            self::assertSame(404, $this->dialog($path)->getStatusCode(), $path);
        }
        // Signed out: the firewall, as every manage page.
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/tools/wiki/spaces/eng?dialog=tree-icon', [], [], ['HTTP_X_NOWO_DIALOG' => '1']);
        self::assertContains($this->client->getResponse()->getStatusCode(), [302, 401, 403]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function dialog(string $path, string $method = 'GET', array $parameters = []): Response
    {
        $this->client->request($method, $path, $parameters, [], ['HTTP_X_NOWO_DIALOG' => '1']);

        return $this->client->getResponse();
    }

    private function formToken(string $html): string
    {
        self::assertSame(1, preg_match('/name="wiki_page_form\[_token\]"[^>]*value="([^"]+)"/', $html, $match), 'the form token');

        return $match[1];
    }

    private function signedInPage(TestUserEntity $user, string $path): string
    {
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($user, 'main');
        $this->client->request('GET', $path);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return (string) $response->getContent();
    }
}
