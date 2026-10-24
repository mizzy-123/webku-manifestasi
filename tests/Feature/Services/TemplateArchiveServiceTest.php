<?php

use App\Models\Category;
use App\Models\Template;
use App\Services\TemplateArchiveService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::factory()->create();
    $this->service = app(TemplateArchiveService::class);
});

test('a valid zip is extracted and stored', function () {
    $template = $this->service->storeFromPath(makeTemplateZip(validTemplateFiles()), $this->category);

    expect($template->slug)->toBe('demo-template')
        ->and($template->type)->toBe('single-page')
        ->and($template->thumbnail_path)->toBe('templates/demo-template/thumbnail.jpg')
        ->and($template->manifest_data['pages'])->toHaveCount(1);

    Storage::disk('public')->assertExists('templates/demo-template/index.html');
    Storage::disk('public')->assertExists('templates/demo-template/assets/css/style.css');
});

test('files nested in a single sub-folder are extracted without the extra folder', function () {
    $files = [];
    foreach (validTemplateFiles() as $name => $contents) {
        $files["my-folder/{$name}"] = $contents;
    }
    $files['__MACOSX/my-folder/._index.html'] = 'junk';

    $template = $this->service->storeFromPath(makeTemplateZip($files), $this->category);

    Storage::disk('public')->assertExists("{$template->storage_path}/index.html");
    Storage::disk('public')->assertMissing("{$template->storage_path}/my-folder/index.html");
    Storage::disk('public')->assertMissing("{$template->storage_path}/__MACOSX");
});

test('a duplicate template name gets a unique slug', function () {
    $first = $this->service->storeFromPath(makeTemplateZip(validTemplateFiles()), $this->category);
    $second = $this->service->storeFromPath(makeTemplateZip(validTemplateFiles()), $this->category);

    expect($first->slug)->toBe('demo-template')
        ->and($second->slug)->toBe('demo-template-2');
});

test('invalid archives are rejected and leave nothing behind', function (Closure $filesFactory, string $messagePart) {
    $zipPath = makeTemplateZip($filesFactory());

    try {
        $this->service->storeFromPath($zipPath, $this->category);
        $this->fail('Expected the archive to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['file'][0])->toContain($messagePart);
    }

    expect(Template::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'php file' => [fn () => validTemplateFiles(['shell.php' => '<?php echo 1;']), 'terlarang'],
    'double extension' => [fn () => validTemplateFiles(['evil.php.jpg' => 'x']), 'terlarang'],
    'htaccess' => [fn () => validTemplateFiles(['.htaccess' => 'x']), 'tersembunyi'],
    'path traversal' => [fn () => validTemplateFiles(['../evil.html' => 'x']), 'berbahaya'],
    'unknown extension' => [fn () => validTemplateFiles(['data.xyz' => 'x']), 'tidak diizinkan'],
    'missing manifest' => [fn () => array_diff_key(validTemplateFiles(), ['manifest.json' => 1]), 'manifest.json'],
    'missing index' => [fn () => array_diff_key(validTemplateFiles(), ['index.html' => 1]), 'index.html'],
    'missing thumbnail' => [fn () => array_diff_key(validTemplateFiles(), ['thumbnail.jpg' => 1]), 'thumbnail'],
    'invalid json' => [fn () => validTemplateFiles(['manifest.json' => '{not json']), 'JSON'],
    'invalid type' => [fn () => validTemplateFiles(['manifest.json' => json_encode(['name' => 'X', 'type' => 'weird'])]), 'type'],
    'page file missing in zip' => [fn () => validTemplateFiles(['manifest.json' => json_encode([
        'name' => 'X',
        'type' => 'multi-page',
        'pages' => [
            ['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html'],
            ['slug' => 'about', 'title' => 'Tentang', 'file' => 'about.html'],
        ],
        'colors' => [['variable' => '--color-primary', 'label' => 'Utama', 'default' => '#fff']],
    ])]), 'about.html'],
    'invalid color' => [fn () => validTemplateFiles(['manifest.json' => json_encode([
        'name' => 'X',
        'type' => 'single-page',
        'pages' => [['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html']],
        'colors' => [['variable' => 'primary', 'label' => 'Utama', 'default' => 'blue']],
    ])]), 'colors'],
]);

test('deleting a template removes its files and record', function () {
    $template = $this->service->storeFromPath(makeTemplateZip(validTemplateFiles()), $this->category);

    $this->service->delete($template);

    Storage::disk('public')->assertMissing('templates/demo-template/index.html');
    expect(Template::query()->count())->toBe(0);
});
