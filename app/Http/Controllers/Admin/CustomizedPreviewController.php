<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomizedPreview;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomizedPreviewController extends Controller
{
    /**
     * List all visitor customizations.
     */
    public function index(): Response
    {
        return Inertia::render('admin/previews/index', [
            'previews' => CustomizedPreview::query()
                ->with('template:id,name')
                ->latest('last_accessed_at')
                ->latest('id')
                ->get()
                ->map(fn (CustomizedPreview $preview): array => [
                    'id' => $preview->id,
                    'unique_code' => $preview->unique_code,
                    'url' => route('previews.show', $preview->unique_code),
                    'template_name' => $preview->template->name,
                    'order_clicked_at' => $preview->order_clicked_at?->toIso8601String(),
                    'is_locked_by_admin' => $preview->is_locked_by_admin,
                    'is_expired' => ! $preview->isAvailable(),
                    'last_accessed_at' => $preview->last_accessed_at->toIso8601String(),
                    'expires_at' => $preview->expires_at->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Lock or unlock a preview so it is (not) protected from automatic cleanup.
     */
    public function toggleLock(CustomizedPreview $preview): RedirectResponse
    {
        $preview->update(['is_locked_by_admin' => ! $preview->is_locked_by_admin]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $preview->is_locked_by_admin ? 'Preview dikunci.' : 'Kunci preview dibuka.']);

        return back();
    }
}
