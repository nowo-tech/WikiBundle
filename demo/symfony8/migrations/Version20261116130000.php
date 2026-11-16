<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.24: the REST API idempotency table (mapped always; doctrine:fixtures:load purges it even with the API off)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_api_idempotency (scope VARCHAR(64) NOT NULL, key_hash VARCHAR(64) NOT NULL, fingerprint VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, status SMALLINT DEFAULT NULL, body LONGTEXT DEFAULT NULL, wiki_slug VARCHAR(120) DEFAULT NULL, page_id VARCHAR(36) DEFAULT NULL, INDEX wiki_api_idempotency_expires_idx (expires_at), PRIMARY KEY(scope, key_hash)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wiki_api_idempotency');
    }
}
