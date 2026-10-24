<?php

use App\Models\Category;
use App\Models\User;
use App\Services\TemplateArchiveService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::factory()->create();

    $this->template = app(TemplateArchiveService::class)->storeFromPath(makeTemplateZip(validTemplateFiles([
        'index.html' => "<!DOCTYPE html>\n<html><head><title>Hai</title></head><body><p>© Rp 10.000 😀</p><a href=\"#x\">x</a></body></html>",
    ])), $this->category);
});

test('index page gets the base tag after head and scripts before closing body', function () {
    $response = $this->get(route('templates.render', $this->template))->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('<head><base href="/storage/templates/demo-template/"><title>Hai</title>')
        ->and($html)->toContain('<script src="/js/editor-bridge.js"></script>')
        ->and(strpos($html, 'editor-bridge.js'))->toBeLessThan(strpos($html, '</body>'))
        ->and($html)->toContain('© Rp 10.000 😀')
        ->and($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8');
});

test('explicit page file is rendered for multi-page templates', function () {
    $template = app(TemplateArchiveService::class)->storeFromPath(makeTemplateZip(validTemplateFiles([
        'manifest.json' => json_encode([
            'name' => 'Multi',
            'type' => 'multi-page',
            'pages' => [
                ['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html'],
                ['slug' => 'about', 'title' => 'Tentang', 'file' => 'about.html'],
            ],
            'colors' => [['variable' => '--color-primary', 'label' => 'Utama', 'default' => '#fff']],
        ]),
        'about.html' => '<html><head></head><body>Tentang Kami</body></html>',
    ])), $this->category);

    $this->get(route('templates.render', [$template, 'about.html']))
        ->assertOk()
        ->assertSee('Tentang Kami', false)
        ->assertSee('<base href="/storage/templates/multi/">', false);
});

test('pages not registered in the manifest are not served', function (string $file) {
    $this->get(route('templates.render', [$this->template, $file]))->assertNotFound();
})->with(['manifest.json', 'style.css', 'missing.html', '..%2Fmanifest.json']);

test('a registered page missing from storage returns 404', function () {
    Storage::disk('public')->delete('templates/demo-template/index.html');

    $this->get(route('templates.render', $this->template))->assertNotFound();
});

test('inactive templates are hidden from guests but visible to admins', function () {
    $this->template->update(['is_active' => false]);

    $this->get(route('templates.render', $this->template))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('templates.render', $this->template))
        ->assertOk();
});

test('html without head or body tags still receives the injections', function () {
    Storage::disk('public')->put('templates/demo-template/index.html', '<p>fragment</p>');

    $html = $this->get(route('templates.render', $this->template))->assertOk()->getContent();

    expect($html)->toStartWith('<base href="/storage/templates/demo-template/">')
        ->and($html)->toContain('editor-bridge.js');
});
