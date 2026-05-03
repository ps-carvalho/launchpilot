<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

class TextChunker
{
    /**
     * Split text into chunks of approximately $chunkSize characters,
     * with $overlap characters of overlap between chunks.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, int $chunkSize = 2000, int $overlap = 200): array
    {
        $text = trim($text);

        if (strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $position = 0;

        while ($position < strlen($text)) {
            $chunk = substr($text, $position, $chunkSize);

            // Try to break at a sentence or word boundary
            if (strlen($chunk) === $chunkSize) {
                // Look for sentence ending within last 20% of chunk
                $searchStart = (int) (strlen($chunk) * 0.8);
                $sentenceEnd = strrpos(substr($chunk, $searchStart), '. ');

                if ($sentenceEnd !== false) {
                    $chunk = substr($chunk, 0, $searchStart + $sentenceEnd + 1);
                } else {
                    // Fall back to word boundary
                    $lastSpace = strrpos($chunk, ' ');
                    if ($lastSpace !== false && $lastSpace > $chunkSize * 0.5) {
                        $chunk = substr($chunk, 0, $lastSpace);
                    }
                }
            }

            $chunks[] = trim($chunk);
            $advance = strlen($chunk) - $overlap;

            if ($advance <= 0) {
                $advance = (int) ($chunkSize * 0.5);
            }

            $position += $advance;
        }

        return $chunks;
    }
}
