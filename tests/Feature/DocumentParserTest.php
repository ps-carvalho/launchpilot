<?php

declare(strict_types=1);

use App\Dashboard\Service\DocumentParser;

beforeEach(function () {
    $this->parser = null;
});

afterEach(function () {
    // Clean up temp files
    if (!empty($this->tempFiles)) {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
});

describe('parse', function () {
    it('parses plain text files', function () {
        $parser = new DocumentParser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'txt_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, "Hello World\nThis is a test.");

        $result = $parser->parse($tmpFile, 'text/plain');

        expect($result)->toBe("Hello World\nThis is a test.");
    });

    it('parses text/plain with charset', function () {
        $parser = new DocumentParser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'txt_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'UTF-8 content here');

        $result = $parser->parse($tmpFile, 'text/plain; charset=utf-8');

        expect($result)->toBe('UTF-8 content here');
    });

    it('parses markdown files', function () {
        $parser = new DocumentParser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'md_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, "# Hello\n\nThis is **bold** and _italic_.");

        $result = $parser->parse($tmpFile, 'text/markdown');

        expect($result)->toBe("# Hello\n\nThis is **bold** and _italic_.");
    });

    it('returns null for unsupported mime type', function () {
        $parser = new DocumentParser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'bin_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'binary data');

        $result = $parser->parse($tmpFile, 'application/octet-stream');

        expect($result)->toBeNull();
    });

    it('parses DOCX files', function () {
        $parser = new DocumentParser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $this->tempFiles[] = $tmpFile;

        // Create a minimal DOCX file
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Hello from DOCX</w:t></w:r></w:p><w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $result = $parser->parse($tmpFile, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        expect($result)->toContain('Hello from DOCX')
            ->and($result)->toContain('Second paragraph');
    });

    it('returns null for invalid DOCX', function () {
        $parser = new DocumentParser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'bad_');
        $this->tempFiles[] = $tmpFile;
        file_put_contents($tmpFile, 'not a zip file');

        $result = $parser->parse($tmpFile, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        expect($result)->toBeNull();
    });
});
