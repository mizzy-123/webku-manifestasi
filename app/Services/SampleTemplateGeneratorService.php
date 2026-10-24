<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Template;
use FilesystemIterator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class SampleTemplateGeneratorService
{
    /**
     * Sample template folders (inside resources/sample-templates) and the category each belongs to.
     *
     * @var array<string, string>
     */
    private const SAMPLES = [
        'saas-agency-landing' => 'Landing Page',
        'constructpro-company-profile' => 'Konstruksi & Properti',
    ];

    public function __construct(private TemplateArchiveService $archiveService) {}

    /**
     * Generate the sample templates that do not exist yet.
     *
     * @return Collection<int, Template>
     */
    public function generate(): Collection
    {
        $created = collect();

        foreach (self::SAMPLES as $directory => $categoryName) {
            $sourcePath = resource_path("sample-templates/{$directory}");
            $manifest = json_decode((string) file_get_contents("{$sourcePath}/manifest.json"), true);

            if (Template::query()->where('name', $manifest['name'])->exists()) {
                continue;
            }

            $category = Category::query()->firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName],
            );

            $zipPath = $this->buildZip($sourcePath, $manifest);

            try {
                $created->push($this->archiveService->storeFromPath($zipPath, $category));
            } finally {
                @unlink($zipPath);
            }
        }

        return $created;
    }

    /**
     * Pack a sample folder plus a generated thumbnail into a temporary ZIP file.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function buildZip(string $sourcePath, array $manifest): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'sample-template-');
        $thumbnailPath = $this->buildThumbnail($manifest);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat file ZIP sementara untuk sample template.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($sourcePath) + 1));
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        $zip->addFile($thumbnailPath, 'thumbnail.jpg');
        $zip->close();

        @unlink($thumbnailPath);

        return $zipPath;
    }

    /**
     * Draw a simple website mock-up thumbnail (16:10) using the template palette.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function buildThumbnail(array $manifest): string
    {
        $palette = collect($manifest['colors'])->pluck('default', 'variable');
        $image = imagecreatetruecolor(1280, 800);

        $color = fn (string $variable, string $fallback) => $this->allocate($image, $palette[$variable] ?? $fallback);

        $background = $color('--color-bg', '#ffffff');
        $primary = $color('--color-primary', '#9275f7');
        $secondary = $color('--color-secondary', '#1c1e1e');
        $accent = $color('--color-accent', '#f59e0b');
        $muted = $this->allocate($image, '#e7e5ef');
        $white = $this->allocate($image, '#ffffff');

        imagefilledrectangle($image, 0, 0, 1280, 800, $background);

        imagefilledrectangle($image, 0, 0, 1280, 88, $secondary);
        imagefilledrectangle($image, 64, 26, 100, 62, $accent);
        foreach ([760, 880, 1000, 1120] as $x) {
            imagefilledrectangle($image, $x, 40, $x + 80, 48, $white);
        }

        foreach ([[64, 600], [64, 520], [64, 440]] as $row => [$x, $width]) {
            imagefilledrectangle($image, $x, 170 + $row * 56, $x + $width, 202 + $row * 56, $secondary);
        }
        imagefilledrectangle($image, 64, 360, 520, 372, $muted);
        imagefilledrectangle($image, 64, 388, 420, 400, $muted);
        imagefilledrectangle($image, 64, 440, 280, 496, $primary);

        imagefilledrectangle($image, 700, 140, 1216, 500, $muted);
        imagefilledellipse($image, 958, 320, 220, 220, $primary);
        imagefilledellipse($image, 1090, 220, 90, 90, $accent);

        foreach ([64, 464, 864] as $x) {
            imagefilledrectangle($image, $x, 580, $x + 352, 740, $muted);
            imagefilledrectangle($image, $x + 2, 582, $x + 350, 738, $background);
            imagefilledrectangle($image, $x + 28, 612, $x + 148, 628, $primary);
            imagefilledrectangle($image, $x + 28, 654, $x + 300, 664, $muted);
            imagefilledrectangle($image, $x + 28, 680, $x + 240, 690, $muted);
        }

        $path = tempnam(sys_get_temp_dir(), 'sample-thumbnail-');
        imagejpeg($image, $path, 88);

        return $path;
    }

    private function allocate(\GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return (int) imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
}
