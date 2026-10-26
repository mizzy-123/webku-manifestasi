<?php

use App\Models\Category;
use App\Models\Template;

test('catalog only lists active templates', function () {
    $active = Template::factory()->create(['price' => 1500000]);
    Template::factory()->inactive()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/index')
            ->has('templates', 1)
            ->where('templates.0.id', $active->id)
            ->where('templates.0.thumbnail_url', '/storage/'.$active->thumbnail_path)
            ->where('templates.0.price', '1500000.00')
            ->where('filters', ['category' => '', 'type' => '', 'search' => '']));
});

test('catalog filters by category slug', function () {
    $landing = Category::factory()->create(['slug' => 'landing-page']);
    $profile = Category::factory()->create(['slug' => 'company-profile']);
    $expected = Template::factory()->for($landing)->create();
    Template::factory()->for($profile)->create();

    $this->get(route('home', ['category' => 'landing-page']))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->where('templates.0.id', $expected->id)
            ->where('filters.category', 'landing-page'));
});

test('catalog filters by template type and ignores invalid types', function () {
    Template::factory()->create();
    $multi = Template::factory()->multiPage()->create();

    $this->get(route('home', ['type' => 'multi-page']))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 1)
            ->where('templates.0.id', $multi->id)
            ->where('templates.0.pages_count', 2));

    $this->get(route('home', ['type' => 'bogus']))
        ->assertInertia(fn ($page) => $page->has('templates', 2)->where('filters.type', ''));
});

test('catalog searches name and description', function () {
    $byName = Template::factory()->create(['name' => 'Kopi Senja', 'description' => 'Cafe']);
    $byDescription = Template::factory()->create(['name' => 'Alpha', 'description' => 'Untuk restoran kopi']);
    Template::factory()->create(['name' => 'Beta', 'description' => 'Konstruksi']);

    $this->get(route('home', ['search' => 'kopi']))
        ->assertInertia(fn ($page) => $page
            ->has('templates', 2)
            ->where('templates.0.id', $byDescription->id)
            ->where('templates.1.id', $byName->id));

    $this->get(route('home', ['search' => '%']))
        ->assertInertia(fn ($page) => $page->has('templates', 0));
});

test('catalog categories only include those with active templates', function () {
    Template::factory()->create();
    Template::factory()->inactive()->create();
    Category::factory()->create();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->has('categories', 1));
});
