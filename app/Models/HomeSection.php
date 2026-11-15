<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    public const TYPES = [
        'hero' => ['label' => 'Hero banner', 'icon' => 'ri-image-2-line'],
        'features' => ['label' => 'Service highlights', 'icon' => 'ri-shield-check-line'],
        'categories' => ['label' => 'Featured categories', 'icon' => 'ri-layout-grid-line'],
        'product_rail' => ['label' => 'Product row', 'icon' => 'ri-shopping-bag-3-line'],
        'flash_sale' => ['label' => 'Flash sale', 'icon' => 'ri-flashlight-line'],
        'shop_by_device' => ['label' => 'Shop by device', 'icon' => 'ri-smartphone-line'],
        'promo_banner' => ['label' => 'Promo banners', 'icon' => 'ri-megaphone-line'],
        'brands' => ['label' => 'Brand strip', 'icon' => 'ri-award-line'],
        'bundles' => ['label' => 'Bundles & combos', 'icon' => 'ri-gift-line'],
        'testimonials' => ['label' => 'Testimonials', 'icon' => 'ri-chat-quote-line'],
        'newsletter' => ['label' => 'Newsletter signup', 'icon' => 'ri-mail-send-line'],
    ];

    protected $fillable = ['theme', 'type', 'title', 'subtitle', 'settings', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    public function scopeForTheme(Builder $q, string $theme): Builder
    {
        return $q->where('theme', $theme)->orderBy('sort_order');
    }

    public function setting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
