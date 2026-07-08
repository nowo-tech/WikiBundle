<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;

interface WikiPageRevisionRepositoryInterface
{
    public function save(WikiPageRevision $revision): void;

    public function findById(string $id): ?WikiPageRevision;

    /**
     * @return list<WikiPageRevision>
     */
    public function findByPage(WikiPage $page): array;

    public function findByPageAndNumber(WikiPage $page, int $number): ?WikiPageRevision;

    public function getNextRevisionNumber(WikiPage $page): int;
}
