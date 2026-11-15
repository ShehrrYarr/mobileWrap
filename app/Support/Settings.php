<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Cached key/value settings (Admin → Settings). */
class Settings
{
    public const CACHE_KEY = 'app.settings';

    protected ?array $items = null;

    public function all(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        try {
            return $this->items = Cache::rememberForever(self::CACHE_KEY, function () {
                return Schema::hasTable('settings') ? Setting::query()->pluck('value', 'key')->all() : [];
            });
        } catch (Throwable) {
            return $this->items = [];
        }
    }

    public function get(string $key, $default = null)
    {
        $all = $this->all();

        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : $default;
    }

    public function set(string|array $key, $value = null): void
    {
        $pairs = is_array($key) ? $key : [$key => $value];

        foreach ($pairs as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->items = null;
    }
}
