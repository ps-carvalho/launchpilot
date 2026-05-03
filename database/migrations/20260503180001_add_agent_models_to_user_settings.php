<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(ConnectionInterface $connection): void
    {
        $this->execute($connection, <<<'SQL'
            ALTER TABLE user_settings
            ADD COLUMN agent_models JSONB DEFAULT '{}'
            SQL);
    }

    public function down(ConnectionInterface $connection): void
    {
        $this->execute($connection, 'ALTER TABLE user_settings DROP COLUMN IF EXISTS agent_models;');
    }
};
