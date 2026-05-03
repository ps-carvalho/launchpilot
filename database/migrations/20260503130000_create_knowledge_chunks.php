<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE knowledge_chunks (
                id SERIAL PRIMARY KEY,
                document_id INT NOT NULL,
                chunk_text TEXT NOT NULL,
                embedding vector(1536),
                chunk_index INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL);

        $this->execute($connection, <<<'SQL'
            CREATE INDEX idx_knowledge_chunks_document ON knowledge_chunks(document_id)
            SQL);

        $this->execute($connection, <<<'SQL'
            CREATE INDEX idx_knowledge_chunks_embedding ON knowledge_chunks USING hnsw (embedding vector_cosine_ops)
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'DROP TABLE IF EXISTS knowledge_chunks;');
    }
};
