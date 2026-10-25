<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomizedPreview;
use App\Models\Template;
use App\Services\WordPressThemeExporterService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WordPressExportController extends Controller
{
    public function __construct(private readonly WordPressThemeExporterService $exporter) {}

    /**
     * Quick export: accepts a full /p/{code} URL or just the code.
     */
    public function quick(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:500'],
            'theme_name' => ['nullable', 'string', 'max:100'],
        ]);

        $path = parse_url(trim($validated['source']), PHP_URL_PATH) ?: trim($validated['source']);

        if (preg_match('#(?:^|/)([A-Za-z0-9]{10})/?$#', $path, $match) !== 1) {
            throw ValidationException::withMessages(['source' => 'URL atau kode preview tidak valid.']);
        }

        $preview = CustomizedPreview::query()->with('template')->where('unique_code', $match[1])->first();

        if ($preview === null) {
            throw ValidationException::withMessages(['source' => 'Preview tidak ditemukan atau sudah dihapus.']);
        }

        return $this->download($preview->template, $preview, $validated['theme_name'] ?? null);
    }

    /**
     * Export a client's preview.
     */
    public function preview(Request $request, CustomizedPreview $preview): BinaryFileResponse
    {
        return $this->download($preview->template, $preview, $request->string('theme_name')->toString());
    }

    /**
     * Export the base template without customizations.
     */
    public function template(Request $request, Template $template): BinaryFileResponse
    {
        return $this->download($template, null, $request->string('theme_name')->toString());
    }

    private function download(Template $template, ?CustomizedPreview $preview, ?string $themeName): BinaryFileResponse
    {
        $themeName = trim((string) $themeName) !== '' ? Str::limit(trim((string) $themeName), 100, '') : $template->name;
        $zipPath = $this->exporter->export($template, $preview, $themeName);

        return response()
            ->download($zipPath, (Str::slug($themeName) ?: 'webku-theme').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
