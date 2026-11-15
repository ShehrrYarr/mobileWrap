<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductColorImage extends Model
{
    protected $fillable = ['product_color_id', 'path', 'alt', 'sort_order'];

    protected static function booted(): void
    {
        static::deleted(function (ProductColorImage $image) {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('uploads')->delete($image->path);
            }
        });
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function getUrlAttribute(): string
    {
        return upload_url($this->path);
    }
}
