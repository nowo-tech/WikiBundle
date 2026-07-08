<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Enum;

/**
 * Supported wiki document interchange layouts (Outline, Notion, auto-detect).
 */
enum WikiInterchangeFormat: string
{
    case Auto    = 'auto';
    case Outline = 'outline';
    case Notion  = 'notion';
}
