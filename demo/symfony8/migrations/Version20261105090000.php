<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.16: comments (threads, comments, reactions) and the plain-text search column (content_text)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_revisions ADD content_text LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE TABLE wiki_comment_threads (id VARCHAR(36) NOT NULL, resolved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, anchor LONGTEXT DEFAULT NULL, quote LONGTEXT DEFAULT NULL, page_id VARCHAR(36) NOT NULL, created_by_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, INDEX wiki_comment_thread_page (page_id, resolved_at), INDEX IDX_914EFA5EC4663E4 (page_id), INDEX IDX_914EFA5EB03A8386 (created_by_id), INDEX IDX_914EFA5E6713A32B (resolved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_comments (id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, edited_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, body LONGTEXT NOT NULL, position INT NOT NULL, thread_id VARCHAR(36) NOT NULL, author_id INT DEFAULT NULL, INDEX wiki_comment_thread_position (thread_id, position), INDEX IDX_B45893E5E2904019 (thread_id), INDEX IDX_B45893E5F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_comment_reactions (id VARCHAR(36) NOT NULL, emoji VARCHAR(32) NOT NULL, comment_id VARCHAR(36) NOT NULL, user_id INT NOT NULL, UNIQUE INDEX wiki_comment_reaction_unique (comment_id, user_id, emoji), INDEX IDX_2DDD1DE8F8697D13 (comment_id), INDEX IDX_2DDD1DE8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_comment_threads ADD CONSTRAINT FK_914EFA5EC4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_comment_threads ADD CONSTRAINT FK_914EFA5EB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_comment_threads ADD CONSTRAINT FK_914EFA5E6713A32B FOREIGN KEY (resolved_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_comments ADD CONSTRAINT FK_B45893E5E2904019 FOREIGN KEY (thread_id) REFERENCES wiki_comment_threads (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_comments ADD CONSTRAINT FK_B45893E5F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_comment_reactions ADD CONSTRAINT FK_2DDD1DE8F8697D13 FOREIGN KEY (comment_id) REFERENCES wiki_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_comment_reactions ADD CONSTRAINT FK_2DDD1DE8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_comment_reactions DROP FOREIGN KEY FK_2DDD1DE8F8697D13');
        $this->addSql('ALTER TABLE wiki_comment_reactions DROP FOREIGN KEY FK_2DDD1DE8A76ED395');
        $this->addSql('ALTER TABLE wiki_comments DROP FOREIGN KEY FK_B45893E5E2904019');
        $this->addSql('ALTER TABLE wiki_comments DROP FOREIGN KEY FK_B45893E5F675F31B');
        $this->addSql('ALTER TABLE wiki_comment_threads DROP FOREIGN KEY FK_914EFA5EC4663E4');
        $this->addSql('ALTER TABLE wiki_comment_threads DROP FOREIGN KEY FK_914EFA5EB03A8386');
        $this->addSql('ALTER TABLE wiki_comment_threads DROP FOREIGN KEY FK_914EFA5E6713A32B');
        $this->addSql('DROP TABLE wiki_comment_reactions');
        $this->addSql('DROP TABLE wiki_comments');
        $this->addSql('DROP TABLE wiki_comment_threads');
        $this->addSql('ALTER TABLE wiki_page_revisions DROP content_text');
    }
}
