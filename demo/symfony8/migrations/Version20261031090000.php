<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.11: Markdown revisions (content_markdown, format)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE wiki_page_revisions ADD content_markdown LONGTEXT DEFAULT NULL, ADD format VARCHAR(16) DEFAULT 'html' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_revisions DROP content_markdown, DROP format');
    }
}
