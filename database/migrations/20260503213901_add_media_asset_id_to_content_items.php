<?php

declare(strict_types=1);

use Marko\Database\Migration\Migration;
use Marko\Database\Connection\ConnectionInterface;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'ALTER TABLE content_items ADD COLUMN media_asset_id INTEGER NULL;');
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'ALTER TABLE content_items DROP COLUMN IF EXISTS media_asset_id;');
    }
};
