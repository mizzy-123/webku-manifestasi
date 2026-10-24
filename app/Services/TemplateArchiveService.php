<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

class TemplateArchiveService
{
    /** @var list<string> */
    private const FORBIDDEN_EXTENSIONS = ['php', 'phtml', 'phar', 'sh', 'exe', 'bat', 'env', 'htaccess'];

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'css', 'js', 'json', 'txt', 'md',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot',
    ];

    /** @var list<string> */
    private const IGNORED_BASENAMES = ['.ds_store', 'thumbs.db'];

    /** @var list<string> */
    private const THUMBNAIL_NAMES = ['thumbnail.jpg', 'thumbnail.jpeg', 'thumbnail.png', 'thumbnail.webp'];

    private const MAX_ENTRIES = 2000;

    private const MAX_UNCOMPRESSED_BYTES = 104_857_600;

    /**
     * Validate and extract an uploaded template archive.
     *
     * @param  array{description?: string|null, price?: int|float|string|null}  $attributes
     */
    public function storeFromUpload(UploadedFile $file, Category $category, array $attributes = []): Template
    {
        return $this->storeFromPath($file->getRealPath(), $category, $attributes);
    }

    /**
     * Validate and extract a template archive located on disk.
     *
     * @param  array{description?: string|null, price?: int|float|string|null}  $attributes
     *
     * @throws ValidationException
     */
    public function storeFromPath(string $zipPath, Category $category, array $attributes = []): Template
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->fail('File ZIP tidak dapat dibuka atau rusak.');
        }

        try {
            $entries = $this->collectEntries($zip);
            $files = $this->resolveFiles($entries);

            $manifest = $this->readManifest($zip, $files);
            $this->validateManifest($manifest, $files);

            $thumbnail = $this->findThumbnail($files);

            $slug = $this->uniqueSlug($manifest['name']);
            $storagePath = "templates/{$slug}";

            try {
                $this->extract($zip, $files, $storagePath);

                return Template::query()->create([
                    'category_id' => $category->id,
                    'name' => $manifest['name'],
                    'slug' => $slug,
                    'type' => $manifest['type'],
                    'description' => $attributes['description']
                        ?? (is_string($manifest['description'] ?? null) ? $manifest['description'] : null),
                    'thumbnail_path' => "{$storagePath}/{$thumbnail}",
                    'storage_path' => $storagePath,
                    'manifest_data' => $manifest,
                    'price' => $attributes['price'] ?? null,
                    'is_active' => true,
                ]);
            } catch (Throwable $exception) {
                Storage::disk('public')->deleteDirectory($storagePath);

                throw $exception;
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Delete a template together with its extracted files.
     */
    public function delete(Template $template): void
    {
        if (Str::startsWith($template->storage_path, 'templates/') && $template->storage_path !== 'templates/') {
            Storage::disk('public')->deleteDirectory($template->storage_path);
        }

        $template->delete();
    }

    /**
     * Security-check every archive entry and return the usable file entries.
     *
     * @return list<array{index: int, name: string}>
     */
    private function collectEntries(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $this->fail('Jumlah file di dalam ZIP terlalu banyak.');
        }

        $entries = [];
        $totalSize = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string) $stat['name']);

            $this->assertSafePath($name);

            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/')) {
                continue;
            }

            $basename = strtolower(basename($name));

            if (in_array($basename, self::IGNORED_BASENAMES, true)) {
                continue;
            }

            $this->assertAllowedExtension($name, $basename);

            $totalSize += (int) $stat['size'];

            if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                $this->fail('Ukuran total isi ZIP melebihi batas 100 MB.');
            }

            $entries[] = ['index' => $index, 'name' => $name];
        }

        return $entries;
    }

    private function assertSafePath(string $name): void
    {
        $isUnsafe = str_contains($name, "\0")
            || str_starts_with($name, '/')
            || preg_match('#^[A-Za-z]:#', $name) === 1
            || in_array('..', explode('/', $name), true);

        if ($isUnsafe) {
            $this->fail("ZIP ditolak: path berbahaya terdeteksi ({$name}).");
        }
    }

    private function assertAllowedExtension(string $name, string $basename): void
    {
        $segments = explode('.', $basename);

        if ($segments[0] === '') {
            $this->fail("ZIP ditolak: file tersembunyi/terlarang ditemukan ({$name}).");
        }

        foreach (array_slice($segments, 1) as $segment) {
            if (in_array($segment, self::FORBIDDEN_EXTENSIONS, true)) {
                $this->fail("ZIP ditolak: file dengan ekstensi terlarang ditemukan ({$name}).");
            }
        }

        $extension = count($segments) > 1 ? end($segments) : '';

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->fail("ZIP ditolak: tipe file tidak diizinkan ({$name}).");
        }
    }

    /**
     * Detect the template root (archive root or a single sub-folder) and map relative path => entry index.
     *
     * @param  list<array{index: int, name: string}>  $entries
     * @return array<string, int>
     */
    private function resolveFiles(array $entries): array
    {
        $names = array_column($entries, 'name');
        $prefix = '';

        if (! in_array('manifest.json', $names, true)) {
            $candidates = array_values(array_filter($names, fn (string $name): bool => preg_match('#^[^/]+/manifest\.json$#', $name) === 1));

            if (count($candidates) !== 1) {
                $this->fail('manifest.json tidak ditemukan di root ZIP (atau di 1 level sub-folder).');
            }

            $prefix = dirname($candidates[0]).'/';
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($prefix === '' || str_starts_with($entry['name'], $prefix)) {
                $files[substr($entry['name'], strlen($prefix))] = $entry['index'];
            }
        }

        foreach (['manifest.json', 'index.html'] as $required) {
            if (! isset($files[$required])) {
                $this->fail("File wajib {$required} tidak ditemukan di dalam ZIP.");
            }
        }

        return $files;
    }

    /**
     * @param  array<string, int>  $files
     * @return array<string, mixed>
     */
    private function readManifest(ZipArchive $zip, array $files): array
    {
        $manifest = json_decode((string) $zip->getFromIndex($files['manifest.json']), true);

        if (! is_array($manifest)) {
            $this->fail('manifest.json bukan JSON yang valid.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, int>  $files
     */
    private function validateManifest(array $manifest, array $files): void
    {
        if (! isset($manifest['name']) || ! is_string($manifest['name']) || trim($manifest['name']) === '') {
            $this->fail('manifest.json: field "name" wajib diisi.');
        }

        if (! in_array($manifest['type'] ?? null, [Template::TYPE_SINGLE_PAGE, Template::TYPE_MULTI_PAGE], true)) {
            $this->fail('manifest.json: field "type" harus "single-page" atau "multi-page".');
        }

        $this->validatePages($manifest, $files);
        $this->validateColors($manifest);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, int>  $files
     */
    private function validatePages(array $manifest, array $files): void
    {
        $pages = $manifest['pages'] ?? null;

        if (! is_array($pages) || $pages === []) {
            $this->fail('manifest.json: field "pages" wajib berisi minimal satu halaman.');
        }

        $slugs = [];
        $hasIndex = false;

        foreach ($pages as $page) {
            $isValid = is_array($page)
                && is_string($page['slug'] ?? null) && preg_match('/^[a-z0-9-]+$/', $page['slug']) === 1
                && is_string($page['title'] ?? null) && trim($page['title']) !== ''
                && is_string($page['file'] ?? null) && preg_match('/\.html?$/i', $page['file']) === 1;

            if (! $isValid) {
                $this->fail('manifest.json: setiap item "pages" wajib memiliki "slug" (a-z, 0-9, -), "title", dan "file" berekstensi .html.');
            }

            if (in_array($page['slug'], $slugs, true)) {
                $this->fail("manifest.json: slug halaman \"{$page['slug']}\" duplikat.");
            }

            if (! isset($files[$page['file']])) {
                $this->fail("manifest.json: file halaman \"{$page['file']}\" tidak ditemukan di dalam ZIP.");
            }

            $slugs[] = $page['slug'];
            $hasIndex = $hasIndex || $page['file'] === 'index.html';
        }

        if (! $hasIndex) {
            $this->fail('manifest.json: "pages" harus memuat halaman utama dengan file "index.html".');
        }

        if ($manifest['type'] === Template::TYPE_SINGLE_PAGE && count($pages) !== 1) {
            $this->fail('Template single-page harus memiliki tepat 1 halaman.');
        }

        if ($manifest['type'] === Template::TYPE_MULTI_PAGE && count($pages) < 2) {
            $this->fail('Template multi-page harus memiliki minimal 2 halaman.');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function validateColors(array $manifest): void
    {
        $colors = $manifest['colors'] ?? null;

        if (! is_array($colors) || $colors === []) {
            $this->fail('manifest.json: field "colors" wajib berisi minimal satu warna.');
        }

        foreach ($colors as $color) {
            $isValid = is_array($color)
                && is_string($color['variable'] ?? null) && preg_match('/^--[a-z0-9-]+$/i', $color['variable']) === 1
                && is_string($color['label'] ?? null) && trim($color['label']) !== ''
                && is_string($color['default'] ?? null) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color['default']) === 1;

            if (! $isValid) {
                $this->fail('manifest.json: setiap item "colors" wajib memiliki "variable" (--nama), "label", dan "default" (hex, misal #2563eb).');
            }
        }
    }

    /**
     * @param  array<string, int>  $files
     */
    private function findThumbnail(array $files): string
    {
        foreach (self::THUMBNAIL_NAMES as $name) {
            if (isset($files[$name])) {
                return $name;
            }
        }

        $this->fail('File thumbnail.jpg (atau .png/.webp) tidak ditemukan di dalam ZIP.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $counter = 2;

        while (Template::query()->where('slug', $slug)->exists() || Storage::disk('public')->exists("templates/{$slug}")) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, int>  $files
     */
    private function extract(ZipArchive $zip, array $files, string $storagePath): void
    {
        $disk = Storage::disk('public');

        foreach ($files as $relativePath => $index) {
            $stream = $zip->getStreamIndex($index);

            if ($stream === false) {
                $this->fail("Gagal mengekstrak file {$relativePath}.");
            }

            $disk->put("{$storagePath}/{$relativePath}", $stream);

            fclose($stream);
        }
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
