<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Job in the laser cutter queue (tracker only). */
class LaserCutterJob extends Model
{
    public const STATUSES = [
        'queued' => ['Queued', 'secondary'],
        'cutting' => ['Cutting', 'primary'],
        'completed' => ['Completed', 'success'],
        'failed' => ['Failed', 'danger'],
        'cancelled' => ['Cancelled', 'dark'],
    ];

    protected $fillable = [
        'number', 'order_id', 'order_item_id', 'parent_id', 'attempt', 'customer_id', 'device_model_id', 'material_variant_id',
        'side', 'cut_width_mm', 'cut_height_mm', 'quantity', 'area_cm2', 'wastage_percent', 'total_area_cm2',
        'service_fee', 'status', 'priority', 'operator_id', 'notes', 'failure_reason',
        'started_at', 'completed_at', 'material_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'cut_width_mm' => 'decimal:2',
            'cut_height_mm' => 'decimal:2',
            'area_cm2' => 'decimal:2',
            'wastage_percent' => 'decimal:2',
            'total_area_cm2' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'material_deducted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'material_variant_id')->withTrashed();
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LaserCutterJob::class, 'parent_id');
    }

    public function recuts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LaserCutterJob::class, 'parent_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function sideLabel(): string
    {
        return ['front' => 'Front', 'back' => 'Back', 'full' => 'Full body'][$this->side] ?? ucfirst($this->side);
    }

    /** Area in cm² for given mm dimensions × qty, plus wastage. */
    public static function calculateArea(float $widthMm, float $heightMm, int $qty = 1, float $wastagePercent = 0): array
    {
        $piece = round(($widthMm / 10) * ($heightMm / 10), 2);
        $total = round($piece * $qty * (1 + $wastagePercent / 100), 2);

        return ['piece' => $piece, 'total' => $total];
    }
}
