<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Provenance;

use InvalidArgumentException;
use Nowo\WikiBundle\Util\WikiPlainLabel;

use function implode;
use function in_array;
use function sprintf;

/**
 * Spec 234: where a write came from, as the host's {@see WikiWriteProvenanceProviderInterface} says — the
 * channel (`web`, `app`, `api` or `mcp`, decided by the host from the request path, never from client input) and a
 * plain-text actor label (a token name or a host label; never a secret, a login or an e-mail address).
 *
 * The channel is refused when it is not in the allow-list. The actor label is personal data: it is cut to
 * {@see self::MAX_LABEL} characters (truncated, not refused), control characters are removed and whitespace collapsed;
 * an empty label is null. Framework-free value object (REQ-ARCH-001 rule 3).
 */
final readonly class WikiWriteProvenance
{
    public const CHANNEL_WEB = 'web';
    public const CHANNEL_APP = 'app';
    public const CHANNEL_API = 'api';
    public const CHANNEL_MCP = 'mcp';

    /** The allowed channels. */
    public const CHANNELS = [self::CHANNEL_WEB, self::CHANNEL_APP, self::CHANNEL_API, self::CHANNEL_MCP];

    /** Characters kept of the actor label (the column length). */
    public const MAX_LABEL = 120;

    /** One of {@see self::CHANNELS}. */
    public string $channel;

    /** The normalised actor label, or null. */
    public ?string $actorLabel;

    /**
     * A stored channel read back (audit PQ-I2): itself when it is in the allow-list, else null (a row written outside
     * the bundle never reaches an answer or a translation key).
     */
    public static function channelOrNull(?string $channel): ?string
    {
        return in_array($channel, self::CHANNELS, true) ? $channel : null;
    }

    /**
     * @throws InvalidArgumentException when the channel is not one of {@see self::CHANNELS}
     */
    public function __construct(string $channel, ?string $actorLabel = null)
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown write channel "%s" (expected one of: %s).', $channel, implode(', ', self::CHANNELS)));
        }
        $this->channel    = $channel;
        $this->actorLabel = WikiPlainLabel::normalize($actorLabel, self::MAX_LABEL);
    }
}
