<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Ai\WikiAiAnswer;
use Nowo\WikiBundle\Ai\WikiAiAnswererInterface;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Asks a framework-free {@see WikiAiAnswererInterface} (1.33.0) and turns its reply into a {@see WikiAiAnswer}: a
 * source is kept only when its page exists with exactly that id, in exactly the named space, inside the asked space
 * (if any), and the user may access that space.
 *
 * @internal
 */
final readonly class WikiAiAnswererGate implements WikiAiAssistantInterface
{
    public function __construct(
        private WikiAiAnswererInterface $answerer,
        private WikiPageRepositoryInterface $pages,
        private WikiSpaceAccessResolverInterface $access,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->answerer->isAvailable();
    }

    public function ask(UserInterface $user, string $question, ?WikiSpace $space = null): WikiAiAnswer
    {
        $reply   = $this->answerer->ask(WikiUserRefs::fromUser($user), $question, WikiSnapshots::spaceOrNull($space));
        $sources = [];
        foreach ($reply->sources as $source) {
            $page = $this->pages->findById($source['pageId']);
            if (!$page instanceof WikiPage || $page->getId() !== $source['pageId']) {
                continue;
            }
            $pageSpace = $page->getSpace();
            if ($pageSpace->getId() !== $source['spaceId'] || ($space instanceof WikiSpace && $pageSpace->getId() !== $space->getId()) || !$this->access->canAccessSpace($user, $pageSpace)) {
                continue;
            }
            $sources[] = ['page' => $page, 'space' => $pageSpace, 'excerpt' => $source['excerpt']];
        }

        return new WikiAiAnswer($reply->answer, $sources);
    }
}
