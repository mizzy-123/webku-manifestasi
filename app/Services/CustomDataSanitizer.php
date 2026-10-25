<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class CustomDataSanitizer
{
    private const ALLOWED_TAGS = ['br', 'b', 'strong', 'i', 'em', 'u', 'span', 'small', 'sup', 'sub'];

    private const DROPPED_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript'];

    private const KEY_PATTERN = '/^[A-Za-z0-9_.:-]{1,100}$/';

    /**
     * Clean a visitor-supplied custom_data payload so it is safe to store and replay in the iframe.
     *
     * @param  array<mixed>  $data
     * @return array{colors: array<string, string>, global: array{texts: array<string, string>, images: array<string, string>, links: array<string, array{text?: string, href?: string}>}, pages: array<string, array{texts: array<string, string>, images: array<string, string>, links: array<string, array{text?: string, href?: string}>}>}
     */
    public function sanitize(array $data): array
    {
        $colors = [];

        foreach ($this->arrayOf($data['colors'] ?? null) as $variable => $value) {
            if (preg_match('/^--[A-Za-z0-9-]{1,60}$/', (string) $variable) === 1
                && is_string($value)
                && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1) {
                $colors[(string) $variable] = $value;
            }
        }

        $pages = [];

        foreach ($this->arrayOf($data['pages'] ?? null) as $slug => $group) {
            if (preg_match(self::KEY_PATTERN, (string) $slug) === 1) {
                $pages[(string) $slug] = $this->sanitizeGroup($this->arrayOf($group));
            }
        }

        return [
            'colors' => $colors,
            'global' => $this->sanitizeGroup($this->arrayOf($data['global'] ?? null)),
            'pages' => $pages,
        ];
    }

    /**
     * Keep only a small set of formatting tags and drop every attribute.
     */
    public function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="sanitize-root">'.$html.'</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('sanitize-root');

        if (! $root instanceof DOMElement) {
            return e(strip_tags($html));
        }

        $this->cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    /**
     * Allow http(s), mailto, tel, in-page anchors and relative URLs only.
     */
    public function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        $compact = preg_replace('/[\x00-\x20]/', '', $url) ?? '';

        if ($compact === '' || strlen($url) > 2048) {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $compact) === 1) {
            return preg_match('/^(https?|mailto|tel):/i', $compact) === 1 ? $url : '';
        }

        return str_starts_with($compact, '//') ? '' : $url;
    }

    /**
     * Images may only point at http(s) or relative URLs (visitor uploads are stored on the server).
     */
    public function sanitizeImageUrl(string $url): string
    {
        $url = $this->sanitizeUrl($url);

        return preg_match('/^(mailto|tel):/i', $url) === 1 ? '' : $url;
    }

    /**
     * @param  array<mixed>  $group
     * @return array{texts: array<string, string>, images: array<string, string>, links: array<string, array{text?: string, href?: string}>}
     */
    private function sanitizeGroup(array $group): array
    {
        $texts = [];
        $images = [];
        $links = [];

        foreach ($this->arrayOf($group['texts'] ?? null) as $id => $value) {
            if (preg_match(self::KEY_PATTERN, (string) $id) === 1 && is_string($value) && strlen($value) <= 20000) {
                $texts[(string) $id] = $this->sanitizeHtml($value);
            }
        }

        foreach ($this->arrayOf($group['images'] ?? null) as $id => $value) {
            if (preg_match(self::KEY_PATTERN, (string) $id) === 1 && is_string($value)) {
                $clean = $this->sanitizeImageUrl($value);

                if ($clean !== '') {
                    $images[(string) $id] = $clean;
                }
            }
        }

        foreach ($this->arrayOf($group['links'] ?? null) as $id => $value) {
            if (preg_match(self::KEY_PATTERN, (string) $id) !== 1 || ! is_array($value)) {
                continue;
            }

            $link = [];

            if (isset($value['text']) && is_string($value['text']) && strlen($value['text']) <= 500) {
                $link['text'] = $this->sanitizeHtml($value['text']);
            }

            if (isset($value['href']) && is_string($value['href'])) {
                $link['href'] = $this->sanitizeUrl($value['href']);
            }

            $links[(string) $id] = $link;
        }

        return ['texts' => $texts, 'images' => $images, 'links' => $links];
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED_WITH_CONTENT, true)) {
                $parent->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function arrayOf(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
