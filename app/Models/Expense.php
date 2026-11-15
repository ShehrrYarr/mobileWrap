<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Expense extends Model
{
    protected $fillable = [
        'expense_category_id', 'account_type', 'account_id', 'amount', 'date',
        'note', 'attachment', 'financial_transaction_id', 'user_id', 'edited_by', 'edited_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date', 'edited_at' => 'datetime'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** "cash:1" / "bank:2" — the key the account pickers use. */
    public function accountKey(): ?string
    {
        if (! $this->account_type) {
            return null;
        }

        return ($this->account_type === (new CashDrawer)->getMorphClass() ? 'cash:' : 'bank:').$this->account_id;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function account(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
