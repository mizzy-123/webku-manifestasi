<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTemplateRequest;
use App\Http\Requests\Admin\UpdateTemplateRequest;
use App\Models\Category;
use App\Models\Template;
use App\Services\SampleTemplateGeneratorService;
use App\Services\TemplateArchiveService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /**
     * List all templates and the upload form data.
     */
    public function index(): Response
    {
        return Inertia::render('admin/templates/index', [
            'templates' => Template::query()
                ->with('category:id,name')
                ->latest()
                ->get()
                ->map(fn (Template $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'type' => $template->type,
                    'description' => $template->description,
                    'category' => $template->category->name,
                    'thumbnail_url' => '/storage/'.$template->thumbnail_path,
                    'pages_count' => count($template->manifest_data['pages'] ?? []),
                    'colors_count' => count($template->manifest_data['colors'] ?? []),
                    'price' => $template->price,
                    'is_active' => $template->is_active,
                ]),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Upload, validate and extract a template ZIP.
     */
    public function store(StoreTemplateRequest $request, TemplateArchiveService $archiveService): RedirectResponse
    {
        $template = $archiveService->storeFromUpload(
            $request->file('file'),
            Category::query()->findOrFail($request->integer('category_id')),
            [
                'description' => $request->input('description'),
                'price' => $request->input('price'),
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Template \"{$template->name}\" berhasil diunggah."]);

        return back();
    }

    /**
     * Toggle a template's active state.
     */
    public function update(UpdateTemplateRequest $request, Template $template): RedirectResponse
    {
        $template->update(['is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $template->is_active ? 'Template diaktifkan.' : 'Template dinonaktifkan.',
        ]);

        return back();
    }

    /**
     * Delete a template and its extracted files.
     */
    public function destroy(Template $template, TemplateArchiveService $archiveService): RedirectResponse
    {
        $archiveService->delete($template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template beserta file fisiknya berhasil dihapus.']);

        return back();
    }

    /**
     * Generate the two bundled sample templates.
     */
    public function generateSamples(SampleTemplateGeneratorService $generator): RedirectResponse
    {
        $created = $generator->generate();

        Inertia::flash('toast', $created->isEmpty()
            ? ['type' => 'info', 'message' => 'Sample template sudah ada semua.']
            : ['type' => 'success', 'message' => "{$created->count()} sample template berhasil dibuat."]);

        return back();
    }
}
