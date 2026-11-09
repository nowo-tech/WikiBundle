<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.26: revisions outlive their author (author_id nullable, ON DELETE SET NULL instead of CASCADE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_revisions DROP FOREIGN KEY FK_WIKI_REVISION_AUTHOR');
        $this->addSql('ALTER TABLE wiki_page_revisions CHANGE author_id author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_WIKI_REVISION_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Revisions of deleted authors (author_id NULL) block the NOT NULL column: they stay, so down() refuses.
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM wiki_page_revisions WHERE author_id IS NULL') > 0, 'Revisions of deleted authors exist; they cannot go back to a NOT NULL author.');
        $this->addSql('ALTER TABLE wiki_page_revisions DROP FOREIGN KEY FK_WIKI_REVISION_AUTHOR');
        $this->addSql('ALTER TABLE wiki_page_revisions CHANGE author_id author_id INT NOT NULL');
        $this->addSql('ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_WIKI_REVISION_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE');
    }
}
