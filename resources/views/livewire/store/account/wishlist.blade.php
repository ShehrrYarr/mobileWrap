<?php

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Wishlist')] class extends Component
{
    use InteractsWithCart;

    #[On('wishlist-updated')]
    public function refreshList(): void {}

    public function with(): array
    {
        $ids = $this->wishlistIds();

        return [
            'products' => Product::online()->withCardData()->whereIn('id', $ids)->get(),
            'wishlist' => $ids,
        ];
    }
}; ?>

<section class="sf-section">
    <div class="container">
        <h1 class="sf-title mb-4">Wishlist</h1>
        <div class="row g-4">
            <div class="col-lg-3">@include('themes.base.partials.account-nav')</div>
            <div class="col-lg-9">
                @if ($products->isEmpty())
                    <div class="sf-empty sf-card-panel"><i class="ri-heart-3-line"></i><h4>Nothing saved yet</h4><p>Tap the heart on any product to save it here.</p><a href="{{ route('shop') }}" wire:navigate class="btn btn-primary">Browse products</a></div>
                @else
                    <div class="sf-grid sf-grid-3">
                        @foreach ($products as $product)
                            @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
