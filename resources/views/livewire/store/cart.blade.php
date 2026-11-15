<?php

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Your cart')] class extends Component
{
    use InteractsWithCart;

    public string $coupon = '';

    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function setQty(string $key, int $qty): void
    {
        $cart = app(CartService::class);
        $line = $cart->lines()->firstWhere('key', $key);
        if (! $line) {
            return;
        }
        if ($line['type'] === 'bundle') {
            $cart->updateBundle($line['id'], $qty);
        } elseif ($error = $cart->update($line['id'], $qty)) {
            $this->dispatch('store-toast', type: 'error', message: $error);
        }
        $this->dispatch('cart-updated', count: $cart->count());
    }

    public function applyCoupon(): void
    {
        $this->validate(['coupon' => 'required|string|max:40'], [], ['coupon' => 'coupon code']);
        $cart = app(CartService::class);
        if ($error = $cart->applyCoupon($this->coupon, auth('customer')->user())) {
            $this->addError('coupon', $error);

            return;
        }
        $this->coupon = '';
        $this->dispatch('store-toast', type: 'success', message: 'Coupon applied!');
    }

    public function removeCoupon(): void
    {
        app(CartService::class)->removeCoupon();
    }

    public function with(): array
    {
        $cart = app(CartService::class);

        return [
            'lines' => $cart->lines(),
            'totals' => $cart->totals(auth('customer')->user()),
            'suggestions' => Product::online()->withCardData()->where('is_featured', true)->inRandomOrder()->limit(4)->get(),
            'wishlist' => $this->wishlistIds(),
        ];
    }
}; ?>

<div>
    <section class="sf-section-sm">
        <div class="container">
            <h1 class="sf-title mb-4">Your cart</h1>
            @if ($lines->isEmpty())
                <div class="sf-empty sf-card-panel">
                    <i class="ri-shopping-bag-3-line"></i>
                    <h4>Your cart is empty</h4>
                    <p>Looks like you haven’t added anything yet.</p>
                    <a href="{{ route('shop') }}" wire:navigate class="btn btn-primary">Continue shopping</a>
                </div>
            @else
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="sf-card-panel">
                            @if ($totals['free_shipping_enabled'])
                                <div class="mb-3">
                                    @if ($totals['free_shipping_remaining'] > 0)
                                        <div class="mb-2" style="font-size: 15px"><i class="ri-truck-line"></i> You’re <strong>{{ money($totals['free_shipping_remaining']) }}</strong> away from free delivery</div>
                                    @else
                                        <div class="mb-2" style="font-size: 15px"><i class="ri-checkbox-circle-fill text-success"></i> Free delivery unlocked</div>
                                    @endif
                                    <div class="sf-progress"><span style="width: {{ $totals['free_shipping_progress'] }}%"></span></div>
                                </div>
                            @endif
                            @foreach ($lines as $line)
                                <div class="sf-line align-items-center" wire:key="cl-{{ $line['key'] }}">
                                    <a href="{{ $line['url'] }}" wire:navigate class="sf-line-img" style="width: 96px; height: 96px"><img src="{{ $line['image'] }}" alt=""></a>
                                    <div class="flex-grow-1 min-w-0">
                                        <a href="{{ $line['url'] }}" wire:navigate class="sf-line-title d-block" style="font-size: 16px">{{ $line['name'] }}</a>
                                        @if ($line['label'])<div class="sf-line-meta">{{ $line['label'] }}</div>@endif
                                        <div class="sf-line-meta mt-1">{{ money($line['unit']) }} each @if ($line['compare'])<span class="text-decoration-line-through ms-1">{{ money($line['compare']) }}</span>@endif</div>
                                        <button type="button" class="sf-remove mt-2" wire:click="setQty('{{ $line['key'] }}', 0)">Remove</button>
                                    </div>
                                    <div class="sf-qty sf-qty-sm">
                                        <button type="button" wire:click="setQty('{{ $line['key'] }}', {{ $line['qty'] - 1 }})" aria-label="Decrease"><i class="ri-subtract-line"></i></button>
                                        <input type="text" value="{{ $line['qty'] }}" readonly aria-label="Quantity">
                                        <button type="button" wire:click="setQty('{{ $line['key'] }}', {{ $line['qty'] + 1 }})" @disabled($line['qty'] >= $line['max']) aria-label="Increase"><i class="ri-add-line"></i></button>
                                    </div>
                                    <div class="fw-bold text-end" style="min-width: 100px">{{ money($line['total']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="sf-card-panel" style="position: sticky; top: 110px">
                            <h5 class="mb-3">Order summary</h5>
                            <div class="sf-summary-row"><span>Subtotal ({{ $totals['count'] }} items)</span><span>{{ money($totals['subtotal']) }}</span></div>
                            @if ($totals['savings'] > 0)<div class="sf-summary-row"><span>Deal savings</span><span class="sf-off">−{{ money($totals['savings']) }}</span></div>@endif
                            @if ($totals['coupon_code'])
                                <div class="sf-summary-row">
                                    <span>Coupon <span class="sf-badge sf-badge--new ms-1">{{ $totals['coupon_code'] }}</span> <button class="sf-remove ms-1" wire:click="removeCoupon">remove</button></span>
                                    <span class="{{ $totals['coupon_error'] ? 'sf-muted' : 'sf-off' }}">{{ $totals['coupon_error'] ? '—' : '−'.money($totals['coupon_discount']) }}</span>
                                </div>
                                @if ($totals['coupon_error'])<div class="text-danger" style="font-size: 13px">{{ $totals['coupon_error'] }}</div>@endif
                            @endif
                            <div class="sf-summary-row"><span>Delivery</span><span>{{ $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' }}</span></div>
                            <div class="sf-summary-total"><span>Total</span><span>{{ money($totals['total']) }}</span></div>

                            <form wire:submit="applyCoupon" class="d-flex gap-2 mt-4">
                                <input type="text" class="form-control text-uppercase @error('coupon') is-invalid @enderror" placeholder="Coupon code" wire:model="coupon" aria-label="Coupon code">
                                <button class="btn btn-outline-ink" type="submit">Apply</button>
                            </form>
                            @error('coupon')<div class="text-danger mt-1" style="font-size: 13px">{{ $message }}</div>@enderror

                            <a href="{{ route('checkout') }}" wire:navigate class="btn btn-primary btn-lg w-100 mt-4">Proceed to checkout</a>
                            <a href="{{ route('shop') }}" wire:navigate class="btn btn-link w-100 sf-muted mt-1">Continue shopping</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if ($suggestions->isNotEmpty())
        <section class="sf-section pt-0">
            <div class="container">
                <div class="sf-section-head"><h2 class="sf-title" style="font-size: 28px">Complete the look</h2></div>
                <div class="sf-grid">
                    @foreach ($suggestions as $product)
                        @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
