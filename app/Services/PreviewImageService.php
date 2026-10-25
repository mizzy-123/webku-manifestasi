<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class PreviewImageService
{
    private const MAX_WIDTH = 1920;

    private const WEBP_QUALITY = 80;

    /**
     * Store a visitor upload under custom-uploads/{code} and return its public URL.
     * Raster images become compressed WebP; SVG files are sanitized and kept as SVG.
     */
    public function store(UploadedFile $file, string $uniqueCode): string
    {
        $directory = "custom-uploads/{$uniqueCode}";
        $name = Str::lower(Str::random(16));
        $isSvg = strtolower($file->getClientOriginalExtension()) === 'svg';

        if ($isSvg) {
            $contents = $this->sanitizeSvg((string) file_get_contents($file->getRealPath()));
            $path = "{$directory}/{$name}.svg";
        } else {
            $contents = (string) (new ImageManager(new Driver))
                ->decodePath($file->getRealPath())
                ->scaleDown(width: self::MAX_WIDTH)
                ->encodeUsingFormat(Format::WEBP, quality: self::WEBP_QUALITY);
            $path = "{$directory}/{$name}.webp";
        }

        Storage::disk('public')->put($path, $contents);

        return '/storage/'.$path;
    }

    /**
     * Remove everything executable from an SVG document.
     */
    public function sanitizeSvg(string $svg): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
            throw ValidationException::withMessages(['image' => 'File SVG tidak valid.']);
        }

        $this->cleanSvgNode($root);

        return (string) $document->saveXML($root);
    }

    private function cleanSvgNode(DOMElement $element): void
    {
        $forbiddenTags = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'animate', 'set', 'handler'];

        foreach (iterator_to_array($element->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
                    $element->removeChild($child);
                }

                continue;
            }

            $unsafeStyle = strtolower($child->localName) === 'style'
                && preg_match('/@import|url\s*\(|expression\s*\(|javascript:/i', $child->textContent) === 1;

            if (in_array(strtolower($child->localName), $forbiddenTags, true) || $unsafeStyle) {
                $element->removeChild($child);

                continue;
            }

            $this->cleanSvgNode($child);
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = preg_replace('/[\x00-\x20]/', '', $attribute->value) ?? '';
            $isLink = $name === 'href' || str_ends_with($name, ':href');

            if (str_starts_with($name, 'on')
                || ($isLink && ! str_starts_with($value, '#'))
                || ($name === 'style' && preg_match('/url\s*\(|expression\s*\(|javascript:/i', $value) === 1)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
