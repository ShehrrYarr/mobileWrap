<?php

use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('My account')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        $me = auth('customer')->user();

        return [
            'me' => $me,
            'orders' => $me->orders()->withCount('items')->with('items')->paginate(8),
            'stats' => [
                'orders' => $me->orders()->count(),
                'open' => $me->orders()->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])->count(),
                'spent' => (float) $me->orders()->whereNotIn('status', ['cancelled'])->sum('grand_total'),
            ],
        ];
    }
}; ?>

<section class="sf-section">
    <div class="container">
        <h1 class="sf-title mb-4">Hi, {{ Str::before($me->name, ' ') }}</h1>
        <div class="row g-4">
            <div class="col-lg-3">@include('themes.base.partials.account-nav')</div>
            <div class="col-lg-9">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3"><div class="sf-card-panel h-100"><div class="sf-muted" style="font-size: 13px">Orders</div><div class="fs-3 fw-bold">{{ $stats['orders'] }}</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="sf-card-panel h-100"><div class="sf-muted" style="font-size: 13px">In progress</div><div class="fs-3 fw-bold">{{ $stats['open'] }}</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="sf-card-panel h-100"><div class="sf-muted" style="font-size: 13px">Total spent</div><div class="fs-3 fw-bold">{{ money($stats['spent']) }}</div></div></div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="sf-card-panel h-100" title="Your store credit (khata) balance">
                            <div class="sf-muted" style="font-size: 13px">Account balance</div>
                            <div class="fs-3 fw-bold {{ $me->current_balance > 0 ? 'text-danger' : '' }}">{{ money($me->current_balance) }}</div>
                            <div class="sf-muted" style="font-size: 12px">{{ $me->current_balance > 0 ? 'Due to store' : 'Nothing due' }}</div>
                        </div>
                    </div>
                </div>

                <div class="sf-card-panel">
                    <h5 class="mb-3">Order history</h5>
                    @forelse ($orders as $order)
                        <a href="{{ route('account.order', $order->number) }}" wire:navigate class="d-flex flex-wrap align-items-center gap-3 py-3" style="border-bottom: 1px solid var(--sf-border)" wire:key="ao-{{ $order->id }}">
                            <div class="d-flex" style="margin-right: 4px">
                                @foreach ($order->items->take(3) as $item)
                                    <span class="sf-line-img" style="width: 48px; height: 48px; margin-right: -12px; border: 2px solid var(--sf-surface)"><img src="{{ $item->imageUrl() }}" alt=""></span>
                                @endforeach
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <b>{{ $order->number }}</b> <span class="sf-muted" style="font-size: 13px">· {{ $order->placed_at?->format('d M Y') }} · {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }} · {{ $order->channel === 'pos' ? 'In store' : 'Online' }}</span>
                                <div><span class="sf-badge {{ in_array($order->status, ['delivered', 'completed']) ? 'sf-badge--new' : ($order->status === 'cancelled' ? 'sf-badge--oos' : '') }}" style="font-size: 11px">{{ $order->statusLabel() }}</span></div>
                            </div>
                            <b>{{ money($order->grand_total) }}</b>
                            <i class="ri-arrow-right-s-line sf-muted"></i>
                        </a>
                    @empty
                        <div class="sf-empty py-4"><i class="ri-shopping-bag-3-line"></i><h4>No orders yet</h4><a href="{{ route('shop') }}" wire:navigate class="btn btn-primary mt-2">Start shopping</a></div>
                    @endforelse
                    <div class="mt-3">{{ $orders->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
