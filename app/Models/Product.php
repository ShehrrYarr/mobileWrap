<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasSlug, SoftDeletes;

    public const MATERIAL_KINDS = [
        'hydrogel' => 'Hydrogel film',
        'privacy_glass' => 'Privacy glass',
        'uv_glass' => 'UV tempered glass',
        'matte_wrap' => 'Matte wrap',
        'carbon_wrap' => 'Carbon wrap',
        'skin_wrap' => 'Skin wrap',
    ];

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'type', 'material_kind', 'wastage_percent', 'unit',
        'short_description', 'description', 'sku_prefix', 'is_active', 'show_online', 'is_featured',
        'track_stock', 'low_stock_threshold', 'meta_title', 'meta_description', 'views',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_online' => 'boolean',
            'is_featured' => 'boolean',
            'track_stock' => 'boolean',
            'wastage_percent' => 'decimal:2',
            'low_stock_threshold' => 'decimal:3',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function deviceModels(): BelongsToMany
    {
        return $this->belongsToMany(DeviceModel::class);
    }

    public function dealItems(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Products visible on the storefront. */
    public function scopeOnline(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('show_online', true)->where('type', 'standard');
    }

    public function scopeRawMaterials(Builder $q): Builder
    {
        return $q->where('type', 'raw_material');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        return $q->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%")
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%")->orWhere('barcode', $term))
                ->orWhereHas('deviceModels', fn ($d) => $d->where('device_models.name', 'like', "%{$term}%"));
        });
    }

    /** Eager loads needed to render a product card. */
    public function scopeWithCardData(Builder $q): Builder
    {
        return $q->with([
            'category:id,name,slug,parent_id',
            'brand:id,name,slug',
            'colors' => fn ($c) => $c->select('id', 'product_id', 'name', 'hex_code', 'is_default', 'sort_order'),
            'colors.images' => fn ($i) => $i->select('id', 'product_color_id', 'path', 'sort_order'),
            'activeVariants' => fn ($v) => $v->select('id', 'product_id', 'product_color_id', 'retail_price', 'wholesale_price', 'compare_at_price', 'is_default', 'is_active'),
            'activeVariants.stock:id,product_variant_id,quantity,reserved_quantity',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isRawMaterial(): bool
    {
        return $this->type === 'raw_material';
    }

    public function defaultVariant(): ?ProductVariant
    {
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->get();

        return $variants->firstWhere('is_default', true) ?? $variants->first();
    }

    public function defaultColor(): ?ProductColor
    {
        $colors = $this->relationLoaded('colors') ? $this->colors : $this->colors()->get();

        return $colors->firstWhere('is_default', true) ?? $colors->first();
    }

    /** Main image URL (default colour's first image) or a placeholder. */
    public function imageUrl(int $index = 0): string
    {
        $color = $this->defaultColor();
        $img = $color?->images->get($index) ?? $color?->images->first();

        return $img ? upload_url($img->path) : asset('assets/store/img/placeholder.svg');
    }

    /** Swatches that should show on cards / product page (only real colours). */
    public function swatches()
    {
        return $this->colors->filter(fn ($c) => filled($c->hex_code))->values();
    }

    public function totalStock(): float
    {
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->with('stock')->get();

        return (float) $variants->sum(fn ($v) => $v->availableQuantity());
    }

    public function inStock(): bool
    {
        return ! $this->track_stock || $this->totalStock() > 0;
    }

    /** ['min' => , 'max' => , 'compare' => , 'deal' => Deal|null] for retail customers. */
    public function priceSummary(): array
    {
        return app(PricingService::class)->productSummary($this);
    }

    protected ?array $cardCache = null;

    /** Everything a theme's product card needs, computed once. */
    public function cardData(): array
    {
        if ($this->cardCache !== null) {
            return $this->cardCache;
        }

        $placeholder = asset('assets/store/img/placeholder.svg');
        $colors = $this->relationLoaded('colors') ? $this->colors : $this->colors()->with('images')->get();
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->with('stock')->get();
        $default = $colors->firstWhere('is_default', true) ?? $colors->first();

        $swatches = $colors->filter(fn ($c) => filled($c->hex_code))->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'hex' => $c->hex_code,
            'image' => $c->images->first() ? upload_url($c->images->first()->path) : $placeholder,
            'image_alt' => $c->images->get(1) ? upload_url($c->images->get(1)->path) : null,
        ])->values()->all();

        $stock = $this->track_stock ? (float) $variants->sum(fn ($v) => $v->availableQuantity()) : 999;
        $price = $this->priceSummary();

        return $this->cardCache = [
            'url' => route('product', $this->slug),
            'image' => $default?->images->first() ? upload_url($default->images->first()->path) : $placeholder,
            'image_alt' => $default?->images->get(1) ? upload_url($default->images->get(1)->path) : null,
            'swatches' => $swatches,
            'price' => $price,
            'single_variant_id' => $variants->count() === 1 ? $variants->first()->id : null,
            'variant_count' => $variants->count(),
            'in_stock' => $stock > 0,
            'low_stock' => $stock > 0 && $stock <= 5,
            'is_new' => $this->created_at && $this->created_at->gt(now()->subDays(21)),
            'category' => $this->category?->name,
            'brand' => $this->brand?->name,
        ];
    }
}
