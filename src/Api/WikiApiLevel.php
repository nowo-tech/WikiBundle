<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

/**
 * 1.24 (spec 218): how much a token may do on the wiki, always within what its person may do. What each level may ask is
 * {@see WikiApiCapability}; "none" is the host's: its authenticator answers {@see WikiApiAuthFailure::NoWikiLevel}.
 */
enum WikiApiLevel: string
{
    case Read = 'read';
    case Edit = 'edit';
    case Full = 'full';
}
