<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_spaces (id VARCHAR(36) NOT NULL, slug VARCHAR(120) NOT NULL, name VARCHAR(255) NOT NULL, owner_scope_type VARCHAR(16) NOT NULL, owner_scope_id VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX wiki_space_scope_slug (owner_scope_type, owner_scope_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_pages (id VARCHAR(36) NOT NULL, space_id VARCHAR(36) NOT NULL, slug VARCHAR(120) NOT NULL, title VARCHAR(255) NOT NULL, parent_id VARCHAR(36) DEFAULT NULL, position INT NOT NULL, current_revision_id VARCHAR(36) DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX wiki_page_space_slug (space_id, slug), INDEX IDX_WIKI_PAGE_SPACE (space_id), INDEX IDX_WIKI_PAGE_PARENT (parent_id), INDEX IDX_WIKI_PAGE_REVISION (current_revision_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page_revisions (id VARCHAR(36) NOT NULL, page_id VARCHAR(36) NOT NULL, revision_number INT NOT NULL, content_html LONGTEXT NOT NULL, author_id INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX wiki_revision_page_number (page_id, revision_number), INDEX IDX_WIKI_REVISION_PAGE (page_id), INDEX IDX_WIKI_REVISION_AUTHOR (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_pages ADD CONSTRAINT FK_WIKI_PAGE_SPACE FOREIGN KEY (space_id) REFERENCES wiki_spaces (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_pages ADD CONSTRAINT FK_WIKI_PAGE_PARENT FOREIGN KEY (parent_id) REFERENCES wiki_pages (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_pages ADD CONSTRAINT FK_WIKI_PAGE_REVISION FOREIGN KEY (current_revision_id) REFERENCES wiki_page_revisions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_WIKI_REVISION_PAGE FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_WIKI_REVISION_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_revisions DROP FOREIGN KEY FK_WIKI_REVISION_AUTHOR');
        $this->addSql('ALTER TABLE wiki_page_revisions DROP FOREIGN KEY FK_WIKI_REVISION_PAGE');
        $this->addSql('ALTER TABLE wiki_pages DROP FOREIGN KEY FK_WIKI_PAGE_REVISION');
        $this->addSql('ALTER TABLE wiki_pages DROP FOREIGN KEY FK_WIKI_PAGE_PARENT');
        $this->addSql('ALTER TABLE wiki_pages DROP FOREIGN KEY FK_WIKI_PAGE_SPACE');
        $this->addSql('DROP TABLE wiki_page_revisions');
        $this->addSql('DROP TABLE wiki_pages');
        $this->addSql('DROP TABLE wiki_spaces');
    }
}
