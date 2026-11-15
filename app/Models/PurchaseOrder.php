<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number', 'bill_number', 'vendor_id', 'status', 'order_date', 'received_date', 'due_date', 'subtotal',
        'discount', 'shipping', 'total', 'received_total', 'paid_amount', 'returned_total', 'notes', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'received_date' => 'date',
            'due_date' => 'date',
            'returned_total' => 'decimal:2',
            'received_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'ordered' => ['Ordered', 'info'],
        'partial' => ['Partly received', 'warning'],
        'received' => ['Received', 'success'],
        'cancelled' => ['Cancelled', 'danger'],
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    /** Bill total ÷ item subtotal — spreads the bill discount / other charges over received units. */
    public function payableFactor(): float
    {
        return (float) $this->subtotal > 0 ? (float) $this->total / (float) $this->subtotal : 1.0;
    }

    /**
     * What we still owe on this purchase: value of goods received (the payable booked)
     * less returns and payments. Negative = advance paid before the goods arrived.
     */
    public function balanceDue(): float
    {
        return round((float) $this->received_total - (float) $this->returned_total - (float) $this->paid_amount, 2);
    }

    /** Payments are allowed once ordered: an advance before delivery, or against the received value. */
    public function canPay(): bool
    {
        return $this->status === 'ordered'
            ? (float) $this->paid_amount < (float) $this->total
            : in_array($this->status, ['partial', 'received'], true) && $this->balanceDue() > 0;
    }

    public function suggestedPayment(): float
    {
        return $this->status === 'ordered'
            ? max(0, round((float) $this->total - (float) $this->paid_amount, 2))
            : max(0, $this->balanceDue());
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->balanceDue() > 0 && $this->status !== 'cancelled';
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
