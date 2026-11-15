<?php

namespace App\Support;

use App\Models\Category;
use App\Models\DeviceBrand;
use App\Services\CartService;
use Illuminate\Support\Collection;

/** Navigation data shared by every theme's header/footer (memoised per request). */
class StoreNav
{
    protected ?Collection $categories = null;

    protected ?Collection $deviceBrands = null;

    public function categories(): Collection
    {
        return $this->categories ??= Category::active()->roots()->where('show_in_menu', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->where('show_in_menu', true)])
            ->orderBy('sort_order')->get();
    }

    public function deviceBrands(): Collection
    {
        return $this->deviceBrands ??= DeviceBrand::active()
            ->with(['models' => fn ($q) => $q->where('is_active', true)->limit(8)])
            ->get();
    }

    public function cartCount(): int
    {
        return app(CartService::class)->count();
    }

    public function customer()
    {
        return auth('customer')->user();
    }

    public function logoUrl(): ?string
    {
        $logo = setting('store.logo');

        return $logo ? upload_url($logo) : null;
    }

    /** Store name split for two-tone text logos: ['Mobile', 'Wrap'] */
    public function nameParts(): array
    {
        $parts = preg_split('/\s+/', trim(store_name()), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    public function whatsappUrl(?string $text = null): ?string
    {
        $number = setting('store.whatsapp');

        return $number ? 'https://wa.me/'.$number.($text ? '?text='.rawurlencode($text) : '') : null;
    }
}
