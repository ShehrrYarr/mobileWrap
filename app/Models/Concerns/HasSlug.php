<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a unique slug from the model's name on create (and when the name
 * changes while the slug is left blank).
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlug($model->slugSource(), $model->getKey());
            }
        });
    }

    protected function slugSource(): string
    {
        return (string) $this->name;
    }

    public static function uniqueSlug(string $value, $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(6));
        $slug = $base;
        $i = 2;

        while (static::query()
            ->when(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class)), fn ($q) => $q->withTrashed())
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
