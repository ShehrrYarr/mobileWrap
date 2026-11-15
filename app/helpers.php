<?php

use App\Support\Settings;
use App\Support\Theme;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('money')) {
    /** Format an amount as PKR, e.g. "Rs. 1,250". */
    function money($amount, bool $withSymbol = true): string
    {
        $amount = (float) $amount;
        $decimals = abs($amount - round($amount)) >= 0.005 ? 2 : 0;
        $formatted = number_format($amount, $decimals);

        return $withSymbol ? setting('store.currency_symbol', 'Rs.').' '.$formatted : $formatted;
    }
}

if (! function_exists('upload_url')) {
    function upload_url(?string $path): string
    {
        if (blank($path)) {
            return asset('assets/store/img/placeholder.svg');
        }

        return str_starts_with($path, 'http') ? $path : asset('uploads/'.ltrim($path, '/'));
    }
}

if (! function_exists('store_url')) {
    /**
     * A link typed in the admin (popup button, hero slide, promo banner) as a URL that
     * works wherever the store is hosted. "/shop?category=chargers" becomes
     * http://host/mobileWrap/shop?category=chargers on the live server; full URLs,
     * #anchors, mailto: and tel: links are left as they are.
     */
    function store_url(?string $link, ?string $fallback = null): string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return $fallback ?? route('shop');
        }
        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return $link;
        }

        $base = \App\Support\Livewire\SubdirectoryHandleRequests::basePath();
        if ($base !== '' && ($link === $base || str_starts_with($link, $base.'/'))) {
            $link = substr($link, strlen($base)) ?: '/'; // already includes the sub-folder
        }

        return url($link);
    }
}

if (! function_exists('theme')) {
    function theme(): Theme
    {
        return app(Theme::class);
    }
}

if (! function_exists('theme_view')) {
    function theme_view(string $name): string
    {
        return app(Theme::class)->view($name);
    }
}

if (! function_exists('store_nav')) {
    function store_nav(): App\Support\StoreNav
    {
        return app(App\Support\StoreNav::class);
    }
}

if (! function_exists('store_name')) {
    function store_name(): string
    {
        return (string) setting('store.name', config('app.name'));
    }
}

if (! function_exists('qty')) {
    /** Trim trailing zeros from decimal quantities: 2.000 → 2, 12.500 → 12.5 */
    function qty($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    }
}
