<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomizedPreview;
use App\Models\Template;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard summary.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'templates' => Template::query()->count(),
                'categories' => Category::query()->count(),
                'activePreviews' => CustomizedPreview::query()->where('expires_at', '>', now())->count(),
                'orderClicks' => CustomizedPreview::query()->whereNotNull('order_clicked_at')->count(),
            ],
            'recentOrders' => CustomizedPreview::query()
                ->with('template:id,name')
                ->whereNotNull('order_clicked_at')
                ->latest('order_clicked_at')
                ->limit(5)
                ->get()
                ->map(fn (CustomizedPreview $preview): array => [
                    'id' => $preview->id,
                    'unique_code' => $preview->unique_code,
                    'template_name' => $preview->template->name,
                    'order_clicked_at' => $preview->order_clicked_at?->toIso8601String(),
                ]),
        ]);
    }
}
