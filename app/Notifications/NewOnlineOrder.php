<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class NewOnlineOrder extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New online order '.$this->order->number,
            'message' => $this->order->customer_name.' · '.money($this->order->grand_total).' · '.$this->order->paymentMethodLabel(),
            'url' => route('admin.orders.show', $this->order),
            'icon' => 'ri-shopping-bag-3-line',
            'color' => 'primary',
        ];
    }
}
