<?php

namespace Database\Factories;

use App\Models\CustomizedPreview;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomizedPreview>
 */
class CustomizedPreviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'unique_code' => Str::random(10),
            'custom_data' => [
                'colors' => ['--color-primary' => '#9275f7'],
                'global' => ['texts' => [], 'images' => [], 'links' => []],
                'pages' => [
                    'home' => ['texts' => [], 'images' => [], 'links' => []],
                ],
            ],
            'order_clicked_at' => null,
            'exported_at' => null,
            'is_locked_by_admin' => false,
            'last_accessed_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Indicate that the preview has passed its expiry date.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_accessed_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the preview is locked by an admin.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked_by_admin' => true,
        ]);
    }
}
