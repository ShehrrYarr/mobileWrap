<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'amount', 'bank_account_id', 'cash_drawer_id', 'reference',
        'receipt_path', 'status', 'verified_by', 'verified_at', 'financial_transaction_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function receiptUrl(): ?string
    {
        return $this->receipt_path ? upload_url($this->receipt_path) : null;
    }

    public function methodLabel(): string
    {
        return Order::PAYMENT_METHODS[$this->method] ?? ucfirst($this->method);
    }
}
