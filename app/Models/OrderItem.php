<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'item_type', 'name', 'sku', 'variant_label',
        'image', 'quantity', 'unit_price', 'unit_cost', 'discount', 'total', 'returned_quantity', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'returned_quantity' => 'decimal:3',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function imageUrl(): string
    {
        return $this->image ? upload_url($this->image) : asset('assets/store/img/placeholder.svg');
    }

    public function qty(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 3, '.', ''), '0'), '.');
    }
}
