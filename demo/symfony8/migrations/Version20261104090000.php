<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.15: named versions (revision_labels)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_revision_labels (id VARCHAR(36) NOT NULL, revision_id VARCHAR(36) NOT NULL, label VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX wiki_revision_label_revision (revision_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_revision_labels ADD CONSTRAINT FK_wiki_revision_labels_revision FOREIGN KEY (revision_id) REFERENCES wiki_page_revisions (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wiki_revision_labels');
    }
}
