<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiPageService;

use function sprintf;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Seeds rich demo wiki pages: nested tree, internal links, images, and video embeds.
 */
final class WikiDemoSeeder
{
    private const WIKI_BASE = '/tools/wiki/spaces';

    public function __construct(
        private readonly WikiPageService $pageService,
    ) {
    }

    public function seedEngineeringSpace(WikiSpace $space, User $user): void
    {
        $welcome        = $this->create($space, 'Welcome', 'welcome', $this->welcomeContent($space), $user);
        $gettingStarted = $this->create($space, 'Getting Started', 'getting-started', $this->gettingStartedContent($space), $user);
        $installation   = $this->create($space, 'Installation', 'installation', $this->installationContent($space), $user, $gettingStarted);
        $configuration  = $this->create($space, 'Configuration', 'configuration', $this->configurationContent($space), $user, $gettingStarted);

        $architecture = $this->create($space, 'Architecture', 'architecture', $this->architectureContent($space), $user);
        $frontend     = $this->create($space, 'Frontend', 'frontend', $this->frontendContent($space), $user, $architecture);
        $tiptap       = $this->create($space, 'Tiptap Editor', 'tiptap-editor', $this->tiptapEditorContent($space), $user, $frontend);
        $backend      = $this->create($space, 'Backend', 'backend', $this->backendContent($space), $user, $architecture);

        $runbooks = $this->create($space, 'Runbooks', 'runbooks', $this->runbooksContent($space), $user);
        $deploy   = $this->create($space, 'Deploy', 'deploy', $this->deployContent($space), $user, $runbooks);
        $rollback = $this->create($space, 'Rollback', 'rollback', $this->rollbackContent($space), $user, $runbooks);

        $this->create($space, 'Resources', 'resources', $this->resourcesContent($space), $user);

        unset($welcome, $gettingStarted, $installation, $configuration, $architecture, $frontend, $tiptap, $backend, $runbooks, $deploy, $rollback);
    }

    public function seedProductSpace(WikiSpace $space, User $user): void
    {
        $roadmap = $this->create($space, 'Roadmap', 'roadmap', $this->roadmapContent($space), $user);
        $this->create($space, 'Release Notes', 'release-notes', $this->releaseNotesContent($space), $user);
        $this->create($space, 'v1.0 — Wiki launch', 'v1-0-wiki-launch', $this->v10Content($space), $user, $roadmap);
    }

    private function create(
        WikiSpace $space,
        string $title,
        string $slug,
        string $html,
        User $user,
        ?WikiPage $parent = null,
    ): WikiPage {
        return $this->pageService->create($space, $title, $html, $user, $parent, $slug);
    }

    private function pageLink(WikiSpace $space, string $pageSlug, string $label): string
    {
        $url = self::WIKI_BASE . '/' . $space->getSlug() . '/pages/' . $pageSlug;

        return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private function productPageLink(string $pageSlug, string $label): string
    {
        $url = self::WIKI_BASE . '/product/pages/' . $pageSlug;

        return sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private function welcomeContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Engineering Wiki</h1>
<p><img src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=960&amp;h=400&amp;fit=crop" alt="Engineering workspace" width="100%"></p>
<p>Demo space for <strong>WikiBundle</strong>: nested pages, revision history, search, and Tiptap rich text.</p>
<blockquote><p>Start with {$this->pageLink($space, 'getting-started', 'Getting Started')} or explore {$this->pageLink($space, 'architecture', 'Architecture')}.</p></blockquote>
<h2>Quick links</h2>
<ul>
<li>{$this->pageLink($space, 'installation', 'Installation guide')}</li>
<li>{$this->pageLink($space, 'tiptap-editor', 'Tiptap editor notes')}</li>
<li>{$this->pageLink($space, 'deploy', 'Deploy runbook')}</li>
<li>{$this->pageLink($space, 'resources', 'Resources &amp; embeds')}</li>
</ul>
<h2>Related spaces</h2>
<p>Product docs live in the {$this->productPageLink('roadmap', 'Product → Roadmap')} space.</p>
HTML;
    }

    private function gettingStartedContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Getting Started</h1>
<p>Onboarding path for contributors. Child pages cover setup end-to-end.</p>
<ol>
<li>{$this->pageLink($space, 'installation', 'Installation')} — Docker demo and Composer path repos</li>
<li>{$this->pageLink($space, 'configuration', 'Configuration')} — <code>nowo_wiki</code> and Tiptap profiles</li>
</ol>
<p>← Back to {$this->pageLink($space, 'welcome', 'Welcome')}</p>
HTML;
    }

    private function installationContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Installation</h1>
<p>Run the Symfony 8 demo from the bundle repository:</p>
<pre><code>make -C demo/symfony8 up
# Demo: http://localhost:8025/tools/wiki</code></pre>
<p>Requires <strong>TiptapEditorBundle</strong> and published assets:</p>
<pre><code>php bin/console assets:install public</code></pre>
<p>Next: {$this->pageLink($space, 'configuration', 'Configuration')} · Parent: {$this->pageLink($space, 'getting-started', 'Getting Started')}</p>
HTML;
    }

    private function configurationContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Configuration</h1>
<table>
<thead><tr><th>Key</th><th>Default</th><th>Description</th></tr></thead>
<tbody>
<tr><td><code>nowo_wiki.editor.tiptap_config</code></td><td><code>notion</code></td><td>Tiptap profile (toolbar, min height)</td></tr>
<tr><td><code>nowo_wiki.space_scope</code></td><td><code>team</code></td><td><code>user</code> in this demo</td></tr>
<tr><td><code>nowo_wiki.security.*_roles</code></td><td><code>ROLE_USER</code></td><td>ACL per action</td></tr>
</tbody>
</table>
<p>See also {$this->pageLink($space, 'backend', 'Backend architecture')}.</p>
HTML;
    }

    private function architectureContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Architecture</h1>
<p>The wiki splits UI, persistence, and security across Symfony services and Doctrine entities.</p>
<div>
<p><strong>Frontend</strong> — {$this->pageLink($space, 'frontend', 'Frontend stack')} (Tiptap, Twig, CSS)</p>
<p><strong>Backend</strong> — {$this->pageLink($space, 'backend', 'Backend stack')} (entities, revisions, sanitizer)</p>
</div>
<hr>
<p>External reference: <a href="https://symfony.com/doc/current/bundles.html" rel="noopener noreferrer">Symfony Bundles</a></p>
HTML;
    }

    private function frontendContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Frontend</h1>
<p><img src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&amp;h=320&amp;fit=crop" alt="Code on screen" width="100%"></p>
<p>Page bodies are edited with {$this->pageLink($space, 'tiptap-editor', 'Tiptap Editor')} (<code>variant: notion</code>).</p>
<ul>
<li>Sidebar tree with nested pages</li>
<li>Immutable revisions + diff view</li>
<li>Full-text search per space</li>
</ul>
HTML;
    }

    private function tiptapEditorContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Tiptap Editor</h1>
<p>Wiki pages use <code>TiptapEditorType</code> with the Notion-like chrome. Load <code>tiptap-editor.js</code> on edit screens.</p>
<h2>Intro video</h2>
<iframe src="https://www.youtube.com/embed/LtM5qQAeYIo" width="100%" height="360" allowfullscreen="allowfullscreen"></iframe>
<p>Parent: {$this->pageLink($space, 'frontend', 'Frontend')}</p>
HTML;
    }

    private function backendContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Backend</h1>
<p>Core flow: controller → <code>WikiPageService</code> → sanitized HTML revision → Doctrine.</p>
<pre><code>WikiPage
  └── WikiPageRevision (immutable, numbered)
WikiSpace (team | user scope)</code></pre>
<p>Operations: {$this->pageLink($space, 'deploy', 'Deploy runbook')}</p>
HTML;
    }

    private function runbooksContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Runbooks</h1>
<p>Operational procedures for the demo environment.</p>
<ul>
<li>{$this->pageLink($space, 'deploy', 'Deploy')} — start containers, migrations, fixtures</li>
<li>{$this->pageLink($space, 'rollback', 'Rollback')} — recovery steps</li>
</ul>
HTML;
    }

    private function deployContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Deploy</h1>
<ol>
<li><code>make -C demo/symfony8 up</code></li>
<li><code>make assets</code> in bundle root (wiki + tiptap JS)</li>
<li><code>make -C demo/symfony8 update-bundle</code></li>
<li>Open <a href="/tools/wiki">/tools/wiki</a></li>
</ol>
<p>If something fails, see {$this->pageLink($space, 'rollback', 'Rollback')}.</p>
HTML;
    }

    private function rollbackContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Rollback</h1>
<blockquote><p><strong>Warning:</strong> rolling back drops ephemeral container state; export DB if needed.</p></blockquote>
<pre><code>make -C demo/symfony8 down
docker volume rm wiki-bundle-demo-symfony-8_mysql-data  # optional reset</code></pre>
<p>Then re-run {$this->pageLink($space, 'deploy', 'Deploy')}.</p>
HTML;
    }

    private function resourcesContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Resources</h1>
<h2>Image gallery</h2>
<p><img src="https://picsum.photos/seed/wiki-board/400/240" alt="Board" width="48%"> <img src="https://picsum.photos/seed/wiki-team/400/240" alt="Team" width="48%"></p>
<h2>Embedded talk (Vimeo)</h2>
<iframe src="https://player.vimeo.com/video/1084537" width="100%" height="360" allowfullscreen="allowfullscreen"></iframe>
<h2>Bookmarks</h2>
<table>
<thead><tr><th>Topic</th><th>Link</th></tr></thead>
<tbody>
<tr><td>Tiptap</td><td><a href="https://tiptap.dev/" rel="noopener noreferrer">tiptap.dev</a></td></tr>
<tr><td>Outline</td><td><a href="https://www.getoutline.com/" rel="noopener noreferrer">getoutline.com</a></td></tr>
<tr><td>WikiBundle</td><td><a href="https://github.com/nowo-tech/WikiBundle" rel="noopener noreferrer">GitHub</a></td></tr>
</tbody>
</table>
<p>Internal: {$this->pageLink($space, 'welcome', 'Welcome')} · {$this->productPageLink('v1-0-wiki-launch', 'Product release v1.0')}</p>
HTML;
    }

    private function roadmapContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Product Roadmap</h1>
<p>Second demo space — <em>Product</em> — shows multi-space navigation.</p>
<ul>
<li>Q3 — Wiki v1 (spaces, revisions, search) ✓</li>
<li>Q4 — Team ACL resolver + mentions</li>
<li>Q1 — Real-time co-editing (research)</li>
</ul>
<p>Details: {$this->pageLink($space, 'v1-0-wiki-launch', 'v1.0 — Wiki launch')}</p>
<p>Engineering docs: <a href="/tools/wiki/spaces/engineering/pages/welcome">Engineering → Welcome</a></p>
HTML;
    }

    private function releaseNotesContent(WikiSpace $space): string
    {
        return <<<HTML
<h1>Release Notes</h1>
<p>Version history for the product wiki module.</p>
<ul>
<li>{$this->pageLink($space, 'v1-0-wiki-launch', 'v1.0 — Wiki launch')}</li>
</ul>
HTML;
    }

    private function v10Content(WikiSpace $space): string
    {
        return <<<HTML
<h1>v1.0 — Wiki launch</h1>
<p><img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&amp;h=300&amp;fit=crop" alt="Team launch" width="100%"></p>
<h2>Highlights</h2>
<ul>
<li>Nested page tree per space</li>
<li>Tiptap Notion variant for editing</li>
<li>Revision diff and full-text search</li>
</ul>
<p>Parent: {$this->pageLink($space, 'roadmap', 'Roadmap')}</p>
HTML;
    }
}
