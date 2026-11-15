<?php

namespace App\Services;

use App\Models\LaserCutterJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Laser cutter queue (tracker only). Material (cm²) is deducted from the
 * roll when the sale completes; re-cuts after a failure deduct again as wastage.
 */
class LaserService
{
    public function __construct(protected InventoryService $inventory) {}

    public static function nextNumber(): string
    {
        $last = LaserCutterJob::pluck('number')->map(fn ($n) => (int) substr($n, 3))->max();

        return 'LJ-'.max(1001, (int) $last + 1);
    }

    /** Called by the POS for every laser line on a completed sale. */
    public function createFromSale(Order $order, OrderItem $item, array $line): LaserCutterJob
    {
        $material = ProductVariant::with('product')->findOrFail($line['material_variant_id']);
        if (! $material->product?->isRawMaterial()) {
            throw ValidationException::withMessages(['cart' => 'Choose a laser raw material for the cut.']);
        }

        $wastage = (float) $material->product->wastage_percent;
        $area = LaserCutterJob::calculateArea((float) $line['width'], (float) $line['height'], (int) $line['qty'], $wastage);

        $job = LaserCutterJob::create([
            'number' => self::nextNumber(),
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $order->customer_id,
            'device_model_id' => $line['device_model_id'],
            'material_variant_id' => $material->id,
            'side' => $line['side'],
            'cut_width_mm' => $line['width'],
            'cut_height_mm' => $line['height'],
            'quantity' => (int) $line['qty'],
            'area_cm2' => $area['piece'],
            'wastage_percent' => $wastage,
            'total_area_cm2' => $area['total'],
            'service_fee' => round((float) $line['fee'] * (int) $line['qty'], 2),
            'status' => 'queued',
            'notes' => $line['notes'] ?? null,
        ]);

        $this->inventory->adjust($material, -$area['total'], 'laser_usage', $job, "Laser cut {$job->number} ({$order->number})");
        $job->update(['material_deducted_at' => now()]);

        return $job;
    }

    public function start(LaserCutterJob $job): void
    {
        $this->guard($job, ['queued']);
        $job->update(['status' => 'cutting', 'started_at' => now(), 'operator_id' => auth('web')->id()]);
    }

    public function complete(LaserCutterJob $job): void
    {
        $this->guard($job, ['queued', 'cutting']);
        $job->update([
            'status' => 'completed',
            'started_at' => $job->started_at ?? now(),
            'completed_at' => now(),
            'operator_id' => $job->operator_id ?? auth('web')->id(),
        ]);
    }

    public function fail(LaserCutterJob $job, ?string $reason = null): void
    {
        $this->guard($job, ['queued', 'cutting']);
        $job->update([
            'status' => 'failed',
            'failure_reason' => $reason ?: 'Cut failed',
            'completed_at' => now(),
            'operator_id' => $job->operator_id ?? auth('web')->id(),
        ]);
    }

    /** Queue a fresh attempt for a failed job; its material is logged as wastage. */
    public function recut(LaserCutterJob $failed): LaserCutterJob
    {
        $this->guard($failed, ['failed']);
        if ($existing = $failed->recuts()->first()) {
            throw ValidationException::withMessages(['job' => "{$failed->number} was already re-cut as {$existing->number}."]);
        }

        return DB::transaction(function () use ($failed) {
            $job = $failed->replicate(['number', 'status', 'started_at', 'completed_at', 'failure_reason', 'operator_id', 'material_deducted_at']);
            $job->fill([
                'number' => self::nextNumber(),
                'parent_id' => $failed->id,
                'attempt' => $failed->attempt + 1,
                'status' => 'queued',
                'priority' => 1,
                'service_fee' => 0,
                'notes' => trim('Re-cut of '.$failed->number.'. '.($failed->notes ?? '')),
            ])->save();

            $material = ProductVariant::findOrFail($failed->material_variant_id);
            $this->inventory->adjust($material, -(float) $job->total_area_cm2, 'wastage', $job, "Re-cut {$job->number} after failed {$failed->number}");
            $job->update(['material_deducted_at' => now()]);

            return $job;
        });
    }

    /** Cancel a job that hasn't been cut; optionally return its material to stock. */
    public function cancel(LaserCutterJob $job, bool $restock = true): void
    {
        $this->guard($job, ['queued']);

        DB::transaction(function () use ($job, $restock) {
            if ($restock && $job->material_deducted_at) {
                $material = ProductVariant::findOrFail($job->material_variant_id);
                $this->inventory->adjust($material, (float) $job->total_area_cm2, 'adjustment_in', $job, "Laser job {$job->number} cancelled — material returned");
            }
            $job->update(['status' => 'cancelled', 'completed_at' => now()]);
        });
    }

    protected function guard(LaserCutterJob $job, array $allowed): void
    {
        if (! in_array($job->status, $allowed, true)) {
            throw ValidationException::withMessages(['job' => "Job {$job->number} is already {$job->statusLabel()}."]);
        }
    }
}
