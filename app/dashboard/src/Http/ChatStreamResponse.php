<?php

declare(strict_types=1);

namespace App\Dashboard\Http;

use Generator;
use Marko\Routing\Http\Response;
use Override;

/**
 * Custom SSE response for streaming AI chat tokens.
 * Consumes a generator that yields tokens as they arrive from the upstream API.
 */
readonly class ChatStreamResponse extends Response
{
    /**
     * @param Generator<int, string> $tokenStream
     */
    public function __construct(
        private Generator $tokenStream,
        int $statusCode = 200,
    ) {
        parent::__construct(
            body: '',
            statusCode: $statusCode,
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    #[Override]
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode());

            foreach ($this->headers() as $name => $value) {
                header("$name: $value");
            }
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
        set_time_limit(0);

        foreach ($this->tokenStream as $token) {
            if (connection_aborted()) {
                break;
            }

            $data = json_encode(['token' => $token], JSON_THROW_ON_ERROR);
            echo "event: token\ndata: {$data}\n\n";
            flush();
        }

        echo "event: done\ndata: {}\n\n";
        flush();
    }
}
