<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.14: the trash (page_trash)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_page_trash (id VARCHAR(36) NOT NULL, page_id VARCHAR(36) NOT NULL, batch_id VARCHAR(36) NOT NULL, is_root TINYINT(1) NOT NULL, was_archived TINYINT(1) NOT NULL, deleted_at DATETIME NOT NULL, UNIQUE INDEX wiki_page_trash_page (page_id), INDEX wiki_page_trash_batch (batch_id), INDEX wiki_page_trash_deleted (deleted_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_page_trash ADD CONSTRAINT FK_wiki_page_trash_page FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wiki_page_trash');
    }
}
