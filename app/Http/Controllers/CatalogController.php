<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /**
     * Show the public template catalog with category, type and search filters.
     */
    public function index(Request $request): Response
    {
        $category = $request->string('category')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $search = $request->string('search')->trim()->limit(100, '')->toString();

        if (! in_array($type, [Template::TYPE_SINGLE_PAGE, Template::TYPE_MULTI_PAGE], true)) {
            $type = '';
        }

        $templates = Template::query()
            ->with('category:id,name,slug')
            ->where('is_active', true)
            ->when($category !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $category),
            ))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
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
                'thumbnail_url' => '/storage/'.$template->thumbnail_path,
                'pages_count' => count($template->manifest_data['pages'] ?? []),
            ]);

        return Inertia::render('catalog/index', [
            'templates' => $templates,
            'categories' => Category::query()
                ->whereHas('templates', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'filters' => [
                'category' => $category,
                'type' => $type,
                'search' => $search,
            ],
        ]);
    }
}
