<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BankAccount extends Model
{
    protected $fillable = [
        'bank_name', 'account_title', 'account_number', 'iban', 'branch',
        'opening_balance', 'current_balance', 'show_on_checkout', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'show_on_checkout' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(FinancialTransaction::class, 'account')->latest('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForCheckout(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('show_on_checkout', true);
    }

    public function displayName(): string
    {
        return $this->bank_name.' — '.$this->account_title.' (…'.substr($this->account_number, -4).')';
    }
}
