<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            ALTER TABLE content_items
            ADD COLUMN deleted_at TIMESTAMP DEFAULT NULL
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'ALTER TABLE content_items DROP COLUMN IF EXISTS deleted_at;');
    }
};
