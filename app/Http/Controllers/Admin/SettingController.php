<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Setting keys managed from the admin settings page.
     *
     * @var list<string>
     */
    private const KEYS = [
        'whatsapp_number',
        'whatsapp_message_template',
        'wp_theme_author',
        'wp_theme_author_uri',
    ];

    /**
     * Show the settings form.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/edit', [
            'settings' => collect(self::KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => Setting::getValue($key, '')])
                ->all(),
        ]);
    }

    /**
     * Persist the settings.
     */
    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        foreach (self::KEYS as $key) {
            Setting::setValue($key, $request->validated($key));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan berhasil disimpan.']);

        return back();
    }
}
