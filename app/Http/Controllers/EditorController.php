<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Inertia\Inertia;
use Inertia\Response;

class EditorController extends Controller
{
    /**
     * Show the live customizer workspace for an active template.
     */
    public function show(Template $template): Response
    {
        abort_unless($template->is_active || auth()->check(), 404);

        $manifest = $template->manifest_data ?? [];

        return Inertia::render('editor/workspace', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'type' => $template->type,
                'pages' => array_values($manifest['pages'] ?? []),
                'colors' => array_values($manifest['colors'] ?? []),
            ],
        ]);
    }
}
