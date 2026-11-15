<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const TYPES = [
        'opening' => ['Opening stock', 'secondary'],
        'purchase' => ['Purchase', 'success'],
        'purchase_return' => ['Purchase return', 'warning'],
        'sale' => ['POS sale', 'primary'],
        'sale_return' => ['Sale return', 'info'],
        'online_order' => ['Online order', 'primary'],
        'online_cancel' => ['Order cancelled', 'info'],
        'adjustment_in' => ['Adjustment (+)', 'success'],
        'adjustment_out' => ['Adjustment (−)', 'danger'],
        'laser_usage' => ['Laser cut', 'purple'],
        'wastage' => ['Wastage', 'danger'],
    ];

    protected $fillable = [
        'product_variant_id', 'type', 'quantity', 'unit_cost', 'balance_after',
        'reference_type', 'reference_id', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'balance_after' => 'decimal:3',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function typeColor(): string
    {
        return self::TYPES[$this->type][1] ?? 'secondary';
    }
}
