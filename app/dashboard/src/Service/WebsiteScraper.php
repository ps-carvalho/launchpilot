<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

class WebsiteScraper
{
    /**
     * Scrape a website and extract title, meta description, and body text.
     *
     * @return array{title: string, description: string, body: string}|null
     */
    public function scrape(string $url): ?array
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $html = $this->fetch($url);

        if ($html === null) {
            return null;
        }

        $dom = new \DOMDocument();
        \libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        \libxml_clear_errors();

        $title = $this->extractTitle($dom);
        $description = $this->extractMetaDescription($dom);
        $body = $this->extractBodyText($dom);

        return [
            'title' => $title,
            'description' => $description,
            'body' => $body,
        ];
    }

    private function fetch(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'LaunchPilotBot/1.0 (https://launchpilot.ai)',
                'follow_location' => true,
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        return $html !== false ? $html : null;
    }

    private function extractTitle(\DOMDocument $dom): string
    {
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            return trim($titles->item(0)->textContent);
        }

        $h1s = $dom->getElementsByTagName('h1');
        if ($h1s->length > 0) {
            return trim($h1s->item(0)->textContent);
        }

        return '';
    }

    private function extractMetaDescription(\DOMDocument $dom): string
    {
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            $property = strtolower($meta->getAttribute('property'));

            if ($name === 'description' || $property === 'og:description') {
                return trim($meta->getAttribute('content'));
            }
        }

        return '';
    }

    private function extractBodyText(\DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }

        // Remove script and style tags
        $scripts = $body->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode?->removeChild($scripts->item(0));
        }

        $styles = $body->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode?->removeChild($styles->item(0));
        }

        $navs = $body->getElementsByTagName('nav');
        while ($navs->length > 0) {
            $navs->item(0)->parentNode?->removeChild($navs->item(0));
        }

        $footers = $body->getElementsByTagName('footer');
        while ($footers->length > 0) {
            $footers->item(0)->parentNode?->removeChild($footers->item(0));
        }

        $text = $this->nodeTextContent($body);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Limit to ~10,000 chars to avoid storing massive pages
        if (strlen($text) > 10000) {
            $text = substr($text, 0, 10000) . '...';
        }

        return $text;
    }

    private function nodeTextContent(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->nodeTextContent($child) . ' ';
        }

        return $text;
    }
}
