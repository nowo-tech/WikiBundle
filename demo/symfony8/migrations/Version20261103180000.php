<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WikiBundle 1.14.1: co-editing log counters on the document row (update_count, log_bytes, last_seq, snapshot_seq) with a backfill';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_collab_documents ADD update_count INT DEFAULT 0 NOT NULL, ADD log_bytes INT DEFAULT 0 NOT NULL, ADD last_seq INT DEFAULT 0 NOT NULL, ADD snapshot_seq INT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE wiki_page_collab_documents d SET
            update_count = (SELECT COUNT(*) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id AND u.kind = 'update'),
            log_bytes = (SELECT COALESCE(SUM(LENGTH(u.data)), 0) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id),
            last_seq = (SELECT COALESCE(MAX(u.id), 0) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id),
            snapshot_seq = (SELECT COALESCE(MAX(u.covers_seq), 0) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id AND u.kind = 'snapshot')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_page_collab_documents DROP update_count, DROP log_bytes, DROP last_seq, DROP snapshot_seq');
    }
}
