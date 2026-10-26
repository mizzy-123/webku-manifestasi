<?php

use App\Models\Category;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests cannot manage categories', function () {
    auth()->logout();
    $category = Category::factory()->create();

    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
    $this->post(route('admin.categories.store'), ['name' => 'Kafe'])->assertRedirect(route('login'));
    $this->put(route('admin.categories.update', $category), ['name' => 'Baru'])->assertRedirect(route('login'));
    $this->delete(route('admin.categories.destroy', $category))->assertRedirect(route('login'));
});

test('admins can list categories', function () {
    Category::factory()->create(['name' => 'Kafe', 'slug' => 'kafe']);

    $this->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/categories/index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Kafe')
            ->where('categories.0.templates_count', 0));
});

test('admins can create a category and its slug is generated from the name', function () {
    $this->post(route('admin.categories.store'), ['name' => '  Kafe & Restoran  '])->assertRedirect();

    $category = Category::query()->firstOrFail();

    expect($category->name)->toBe('Kafe & Restoran')
        ->and($category->slug)->toBe('kafe-restoran');
});

test('duplicate category names are rejected', function () {
    Category::factory()->create(['name' => 'Kafe', 'slug' => 'kafe']);

    $this->post(route('admin.categories.store'), ['name' => 'Kafe'])->assertSessionHasErrors('slug');
    expect(Category::query()->count())->toBe(1);
});

test('admins can rename a category', function () {
    $category = Category::factory()->create(['name' => 'Kafe', 'slug' => 'kafe']);

    $this->put(route('admin.categories.update', $category), ['name' => 'Kedai Kopi'])->assertRedirect();

    expect($category->refresh()->name)->toBe('Kedai Kopi')
        ->and($category->slug)->toBe('kedai-kopi');
});

test('a category used by a template cannot be deleted', function () {
    $category = Category::factory()->create();
    Template::factory()->for($category)->create();

    $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

    expect($category->fresh())->not->toBeNull();
});

test('admins can delete an unused category', function () {
    $category = Category::factory()->create();

    $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

    expect(Category::query()->count())->toBe(0);
});
