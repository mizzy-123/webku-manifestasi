<?php

use App\Models\CustomizedPreview;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access the preview list', function () {
    $this->get(route('admin.previews.index'))->assertRedirect(route('login'));
});

test('admins can list previews', function () {
    CustomizedPreview::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.previews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/previews/index')
            ->has('previews', 2));
});

test('admins can lock and unlock a preview', function () {
    $preview = CustomizedPreview::factory()->create();
    $admin = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.previews.lock', $preview))->assertRedirect();
    expect($preview->refresh()->is_locked_by_admin)->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.previews.lock', $preview))->assertRedirect();
    expect($preview->refresh()->is_locked_by_admin)->toBeFalse();
});

test('guests cannot lock previews', function () {
    $preview = CustomizedPreview::factory()->create();

    $this->patch(route('admin.previews.lock', $preview))->assertRedirect(route('login'));
    expect($preview->refresh()->is_locked_by_admin)->toBeFalse();
});
