<?php

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    public function __construct(public ProductVariant $variant, public float $remaining) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $unit = $this->variant->product?->unit === 'cm2' ? ' cm²' : '';

        return [
            'title' => 'Low stock: '.$this->variant->displayName(),
            'message' => 'Only '.qty($this->remaining).$unit.' left (SKU '.$this->variant->sku.')',
            'url' => route('admin.inventory.index', ['filter' => 'low']),
            'icon' => 'ri-error-warning-line',
            'color' => 'warning',
        ];
    }
}
