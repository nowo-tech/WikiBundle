<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218): how far a render may reach beyond the viewer's own access. The API passes the token's *where*: a
 * page link, file or block of a space outside it reads as *Content not visible* even when the person could open it.
 * The UI renders without one (the person's access only).
 *
 * @internal implemented by the bundle only (the REST API's reach); not a host port (1.32, REQ-ARCH-001)
 */
interface WikiRenderReach
{
    public function reachesSpace(WikiSpace $space): bool;
}
