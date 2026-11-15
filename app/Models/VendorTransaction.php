<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VendorTransaction extends Model
{
    protected $fillable = [
        'vendor_id', 'type', 'debit', 'credit', 'balance_after', 'reference_type', 'reference_id',
        'financial_transaction_id', 'date', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'balance_after' => 'decimal:2', 'date' => 'date'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
