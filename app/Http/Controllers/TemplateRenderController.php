<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class TemplateRenderController extends Controller
{
    /**
     * Inline script so in-page anchors keep working despite the injected <base> tag.
     */
    private const ANCHOR_FIX_SCRIPT = <<<'HTML'
<script>document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href^="#"]');if(!a){return}var id=a.getAttribute('href').slice(1);e.preventDefault();var t=id?document.getElementById(id):document.body;if(t){t.scrollIntoView({behavior:'smooth'})}});</script>
HTML;

    /**
     * Render a template page with the iframe <base> tag and editor bridge injected.
     */
    public function show(Template $template, string $pageFile = 'index.html'): Response
    {
        abort_unless($template->is_active || auth()->check(), 404);

        $registeredFiles = array_column($template->manifest_data['pages'] ?? [], 'file');

        abort_unless(in_array($pageFile, $registeredFiles, true), 404);

        $disk = Storage::disk('public');
        $path = "{$template->storage_path}/{$pageFile}";

        abort_unless($disk->exists($path), 404);

        $html = $this->inject($disk->get($path), $template);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Inject the <base> tag right after <head> and the scripts right before </body>.
     */
    private function inject(string $html, Template $template): string
    {
        $baseTag = '<base href="/storage/'.e($template->storage_path).'/">';
        $scripts = '<script src="/js/editor-bridge.js"></script>'.self::ANCHOR_FIX_SCRIPT;

        if (preg_match('/<head\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $html = substr_replace($html, $baseTag, $offset, 0);
        } else {
            $html = $baseTag.$html;
        }

        $closingBody = strripos($html, '</body>');

        if ($closingBody === false) {
            return $html.$scripts;
        }

        return substr_replace($html, $scripts, $closingBody, 0);
    }
}
