<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_order_amount', 'max_discount_amount',
        'usage_limit', 'usage_limit_per_customer', 'used_count', 'applies_to',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $c) => $c->code = strtoupper(trim($c->code)));
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public static function findByCode(?string $code): ?self
    {
        return filled($code) ? static::where('code', strtoupper(trim($code)))->first() : null;
    }

    /**
     * Returns null when the coupon can be applied, otherwise a human message.
     */
    public function validationError(float $subtotal, ?Customer $customer = null, string $channel = 'online'): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not active yet.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->applies_to !== 'all' && $this->applies_to !== $channel) {
            return $channel === 'online' ? 'This coupon is only valid in store.' : 'This coupon is only valid online.';
        }
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }
        if ($this->min_order_amount > 0 && $subtotal < (float) $this->min_order_amount) {
            return 'Add '.money((float) $this->min_order_amount - $subtotal).' more to use this coupon.';
        }
        if ($customer && $this->usage_limit_per_customer
            && $this->usages()->where('customer_id', $customer->id)->count() >= $this->usage_limit_per_customer) {
            return 'You have already used this coupon.';
        }

        return null;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? round($subtotal * (float) $this->value / 100, 2)
            : (float) $this->value;

        if ($this->max_discount_amount) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return min($discount, $subtotal);
    }

    public function label(): string
    {
        return $this->type === 'percent' ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off' : money((float) $this->value).' off';
    }
}
