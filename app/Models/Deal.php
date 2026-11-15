<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Flash sale, bundle (combo) or category-wide discount. */
class Deal extends Model
{
    use HasSlug;

    public const TYPES = [
        'flash_sale' => 'Flash sale',
        'bundle' => 'Bundle / combo',
        'category_discount' => 'Category discount',
    ];

    protected $fillable = [
        'title', 'slug', 'type', 'discount_type', 'discount_value', 'bundle_price', 'category_id',
        'banner_image', 'description', 'starts_at', 'ends_at', 'show_on_home', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'bundle_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'show_on_home' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    public function items(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Running right now. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function isLive(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gte(now()));
    }

    public function apply(float $price): float
    {
        $discounted = $this->discount_type === 'percent'
            ? $price * (1 - (float) $this->discount_value / 100)
            : $price - (float) $this->discount_value;

        return max(0, round($discounted));
    }

    public function discountLabel(): string
    {
        return $this->discount_type === 'percent'
            ? '-'.rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'%'
            : '-'.money((float) $this->discount_value);
    }

    /** Sum of regular prices of all bundle items. */
    public function bundleRegularTotal(): float
    {
        return (float) $this->items->sum(function (DealItem $item) {
            $variant = $item->variant ?? $item->product?->defaultVariant();

            return ($variant ? (float) $variant->retail_price : 0) * $item->quantity;
        });
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_image ? upload_url($this->banner_image) : null;
    }
}
