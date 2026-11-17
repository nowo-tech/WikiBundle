<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Page URLs `/wiki/<space>/<page>` of the link picker tests.
 */
final class LinkPickerTestUrls implements UrlGeneratorInterface
{
    private RequestContext $context;

    public function __construct()
    {
        $this->context = new RequestContext();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '/wiki/' . $parameters['spaceSlug'] . '/' . $parameters['pageSlug'];
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }
}
