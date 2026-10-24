<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build a temporary template ZIP file from a map of path => contents and return its path.
 *
 * @param  array<string, string>  $files
 */
function makeTemplateZip(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'tpl-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

/**
 * The files of a minimal valid single-page template, optionally overridden.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function validTemplateFiles(array $overrides = []): array
{
    return array_merge([
        'manifest.json' => json_encode([
            'name' => 'Demo Template',
            'type' => 'single-page',
            'pages' => [['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html']],
            'colors' => [['variable' => '--color-primary', 'label' => 'Warna Utama', 'default' => '#9275f7']],
        ]),
        'index.html' => '<html><head></head><body>Halo</body></html>',
        'thumbnail.jpg' => 'jpeg-bytes',
        'assets/css/style.css' => ':root { --color-primary: #9275f7; }',
    ], $overrides);
}
