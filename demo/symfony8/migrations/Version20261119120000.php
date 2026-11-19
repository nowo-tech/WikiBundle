<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.45: favourites, recent visits and the backlink index (favourites / recents / backlinks.enabled)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki_page_favourites (id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, user_id VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_bin`, page_id VARCHAR(36) NOT NULL, INDEX wiki_page_favourite_user (user_id, created_at), UNIQUE INDEX wiki_page_favourite_unique (user_id, page_id), INDEX IDX_7954510CC4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page_visits (id VARCHAR(36) NOT NULL, user_id VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_bin`, visited_at DATETIME NOT NULL, page_id VARCHAR(36) NOT NULL, INDEX wiki_page_visit_user (user_id, visited_at), UNIQUE INDEX wiki_page_visit_unique (user_id, page_id), INDEX IDX_BE1D8DA2C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page_references (id VARCHAR(36) NOT NULL, target_page_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_bin`, source_page_id VARCHAR(36) NOT NULL, INDEX wiki_page_reference_target (target_page_id, source_page_id), UNIQUE INDEX wiki_page_reference_unique (source_page_id, target_page_id), INDEX IDX_994DB9914599DB8C (source_page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_page_favourites ADD CONSTRAINT FK_7954510CC4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page_visits ADD CONSTRAINT FK_BE1D8DA2C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page_references ADD CONSTRAINT FK_994DB9914599DB8C FOREIGN KEY (source_page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wiki_page_references');
        $this->addSql('DROP TABLE wiki_page_visits');
        $this->addSql('DROP TABLE wiki_page_favourites');
    }
}
