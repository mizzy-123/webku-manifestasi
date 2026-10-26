<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * List every category and how many templates use it.
     */
    public function index(): Response
    {
        return Inertia::render('admin/categories/index', [
            'categories' => Category::query()
                ->withCount('templates')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'templates_count' => $category->templates_count,
                ]),
        ]);
    }

    /**
     * Create a category. The slug is derived from the name.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->safe()->only(['name', 'slug']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil ditambahkan.']);

        return back();
    }

    /**
     * Rename a category and refresh its slug.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->safe()->only(['name', 'slug']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil diperbarui.']);

        return back();
    }

    /**
     * Delete a category that is not used by any template.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->templates()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Kategori masih dipakai template, jadi tidak bisa dihapus.',
            ]);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil dihapus.']);

        return back();
    }
}
