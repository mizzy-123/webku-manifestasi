<?php

use App\Models\Category;
use App\Models\CustomizedPreview;
use App\Models\Setting;
use App\Models\Template;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;

test('seeder creates admin, default settings and categories', function () {
    $this->seed(DatabaseSeeder::class);

    $this->seed(DatabaseSeeder::class);

    expect(Setting::getValue('whatsapp_number'))->toBe('6281234567890')
        ->and(Setting::getValue('whatsapp_message_template'))->toContain('{template_name}', '{preview_url}')
        ->and(Setting::getValue('wp_theme_author'))->toBe('WebCatalog Studio')
        ->and(Setting::getValue('wp_theme_author_uri'))->toBe('https://example.com')
        ->and(Category::query()->count())->toBe(5)
        ->and(Category::query()->pluck('name')->all())->toContain('Landing Page', 'F&B & Restoran');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['email' => 'admin@admin.com']);
});

test('setting helpers return the default when missing and overwrite when present', function () {
    expect(Setting::getValue('missing', 'fallback'))->toBe('fallback');

    Setting::setValue('whatsapp_number', '6280000000000');
    Setting::setValue('whatsapp_number', '6281111111111');

    expect(Setting::getValue('whatsapp_number'))->toBe('6281111111111')
        ->and(Setting::query()->where('key', 'whatsapp_number')->count())->toBe(1);
});

test('json columns are cast to arrays without double encoding', function () {
    $template = Template::factory()->multiPage()->create();
    $preview = CustomizedPreview::factory()->for($template)->create([
        'custom_data' => ['colors' => ['--color-primary' => '#e11d48']],
    ]);

    $template = $template->fresh();
    $preview = $preview->fresh();

    expect($template->manifest_data)->toBeArray()
        ->and($template->manifest_data['pages'])->toHaveCount(2)
        ->and($template->is_active)->toBeTrue()
        ->and($preview->custom_data)->toBe(['colors' => ['--color-primary' => '#e11d48']])
        ->and($preview->expires_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('relationships connect categories, templates and previews', function () {
    $category = Category::factory()->create();
    $template = Template::factory()->for($category)->create();
    $preview = CustomizedPreview::factory()->for($template)->create();

    expect($category->templates->pluck('id')->all())->toBe([$template->id])
        ->and($template->category->is($category))->toBeTrue()
        ->and($template->customizedPreviews->pluck('id')->all())->toBe([$preview->id])
        ->and($preview->template->is($template))->toBeTrue();
});

test('deleting a template cascades to its previews', function () {
    $template = Template::factory()->create();
    CustomizedPreview::factory()->for($template)->count(2)->create();

    $template->delete();

    $this->assertDatabaseCount('customized_previews', 0);
});

test('template slug and preview unique code must be unique', function () {
    $template = Template::factory()->create(['slug' => 'same-slug']);
    CustomizedPreview::factory()->for($template)->create(['unique_code' => 'k9Xm2pL8qR']);

    expect(fn () => Template::factory()->create(['slug' => 'same-slug']))->toThrow(QueryException::class)
        ->and(fn () => CustomizedPreview::factory()->for($template)->create(['unique_code' => 'k9Xm2pL8qR']))
        ->toThrow(QueryException::class);
});
