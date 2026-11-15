<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Single entry point for every stock change — keeps the stock ledger complete. */
class InventoryService
{
    public function adjust(
        ProductVariant $variant,
        float $quantity,
        string $type,
        ?Model $reference = null,
        ?string $note = null,
        ?float $unitCost = null,
    ): StockMovement {
        return DB::transaction(function () use ($variant, $quantity, $type, $reference, $note, $unitCost) {
            InventoryStock::firstOrCreate(['product_variant_id' => $variant->id]);
            $stock = InventoryStock::where('product_variant_id', $variant->id)->lockForUpdate()->first();

            $before = (float) $stock->quantity;
            $after = round($before + $quantity, 3);

            // Weighted average cost on incoming stock
            if ($quantity > 0 && $unitCost !== null && $unitCost > 0) {
                $stock->average_cost = $after > 0
                    ? round((max($before, 0) * (float) $stock->average_cost + $quantity * $unitCost) / max($after, $quantity), 4)
                    : $unitCost;
            }

            $stock->quantity = $after;
            $stock->save();

            $movement = StockMovement::create([
                'product_variant_id' => $variant->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost ?? (float) $variant->cost_price,
                'balance_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => auth('web')->id(),
            ]);

            $variant->setRelation('stock', $stock);
            $threshold = $variant->lowStockThreshold();
            if ($quantity < 0 && $before > $threshold && $after <= $threshold) {
                DB::afterCommit(fn () => $this->notifyLowStock($variant, $after));
            }

            return $movement;
        });
    }

    /** Set an absolute quantity (stock count) and log the difference. */
    public function setQuantity(ProductVariant $variant, float $newQuantity, ?string $note = null): ?StockMovement
    {
        $current = (float) ($variant->stock?->quantity ?? 0);
        $diff = round($newQuantity - $current, 3);

        if ($diff == 0.0) {
            return null;
        }

        return $this->adjust($variant, $diff, $diff > 0 ? 'adjustment_in' : 'adjustment_out', null, $note ?? 'Stock count');
    }

    protected function notifyLowStock(ProductVariant $variant, float $remaining): void
    {
        $staff = User::permission('inventory.manage')->get()
            ->merge(User::role('Admin')->get())->unique('id');

        if ($staff->isNotEmpty()) {
            Notification::send($staff, new LowStockAlert($variant->loadMissing('product'), $remaining));
        }
    }
}
