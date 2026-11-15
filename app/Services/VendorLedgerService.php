<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Vendor ledger. Credit = we owe the vendor more (purchase received);
 * debit = we owe less (payment, return). Balance > 0 means payable.
 */
class VendorLedgerService
{
    public function record(
        Vendor $vendor,
        string $type,
        float $debit,
        float $credit = 0,
        ?Model $reference = null,
        ?string $note = null,
        ?int $financialTransactionId = null,
        $date = null,
    ): VendorTransaction {
        return DB::transaction(function () use ($vendor, $type, $debit, $credit, $reference, $note, $financialTransactionId, $date) {
            $locked = Vendor::withTrashed()->lockForUpdate()->findOrFail($vendor->id);
            $locked->current_balance = round((float) $locked->current_balance + $credit - $debit, 2);
            $locked->save();
            $vendor->current_balance = $locked->current_balance;

            return VendorTransaction::create([
                'vendor_id' => $locked->id,
                'type' => $type,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $locked->current_balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'financial_transaction_id' => $financialTransactionId,
                'date' => $date ?? now()->toDateString(),
                'note' => $note,
                'user_id' => auth('web')->id(),
            ]);
        });
    }
}
