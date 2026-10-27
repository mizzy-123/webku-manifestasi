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
        ->and($names)->toContain('toko-maju/style.css', 'toko-maju/functions.php', 'toko-maju/index.php', 'toko-maju/front-page.php', 'toko-maju/page-about.php', 'toko-maju/screenshot.png', 'toko-maju/assets/custom-uploads/logo.webp', 'toko-maju/assets/css/style.css', 'toko-maju/assets/js/webku-customizer-preview.js', 'toko-maju/assets/js/webku-customizer-controls.js');

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

test('content can be edited from wordpress by clicking elements in the customizer preview', function () {
    $zipPath = app(WordPressThemeExporterService::class)->export($this->template);
    $zip = ($this->open)($zipPath);
    $front = $zip->getFromName('demo-template/front-page.php');
    $functions = $zip->getFromName('demo-template/functions.php');

    expect($front)
        ->toContain('data-webku-setting="webku_home_hero"')
        ->toContain('data-webku-setting="webku_global_logo"')
        ->toContain('data-webku-setting="webku_home_cta_text webku_home_cta_url"');

    expect($functions)
        ->toContain("'transport'         => 'postMessage'")
        ->not->toContain("'transport'         => 'refresh'")
        ->toContain("'variable' => '--color-primary'")
        ->toContain("'webku_page_home' => ''")
        ->toContain("'webku_page_about' => 'about'")
        ->toContain('webku-customizer-preview.js')
        ->toContain('webku-customizer-controls.js')
        ->toContain('admin_notices')
        ->toContain('page_row_actions')
        ->toContain('enqueue_block_editor_assets');

    expect($zip->getFromName('demo-template/assets/js/webku-customizer-preview.js'))->toContain('focus-control-for-setting')
        ->and($zip->getFromName('demo-template/assets/js/webku-customizer-controls.js'))->toContain('previewUrl.set');

    $zip->close();
    unlink($zipPath);
});

test('text on a circular svg badge stays editable', function () {
    $template = app(TemplateArchiveService::class)->storeFromPath(makeTemplateZip(validTemplateFiles([
        'manifest.json' => json_encode([
            'name' => 'Badge Template',
            'type' => 'single-page',
            'pages' => [['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html']],
            'colors' => [['variable' => '--color-primary', 'label' => 'Warna Utama', 'default' => '#9275f7']],
        ]),
        'index.html' => <<<'HTML'
<!DOCTYPE html>
<html><head></head><body>
<a class="spin-badge" href="#kontak">
<svg class="spin-ring" viewBox="0 0 120 120"><text><textPath href="#badge-ring" textLength="286" data-edit-id="spin-badge-text">Reservasi Meja • Selera •</textPath></text></svg>
<span class="spin-label" data-edit-id="spin-badge-text" data-edit-type="text">Reservasi Meja • Selera •</span>
</a>
<svg viewBox="0 0 10 10"><path d="M0 0"/></svg>
</body></html>
HTML,
    ])), Category::factory()->create());

    $preview = CustomizedPreview::factory()->for($template)->create([
        'custom_data' => ['pages' => ['home' => ['texts' => ['spin-badge-text' => 'Meja Baru • Toko •'], 'images' => [], 'links' => []]]],
    ]);

    $zipPath = app(WordPressThemeExporterService::class)->export($template, $preview);
    $zip = ($this->open)($zipPath);
    $front = $zip->getFromName('badge-template/front-page.php');

    expect(substr_count($front, "get_theme_mod( 'webku_home_spin_badge_text', 'Meja Baru • Toko •' )"))->toBe(2)
        ->and(substr_count($front, 'data-webku-setting="webku_home_spin_badge_text"'))->toBe(2)
        ->and($front)->toContain('viewBox="0 0 120 120"')
        ->toContain('viewBox="0 0 10 10"')
        ->not->toContain('data-edit-id')
        ->not->toContain('__WPMOD_')
        ->not->toContain('Reservasi Meja');

    $zip->close();
    unlink($zipPath);
});

test('unmarked footer copies of the logo and site name follow the header settings', function () {
    $template = app(TemplateArchiveService::class)->storeFromPath(makeTemplateZip(validTemplateFiles([
        'manifest.json' => json_encode([
            'name' => 'Brand Template',
            'type' => 'single-page',
            'pages' => [['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html']],
            'colors' => [['variable' => '--color-primary', 'label' => 'Warna Utama', 'default' => '#9275f7']],
        ]),
        'index.html' => <<<'HTML'
<!DOCTYPE html>
<html><head></head><body>
<header><img data-edit-id="site-logo" data-edit-type="image" data-edit-scope="global" src="assets/img/logo.svg"><span data-edit-id="brand-name" data-edit-type="text" data-edit-scope="global">Arunika</span></header>
<footer><img src="assets/img/logo.svg" alt=""><span>Arunika</span><h3>Menu</h3></footer>
</body></html>
HTML,
        'assets/img/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
    ])), Category::factory()->create());

    $preview = CustomizedPreview::factory()->for($template)->create([
        'custom_data' => ['global' => ['texts' => ['brand-name' => 'Toko Baru'], 'images' => [], 'links' => []]],
    ]);

    $zipPath = app(WordPressThemeExporterService::class)->export($template, $preview);
    $zip = ($this->open)($zipPath);
    $front = $zip->getFromName('brand-template/front-page.php');

    expect(substr_count($front, "get_theme_mod( 'webku_global_site_logo'"))->toBe(2)
        ->and(substr_count($front, "get_theme_mod( 'webku_global_brand_name', 'Toko Baru' )"))->toBe(2)
        ->and($front)->toContain('<h3>Menu</h3>')
        ->not->toContain('>Arunika<');

    $zip->close();
    unlink($zipPath);
});

test('a marked news archive becomes wordpress posts instead of extra pages', function () {
    $template = app(TemplateArchiveService::class)->storeFromPath(makeTemplateZip(validTemplateFiles([
        'manifest.json' => json_encode([
            'name' => 'Blog Template',
            'type' => 'multi-page',
            'pages' => [
                ['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html'],
                ['slug' => 'kabar', 'title' => 'Kabar', 'file' => 'kabar.html'],
                ['slug' => 'resep-steak', 'title' => 'Judul Lama', 'file' => 'berita.html'],
            ],
            'colors' => [['variable' => '--color-primary', 'label' => 'Warna Utama', 'default' => '#9275f7']],
        ]),
        'index.html' => <<<'HTML'
<!DOCTYPE html>
<html><head><title>Beranda</title></head><body>
<div data-wp-loop="post" data-wp-count="2">
<article data-wp-post="resep-steak"><h3 data-wp-field="title">Rahasia Steak</h3><a data-wp-field="permalink" href="berita.html">Baca</a></article>
<article data-wp-post="lain"><h3 data-wp-field="title">Minuman yang Tepat</h3><a data-wp-field="permalink" href="berita.html">Baca</a></article>
</div>
</body></html>
HTML,
        'kabar.html' => <<<'HTML'
<!DOCTYPE html>
<html><head><title>Kabar</title></head><body>
<form data-wp-search="post" data-blog-filter="client" action="kabar.html" method="get"><input type="search" name="q" placeholder="Cari"></form>
<div data-wp-loop="post" data-wp-query="main">
<article data-wp-post="resep-steak"><h3 data-wp-field="title">Rahasia Steak</h3></article>
<article data-wp-post="lain"><h3 data-wp-field="title">Minuman yang Tepat</h3></article>
</div>
<div data-wp-pagination></div>
</body></html>
HTML,
        'berita.html' => <<<'HTML'
<!DOCTYPE html>
<html><head><title>Berita</title></head><body>
<article data-wp-single="post">
<h1 data-wp-field="title" data-edit-id="article-title">Judul Lama</h1>
<span data-wp-field="date" data-wp-date="2026-10-12">12 Okt 2026</span>
<span data-wp-field="category" data-edit-id="article-category">Resep</span>
<img data-wp-field="thumbnail" data-edit-id="article-image" src="assets/img/logo.svg" alt="">
<div data-wp-field="content" data-edit-id="article-body"><p>Isi asli</p></div>
</article>
</body></html>
HTML,
    ])), Category::factory()->create());

    $preview = CustomizedPreview::factory()->for($template)->create([
        'custom_data' => [
            'pages' => [
                'resep-steak' => [
                    'texts' => [
                        'article-title' => "Chef's cut",
                        'article-body' => 'Isi <b>baru</b> <?php echo "x"; ?>',
                    ],
                    'images' => ['article-image' => '/storage/custom-uploads/abcdefghij/logo.webp'],
                    'links' => [],
                ],
            ],
        ],
    ]);

    $zipPath = app(WordPressThemeExporterService::class)->export($template, $preview, 'Blog Tema');
    $zip = ($this->open)($zipPath);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
    $front = $zip->getFromName('blog-tema/front-page.php');
    $archive = $zip->getFromName('blog-tema/home.php');
    $single = $zip->getFromName('blog-tema/single.php');
    $functions = $zip->getFromName('blog-tema/functions.php');

    expect($names)->toContain('blog-tema/front-page.php', 'blog-tema/home.php', 'blog-tema/archive.php', 'blog-tema/single.php')
        ->and($names)->not->toContain('blog-tema/page-kabar.php', 'blog-tema/page-resep-steak.php');

    expect($front)
        ->toContain('new WP_Query')
        ->toContain("'posts_per_page' => 2")
        ->toContain('esc_html( get_the_title() )')
        ->toContain('esc_url( get_permalink() )')
        ->not->toContain('Minuman yang Tepat')
        ->not->toContain('data-wp-');

    expect($archive)
        ->toContain('have_posts()')
        ->toContain('the_posts_pagination')
        ->toContain('name="q"')
        ->toContain("get_option( 'page_for_posts' )")
        ->toContain('webku_news_query()')
        ->not->toContain('data-wp-')
        ->not->toContain('data-blog-filter')
        ->not->toContain('new WP_Query')
        ->not->toContain('Minuman yang Tepat');

    expect($zip->getFromName('blog-tema/index.php'))->toBe($archive)
        ->and($single)
        ->toContain('esc_html( get_the_title() )')
        ->toContain("apply_filters( 'the_content', get_the_content() )")
        ->not->toContain('Judul Lama')
        ->not->toContain('data-wp-')
        ->not->toContain('data-edit');

    expect($functions)
        ->toContain("update_option( 'page_for_posts'")
        ->toContain("'slug' => 'kabar'")
        ->toContain("'is_posts' => true")
        ->not->toContain("'title' => 'Judul Lama'")
        ->toContain("Chef\\'s cut")
        ->toContain('Isi <b>baru</b> <?php echo "x"; ?>')
        ->toContain('assets/custom-uploads/logo.webp')
        ->toContain('get_page_by_path')
        ->toContain('webku_news_search')
        ->toContain('posts_search')
        ->not->toContain('Isi asli');

    $directory = sys_get_temp_dir().'/wpexport-'.uniqid();
    $zip->extractTo($directory);

    foreach (['front-page.php', 'home.php', 'single.php', 'functions.php'] as $file) {
        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$directory}/blog-tema/{$file}").' 2>&1', $output, $code);

        expect($code)->toBe(0, implode("\n", $output));
    }

    $zip->close();
    unlink($zipPath);
});

test('the selera steakhouse news pages export as a wordpress loop', function () {
    $source = resource_path('upload-templates/selera-steakhouse');
    $zipPath = tempnam(sys_get_temp_dir(), 'selera-');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($source))), '/');
        $zip->addFile($file->getPathname(), $relative);
    }

    $zip->addFile($source.'/assets/img/hero-steak.jpg', 'thumbnail.jpg');
    $zip->close();

    $template = app(TemplateArchiveService::class)->storeFromPath($zipPath, Category::factory()->create());
    $export = app(WordPressThemeExporterService::class)->export($template, null, 'Selera Steak');
    $theme = ($this->open)($export);
    $single = $theme->getFromName('selera-steak/single.php');
    $archive = $theme->getFromName('selera-steak/home.php');
    $functions = $theme->getFromName('selera-steak/functions.php');

    expect($theme->getFromName('selera-steak/front-page.php'))
        ->toContain('new WP_Query')
        ->toContain('get_permalink()')
        ->not->toContain('Memadukan Steak dengan Minuman yang Tepat');

    expect($archive)->toContain('the_posts_pagination')
        ->toContain('name="q"')
        ->and($single)->toContain("apply_filters( 'the_content', get_the_content() )")
        ->and($functions)->toContain('rahasia-steak-juicy')
        ->toContain('Hidangan Penutup Baru Musim Ini')
        ->not->toContain('page-rahasia-steak-juicy');

    $directory = sys_get_temp_dir().'/selera-theme-'.uniqid();
    $theme->extractTo($directory);
    $theme->close();

    foreach (['front-page.php', 'home.php', 'single.php', 'functions.php'] as $file) {
        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg("{$directory}/selera-steak/{$file}").' 2>&1', $output, $code);

        expect($code)->toBe(0, implode("\n", $output));
    }

    unlink($zipPath);
    unlink($export);
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
