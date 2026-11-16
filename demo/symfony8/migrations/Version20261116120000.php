<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.32 / 1.38: the optional icon of spaces and pages (also emoji:<grapheme> since 1.39)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_spaces ADD icon VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE wiki_pages ADD icon VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_pages DROP icon');
        $this->addSql('ALTER TABLE wiki_spaces DROP icon');
    }
}
