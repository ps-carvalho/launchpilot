<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            ALTER TABLE knowledge_chunks
            ADD CONSTRAINT fk_knowledge_chunks_document
            FOREIGN KEY (document_id) REFERENCES knowledge_documents(id)
            ON DELETE CASCADE
            SQL);

        $this->execute($connection, <<<'SQL'
            ALTER TABLE content_items
            ADD CONSTRAINT fk_content_items_campaign
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
            ON DELETE CASCADE
            SQL);

        $this->execute($connection, <<<'SQL'
            ALTER TABLE content_items
            ADD CONSTRAINT fk_content_items_session
            FOREIGN KEY (agent_session_id) REFERENCES agent_sessions(id)
            ON DELETE SET NULL
            SQL);

        $this->execute($connection, <<<'SQL'
            ALTER TABLE agent_sessions
            ADD CONSTRAINT fk_agent_sessions_campaign
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
            ON DELETE CASCADE
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'ALTER TABLE knowledge_chunks DROP CONSTRAINT IF EXISTS fk_knowledge_chunks_document;');
        $this->execute($connection, 'ALTER TABLE content_items DROP CONSTRAINT IF EXISTS fk_content_items_campaign;');
        $this->execute($connection, 'ALTER TABLE content_items DROP CONSTRAINT IF EXISTS fk_content_items_session;');
        $this->execute($connection, 'ALTER TABLE agent_sessions DROP CONSTRAINT IF EXISTS fk_agent_sessions_campaign;');
    }
};
