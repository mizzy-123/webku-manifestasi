<?php

use App\Models\Category;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

test('guests cannot access template management', function () {
    auth()->logout();
    $template = Template::factory()->create();

    $this->get(route('admin.templates.index'))->assertRedirect(route('login'));
    $this->post(route('admin.templates.samples'))->assertRedirect(route('login'));
    $this->put(route('admin.templates.details', $template), [
        'category_id' => $template->category_id,
        'price' => 1000,
    ])->assertRedirect(route('login'));
});

test('template index lists templates with categories', function () {
    $template = Template::factory()->multiPage()->create();
    Category::factory()->create();

    $this->get(route('admin.templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/templates/index')
            ->has('templates', 1)
            ->where('templates.0.id', $template->id)
            ->where('templates.0.pages_count', 2)
            ->where('templates.0.thumbnail_url', '/storage/'.$template->thumbnail_path)
            ->has('categories', 2)
            ->where('filters', ['category' => '', 'type' => '', 'status' => '', 'search' => '']));
});

test('admin template list can be searched and filtered', function () {
    $landing = Category::factory()->create(['name' => 'Landing']);
    $profile = Category::factory()->create(['name' => 'Profile']);
    $byName = Template::factory()->for($landing)->create(['name' => 'Kopi Senja', 'description' => 'Cafe']);
    $byDescription = Template::factory()->for($profile)->inactive()->create(['name' => 'Alpha', 'description' => 'Untuk restoran kopi']);
    Template::factory()->for($profile)->multiPage()->create(['name' => 'Beta', 'description' => 'Konstruksi']);

    $this->get(route('admin.templates.index', ['search' => 'kopi']))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 2)
            ->where('templates.0.id', $byDescription->id)
            ->where('templates.1.id', $byName->id)
            ->where('filters.search', 'kopi'));

    $this->get(route('admin.templates.index', ['search' => '%']))
        ->assertInertia(fn ($page) => $page->has('templates', 0));

    $this->get(route('admin.templates.index', ['category' => $landing->id]))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->where('templates.0.id', $byName->id)
            ->where('filters.category', (string) $landing->id));

    $this->get(route('admin.templates.index', ['type' => 'multi-page']))
        ->assertInertia(fn ($page) => $page->has('templates', 1)->where('filters.type', 'multi-page'));

    $this->get(route('admin.templates.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->where('templates.0.id', $byDescription->id));

    $this->get(route('admin.templates.index', ['type' => 'bogus', 'status' => 'nope', 'category' => 999999]))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 3)
            ->where('filters.type', '')
            ->where('filters.status', '')
            ->where('filters.category', ''));
});

test('admin can upload a template zip', function () {
    $category = Category::factory()->create();
    $upload = new UploadedFile(makeTemplateZip(validTemplateFiles()), 'demo.zip', 'application/zip', null, true);

    $this->post(route('admin.templates.store'), [
        'file' => $upload,
        'category_id' => $category->id,
        'price' => 1500000,
    ])->assertSessionHasNoErrors();

    $template = Template::query()->firstOrFail();

    expect($template->category_id)->toBe($category->id)
        ->and($template->price)->toBe('1500000.00')
        ->and($template->is_active)->toBeTrue();

    Storage::disk('public')->assertExists('templates/demo-template/index.html');
});

test('uploading a malicious zip is rejected', function () {
    $category = Category::factory()->create();
    $upload = new UploadedFile(
        makeTemplateZip(validTemplateFiles(['backdoor.php' => '<?php system($_GET["c"]);'])),
        'bad.zip',
        'application/zip',
        null,
        true,
    );

    $this->post(route('admin.templates.store'), [
        'file' => $upload,
        'category_id' => $category->id,
    ])->assertSessionHasErrors('file');

    expect(Template::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('upload requires a zip file and an existing category', function () {
    $this->post(route('admin.templates.store'), [
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        'category_id' => 999,
    ])->assertSessionHasErrors(['file', 'category_id']);
});

test('admin can replace the catalog cover image', function () {
    $template = Template::factory()->create();

    $this->post(route('admin.templates.thumbnail', $template), [
        'thumbnail' => UploadedFile::fake()->image('cover.png', 2000, 1250),
    ])->assertRedirect();

    $template->refresh();

    expect($template->thumbnail_path)->toBe("{$template->storage_path}/catalog-cover.webp");
    Storage::disk('public')->assertExists($template->thumbnail_path);

    [$width] = getimagesizefromstring(Storage::disk('public')->get($template->thumbnail_path));
    expect($width)->toBe(1600);
});

test('catalog cover upload rejects files that are not images', function () {
    $template = Template::factory()->create();

    $this->post(route('admin.templates.thumbnail', $template), [
        'thumbnail' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('thumbnail');

    expect($template->refresh()->thumbnail_path)->not->toEndWith('catalog-cover.webp');
});

test('guests cannot replace a catalog cover', function () {
    auth()->logout();
    $template = Template::factory()->create();

    $this->post(route('admin.templates.thumbnail', $template), [
        'thumbnail' => UploadedFile::fake()->image('cover.png'),
    ])->assertRedirect(route('login'));
});

test('admin can change a template category and price', function () {
    $template = Template::factory()->create(['price' => 500000]);
    $category = Category::factory()->create();

    $this->put(route('admin.templates.details', $template), [
        'category_id' => $category->id,
        'price' => 2500000,
    ])->assertRedirect();

    expect($template->refresh()->category_id)->toBe($category->id)
        ->and($template->price)->toBe('2500000.00');
});

test('clearing the price removes it', function () {
    $template = Template::factory()->create(['price' => 500000]);

    $this->put(route('admin.templates.details', $template), [
        'category_id' => $template->category_id,
        'price' => '',
    ])->assertRedirect();

    expect($template->refresh()->price)->toBeNull();
});

test('template details reject an unknown category', function () {
    $template = Template::factory()->create();

    $this->put(route('admin.templates.details', $template), [
        'category_id' => 999999,
        'price' => 1000,
    ])->assertSessionHasErrors('category_id');
});

test('admin can toggle a template active state', function () {
    $template = Template::factory()->create();

    $this->patch(route('admin.templates.update', $template), ['is_active' => false])->assertRedirect();
    expect($template->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.templates.update', $template), ['is_active' => true])->assertRedirect();
    expect($template->fresh()->is_active)->toBeTrue();
});

test('deleting a template removes its physical folder', function () {
    $template = Template::factory()->create(['slug' => 'to-delete', 'storage_path' => 'templates/to-delete']);
    Storage::disk('public')->put('templates/to-delete/index.html', 'x');
    Storage::disk('public')->put('templates/keep/index.html', 'x');

    $this->delete(route('admin.templates.destroy', $template))->assertRedirect();

    expect(Template::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing('templates/to-delete/index.html');
    Storage::disk('public')->assertExists('templates/keep/index.html');
});

test('generating samples creates one single-page and one multi-page template', function () {
    $this->post(route('admin.templates.samples'))->assertSessionHasNoErrors();

    $single = Template::query()->where('type', 'single-page')->firstOrFail();
    $multi = Template::query()->where('type', 'multi-page')->firstOrFail();

    expect(Template::query()->count())->toBe(2)
        ->and($multi->manifest_data['pages'])->toHaveCount(4);

    foreach ([$single, $multi] as $template) {
        Storage::disk('public')->assertExists($template->thumbnail_path);
        Storage::disk('public')->assertExists("{$template->storage_path}/index.html");
        Storage::disk('public')->assertExists("{$template->storage_path}/assets/css/style.css");
    }

    Storage::disk('public')->assertExists("{$multi->storage_path}/about.html");
    expect(Storage::disk('public')->get("{$multi->storage_path}/index.html"))
        ->toContain('data-edit-scope="global"');
});

test('generating samples twice does not duplicate them', function () {
    $this->post(route('admin.templates.samples'));
    $this->post(route('admin.templates.samples'));

    expect(Template::query()->count())->toBe(2);
});
