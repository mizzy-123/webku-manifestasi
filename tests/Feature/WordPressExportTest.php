<?php

use App\Models\Category;
use App\Models\CustomizedPreview;
use App\Models\User;
use App\Services\TemplateArchiveService;
use App\Services\WordPressThemeExporterService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filesystems.disks.public.root', Storage::disk('public')->path(''));

    $this->template = app(TemplateArchiveService::class)->storeFromPath(makeTemplateZip(validTemplateFiles([
        'manifest.json' => json_encode([
            'name' => 'Demo Template',
            'type' => 'multi-page',
            'pages' => [
                ['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html'],
                ['slug' => 'about', 'title' => 'Tentang', 'file' => 'about.html'],
            ],
            'colors' => [['variable' => '--color-primary', 'label' => 'Warna Utama', 'default' => '#9275f7']],
        ]),
        'index.html' => <<<'HTML'
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Beranda</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="home"><a href="about.html#tim">Tentang</a> <a href="index.html">Home</a> <a href="https://wa.me/62">WA</a>
<img data-edit-id="logo" data-edit-type="image" data-edit-scope="global" src="assets/img/logo.svg">
<h1 data-edit-id="hero" data-edit-type="text" contenteditable="true">Halo Â© Rp 10.000 &amp; 'kutip'</h1>
<a data-edit-id="cta" data-edit-type="link" href="about.html">Pesan</a>
<svg viewBox="0 0 10 10"><path d="M0 0"/></svg></body></html>
HTML,
        'about.html' => '<html><head></head><body><p data-edit-id="logo-note" data-edit-type="text">Tentang kami</p><img data-edit-id="logo" data-edit-type="image" data-edit-scope="global" src="assets/img/logo.svg"></body></html>',
        'assets/img/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
    ])), Category::factory()->create());

    $this->open = function (string $zipPath): ZipArchive {
        $zip = new ZipArchive;
        $zip->open($zipPath);

        return $zip;
    };
});

test('the exported zip has one theme folder with all wordpress files', function () {
    $preview = CustomizedPreview::factory()->for($this->template)->create(['unique_code' => 'abcdefghij']);
    Storage::disk('public')->put('custom-uploads/abcdefghij/logo.webp', 'webp');

    $zipPath = app(WordPressThemeExporterService::class)->export($this->template, $preview, 'Toko Maju');
    $zip = ($this->open)($zipPath);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));

    expect($names->every(fn ($name) => str_starts_with($name, 'toko-maju/')))->toBeTrue()
        ->and($names)->toContain('toko-maju/style.css', 'toko-maju/functions.php', 'toko-maju/index.php', 'toko-maju/front-page.php', 'toko-maju/page-about.php', 'toko-maju/screenshot.png', 'toko-maju/assets/custom-uploads/logo.webp', 'toko-maju/assets/css/style.css');

    expect($zip->getFromName('toko-maju/style.css'))->toContain('Theme Name: Toko Maju')->toContain('Text Domain: toko-maju');

    $zip->close();
    unlink($zipPath);
});

test('pages are converted to clean php without leaking editor attributes', function () {
    $zipPath = app(WordPressThemeExporterService::class)->export($this->template);
    $zip = ($this->open)($zipPath);
    $front = $zip->getFromName('demo-template/front-page.php');

    expect($front)
        ->toContain('<html <?php language_attributes(); ?>>')
        ->toContain('<?php body_class( \'home\' ); ?>')
        ->toContain('<?php wp_body_open(); ?>')
        ->toContain('<?php wp_head(); ?>')
        ->toContain('<?php wp_footer(); ?>')
        ->toContain("esc_url( get_template_directory_uri() . '/assets/css/style.css' )")
        ->toContain("esc_url( home_url( '/about/' ) . '#tim' )")
        ->toContain("esc_url( home_url( '/' ) )")
        ->toContain('href="https://wa.me/62"')
        ->toContain("get_theme_mod( 'webku_global_logo'")
        ->toContain("get_theme_mod( 'webku_home_hero', 'Halo Â© Rp 10.000 &amp; \\'kutip\\'' )")
        ->toContain('viewBox="0 0 10 10"')
        ->not->toContain('&lt;?php')
        ->not->toContain('data-edit')
        ->not->toContain('contenteditable')
        ->not->toContain('<title>')
        ->not->toContain('__WPMOD_')
        ->not->toContain('Ãƒ');

    expect($zip->getFromName('demo-template/index.php'))->toBe($front);

    $zip->close();
    unlink($zipPath);
});

test('client edits and colors are baked into the defaults', function () {
    $preview = CustomizedPreview::factory()->for($this->template)->create([
        'custom_data' => [
            'colors' => ['--color-primary' => '#ff0000'],
            'global' => ['texts' => [], 'images' => ['logo' => '/storage/custom-uploads/abcdefghij/logo.webp'], 'links' => []],
            'pages' => ['home' => [
                'texts' => ['hero' => 'Judul <b>Baru</b>'],
                'images' => [],
                'links' => ['cta' => ['text' => 'Hubungi', 'href' => 'https://wa.me/6281']],
            ]],
        ],
    ]);

    $zipPath = app(WordPressThemeExporterService::class)->export($this->template, $preview);
    $zip = ($this->open)($zipPath);
    $front = $zip->getFromName('demo-template/front-page.php');
    $functions = $zip->getFromName('demo-template/functions.php');

    expect($front)
        ->toContain("get_theme_mod( 'webku_home_hero', 'Judul <b>Baru</b>' )")
        ->toContain("get_theme_mod( 'webku_home_cta_url', 'https://wa.me/6281' )")
        ->toContain("get_theme_mod( 'webku_home_cta_text', 'Hubungi' )")
        ->toContain("get_template_directory_uri() . '/assets/custom-uploads/logo.webp'")
        ->toContain("get_theme_mod( 'webku_color_color_primary', '#ff0000' )");

    expect($zip->getFromName('demo-template/style.css'))->toContain('--color-primary: #ff0000;')
        ->and($functions)->toContain("add_theme_support( 'title-tag' )")
        ->toContain("update_option( 'page_on_front'")
        ->toContain("'webku_global_logo'")
        ->toContain("'webku_about_logo_note'")
        ->toContain('WP_Customize_Color_Control');

    $zip->close();
    unlink($zipPath);
});

test('exported php files are syntactically valid', function () {
    $zipPath = app(WordPressThemeExporterService::class)->export($this->template);
    $zip = ($this->open)($zipPath);
    $directory = sys_get_temp_dir().'/wpexport-'.uniqid();
    $zip->extractTo($directory);

    foreach (['front-page.php', 'page-about.php', 'functions.php'] as $file) {
        $file = "{$directory}/demo-template/{$file}";
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $code);

        expect($code)->toBe(0, implode("\n", $output));
    }

    $zip->close();
    unlink($zipPath);
});

test('admins can paste a preview url to download the theme', function () {
    $preview = CustomizedPreview::factory()->for($this->template)->create(['unique_code' => 'k9Xm2pL8qR']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('admin.wordpress.quick', ['source' => 'https://domain.com/p/'.$preview->unique_code, 'theme_name' => 'Toko Maju']));

    $response->assertOk()->assertDownload('toko-maju.zip');
});

test('the quick export accepts a bare code and rejects unknown ones', function () {
    CustomizedPreview::factory()->for($this->template)->create(['unique_code' => 'k9Xm2pL8qR']);
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.wordpress.quick', ['source' => 'k9Xm2pL8qR']))->assertOk();
    $this->actingAs($admin)->get(route('admin.wordpress.quick', ['source' => 'zzzzzzzzzz']))->assertSessionHasErrors('source');
    $this->actingAs($admin)->get(route('admin.wordpress.quick', ['source' => 'bukan url']))->assertSessionHasErrors('source');
});

test('admins can export a preview or a base template directly', function () {
    $preview = CustomizedPreview::factory()->for($this->template)->create();
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.wordpress.preview', $preview))->assertOk()->assertDownload();
    $this->actingAs($admin)->get(route('admin.wordpress.template', $this->template))->assertOk()->assertDownload('demo-template.zip');
});

test('guests cannot export themes', function () {
    $preview = CustomizedPreview::factory()->for($this->template)->create();

    $this->get(route('admin.wordpress.preview', $preview))->assertRedirect(route('login'));
    $this->get(route('admin.wordpress.template', $this->template))->assertRedirect(route('login'));
    $this->get(route('admin.wordpress.quick', ['source' => $preview->unique_code]))->assertRedirect(route('login'));
});
