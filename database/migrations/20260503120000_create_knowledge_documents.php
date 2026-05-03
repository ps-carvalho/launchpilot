<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE knowledge_documents (
                id SERIAL PRIMARY KEY,
                workspace_id INT NOT NULL,
                filename VARCHAR(255) DEFAULT NULL,
                original_name VARCHAR(255) DEFAULT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                source_url VARCHAR(500) DEFAULT NULL,
                raw_text TEXT NOT NULL,
                metadata JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'DROP TABLE IF EXISTS knowledge_documents;');
    }
};
