<?php

declare(strict_types=1);

namespace App\TwoZero;

use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler\AllowAllWikiAccessCheckerShim;
use Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler\InternalLinks;

/*
 * 2.0: application classes (outside the bundle's namespace) for the ports tests, loaded with require_once (not
 * autoloaded): only the bundle's own classes may serve the bundle-side port interfaces.
 */

final class HostBoth extends AllowAllWikiAccessCheckerShim implements WikiAccessPolicyInterface
{
}

final class HostInternalOnly extends AllowAllWikiAccessCheckerShim
{
}

final class HostInternalLinks extends InternalLinks
{
}
