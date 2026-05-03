<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE user_settings (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                tier VARCHAR(20) NOT NULL DEFAULT 'free',
                daily_runs_used INT NOT NULL DEFAULT 0,
                runs_reset_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                openrouter_api_key VARCHAR(255) DEFAULT NULL,
                gsc_refresh_token TEXT DEFAULT NULL,
                gsc_connected_at TIMESTAMP DEFAULT NULL,
                custom_prompts JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'DROP TABLE IF EXISTS user_settings;');
    }
};
