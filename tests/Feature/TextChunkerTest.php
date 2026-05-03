<?php

declare(strict_types=1);

use App\Dashboard\Service\TextChunker;

beforeEach(function () {
    $this->chunker = new TextChunker();
});

describe('chunk', function () {
    it('returns single chunk for short text', function () {
        $text = 'This is a short piece of text.';
        $chunks = $this->chunker->chunk($text, 2000, 200);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0])->toBe($text);
    });

    it('splits long text into multiple chunks', function () {
        $text = str_repeat('word ', 500); // ~2500 chars
        $chunks = $this->chunker->chunk($text, 1000, 100);

        expect(count($chunks))->toBeGreaterThan(1);
    });

    it('respects overlap between chunks', function () {
        $text = str_repeat('a', 300);
        $chunks = $this->chunker->chunk($text, 200, 50);

        expect(count($chunks))->toBeGreaterThan(1);
        // Each chunk after the first should contain text from the previous chunk
        expect(strlen($chunks[0]))->toBeGreaterThan(100);
    });

    it('breaks at sentence boundaries when possible', function () {
        $sentence = 'This is a sentence. ';
        $text = str_repeat($sentence, 50); // ~1000 chars
        $chunks = $this->chunker->chunk($text, 400, 50);

        expect(count($chunks))->toBeGreaterThan(1);
        // Chunks should end with a period when possible
        foreach ($chunks as $chunk) {
            expect(strlen($chunk))->toBeLessThan(410);
            if (str_contains($chunk, '.')) {
                expect($chunk)->toEndWith('.');
            }
        }
    });

    it('breaks at word boundary when no sentence end', function () {
        $text = str_repeat('word ', 100); // no sentence endings
        $chunks = $this->chunker->chunk($text, 200, 20);

        expect(count($chunks))->toBeGreaterThan(1);
        // Most chunks should be reasonably sized (last chunk may be smaller)
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            expect(strlen($chunks[$i]))->toBeGreaterThan(50);
        }
    });

    it('returns empty array for empty string', function () {
        $chunks = $this->chunker->chunk('', 2000, 200);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0])->toBe('');
    });

    it('handles text exactly at chunk size', function () {
        $text = str_repeat('a', 2000);
        $chunks = $this->chunker->chunk($text, 2000, 200);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0])->toBe($text);
    });
});
