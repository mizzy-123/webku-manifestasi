<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedSettings();
        $this->seedCategories();
    }

    /**
     * Create the default admin account.
     */
    private function seedAdmin(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'admin@admin.com']);

        $admin->forceFill([
            'name' => 'Admin',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();
    }

    /**
     * Create the default application settings.
     */
    private function seedSettings(): void
    {
        $settings = [
            'whatsapp_number' => '6281234567890',
            'whatsapp_message_template' => 'Halo Admin, saya tertarik memesan website dengan template *{template_name}*. Berikut link desain yang sudah saya sesuaikan: {preview_url}',
            'wp_theme_author' => 'WebCatalog Studio',
            'wp_theme_author_uri' => 'https://example.com',
        ];

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }
    }

    /**
     * Create the default template categories.
     */
    private function seedCategories(): void
    {
        $categories = [
            'Landing Page',
            'Company Profile',
            'F&B & Restoran',
            'Konstruksi & Properti',
            'Jasa & Konsultan',
        ];

        foreach ($categories as $name) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
