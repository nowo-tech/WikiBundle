<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

/**
 * A host object a page is linked to, as the host describes it for a viewer allowed to open it (1.9).
 */
final readonly class WikiLinkedObject
{
    /**
     * @param string $type host object type (e.g. "issue")
     * @param string $id host object id
     * @param string $label text shown for the object
     * @param string $url where the object opens
     * @param string|null $badge short extra text (a key, a status), or null
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $label,
        public string $url,
        public ?string $badge = null,
    ) {
    }
}
