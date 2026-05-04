<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentParser
{
    public function parse(string $filePath, string $mimeType): ?string
    {
        return match ($mimeType) {
            'text/plain', 'text/plain; charset=utf-8', 'text/markdown' => $this->parseTxt($filePath),
            'application/pdf' => $this->parsePdf($filePath),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword' => $this->parseDocx($filePath),
            default => null,
        };
    }

    private function parseTxt(string $filePath): string
    {
        $content = file_get_contents($filePath);
        return $content !== false ? $content : '';
    }

    private function parsePdf(string $filePath): ?string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDocx(string $filePath): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return null;
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xmlContent === false) {
            return null;
        }

        $xml = new \SimpleXMLElement($xmlContent);
        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $texts = [];
        foreach ($xml->xpath('//w:t') as $node) {
            $texts[] = (string) $node;
        }

        return implode(' ', $texts);
    }
}
