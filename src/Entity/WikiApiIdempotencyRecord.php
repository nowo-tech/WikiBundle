<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Api\Idempotency\DoctrineWikiApiIdempotencyStore;

/**
 * 1.24 (spec 218): the first answer of an API write sent with an `Idempotency-Key`, kept 24 hours. In the schema only
 * with `api.enabled` and the bundle's own store (`<table_prefix>_api_idempotency`). It holds hashes, never the token or
 * the key; the answer may hold page text, so a host forgets a token's rows when it revokes the token or erases its person.
 *
 * @internal the table of {@see DoctrineWikiApiIdempotencyStore}, which writes it with DBAL
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_api_idempotency')]
#[ORM\Index(name: 'wiki_api_idempotency_expires_idx', columns: ['expires_at'])]
class WikiApiIdempotencyRecord
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        public string $scope,
        #[ORM\Id]
        #[ORM\Column(name: 'key_hash', type: 'string', length: 64)]
        public string $keyHash,
        #[ORM\Column(type: 'string', length: 64)]
        public string $fingerprint,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public DateTimeImmutable $expiresAt,
        #[ORM\Column(type: 'smallint', nullable: true)]
        public ?int $status = null,
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $body = null,
        #[ORM\Column(name: 'wiki_slug', type: 'string', length: 120, nullable: true)]
        public ?string $wikiSlug = null,
        #[ORM\Column(name: 'page_id', type: 'string', length: 36, nullable: true)]
        public ?string $pageId = null,
    ) {
    }
}
