<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('admin login redirects to the admin dashboard', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->post(route('login.store'), [
        'email' => 'admin@admin.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/admin/dashboard');
});

test('dashboard lives under the admin prefix', function () {
    expect(route('dashboard', absolute: false))->toBe('/admin/dashboard');
});

test('guests cannot open the admin dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('login'));
});

test('admin can open the dashboard with summary stats', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->has('stats')
            ->where('stats.templates', 0)
            ->has('recentOrders', 0));
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
});
