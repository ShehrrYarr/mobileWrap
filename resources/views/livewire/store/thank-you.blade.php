<?php

use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Order confirmed')] class extends Component
{
    public Order $order;

    public function mount(string $number): void
    {
        $order = Order::online()->where('number', $number)->firstOrFail();
        $mine = session('last_order') === $number || (auth('customer')->id() && auth('customer')->id() === $order->customer_id);
        abort_unless($mine, 404);
        $this->order = $order;
    }

    public function with(): array
    {
        $this->order->load('items', 'payments.bankAccount');

        return ['whatsapp' => store_nav()->whatsappUrl('Hi! I just placed order '.$this->order->number.' on '.store_name().'.')];
    }
}; ?>

<section class="sf-section">
    <div class="container" style="max-width: 860px">
        <div class="text-center mb-5">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 84px; height: 84px; border-radius: 50%; background: var(--sf-primary); color: var(--sf-primary-contrast); font-size: 42px"><i class="ri-check-line"></i></div>
            <h1 class="sf-title">Thank you, {{ Str::before($order->customer_name, ' ') }}!</h1>
            <p class="sf-sub mx-auto">Your order <strong class="text-body">{{ $order->number }}</strong> has been placed. We’ll call or WhatsApp you on {{ $order->customer_phone }} to confirm.</p>
        </div>

        <div class="sf-card-panel mb-4">
            <div class="sf-timeline mb-2">
                @foreach (\App\Models\Order::TRACKING_STEPS as $i => $step)
                    <div class="sf-tl-step {{ $i <= $order->trackingIndex() ? 'is-done' : '' }}"><div class="sf-tl-dot">@if ($i <= $order->trackingIndex())<i class="ri-check-line"></i>@endif</div>{{ \App\Models\Order::STATUSES[$step][0] }}</div>
                @endforeach
            </div>
        </div>

        @php($pay = $order->payments->first())
        @if ($pay?->method === 'bank_transfer')
            <div class="alert alert-info d-flex gap-2"><i class="ri-time-line fs-5"></i><div>We’ve received your transfer receipt for <strong>{{ money($pay->amount) }}</strong> to {{ $pay->bankAccount?->bank_name }}. We’ll verify it shortly and start packing.</div></div>
        @else
            <div class="alert alert-light d-flex gap-2" style="border: 1px solid var(--sf-border)"><i class="ri-hand-coin-line fs-5"></i><div>Please keep <strong>{{ money($order->grand_total) }}</strong> ready in cash for the rider.</div></div>
        @endif

        <div class="row g-4">
            <div class="col-md-7">
                <div class="sf-card-panel h-100">
                    <h5 class="mb-2">Items</h5>
                    @foreach ($order->items as $item)
                        <div class="sf-line py-3">
                            <div class="sf-line-img" style="width: 60px; height: 60px"><img src="{{ $item->imageUrl() }}" alt=""></div>
                            <div class="flex-grow-1"><div class="sf-line-title">{{ $item->name }}</div><div class="sf-line-meta">{{ $item->variant_label }} × {{ $item->qty() }}</div></div>
                            <div class="fw-semibold">{{ money($item->total) }}</div>
                        </div>
                    @endforeach
                    <div class="sf-summary-row mt-2"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
                    @if ($order->coupon_discount > 0)<div class="sf-summary-row"><span>Coupon {{ $order->coupon_code }}</span><span class="sf-off">−{{ money($order->coupon_discount) }}</span></div>@endif
                    <div class="sf-summary-row"><span>Delivery</span><span>{{ $order->shipping_fee > 0 ? money($order->shipping_fee) : 'Free' }}</span></div>
                    <div class="sf-summary-total"><span>Total</span><span>{{ money($order->grand_total) }}</span></div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="sf-card-panel h-100">
                    <h5 class="mb-3">Delivering to</h5>
                    <p class="mb-1 fw-semibold">{{ $order->customer_name }}</p>
                    <p class="sf-muted mb-1">{{ $order->shipping_address }}, {{ $order->shipping_city }}</p>
                    <p class="sf-muted mb-4">{{ $order->customer_phone }}</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('track', ['order' => $order->number, 'phone' => $order->customer_phone]) }}" wire:navigate class="btn btn-ink">Track this order</a>
                        @if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-outline-ink"><i class="ri-whatsapp-line me-1"></i>Message us</a>@endif
                        <a href="{{ route('shop') }}" wire:navigate class="btn btn-link sf-muted">Continue shopping</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
