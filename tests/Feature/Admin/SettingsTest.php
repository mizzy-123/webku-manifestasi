<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('guests cannot access settings', function () {
    $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
    $this->put(route('admin.settings.update'))->assertRedirect(route('login'));
});

test('settings page shows the stored values', function () {
    $this->seed(DatabaseSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/edit')
            ->where('settings.whatsapp_number', '6281234567890')
            ->where('settings.wp_theme_author', 'WebCatalog Studio'));
});

test('admin can update the whatsapp number and author', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('admin.settings.update'), [
            'whatsapp_number' => '6285555555555',
            'whatsapp_message_template' => 'Halo {template_name} {preview_url}',
            'wp_theme_author' => 'Studio Baru',
            'wp_theme_author_uri' => 'https://studio.example',
        ])
        ->assertSessionHasNoErrors();

    expect(Setting::getValue('whatsapp_number'))->toBe('6285555555555')
        ->and(Setting::getValue('wp_theme_author'))->toBe('Studio Baru')
        ->and(Setting::getValue('whatsapp_message_template'))->toBe('Halo {template_name} {preview_url}');
});

test('settings validation rejects invalid input', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('admin.settings.update'), [
            'whatsapp_number' => '+62 812-abc',
            'whatsapp_message_template' => '',
            'wp_theme_author' => '',
            'wp_theme_author_uri' => 'not-a-url',
        ])
        ->assertSessionHasErrors(['whatsapp_number', 'whatsapp_message_template', 'wp_theme_author', 'wp_theme_author_uri']);
});
