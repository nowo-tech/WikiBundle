<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

/**
 * 1.24 (spec 218): why the host's authenticator gave no principal. One answer per case, never a reason in the body.
 */
enum WikiApiAuthFailure
{
    /** Unknown, revoked, expired, disabled, erased or removed: 401 `OAUTH_019`. */
    case Invalid;
    /** The API is switched off on this instance: 404 `API_001`. */
    case ApiDisabled;
    /** A valid token without a wiki level (FR-001): 403 `TOKEN_001`. */
    case NoWikiLevel;
}
