<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.50: space lifecycle columns (archived_at, purge_started_at, lock_version) on wiki_spaces (UPGRADING "To 1.50.0")';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_spaces ADD archived_at DATETIME DEFAULT NULL, ADD purge_started_at DATETIME DEFAULT NULL, ADD lock_version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_spaces DROP archived_at, DROP purge_started_at, DROP lock_version');
    }
}
