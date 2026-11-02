<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.13: co-editing Yjs log (page_collab_documents, page_collab_updates)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_page_collab_documents (page_id VARCHAR(36) NOT NULL, base_revision_id VARCHAR(36) DEFAULT NULL, seeded_at DATETIME DEFAULT NULL, seeded_by VARCHAR(180) DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(page_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page_collab_updates (id INT AUTO_INCREMENT NOT NULL, page_id VARCHAR(36) NOT NULL, kind VARCHAR(16) NOT NULL, data LONGTEXT NOT NULL, covers_seq INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX wiki_collab_update_page (page_id, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wiki_page_collab_updates');
        $this->addSql('DROP TABLE wiki_page_collab_documents');
    }
}
