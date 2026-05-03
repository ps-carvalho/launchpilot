<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            ALTER TABLE campaigns
            ADD COLUMN IF NOT EXISTS type VARCHAR(50) NOT NULL DEFAULT 'one_off',
            ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP DEFAULT NULL
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            ALTER TABLE campaigns
            DROP COLUMN IF EXISTS type,
            DROP COLUMN IF EXISTS archived_at
            SQL);
    }
};
