<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value store for Admin → Settings (store info, active theme, shipping, checkout…)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Storefront homepage builder: sections are stored per theme so every
        // theme can have its own layout, order and content.
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 40);
            $table->string('type', 40); // hero, features, categories, product_rail, flash_sale, shop_by_device, promo_banner, brands, bundles, testimonials, newsletter
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['theme', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('settings');
    }
};
