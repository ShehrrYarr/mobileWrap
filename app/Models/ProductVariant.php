<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'product_color_id', 'sku', 'barcode', 'label',
        'cost_price', 'retail_price', 'wholesale_price', 'compare_at_price',
        'low_stock_threshold', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'low_stock_threshold' => 'decimal:3',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'product_color_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class)->with('attribute');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function quantity(): float
    {
        return (float) ($this->stock?->quantity ?? 0);
    }

    public function availableQuantity(): float
    {
        return max(0, (float) ($this->stock?->quantity ?? 0) - (float) ($this->stock?->reserved_quantity ?? 0));
    }

    public function lowStockThreshold(): float
    {
        return (float) ($this->low_stock_threshold ?? $this->product?->low_stock_threshold ?? 0);
    }

    public function isLowStock(): bool
    {
        return $this->quantity() <= $this->lowStockThreshold();
    }

    /** Price for a customer tier before deals. */
    public function basePrice(string $tier = 'retail'): float
    {
        return (float) ($tier === 'wholesale' && $this->wholesale_price > 0 ? $this->wholesale_price : $this->retail_price);
    }

    /** "Black / 20000mAh" built from colour + attribute values. */
    public function buildLabel(): string
    {
        $parts = [];
        if ($this->color && filled($this->color->hex_code)) {
            $parts[] = $this->color->name;
        }
        foreach ($this->attributeValues as $value) {
            $parts[] = $value->value;
        }

        return $parts ? implode(' / ', $parts) : 'Standard';
    }

    /** Next internal EAN-13 barcode in the "20…" in-store range. */
    public static function generateBarcode(array $reserved = []): string
    {
        $max = (int) static::withTrashed()->where('barcode', 'like', '20%')->whereRaw('LENGTH(barcode) = 13')->max('barcode');
        $seq = $max ? (int) substr((string) $max, 2, 10) + 1 : 100000;

        do {
            $digits = '20'.str_pad((string) $seq++, 10, '0', STR_PAD_LEFT);
            $sum = 0;
            foreach (str_split($digits) as $i => $d) {
                $sum += (int) $d * ($i % 2 ? 3 : 1);
            }
            $code = $digits.((10 - $sum % 10) % 10);
        } while (in_array($code, $reserved, true));

        return $code;
    }

    public function displayName(): string
    {
        $label = $this->label && $this->label !== 'Standard' ? ' — '.$this->label : '';

        return ($this->product?->name ?? 'Item').$label;
    }
}
