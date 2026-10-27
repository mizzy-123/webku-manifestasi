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

    /** @var array<int, string> */
    private array $postPageSlugs = [];

    /**
     * Sample stories copied into WordPress posts when the theme is activated.
     *
     * @var array<int, array{slug: string, title: string, content: string, date: string, category: string, image: string}>
     */
    private array $seedPosts = [];

    private string $postsPageSlug = '';

    private string $lastPageRole = 'page';

    private bool $usesPostLoop = false;

    private int $postLoopIndex = 0;

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
        $this->postPageSlugs = [];
        $this->seedPosts = [];
        $this->postsPageSlug = '';
        $this->lastPageRole = 'page';
        $this->usesPostLoop = false;
        $this->postLoopIndex = 0;

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
        $front = null;
        $archive = null;
        $single = null;

        foreach ($this->pages as $page) {
            $path = "{$template->storage_path}/{$page['file']}";

            if (! $disk->exists($path)) {
                continue;
            }

            $php = $this->convertPage($disk->get($path), $page);

            if ($this->lastPageRole === 'single') {
                $this->postPageSlugs[] = $page['slug'];
                $single ??= $php;

                continue;
            }

            if ($this->lastPageRole === 'archive') {
                $this->postsPageSlug = $page['slug'];
                $archive = $php;

                continue;
            }

            if ($page['slug'] === $homeSlug) {
                File::put($themeDir.'/front-page.php', $php);
                $front = $php;

                continue;
            }

            File::put($themeDir."/page-{$page['slug']}.php", $php);
        }

        if ($archive !== null) {
            File::put($themeDir.'/home.php', $archive);
            File::put($themeDir.'/archive.php', $archive);
            File::put($themeDir.'/index.php', $archive);
        } elseif ($front !== null) {
            File::put($themeDir.'/index.php', $front);
        }

        if ($single !== null) {
            File::put($themeDir.'/single.php', $single);
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
        $this->lastPageRole = $this->pageRole($html);
        $this->convertWordPressPosts($xpath, $page);

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

        foreach ($xpath->query('//*[@data-edit-id or @data-edit-type or @data-edit-scope or @contenteditable or @data-wp-loop or @data-wp-query or @data-wp-count or @data-wp-post or @data-wp-single or @data-wp-field or @data-wp-date or @data-wp-pagination or @data-wp-search or @data-blog-filter]') ?: [] as $element) {
            foreach (['data-edit-id', 'data-edit-type', 'data-edit-scope', 'contenteditable', 'spellcheck', 'data-wp-loop', 'data-wp-query', 'data-wp-count', 'data-wp-post', 'data-wp-single', 'data-wp-field', 'data-wp-date', 'data-wp-pagination', 'data-wp-search', 'data-blog-filter'] as $attribute) {
                $element->removeAttribute($attribute);
            }
        }

        return $this->injectWordPress($document->saveHTML() ?: '');
    }

    /**
     * A page that carries data-wp-single is one story. data-wp-query="main" is the posts archive.
     */
    private function pageRole(string $html): string
    {
        if (str_contains($html, 'data-wp-single="post"')) {
            return 'single';
        }

        if (str_contains($html, 'data-wp-query="main"')) {
            return 'archive';
        }

        return 'page';
    }

    /**
     * Turn marked story cards into a WordPress loop and keep the sample stories for the first activation.
     *
     * @param  array{slug: string, title: string, file: string}  $page
     */
    private function convertWordPressPosts(DOMXPath $xpath, array $page): void
    {
        if ($this->lastPageRole === 'single') {
            $articles = $xpath->query('//*[@data-wp-single="post"]');
            $article = $articles === false ? null : $articles->item(0);

            if ($article instanceof DOMElement) {
                $this->seedPosts[] = $this->extractPost($article, $page);
                $this->usesPostLoop = true;
                $this->fillPostFields($article);
                $article->removeAttribute('data-wp-single');
            }
        }

        foreach (iterator_to_array($xpath->query('//*[@data-wp-loop="post"]') ?: []) as $loop) {
            if ($loop instanceof DOMElement) {
                $this->convertPostLoop($loop);
            }
        }

        $this->convertNewsSearch($xpath, $page);

        foreach (iterator_to_array($xpath->query('//*[@data-wp-pagination]') ?: []) as $pager) {
            if (! $pager instanceof DOMElement) {
                continue;
            }

            while ($pager->firstChild !== null) {
                $pager->removeChild($pager->firstChild);
            }

            $pager->appendChild($pager->ownerDocument->createTextNode($this->rawToken(
                "<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => 'Sebelumnya', 'next_text' => 'Berikutnya' ) ); ?>"
            )));
            $pager->removeAttribute('data-wp-pagination');
        }
    }

    /**
     * @param  array{slug: string, title: string, file: string}  $page
     */
    private function convertNewsSearch(DOMXPath $xpath, array $page): void
    {
        foreach (iterator_to_array($xpath->query('//form[@data-wp-search="post"]') ?: []) as $form) {
            if (! $form instanceof DOMElement) {
                continue;
            }

            $this->usesPostLoop = true;
            $path = '/'.trim($page['slug'], '/').'/';
            $form->setAttribute('action', $this->token("esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '{$path}' ) )"));
            $form->setAttribute('method', 'get');
            $form->removeAttribute('data-wp-search');
            $form->removeAttribute('data-blog-filter');

            $inputs = (new DOMXPath($form->ownerDocument))->query('.//input[@name="q"]', $form);
            $field = $inputs === false ? null : $inputs->item(0);

            if ($field instanceof DOMElement) {
                $field->setAttribute('value', $this->token('esc_attr( webku_news_query() )'));
            }
        }
    }

    private function convertPostLoop(DOMElement $loop): void
    {
        $items = [];

        foreach ($loop->childNodes as $child) {
            if ($child instanceof DOMElement && $child->hasAttribute('data-wp-post')) {
                $items[] = $child;
            }
        }

        if ($items === []) {
            return;
        }

        $count = (int) $loop->getAttribute('data-wp-count');

        if ($count < 1) {
            $count = count($items);
        }

        $count = max(1, min($count, 24));
        $template = $items[0];

        for ($index = 1, $total = count($items); $index < $total; $index++) {
            $items[$index]->parentNode?->removeChild($items[$index]);
        }

        $this->usesPostLoop = true;
        $this->fillPostFields($template);
        $template->removeAttribute('data-wp-post');

        $isMain = $loop->getAttribute('data-wp-query') === 'main';
        $document = $loop->ownerDocument;
        $empty = '<p class="blog-empty">Belum ada kabar.</p>';

        if ($isMain) {
            $before = 'if ( have_posts() ) : while ( have_posts() ) : the_post();';
            $after = "endwhile; else : if ( webku_news_query() !== '' ) : ?><p class=\"blog-empty\">Tidak ada kabar yang cocok.</p><?php else : ?>".$empty.'<?php endif; endif;';
        } else {
            $variable = '$webku_posts_'.$this->postLoopIndex;
            $this->postLoopIndex++;
            $before = "{$variable} = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => {$count} ) ); if ( {$variable}->have_posts() ) : while ( {$variable}->have_posts() ) : {$variable}->the_post();";
            $after = 'endwhile; else : ?>'.$empty.'<?php endif; wp_reset_postdata();';
        }

        $loop->insertBefore($document->createTextNode($this->rawToken('<?php '.$after.' ?>')), $template->nextSibling);
        $loop->insertBefore($document->createTextNode($this->rawToken('<?php '.$before.' ?>')), $template);

        foreach (['data-wp-loop', 'data-wp-query', 'data-wp-count'] as $attribute) {
            $loop->removeAttribute($attribute);
        }
    }

    private function fillPostFields(DOMElement $root): void
    {
        $xpath = new DOMXPath($root->ownerDocument);

        foreach (iterator_to_array($xpath->query('.//*[@data-wp-field]', $root) ?: []) as $field) {
            if (! $field instanceof DOMElement) {
                continue;
            }

            $name = $field->getAttribute('data-wp-field');
            $this->stripEditorAttributes($field);

            if ($name === 'permalink') {
                $field->setAttribute('href', $this->token('esc_url( get_permalink() )'));
            } elseif ($name === 'thumbnail') {
                $fallback = $this->urlExpression($field->getAttribute('src'));
                $field->removeAttribute('srcset');
                $field->setAttribute('src', $this->token("esc_url( get_the_post_thumbnail_url( null, 'large' ) ?: {$fallback} )"));
                $field->setAttribute('alt', $this->token('esc_attr( get_the_title() )'));
            } elseif ($name === 'content') {
                $this->replaceChildrenWithToken($field, "apply_filters( 'the_content', get_the_content() )");
            } elseif ($name === 'date') {
                $this->replaceChildrenWithToken($field, 'esc_html( get_the_date() )');
            } elseif ($name === 'category') {
                $this->replaceChildrenWithToken($field, 'esc_html( webku_post_category() )');
            } elseif ($name === 'excerpt') {
                $this->replaceChildrenWithToken($field, 'wp_kses_post( get_the_excerpt() )');
            } elseif ($name === 'title') {
                $this->replaceChildrenWithToken($field, 'esc_html( get_the_title() )');
            }

            $field->removeAttribute('data-wp-field');
            $field->removeAttribute('data-wp-date');
        }
    }

    /**
     * @param  array{slug: string, title: string, file: string}  $page
     * @return array{slug: string, title: string, content: string, date: string, category: string, image: string}
     */
    private function extractPost(DOMElement $article, array $page): array
    {
        $group = $this->customData['pages'][$page['slug']] ?? [];
        $texts = is_array($group['texts'] ?? null) ? $group['texts'] : [];
        $images = is_array($group['images'] ?? null) ? $group['images'] : [];
        $title = $this->plainField($article, 'title', $texts);
        $date = '';
        $dateField = $this->fieldElement($article, 'date');

        if ($dateField instanceof DOMElement && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateField->getAttribute('data-wp-date')) === 1) {
            $date = $dateField->getAttribute('data-wp-date').' 09:00:00';
        }

        $content = $this->fieldHtml($article, 'content', $texts, $images);

        return [
            'slug' => $page['slug'],
            'title' => $title !== '' ? $title : $page['title'],
            'content' => $content !== '' ? $content : '<p></p>',
            'date' => $date,
            'category' => $this->plainField($article, 'category', $texts),
            'image' => $this->themeAsset($this->imageField($article, 'thumbnail', $images)),
        ];
    }

    /**
     * @param  array<string, mixed>  $texts
     */
    private function plainField(DOMElement $root, string $name, array $texts): string
    {
        $field = $this->fieldElement($root, $name);

        if (! $field instanceof DOMElement) {
            return '';
        }

        $editId = $field->getAttribute('data-edit-id');
        $raw = $editId !== '' && array_key_exists($editId, $texts) ? (string) $texts[$editId] : $field->textContent;
        $text = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return Str::limit($text, $name === 'category' ? 80 : 200, '');
    }

    /**
     * @param  array<string, mixed>  $texts
     * @param  array<string, mixed>  $images
     */
    private function fieldHtml(DOMElement $root, string $name, array $texts, array $images): string
    {
        $field = $this->fieldElement($root, $name);

        if (! $field instanceof DOMElement) {
            return '';
        }

        $editId = $field->getAttribute('data-edit-id');

        if ($editId !== '' && array_key_exists($editId, $texts)) {
            return $this->rewriteContentAssets((string) $texts[$editId]);
        }

        $xpath = new DOMXPath($field->ownerDocument);

        foreach (iterator_to_array($xpath->query('.//*[@data-edit-type="image"]', $field) ?: []) as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }

            $id = $image->getAttribute('data-edit-id');

            if ($id !== '' && isset($images[$id]) && is_string($images[$id]) && $images[$id] !== '') {
                $image->setAttribute('src', $images[$id]);
            }
        }

        return $this->rewriteContentAssets($this->innerHtml($field));
    }

    /**
     * @param  array<string, mixed>  $images
     */
    private function imageField(DOMElement $root, string $name, array $images): string
    {
        $field = $this->fieldElement($root, $name);

        if (! $field instanceof DOMElement) {
            return '';
        }

        $editId = $field->getAttribute('data-edit-id');

        if ($editId !== '' && isset($images[$editId]) && is_string($images[$editId]) && $images[$editId] !== '') {
            return $images[$editId];
        }

        return $field->getAttribute('src');
    }

    private function fieldElement(DOMElement $root, string $name): ?DOMElement
    {
        $nodes = (new DOMXPath($root->ownerDocument))->query('.//*[@data-wp-field="'.$name.'"]', $root);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function themeAsset(string $url): string
    {
        $url = (string) preg_replace('#^\./#', '', trim($url));

        if (preg_match('#^(?:https?:)?(?://[^/]+)?/storage/custom-uploads/[A-Za-z0-9]{10}/([^?\#]+)#', $url, $match) === 1) {
            $url = 'assets/custom-uploads/'.$match[1];
        }

        if (! str_starts_with($url, 'assets/') || str_contains($url, '..')) {
            return '';
        }

        return $url;
    }

    private function rewriteContentAssets(string $html): string
    {
        $html = (string) preg_replace(
            '#([\'"])(?:https?:)?(?://[^\'"]+)?/storage/custom-uploads/[A-Za-z0-9]{10}/([^\'"]+)\1#',
            '$1__THEME_URI__/assets/custom-uploads/$2$1',
            $html,
        );

        return (string) preg_replace_callback(
            '#\b(src|href)\s*=\s*([\'"])(?:\./)?(assets/[^\'"]+)\2#i',
            function (array $match): string {
                if (str_contains($match[3], '..')) {
                    return $match[0];
                }

                return $match[1].'='.$match[2].'__THEME_URI__/'.$match[3].$match[2];
            },
            $html,
        );
    }

    private function stripEditorAttributes(DOMElement $element): void
    {
        foreach (['data-edit-id', 'data-edit-type', 'data-edit-scope', 'contenteditable', 'spellcheck'] as $attribute) {
            $element->removeAttribute($attribute);
        }
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
            if (in_array($page['slug'], $this->postPageSlugs, true)) {
                continue;
            }

            $pages[] = [
                'slug' => $page['slug'],
                'title' => $page['title'],
                'is_home' => $page['slug'] === $this->homePage()['slug'],
                'is_posts' => $page['slug'] === $this->postsPageSlug,
            ];
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

        if ( ! empty( $page['is_posts'] ) && $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'page_for_posts', (int) $page_id );
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
        ]).$this->postHelpers();
    }

    private function postHelpers(): string
    {
        if (! $this->usesPostLoop) {
            return '';
        }

        $helpers = <<<'PHP'

/**
 * Search term from the news list, or an empty string.
 */
function webku_news_query() {
    if ( ! isset( $_GET['q'] ) ) {
        return '';
    }

    return sanitize_text_field( wp_unslash( $_GET['q'] ) );
}

/**
 * Keep a news-list search on the posts page instead of switching to the search template.
 */
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
        return;
    }

    $term = webku_news_query();

    if ( $term !== '' ) {
        $query->set( 'webku_news_search', $term );
    }
} );

/**
 * Match the news search against the title, excerpt, and content.
 *
 * @param string   $search Search SQL.
 * @param WP_Query $query  Current query.
 */
add_filter( 'posts_search', function ( $search, $query ) {
    $term = $query->get( 'webku_news_search' );

    if ( ! is_string( $term ) || $term === '' ) {
        return $search;
    }

    global $wpdb;
    $like = '%' . $wpdb->esc_like( $term ) . '%';

    return $wpdb->prepare(
        " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s) ",
        $like,
        $like,
        $like
    );
}, 10, 2 );

/**
 * First category name for the current post.
 */
function webku_post_category() {
    $categories = get_the_category();

    if ( empty( $categories ) || is_wp_error( $categories ) ) {
        return '';
    }

    return $categories[0]->name;
}

PHP;

        if ($this->seedPosts === []) {
            return $helpers;
        }

        $seed = str_replace('__SEED_POSTS__', var_export($this->seedPosts, true), <<<'PHP'

/**
 * Publish the sample stories once. A story that already exists is left unchanged.
 */
function webku_seed_posts() {
    $posts = __SEED_POSTS__;

    foreach ( $posts as $item ) {
        if ( get_page_by_path( $item['slug'], OBJECT, 'post' ) ) {
            continue;
        }

        $post_id = wp_insert_post( array(
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_title'   => $item['title'],
            'post_name'    => $item['slug'],
            'post_content' => str_replace( '__THEME_URI__', get_template_directory_uri(), $item['content'] ),
            'post_date'    => $item['date'] !== '' ? $item['date'] : current_time( 'mysql' ),
        ) );

        if ( ! $post_id || is_wp_error( $post_id ) ) {
            continue;
        }

        if ( $item['category'] !== '' ) {
            $term = term_exists( $item['category'], 'category' );

            if ( ! $term ) {
                $term = wp_insert_term( $item['category'], 'category' );
            }

            if ( ! is_wp_error( $term ) ) {
                $term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
                wp_set_post_categories( $post_id, array( $term_id ) );
            }
        }

        webku_attach_theme_image( $post_id, $item['image'] );
    }
}
add_action( 'after_switch_theme', 'webku_seed_posts', 20 );

/**
 * Copy a theme image into the media library and use it as the featured image.
 *
 * @param int    $post_id  Post ID.
 * @param string $relative Path inside the theme, starting with assets/.
 */
function webku_attach_theme_image( $post_id, $relative ) {
    if ( ! is_string( $relative ) || ! preg_match( '#^assets/[A-Za-z0-9_./-]+$#', $relative ) || false !== strpos( $relative, '..' ) ) {
        return;
    }

    $path = get_template_directory() . '/' . $relative;

    if ( ! is_readable( $path ) ) {
        return;
    }

    $filename = basename( $path );
    $contents = file_get_contents( $path );

    if ( false === $contents ) {
        return;
    }

    $upload = wp_upload_bits( $filename, null, $contents );

    if ( ! empty( $upload['error'] ) ) {
        return;
    }

    $filetype = wp_check_filetype( $filename );
    $attachment_id = wp_insert_attachment( array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
        'post_status'    => 'inherit',
    ), $upload['file'], $post_id );

    if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
        return;
    }

    if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
    set_post_thumbnail( $post_id, $attachment_id );
}

PHP);

        return $helpers.$seed;
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
        return $this->rawToken("<?php echo {$phpExpression}; ?>");
    }

    private function rawToken(string $php): string
    {
        $token = sprintf(self::TOKEN, count($this->tokens));
        $this->tokens[$token] = $php;

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
