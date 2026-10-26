<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTemplateRequest;
use App\Http\Requests\Admin\UpdateTemplateDetailsRequest;
use App\Http\Requests\Admin\UpdateTemplateRequest;
use App\Http\Requests\Admin\UpdateTemplateThumbnailRequest;
use App\Models\Category;
use App\Models\Template;
use App\Services\SampleTemplateGeneratorService;
use App\Services\TemplateArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /**
     * List templates, narrowed by search, category, type, and active state.
     */
    public function index(Request $request): Response
    {
        $category = $request->integer('category');
        $type = $request->string('type')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $search = $request->string('search')->trim()->limit(100, '')->toString();

        if (! in_array($type, [Template::TYPE_SINGLE_PAGE, Template::TYPE_MULTI_PAGE], true)) {
            $type = '';
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        if ($category > 0 && ! Category::query()->whereKey($category)->exists()) {
            $category = 0;
        }

        $templates = Template::query()
            ->with('category:id,name')
            ->when($category > 0, fn ($query) => $query->where('category_id', $category))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn ($searchQuery) => $searchQuery
                    ->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Template $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'type' => $template->type,
                'description' => $template->description,
                'category' => $template->category->name,
                'category_id' => $template->category_id,
                'thumbnail_url' => '/storage/'.$template->thumbnail_path,
                'pages_count' => count($template->manifest_data['pages'] ?? []),
                'colors_count' => count($template->manifest_data['colors'] ?? []),
                'price' => $template->price,
                'is_active' => $template->is_active,
            ]);

        return Inertia::render('admin/templates/index', [
            'templates' => $templates,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'category' => $category > 0 ? (string) $category : '',
                'type' => $type,
                'status' => $status,
                'search' => $search,
            ],
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
     * Change the category and reference price of an existing template.
     */
    public function updateDetails(UpdateTemplateDetailsRequest $request, Template $template): RedirectResponse
    {
        $template->update([
            'category_id' => $request->integer('category_id'),
            'price' => $request->input('price'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori dan harga template berhasil disimpan.']);

        return back();
    }

    /**
     * Replace the image shown on the public catalog card.
     */
    public function updateThumbnail(UpdateTemplateThumbnailRequest $request, Template $template, TemplateArchiveService $archiveService): RedirectResponse
    {
        $archiveService->replaceCatalogThumbnail($template, $request->file('thumbnail'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gambar katalog berhasil diperbarui.']);

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
