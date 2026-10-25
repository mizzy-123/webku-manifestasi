<?php

use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can open the editor workspace of an active template', function () {
    $template = Template::factory()->create([
        'manifest_data' => [
            'pages' => [['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html']],
            'colors' => [['variable' => '--color-primary', 'label' => 'Utama', 'default' => '#2563eb']],
        ],
    ]);

    $this->get(route('editor.show', $template))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('editor/workspace')
            ->where('template.slug', $template->slug)
            ->has('template.pages', 1)
            ->has('template.colors', 1));
});

test('inactive templates are hidden from guests in the editor', function () {
    $template = Template::factory()->inactive()->create();

    $this->get(route('editor.show', $template))->assertNotFound();
});

test('admins can open the editor of an inactive template', function () {
    $template = Template::factory()->inactive()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('editor.show', $template))
        ->assertOk();
});

test('the editor bridge script is served publicly', function () {
    expect(file_get_contents(public_path('js/editor-bridge.js')))
        ->toContain('IFRAME_READY')
        ->toContain('APPLY_CUSTOM_DATA');
});
