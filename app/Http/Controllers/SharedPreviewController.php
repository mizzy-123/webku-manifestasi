<?php

namespace App\Http\Controllers;

use App\Models\CustomizedPreview;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SharedPreviewController extends Controller
{
    /**
     * Open a saved preview in the workspace, or explain why it is unavailable.
     */
    public function show(string $uniqueCode): Response|HttpResponse
    {
        $preview = CustomizedPreview::query()
            ->with('template')
            ->where('unique_code', $uniqueCode)
            ->first();

        if ($preview === null || ! $preview->isAvailable() || ! $preview->template->is_active) {
            return Inertia::render('shared/unavailable')->toResponse(request())->setStatusCode(404);
        }

        $preview->touchRetention();

        return EditorController::render($preview->template, $preview);
    }
}
