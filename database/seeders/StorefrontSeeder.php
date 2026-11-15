<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use Illuminate\Database\Seeder;

/** Seeds every theme's default homepage sections from config/themes.php. */
class StorefrontSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('themes') as $key => $theme) {
            static::seedTheme($key);
        }
    }

    public static function seedTheme(string $key): void
    {
        HomeSection::where('theme', $key)->delete();

        foreach (config("themes.{$key}.sections", []) as $i => $section) {
            HomeSection::create([
                'theme' => $key,
                'type' => $section['type'],
                'title' => $section['title'] ?? null,
                'subtitle' => $section['subtitle'] ?? null,
                'settings' => $section['settings'] ?? [],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
