<?php

use App\Models\Order;
use Livewire\Volt\Component;

new class extends Component
{
    public Order $order;

    public function mount(string $number): void
    {
        $this->order = Order::where('number', $number)->where('customer_id', auth('customer')->id())->firstOrFail();
    }

    public function rendering($view): void
    {
        $view->title('Order '.$this->order->number);
    }

    public function with(): array
    {
        $this->order->load('items', 'payments.bankAccount', 'statusHistories');

        return [];
    }
}; ?>

<section class="sf-section">
    <div class="container">
        <nav class="sf-breadcrumb pt-0"><a href="{{ route('account') }}" wire:navigate>My account</a><i class="ri-arrow-right-s-line"></i><span>{{ $order->number }}</span></nav>
        <div class="row g-4">
            <div class="col-lg-3">@include('themes.base.partials.account-nav')</div>
            <div class="col-lg-9">
                <div class="sf-card-panel mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div><h3 class="mb-0">{{ $order->number }}</h3><span class="sf-muted">{{ $order->placed_at?->format('d M Y, h:i a') }} · {{ $order->paymentMethodLabel() }}</span></div>
                        <div class="text-end"><span class="sf-badge">{{ $order->statusLabel() }}</span><div class="sf-muted mt-1" style="font-size: 13px">{{ $order->paymentStatusLabel() }}</div></div>
                    </div>
                    @if ($order->channel === 'online' && $order->status !== 'cancelled')
                        <div class="sf-timeline mt-4">
                            @foreach (\App\Models\Order::TRACKING_STEPS as $i => $step)
                                <div class="sf-tl-step {{ $i <= $order->trackingIndex() ? 'is-done' : '' }}"><div class="sf-tl-dot">@if ($i <= $order->trackingIndex())<i class="ri-check-line"></i>@endif</div>{{ \App\Models\Order::STATUSES[$step][0] }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="sf-card-panel h-100">
                            <h5>Items</h5>
                            @foreach ($order->items as $item)
                                <div class="sf-line py-3">
                                    <div class="sf-line-img" style="width: 60px; height: 60px"><img src="{{ $item->imageUrl() }}" alt=""></div>
                                    <div class="flex-grow-1"><div class="sf-line-title">{{ $item->name }}</div><div class="sf-line-meta">{{ $item->variant_label }} × {{ $item->qty() }}</div></div>
                                    <b>{{ money($item->total) }}</b>
                                </div>
                            @endforeach
                            <div class="sf-summary-row mt-2"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
                            @if ($order->coupon_discount > 0)<div class="sf-summary-row"><span>Coupon</span><span class="sf-off">−{{ money($order->coupon_discount) }}</span></div>@endif
                            <div class="sf-summary-row"><span>Delivery</span><span>{{ $order->shipping_fee > 0 ? money($order->shipping_fee) : 'Free' }}</span></div>
                            <div class="sf-summary-total"><span>Total</span><span>{{ money($order->grand_total) }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="sf-card-panel h-100">
                            <h5>Delivery</h5>
                            <p class="mb-1 fw-semibold">{{ $order->customer_name }}</p>
                            <p class="sf-muted">{{ $order->shipping_address }}, {{ $order->shipping_city }}<br>{{ $order->customer_phone }}</p>
                            <h6 class="mt-4">Updates</h6>
                            <ul class="list-unstyled mb-0" style="font-size: 14px">
                                @foreach ($order->statusHistories as $h)
                                    <li class="py-1"><b>{{ \App\Models\Order::STATUSES[$h->status][0] ?? ucfirst($h->status) }}</b> <span class="sf-muted">· {{ $h->created_at->format('d M, h:i a') }}</span>@if ($h->note)<div class="sf-muted">{{ $h->note }}</div>@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
