<?php

use App\Models\CustomizedPreview;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

test('expired unlocked previews are deleted with their uploads', function () {
    Storage::fake('public');
    $expired = CustomizedPreview::factory()->expired()->create(['unique_code' => 'expiredAAA']);
    $locked = CustomizedPreview::factory()->expired()->locked()->create(['unique_code' => 'lockedBBBB']);
    $active = CustomizedPreview::factory()->create(['unique_code' => 'activeCCCC']);

    foreach (['expiredAAA', 'lockedBBBB', 'activeCCCC'] as $code) {
        Storage::disk('public')->put("custom-uploads/{$code}/logo.webp", 'x');
    }

    $this->artisan('previews:cleanup')->assertSuccessful();

    expect(CustomizedPreview::query()->pluck('unique_code')->all())->toEqualCanonicalizing(['lockedBBBB', 'activeCCCC']);
    Storage::disk('public')->assertMissing('custom-uploads/expiredAAA/logo.webp');
    Storage::disk('public')->assertExists('custom-uploads/lockedBBBB/logo.webp');
    Storage::disk('public')->assertExists('custom-uploads/activeCCCC/logo.webp');
});

test('only temp exports older than an hour are removed', function () {
    $directory = storage_path('app/temp-exports');
    File::ensureDirectoryExists($directory);
    File::put("{$directory}/old-test.zip", 'x');
    File::put("{$directory}/new-test.zip", 'x');
    touch("{$directory}/old-test.zip", now()->subHours(2)->getTimestamp());

    $this->artisan('previews:cleanup')->assertSuccessful();

    expect(File::exists("{$directory}/old-test.zip"))->toBeFalse()
        ->and(File::exists("{$directory}/new-test.zip"))->toBeTrue();

    File::delete("{$directory}/new-test.zip");
});

test('the cleanup is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'previews:cleanup'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 0 * * *');
});
