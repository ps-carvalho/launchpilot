<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE agent_sessions (
                id SERIAL PRIMARY KEY,
                campaign_id INT NOT NULL,
                agent_type VARCHAR(50) NOT NULL,
                user_id INT NOT NULL,
                messages JSONB NOT NULL DEFAULT '[]',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'DROP TABLE IF EXISTS agent_sessions;');
    }
};
