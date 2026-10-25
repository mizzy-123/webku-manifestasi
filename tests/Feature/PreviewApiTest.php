<?php

use App\Models\CustomizedPreview;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function customData(array $overrides = []): array
{
    return array_replace_recursive([
        'colors' => ['--color-primary' => '#9275f7'],
        'global' => ['texts' => [], 'images' => [], 'links' => []],
        'pages' => ['home' => ['texts' => ['hero-title' => 'Halo <script>x</script><b>Dunia</b>'], 'images' => [], 'links' => []]],
    ], $overrides);
}

test('the first save creates a unique link and sanitizes the data', function () {
    $template = Template::factory()->create();

    $response = $this->postJson(route('api.previews.store'), [
        'template' => $template->slug,
        'custom_data' => customData(),
    ])->assertCreated();

    $preview = CustomizedPreview::query()->firstOrFail();

    expect($response->json('unique_code'))->toBe($preview->unique_code)->toHaveLength(10)
        ->and($response->json('url'))->toBe(route('previews.show', $preview->unique_code))
        ->and($preview->template_id)->toBe($template->id)
        ->and($preview->custom_data['pages']['home']['texts']['hero-title'])->toBe('Halo <b>Dunia</b>')
        ->and($preview->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('saving again overwrites the same link instead of creating a new one', function () {
    $preview = CustomizedPreview::factory()->create([
        'last_accessed_at' => now()->subDays(10),
        'expires_at' => now()->addDays(20),
    ]);

    $this->putJson(route('api.previews.update', $preview->unique_code), [
        'custom_data' => customData(['colors' => ['--color-primary' => '#111111']]),
    ])->assertOk()->assertJsonPath('unique_code', $preview->unique_code);

    expect(CustomizedPreview::query()->count())->toBe(1)
        ->and($preview->refresh()->custom_data['colors'])->toBe(['--color-primary' => '#111111'])
        ->and($preview->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('expired previews cannot be overwritten', function () {
    $preview = CustomizedPreview::factory()->expired()->create();

    $this->putJson(route('api.previews.update', $preview->unique_code), ['custom_data' => customData()])
        ->assertNotFound();
});

test('inactive templates cannot be saved', function () {
    $template = Template::factory()->inactive()->create();

    $this->postJson(route('api.previews.store'), ['template' => $template->slug, 'custom_data' => customData()])
        ->assertUnprocessable();
});

test('an image upload reserves a code that the first save then reuses', function () {
    Storage::fake('public');
    $template = Template::factory()->create();

    $code = $this->postJson(route('api.previews.upload-image'), [
        'image' => UploadedFile::fake()->image('logo.png', 3000, 1000),
    ])->assertCreated()->json('unique_code');

    $this->postJson(route('api.previews.store'), [
        'template' => $template->slug,
        'unique_code' => $code,
        'custom_data' => customData(),
    ])->assertCreated()->assertJsonPath('unique_code', $code);
});

test('raster uploads are converted to webp inside the code folder', function () {
    Storage::fake('public');

    $response = $this->postJson(route('api.previews.upload-image'), [
        'unique_code' => 'abcdefghij',
        'image' => UploadedFile::fake()->image('logo.png', 3000, 1000),
    ])->assertCreated();

    expect($response->json('url'))->toStartWith('/storage/custom-uploads/abcdefghij/')->toEndWith('.webp');

    $path = str($response->json('url'))->after('/storage/')->toString();
    Storage::disk('public')->assertExists($path);

    [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($width)->toBe(1920);
});

test('svg uploads are sanitized', function () {
    Storage::fake('public');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="x()"><script>alert(1)</script><rect width="1" height="1"/></svg>';

    $response = $this->postJson(route('api.previews.upload-image'), [
        'unique_code' => 'abcdefghij',
        'image' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
    ])->assertCreated();

    $stored = Storage::disk('public')->get(str($response->json('url'))->after('/storage/')->toString());

    expect($stored)->toContain('<rect')->not->toContain('script')->not->toContain('onload');
});

test('invalid uploads are rejected', function (UploadedFile $file) {
    Storage::fake('public');

    $this->postJson(route('api.previews.upload-image'), ['image' => $file])->assertUnprocessable();
})->with([
    'too large' => fn () => UploadedFile::fake()->image('big.png')->size(3000),
    'php file' => fn () => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
]);

test('the upload rejects a code that could escape the upload folder', function () {
    Storage::fake('public');

    $this->postJson(route('api.previews.upload-image'), [
        'unique_code' => '../../x',
        'image' => UploadedFile::fake()->image('a.png'),
    ])->assertUnprocessable();
});

test('the order click is recorded and returns a prefilled whatsapp url', function () {
    Setting::setValue('whatsapp_number', '6281234567890');
    Setting::setValue('whatsapp_message_template', 'Pesan {template_name} {preview_url}');
    $preview = CustomizedPreview::factory()->create();

    $url = $this->postJson(route('api.previews.order-click', $preview->unique_code))
        ->assertOk()
        ->json('whatsapp_url');

    expect($preview->refresh()->order_clicked_at)->not->toBeNull()
        ->and($url)->toStartWith('https://wa.me/6281234567890?text=')
        ->and(urldecode($url))->toContain($preview->template->name)->toContain(route('previews.show', $preview->unique_code));
});

test('a shared preview opens the workspace with its saved data and extends its life', function () {
    $preview = CustomizedPreview::factory()->create([
        'last_accessed_at' => now()->subDays(5),
        'expires_at' => now()->addDays(25),
    ]);

    $this->get(route('previews.show', $preview->unique_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('editor/workspace')
            ->where('preview.unique_code', $preview->unique_code)
            ->where('preview.custom_data.colors.--color-primary', '#9275f7'));

    expect($preview->refresh()->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

test('unknown and expired links show the unavailable page', function () {
    $expired = CustomizedPreview::factory()->expired()->create();

    $this->get(route('previews.show', $expired->unique_code))->assertNotFound();
    $this->get(route('previews.show', 'abcdefghij'))->assertNotFound();
});

test('locked previews stay available after their expiry date', function () {
    $preview = CustomizedPreview::factory()->expired()->locked()->create();

    $this->get(route('previews.show', $preview->unique_code))->assertOk();
});
