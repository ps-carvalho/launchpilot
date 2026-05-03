<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE content_items (
                id SERIAL PRIMARY KEY,
                campaign_id INT NOT NULL,
                agent_session_id INT DEFAULT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'social_post',
                platform VARCHAR(50) DEFAULT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                content TEXT NOT NULL,
                metadata JSONB DEFAULT '{}',
                published_at TIMESTAMP DEFAULT NULL,
                scheduled_at TIMESTAMP DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'DROP TABLE IF EXISTS content_items;');
    }
};
