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

    $this->get(route('admin.templates.index'))->assertRedirect(route('login'));
    $this->post(route('admin.templates.samples'))->assertRedirect(route('login'));
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
            ->has('categories', 2));
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
