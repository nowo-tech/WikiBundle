<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Member;

use InvalidArgumentException;
use Nowo\WikiBundle\Util\WikiPlainLabel;

use function implode;
use function in_array;
use function sprintf;

/**
 * Spec 234: a badge shown next to a member's name (history authors, comment authors), e.g. an "Agent" pill
 * for a service account. Plain text only: the templates print it escaped, never as HTML. The variant is a name from
 * {@see self::VARIANTS} (the bundle maps it to its own classes), never a CSS class from the host.
 *
 * The label is cut to {@see self::MAX_LABEL} characters and the title (the tooltip) to {@see self::MAX_TITLE};
 * control characters are removed. An empty label or an unknown variant is refused. Framework-free value object
 * (REQ-ARCH-001 rule 3).
 */
final readonly class WikiMemberBadge
{
    public const VARIANT_NEUTRAL = 'neutral';
    public const VARIANT_INFO    = 'info';
    public const VARIANT_AGENT   = 'agent';

    /** The allowed variants. */
    public const VARIANTS = [self::VARIANT_NEUTRAL, self::VARIANT_INFO, self::VARIANT_AGENT];

    public const MAX_LABEL = 40;

    public const MAX_TITLE = 120;

    public string $label;

    public string $variant;

    public ?string $title;

    /**
     * @throws InvalidArgumentException when the label is empty or the variant unknown
     */
    public function __construct(string $label, string $variant = self::VARIANT_NEUTRAL, ?string $title = null)
    {
        $plain = WikiPlainLabel::normalize($label, self::MAX_LABEL);
        if ($plain === null) {
            throw new InvalidArgumentException('A member badge needs a label.');
        }
        if (!in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown member badge variant "%s" (expected one of: %s).', $variant, implode(', ', self::VARIANTS)));
        }
        $this->label   = $plain;
        $this->variant = $variant;
        $this->title   = WikiPlainLabel::normalize($title, self::MAX_TITLE);
    }
}
