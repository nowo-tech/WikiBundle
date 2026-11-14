<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle spec 234: write provenance on revisions (channel, actor_label; nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_revisions ADD channel VARCHAR(8) DEFAULT NULL, ADD actor_label VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_revisions DROP channel, DROP actor_label');
    }
}
