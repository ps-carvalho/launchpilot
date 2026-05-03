<?php

declare(strict_types=1);

use Marko\Database\Migration\Migration;
use Marko\Database\Connection\ConnectionInterface;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, '
            CREATE TABLE media_assets (
                id SERIAL PRIMARY KEY,
                campaign_id INTEGER NOT NULL,
                content_item_id INTEGER NULL,
                type VARCHAR(20) NOT NULL,
                source_url TEXT NULL,
                local_path VARCHAR(500) NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                metadata JSONB DEFAULT \'{}\',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'DROP TABLE IF EXISTS media_assets;');
    }
};
