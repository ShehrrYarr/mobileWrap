<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeviceModel extends Model
{
    use HasSlug;

    protected $fillable = [
        'device_brand_id', 'name', 'slug', 'release_year', 'image',
        'front_cut_width_mm', 'front_cut_height_mm', 'back_cut_width_mm', 'back_cut_height_mm',
        'notes', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'front_cut_width_mm' => 'decimal:2',
            'front_cut_height_mm' => 'decimal:2',
            'back_cut_width_mm' => 'decimal:2',
            'back_cut_height_mm' => 'decimal:2',
        ];
    }

    protected function slugSource(): string
    {
        return trim(($this->brand?->name ?? '').' '.$this->name);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(DeviceBrand::class, 'device_brand_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function getFullNameAttribute(): string
    {
        $brand = $this->brand?->name;

        return $brand && ! str_starts_with($this->name, $brand) ? $brand.' '.$this->name : $this->name;
    }

    /** Preset cut size in mm for a side: ['w' => .., 'h' => ..] */
    public function cutSize(string $side = 'front'): array
    {
        return $side === 'back'
            ? ['w' => (float) $this->back_cut_width_mm, 'h' => (float) $this->back_cut_height_mm]
            : ['w' => (float) $this->front_cut_width_mm, 'h' => (float) $this->front_cut_height_mm];
    }
}
