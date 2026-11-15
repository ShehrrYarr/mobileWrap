<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndStaffSeeder::class,
            SettingsSeeder::class,
            FinanceSeeder::class,
            CatalogSeeder::class,
            MarketingSeeder::class,
            StorefrontSeeder::class,
            CustomerSeeder::class,
            DemoOrderSeeder::class,
        ]);
    }
}
