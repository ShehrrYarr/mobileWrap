<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Cash In Hand account. */
class CashDrawer extends Model
{
    protected $fillable = ['name', 'opening_balance', 'current_balance', 'is_active'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(FinancialTransaction::class, 'account')->latest('id');
    }

    public static function main(): self
    {
        return static::query()->where('is_active', true)->orderBy('id')->first()
            ?? static::create(['name' => 'Main Counter']);
    }

    public function displayName(): string
    {
        return $this->name.' (Cash)';
    }
}
