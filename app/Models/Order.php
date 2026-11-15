<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** POS sale or online order. */
class Order extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'held' => ['Held', 'secondary'],
        'pending' => ['Pending', 'warning'],
        'confirmed' => ['Confirmed', 'info'],
        'processing' => ['Processing', 'primary'],
        'shipped' => ['Shipped', 'purple'],
        'delivered' => ['Delivered', 'success'],
        'completed' => ['Completed', 'success'],
        'cancelled' => ['Cancelled', 'danger'],
        'returned' => ['Returned', 'dark'],
        'partially_returned' => ['Partly returned', 'dark'],
    ];

    /** Customer-facing tracking steps for online orders. */
    public const TRACKING_STEPS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

    public const PAYMENT_STATUSES = [
        'unpaid' => ['Unpaid', 'danger'],
        'partial' => ['Partially paid', 'warning'],
        'paid' => ['Paid', 'success'],
        'refunded' => ['Refunded', 'secondary'],
    ];

    public const PAYMENT_METHODS = [
        'cod' => 'Cash on Delivery',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'customer_credit' => 'Customer Credit (Khata)',
        'exchange_credit' => 'Exchange credit',
        'split' => 'Split payment',
    ];

    protected $fillable = [
        'number', 'channel', 'customer_id', 'user_id', 'status', 'payment_status', 'payment_method',
        'price_tier', 'subtotal', 'discount_total', 'coupon_id', 'coupon_code', 'coupon_discount',
        'shipping_fee', 'service_fee_total', 'grand_total', 'paid_total', 'change_due', 'due_total',
        'customer_name', 'customer_phone', 'customer_email', 'shipping_address', 'shipping_city',
        'notes', 'staff_notes', 'placed_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'service_fee_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_due' => 'decimal:2',
            'due_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function laserJobs(): HasMany
    {
        return $this->hasMany(LaserCutterJob::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->where('channel', 'online');
    }

    public function scopePos(Builder $q): Builder
    {
        return $q->where('channel', 'pos');
    }

    /** Sequential numbers per prefix: MW-1001…, POS-1001…, HOLD-1001… */
    public static function generateNumber(string $channel = 'online', ?string $prefix = null): string
    {
        $prefix ??= $channel === 'pos' ? 'POS-' : setting('checkout.order_prefix', 'MW-');

        $last = static::withTrashed()->where('number', 'like', $prefix.'%')
            ->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))
            ->max();
        $next = max(1001, (int) $last + 1);

        while (static::withTrashed()->where('number', $prefix.$next)->exists()) {
            $next++;
        }

        return $prefix.$next;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status][0] ?? ucfirst($this->payment_status);
    }

    public function paymentStatusColor(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status][1] ?? 'secondary';
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'processing'], true);
    }

    /** Index of the current tracking step (-1 when cancelled / not trackable). */
    public function trackingIndex(): int
    {
        $idx = array_search($this->status, self::TRACKING_STEPS, true);

        return $idx === false ? ($this->status === 'completed' ? count(self::TRACKING_STEPS) - 1 : -1) : $idx;
    }
}
