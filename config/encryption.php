<?php

declare(strict_types=1);

return [
    'key' => getenv('ENCRYPTION_KEY') ?: '',
    'cipher' => 'aes-256-gcm',
];
