<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.9 tables: page links and attachments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_attachments (id VARCHAR(36) NOT NULL, uploaded_at DATETIME NOT NULL, original_name VARCHAR(255) NOT NULL, storage_key VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL, sha256 VARCHAR(64) NOT NULL, page_id VARCHAR(36) NOT NULL, space_id VARCHAR(36) NOT NULL, uploaded_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D247B880111795A5 (storage_key), INDEX wiki_attachment_space (space_id), INDEX IDX_D247B880C4663E4 (page_id), INDEX IDX_D247B880A2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page_links (id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, object_type VARCHAR(64) NOT NULL, object_id VARCHAR(64) NOT NULL, page_id VARCHAR(36) NOT NULL, created_by_id INT DEFAULT NULL, INDEX wiki_page_link_object (object_type, object_id), UNIQUE INDEX wiki_page_link_unique (page_id, object_type, object_id), INDEX IDX_8B0B8A06C4663E4 (page_id), INDEX IDX_8B0B8A06B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_attachments ADD CONSTRAINT FK_D247B880C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_attachments ADD CONSTRAINT FK_D247B88023575340 FOREIGN KEY (space_id) REFERENCES wiki_spaces (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_attachments ADD CONSTRAINT FK_D247B880A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_page_links ADD CONSTRAINT FK_8B0B8A06C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page_links ADD CONSTRAINT FK_8B0B8A06B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_attachments DROP FOREIGN KEY FK_D247B880C4663E4');
        $this->addSql('ALTER TABLE wiki_attachments DROP FOREIGN KEY FK_D247B88023575340');
        $this->addSql('ALTER TABLE wiki_attachments DROP FOREIGN KEY FK_D247B880A2B28FE8');
        $this->addSql('ALTER TABLE wiki_page_links DROP FOREIGN KEY FK_8B0B8A06C4663E4');
        $this->addSql('ALTER TABLE wiki_page_links DROP FOREIGN KEY FK_8B0B8A06B03A8386');
        $this->addSql('DROP TABLE wiki_attachments');
        $this->addSql('DROP TABLE wiki_page_links');
    }
}
