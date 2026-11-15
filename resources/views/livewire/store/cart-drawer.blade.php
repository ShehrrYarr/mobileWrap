<?php

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        // re-render with fresh totals
    }

    public function increment(string $key): void
    {
        $this->change($key, 1);
    }

    public function decrement(string $key): void
    {
        $this->change($key, -1);
    }

    protected function change(string $key, int $delta): void
    {
        $cart = app(CartService::class);
        $line = $cart->lines()->firstWhere('key', $key);
        if (! $line) {
            return;
        }

        if ($line['type'] === 'bundle') {
            $cart->updateBundle($line['id'], $line['qty'] + $delta);
        } elseif ($error = $cart->update($line['id'], $line['qty'] + $delta)) {
            $this->dispatch('store-toast', type: 'error', message: $error);
        }
        $this->dispatch('cart-updated', count: $cart->count());
    }

    public function remove(string $key): void
    {
        $cart = app(CartService::class);
        $line = $cart->lines()->firstWhere('key', $key);
        if ($line) {
            $line['type'] === 'bundle' ? $cart->updateBundle($line['id'], 0) : $cart->remove($line['id']);
        }
        $this->dispatch('cart-updated', count: $cart->count());
    }

    public function with(): array
    {
        $cart = app(CartService::class);

        return ['lines' => $cart->lines(), 'totals' => $cart->totals(auth('customer')->user())];
    }
}; ?>

<div x-data="{ open: false }" x-on:open-cart.window="open = true" x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div>
            <div class="sf-drawer-backdrop" x-show="open" x-transition.opacity @click="open = false" x-cloak></div>
            <aside class="sf-drawer" x-show="open" x-cloak
                   x-transition:enter="transition-transform" x-transition:enter-start="translate-x-100" x-transition:leave-end="translate-x-100"
                   :style="open ? 'transform: translateX(0); transition: transform .35s cubic-bezier(.2,.8,.2,1)' : 'transform: translateX(100%); transition: transform .3s ease'"
                   role="dialog" aria-label="Shopping cart">
                <div class="sf-drawer-head">
                    <h5>Your cart <span class="sf-muted fw-normal">({{ $totals['count'] }})</span></h5>
                    <button type="button" class="sf-icon-btn" @click="open = false" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>

                @if ($lines->isEmpty())
                    <div class="sf-drawer-body d-flex flex-column align-items-center justify-content-center text-center">
                        <i class="ri-shopping-bag-3-line" style="font-size: 54px; color: var(--sf-muted)"></i>
                        <h5 class="mt-3 mb-1">Your cart is empty</h5>
                        <p class="sf-muted">Find the perfect fit for your phone.</p>
                        <a href="{{ route('shop') }}" wire:navigate class="btn btn-primary" @click="open = false">Start shopping</a>
                    </div>
                @else
                    @if ($totals['free_shipping_enabled'])
                        <div class="px-4 pt-3">
                            @if ($totals['free_shipping_remaining'] > 0)
                                <div style="font-size: 14px" class="mb-2">Add <strong>{{ money($totals['free_shipping_remaining']) }}</strong> more for <strong>free delivery</strong></div>
                            @else
                                <div style="font-size: 14px" class="mb-2"><i class="ri-truck-line"></i> You’ve unlocked <strong>free delivery</strong> 🎉</div>
                            @endif
                            <div class="sf-progress"><span style="width: {{ $totals['free_shipping_progress'] }}%"></span></div>
                        </div>
                    @endif
                    <div class="sf-drawer-body">
                        @foreach ($lines as $line)
                            <div class="sf-line" wire:key="dl-{{ $line['key'] }}">
                                <a href="{{ $line['url'] }}" wire:navigate class="sf-line-img" @click="open = false"><img src="{{ $line['image'] }}" alt=""></a>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <a href="{{ $line['url'] }}" wire:navigate class="sf-line-title" @click="open = false">{{ $line['name'] }}</a>
                                        <span class="fw-bold text-nowrap">{{ money($line['total']) }}</span>
                                    </div>
                                    @if ($line['label'])<div class="sf-line-meta">{{ $line['label'] }}</div>@endif
                                    @if ($line['compare'])<div class="sf-line-meta"><span class="text-decoration-line-through">{{ money($line['compare']) }}</span> <span class="sf-off">{{ money($line['unit']) }} each</span></div>@endif
                                    <div class="d-flex align-items-center justify-content-between mt-2">
                                        <div class="sf-qty sf-qty-sm">
                                            <button type="button" wire:click="decrement('{{ $line['key'] }}')" aria-label="Decrease"><i class="ri-subtract-line"></i></button>
                                            <input type="text" value="{{ $line['qty'] }}" readonly aria-label="Quantity">
                                            <button type="button" wire:click="increment('{{ $line['key'] }}')" @disabled($line['qty'] >= $line['max']) aria-label="Increase"><i class="ri-add-line"></i></button>
                                        </div>
                                        <button type="button" class="sf-remove" wire:click="remove('{{ $line['key'] }}')">Remove</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="sf-drawer-foot">
                        <div class="sf-summary-row"><span>Subtotal</span><span class="fw-semibold">{{ money($totals['subtotal']) }}</span></div>
                        @if ($totals['savings'] > 0)<div class="sf-summary-row"><span>You save</span><span class="sf-off">{{ money($totals['savings']) }}</span></div>@endif
                        <div class="sf-summary-row"><span>Delivery</span><span>{{ $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' }}</span></div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('checkout') }}" wire:navigate class="btn btn-primary btn-lg" @click="open = false">Checkout · {{ money($totals['total']) }}</a>
                            <a href="{{ route('cart') }}" wire:navigate class="btn btn-outline-ink" @click="open = false">View cart</a>
                        </div>
                        <p class="sf-muted text-center mt-3 mb-0" style="font-size: 12px"><i class="ri-lock-line"></i> Cash on Delivery & bank transfer available</p>
                    </div>
                @endif
            </aside>
        </div>
    </template>
</div>
