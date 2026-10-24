<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $slug = Str::slug($name);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => $slug,
            'type' => Template::TYPE_SINGLE_PAGE,
            'description' => fake()->sentence(),
            'thumbnail_path' => "templates/{$slug}/thumbnail.jpg",
            'storage_path' => "templates/{$slug}",
            'manifest_data' => [
                'name' => Str::title($name),
                'type' => Template::TYPE_SINGLE_PAGE,
                'pages' => [
                    ['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html'],
                ],
                'colors' => [
                    ['variable' => '--color-primary', 'label' => 'Warna Utama', 'default' => '#9275f7'],
                    ['variable' => '--color-bg', 'label' => 'Warna Background', 'default' => '#ffffff'],
                ],
            ],
            'price' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the template has multiple pages.
     */
    public function multiPage(): static
    {
        return $this->state(function (array $attributes): array {
            $manifest = $attributes['manifest_data'];
            $manifest['type'] = Template::TYPE_MULTI_PAGE;
            $manifest['pages'] = [
                ['slug' => 'home', 'title' => 'Beranda', 'file' => 'index.html'],
                ['slug' => 'about', 'title' => 'Tentang Kami', 'file' => 'about.html'],
            ];

            return [
                'type' => Template::TYPE_MULTI_PAGE,
                'manifest_data' => $manifest,
            ];
        });
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
