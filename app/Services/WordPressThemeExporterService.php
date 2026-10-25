<?php

namespace App\Services;

use App\Models\CustomizedPreview;
use App\Models\Setting;
use App\Models\Template;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use RuntimeException;
use ZipArchive;

/**
 * Converts a template (optionally with a client's customizations) into an installable WordPress theme zip.
 *
 * HTML is manipulated with DOMDocument using plain-text placeholders (`__WPMOD_n__`); the placeholders are only
 * turned into `<?php ... ?>` after saveHTML(), because DOMDocument would otherwise escape the PHP tags.
 */
class WordPressThemeExporterService
{
    private const TOKEN = '__WPMOD_%d__';

    /** @var array<string, string> placeholder => replacement */
    private array $tokens = [];

    /** @var array<string, string> */
    private array $svgBlocks = [];

    /** @var array<string, array{id: string, label: string, type: string, section: string, default: string}> */
    private array $settings = [];

    /** @var array<string, string> section id => label */
    private array $sections = [];

    /** @var array<int, array{slug: string, title: string, file: string}> */
    private array $pages = [];

    /** @var array<string, mixed> */
    private array $customData = [];

    /** @var array<int, array{variable: string, label: string, default: string}> */
    private array $templateColors = [];

    /**
     * Build the theme and return the path of the generated zip (the caller must delete it after sending).
     */
    public function export(Template $template, ?CustomizedPreview $preview = null, ?string $themeName = null): string
    {
        $this->reset($template, $preview);

        $themeName = trim((string) $themeName) !== '' ? trim((string) $themeName) : $template->name;
        $themeSlug = Str::slug($themeName) ?: 'webku-theme';

        $exports = storage_path('app/temp-exports');
        $workRoot = $exports.'/'.$themeSlug.'-'.now()->timestamp.'-'.Str::lower(Str::random(6));
        $themeDir = $workRoot.'/'.$themeSlug;
        $zipPath = $exports.'/'.$themeSlug.'-'.Str::lower(Str::random(10)).'.zip';

        File::ensureDirectoryExists($themeDir);

        try {
            $this->copyAssets($template, $preview, $themeDir);
            $this->writeScreenshot($template, $themeDir);
            $this->writePages($template, $themeDir);
            File::put($themeDir.'/style.css', $this->styleCss($template, $themeName, $themeSlug));
            File::put($themeDir.'/functions.php', $this->functionsPhp($themeName));
            $this->zip($workRoot, $themeSlug, $zipPath);
        } finally {
            File::deleteDirectory($workRoot);
        }

        return $zipPath;
    }

    private function reset(Template $template, ?CustomizedPreview $preview): void
    {
        $this->tokens = [];
        $this->svgBlocks = [];
        $this->settings = [];
        $this->sections = [];
        $this->customData = $preview?->custom_data ?? [];
        $this->templateColors = array_values(array_filter(
            $template->manifest_data['colors'] ?? [],
            fn (mixed $color): bool => is_array($color) && isset($color['variable'], $color['label'], $color['default']),
        ));
        $this->pages = array_values(array_filter(
            $template->manifest_data['pages'] ?? [],
            fn (mixed $page): bool => is_array($page) && isset($page['slug'], $page['file']),
        ));

        if ($this->pages === []) {
            throw new RuntimeException('Template tidak memiliki halaman yang terdaftar di manifest.');
        }
    }

    private function copyAssets(Template $template, ?CustomizedPreview $preview, string $themeDir): void
    {
        $disk = Storage::disk('public');
        $assets = $disk->path($template->storage_path.'/assets');

        if (File::isDirectory($assets)) {
            File::copyDirectory($assets, $themeDir.'/assets');
        }

        if ($preview !== null) {
            $uploads = $disk->path("custom-uploads/{$preview->unique_code}");

            if (File::isDirectory($uploads)) {
                File::copyDirectory($uploads, $themeDir.'/assets/custom-uploads');
            }
        }
    }

    private function writeScreenshot(Template $template, string $themeDir): void
    {
        $thumbnail = Storage::disk('public')->path($template->thumbnail_path);

        if (! File::exists($thumbnail)) {
            return;
        }

        try {
            $png = (string) (new ImageManager(new Driver))
                ->decodePath($thumbnail)
                ->scaleDown(width: 1200)
                ->encodeUsingFormat(Format::PNG);

            File::put($themeDir.'/screenshot.png', $png);
        } catch (\Throwable) {
            File::copy($thumbnail, $themeDir.'/screenshot.png');
        }
    }

    private function writePages(Template $template, string $themeDir): void
    {
        $disk = Storage::disk('public');
        $homeSlug = $this->homePage()['slug'];

        foreach ($this->pages as $page) {
            $path = "{$template->storage_path}/{$page['file']}";

            if (! $disk->exists($path)) {
                continue;
            }

            $php = $this->convertPage($disk->get($path), $page);

            if ($page['slug'] === $homeSlug) {
                File::put($themeDir.'/front-page.php', $php);
                File::put($themeDir.'/index.php', $php);
            } else {
                File::put($themeDir."/page-{$page['slug']}.php", $php);
            }
        }
    }

    /**
     * @return array{slug: string, title: string, file: string}
     */
    private function homePage(): array
    {
        foreach ($this->pages as $page) {
            if ($page['slug'] === 'home' || $page['file'] === 'index.html') {
                return $page;
            }
        }

        return $this->pages[0];
    }

    /**
     * @param  array{slug: string, title: string, file: string}  $page
     */
    private function convertPage(string $html, array $page): string
    {
        $html = $this->protectSvg($html);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $document->removeChild($node);
            }
        }

        $document->encoding = 'UTF-8';
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//title') ?: [] as $title) {
            $title->parentNode?->removeChild($title);
        }

        foreach (iterator_to_array($xpath->query('//*[@data-edit-id]') ?: []) as $element) {
            if ($element instanceof DOMElement) {
                $this->convertEditable($element, $page);
            }
        }

        $this->convertUrls($xpath);
        $this->convertInlineStyles($xpath);

        foreach ($xpath->query('//*[@data-edit-id or @data-edit-type or @data-edit-scope or @contenteditable]') ?: [] as $element) {
            foreach (['data-edit-id', 'data-edit-type', 'data-edit-scope', 'contenteditable', 'spellcheck'] as $attribute) {
                $element->removeAttribute($attribute);
            }
        }

        return $this->injectWordPress($document->saveHTML() ?: '');
    }

    /**
     * @param  array{slug: string, title: string, file: string}  $page
     */
    private function convertEditable(DOMElement $element, array $page): void
    {
        $editId = $element->getAttribute('data-edit-id');
        $type = $element->getAttribute('data-edit-type');
        $isGlobal = $element->getAttribute('data-edit-scope') === 'global';
        $group = $isGlobal ? ($this->customData['global'] ?? []) : ($this->customData['pages'][$page['slug']] ?? []);
        $scope = $isGlobal ? 'global' : $page['slug'];
        $settingId = $this->settingId($scope, $editId);
        $section = $isGlobal ? 'webku_global' : 'webku_page_'.$this->slugKey($page['slug']);
        $this->sections[$section] = $isGlobal ? 'Elemen Global' : 'Halaman: '.$page['title'];
        $label = Str::headline($editId);

        if ($type === 'text') {
            $default = $group['texts'][$editId] ?? $this->innerHtml($element);
            $this->settings[$settingId] = ['id' => $settingId, 'label' => $label, 'type' => 'html', 'section' => $section, 'default' => $this->phpString(trim($default))];
            $this->replaceChildrenWithToken($element, "wp_kses_post( get_theme_mod( '{$settingId}', ".$this->phpString(trim($default)).' ) )');

            return;
        }

        if ($type === 'image') {
            $src = (string) ($group['images'][$editId] ?? $element->getAttribute('src'));
            $expression = $this->urlExpression($src);
            $this->settings[$settingId] = ['id' => $settingId, 'label' => $label.' (gambar)', 'type' => 'image', 'section' => $section, 'default' => $expression];
            $element->removeAttribute('srcset');
            $element->setAttribute('src', $this->token("esc_url( get_theme_mod( '{$settingId}', {$expression} ) )"));

            return;
        }

        if ($type === 'link') {
            $href = (string) ($group['links'][$editId]['href'] ?? $element->getAttribute('href'));
            $expression = $this->urlExpression($href);
            $urlId = $settingId.'_url';
            $this->settings[$urlId] = ['id' => $urlId, 'label' => $label.' (tujuan link)', 'type' => 'url', 'section' => $section, 'default' => $expression];
            $element->setAttribute('href', $this->token("esc_url( get_theme_mod( '{$urlId}', {$expression} ) )"));

            if (! $this->hasElementChild($element)) {
                $text = trim((string) ($group['links'][$editId]['text'] ?? $this->innerHtml($element)));
                $textId = $settingId.'_text';
                $this->settings[$textId] = ['id' => $textId, 'label' => $label.' (tulisan)', 'type' => 'text', 'section' => $section, 'default' => $this->phpString($text)];
                $this->replaceChildrenWithToken($element, "wp_kses_post( get_theme_mod( '{$textId}', ".$this->phpString($text).' ) )');
            }
        }
    }

    private function convertUrls(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//*[@src or @href or @poster or @data-src]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            foreach (['src', 'href', 'poster', 'data-src'] as $attribute) {
                $value = $element->getAttribute($attribute);

                if ($value === '' || str_starts_with($value, '__WPMOD_')) {
                    continue;
                }

                $expression = $this->convertUrl($value);

                if ($expression !== null) {
                    $element->setAttribute($attribute, $this->token("esc_url( {$expression} )"));
                }
            }
        }
    }

    private function convertInlineStyles(DOMXPath $xpath): void
    {
        $pattern = '/url\(\s*([\'"]?)(assets\/[^\'")]+)\1\s*\)/i';
        $replace = fn (array $match): string => 'url('.$this->token('esc_url( '.$this->convertUrl($match[2]).' )').')';

        foreach ($xpath->query('//*[@style]') ?: [] as $element) {
            if ($element instanceof DOMElement) {
                $element->setAttribute('style', (string) preg_replace_callback($pattern, $replace, $element->getAttribute('style')));
            }
        }

        foreach ($xpath->query('//style') ?: [] as $style) {
            $style->textContent = (string) preg_replace_callback($pattern, $replace, $style->textContent);
        }
    }

    /**
     * Return the PHP expression for a URL that must change inside WordPress, or null when it can stay as is.
     */
    private function convertUrl(string $url): ?string
    {
        $url = trim($url);
        $url = (string) preg_replace('#^\./#', '', $url);

        if (preg_match('#^(?:https?:)?(?://[^/]+)?/storage/custom-uploads/[A-Za-z0-9]{10}/([^?\#]+)#', $url, $match) === 1) {
            return "get_template_directory_uri() . '/assets/custom-uploads/".addcslashes($match[1], "'\\")."'";
        }

        if (str_starts_with($url, 'assets/')) {
            return "get_template_directory_uri() . '/".addcslashes($url, "'\\")."'";
        }

        if (preg_match('/^([A-Za-z0-9._-]+\.html?)(#.*)?$/', $url, $match) === 1) {
            $page = collect($this->pages)->firstWhere('file', $match[1]);

            if ($page === null && $match[1] !== 'index.html') {
                return null;
            }

            $isHome = $match[1] === 'index.html' || ($page['slug'] ?? '') === 'home';
            $path = $isHome ? '/' : '/'.$page['slug'].'/';
            $fragment = isset($match[2]) && $match[2] !== '' ? ' . '.$this->phpString($match[2]) : '';

            return "home_url( '{$path}' )".$fragment;
        }

        return null;
    }

    private function urlExpression(string $url): string
    {
        return $this->convertUrl($url) ?? $this->phpString($url);
    }

    /**
     * The libxml HTML parser lowercases SVG attributes such as viewBox, so inline SVG is kept out of the DOM.
     */
    private function protectSvg(string $html): string
    {
        return (string) preg_replace_callback('/<svg\b.*?<\/svg>/is', function (array $match): string {
            $token = '__WPSVG_'.count($this->svgBlocks).'__';
            $this->svgBlocks[$token] = $match[0];

            return $token;
        }, $html);
    }

    private function injectWordPress(string $html): string
    {
        $html = (string) preg_replace('/<html\b[^>]*>/i', '<html <?php language_attributes(); ?>>', $html, 1);

        $html = (string) preg_replace_callback('/<body\b([^>]*)>/i', function (array $match): string {
            $class = '';
            $attributes = preg_replace_callback('/\sclass="([^"]*)"/i', function (array $classMatch) use (&$class): string {
                $class = $classMatch[1];

                return '';
            }, $match[1]);

            $argument = $class === '' ? '' : ' '.$this->phpString($class).' ';

            return '<body <?php body_class('.$argument.'); ?>'.$attributes.'>'."\n".'<?php wp_body_open(); ?>';
        }, $html, 1);

        $html = (string) preg_replace('/<\/head>/i', $this->colorStyleBlock()."\n<?php wp_head(); ?>\n</head>", $html, 1);
        $html = (string) preg_replace('/<\/body>/i', "<?php wp_footer(); ?>\n</body>", $html, 1);

        return strtr(strtr($html, $this->tokens), $this->svgBlocks);
    }

    private function colorStyleBlock(): string
    {
        $lines = [];

        foreach ($this->colors() as $color) {
            $id = $this->colorSettingId($color['variable']);
            $default = $this->phpString($this->effectiveColor($color));
            $lines[] = "    {$color['variable']}: <?php echo esc_attr( get_theme_mod( '{$id}', {$default} ) ); ?>;";
        }

        return "<style id=\"webku-theme-colors\">\n:root {\n".implode("\n", $lines)."\n}\n</style>";
    }

    /**
     * @return array<int, array{variable: string, label: string, default: string}>
     */
    private function colors(): array
    {
        return $this->templateColors;
    }

    /**
     * @param  array{variable: string, label: string, default: string}  $color
     */
    private function effectiveColor(array $color): string
    {
        return (string) ($this->customData['colors'][$color['variable']] ?? $color['default']);
    }

    private function styleCss(Template $template, string $themeName, string $themeSlug): string
    {
        $clean = fn (?string $value): string => trim(str_replace(['*/', "\r", "\n"], ' ', (string) $value));
        $description = $clean($template->description) !== '' ? $clean($template->description) : "Tema WordPress {$clean($themeName)}.";

        $lines = [
            '/*',
            'Theme Name: '.$clean($themeName),
            'Author: '.$clean(Setting::getValue('wp_theme_author', 'WebCatalog Studio')),
            'Author URI: '.$clean(Setting::getValue('wp_theme_author_uri', '')),
            'Description: '.$description,
            'Version: 1.0.0',
            'Requires at least: 6.0',
            'Requires PHP: 7.4',
            'License: GNU General Public License v2 or later',
            'License URI: https://www.gnu.org/licenses/gpl-2.0.html',
            'Text Domain: '.$themeSlug,
            '*/',
            '',
            ':root {',
        ];

        foreach ($this->colors() as $color) {
            $lines[] = "    {$color['variable']}: {$this->effectiveColor($color)};";
        }

        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    private function functionsPhp(string $themeName): string
    {
        $pages = [];

        foreach ($this->pages as $page) {
            $pages[] = ['slug' => $page['slug'], 'title' => $page['title'], 'is_home' => $page['slug'] === $this->homePage()['slug']];
        }

        $settings = $this->settings;

        foreach ($this->colors() as $color) {
            $id = $this->colorSettingId($color['variable']);
            $this->sections['webku_colors'] = 'Warna Tema';
            $settings[$id] = ['id' => $id, 'label' => $color['label'], 'type' => 'color', 'section' => 'webku_colors', 'default' => $this->phpString($this->effectiveColor($color))];
        }

        $settingLines = [];

        foreach ($settings as $setting) {
            $settingLines[] = "        array( 'id' => {$this->phpString($setting['id'])}, 'label' => {$this->phpString($setting['label'])}, 'type' => {$this->phpString($setting['type'])}, 'section' => {$this->phpString($setting['section'])}, 'default' => {$setting['default']} ),";
        }

        $template = <<<'PHP'
<?php
/**
 * __THEME_NAME__ - theme functions.
 *
 * @package __THEME_NAME__
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
} );

/**
 * Create the pages of this theme and use the home page as the static front page.
 */
add_action( 'after_switch_theme', function () {
    $pages = __PAGES__;
    $home_id = 0;

    foreach ( $pages as $page ) {
        $existing = get_page_by_path( $page['slug'] );
        $page_id = $existing ? $existing->ID : wp_insert_post( array(
            'post_title'  => $page['title'],
            'post_name'   => $page['slug'],
            'post_status' => 'publish',
            'post_type'   => 'page',
        ) );

        if ( $page['is_home'] && $page_id && ! is_wp_error( $page_id ) ) {
            $home_id = (int) $page_id;
        }
    }

    if ( $home_id ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $home_id );
    }
} );

/**
 * Register Appearance -> Customize options for every editable element.
 */
add_action( 'customize_register', function ( $wp_customize ) {
    $sections = __SECTIONS__;

    $settings = array(
__SETTINGS__
    );

    $wp_customize->add_panel( 'webku_panel', array(
        'title'    => __( 'Pengaturan Tema', 'webku' ),
        'priority' => 30,
    ) );

    foreach ( $sections as $section_id => $section_title ) {
        $wp_customize->add_section( $section_id, array(
            'title' => $section_title,
            'panel' => 'webku_panel',
        ) );
    }

    $sanitizers = array(
        'color' => 'sanitize_hex_color',
        'image' => 'esc_url_raw',
        'url'   => 'esc_url_raw',
        'html'  => 'wp_kses_post',
        'text'  => 'wp_kses_post',
    );

    foreach ( $settings as $setting ) {
        $wp_customize->add_setting( $setting['id'], array(
            'default'           => $setting['default'],
            'sanitize_callback' => $sanitizers[ $setting['type'] ],
            'transport'         => 'refresh',
        ) );

        $args = array(
            'label'    => $setting['label'],
            'section'  => $setting['section'],
            'settings' => $setting['id'],
        );

        if ( 'color' === $setting['type'] ) {
            $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $setting['id'], $args ) );
        } elseif ( 'image' === $setting['type'] ) {
            $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $setting['id'], $args ) );
        } else {
            $args['type'] = 'html' === $setting['type'] ? 'textarea' : ( 'url' === $setting['type'] ? 'url' : 'text' );
            $wp_customize->add_control( $setting['id'], $args );
        }
    }
} );

PHP;

        return strtr($template, [
            '__THEME_NAME__' => str_replace(['*/', "\n", "\r"], ' ', $themeName),
            '__PAGES__' => var_export($pages, true),
            '__SECTIONS__' => var_export($this->sections, true),
            '__SETTINGS__' => implode("\n", $settingLines),
        ]);
    }

    private function zip(string $workRoot, string $themeSlug, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file ZIP tema.');
        }

        foreach (File::allFiles($workRoot.'/'.$themeSlug, true) as $file) {
            $zip->addFile($file->getRealPath(), $themeSlug.'/'.str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();
    }

    private function token(string $phpExpression): string
    {
        $token = sprintf(self::TOKEN, count($this->tokens));
        $this->tokens[$token] = "<?php echo {$phpExpression}; ?>";

        return $token;
    }

    private function replaceChildrenWithToken(DOMElement $element, string $phpExpression): void
    {
        while ($element->firstChild !== null) {
            $element->removeChild($element->firstChild);
        }

        $element->appendChild($element->ownerDocument->createTextNode($this->token($phpExpression)));
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private function hasElementChild(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    private function phpString(string $value): string
    {
        return "'".addcslashes($value, "'\\")."'";
    }

    private function slugKey(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
    }

    private function settingId(string $scope, string $editId): string
    {
        return 'webku_'.$this->slugKey($scope).'_'.$this->slugKey($editId);
    }

    private function colorSettingId(string $variable): string
    {
        return 'webku_color_'.$this->slugKey($variable);
    }
}
