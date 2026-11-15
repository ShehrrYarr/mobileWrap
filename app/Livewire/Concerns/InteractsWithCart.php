<?php

namespace App\Livewire\Concerns;

use App\Models\Product;
use App\Services\CartService;

/** Cart + wishlist actions shared by every storefront page that shows products. */
trait InteractsWithCart
{
    public function addToCart(int $variantId, int $qty = 1, bool $openDrawer = true): bool
    {
        $cart = app(CartService::class);

        if ($error = $cart->add($variantId, max(1, $qty))) {
            $this->dispatch('store-toast', type: 'error', message: $error);

            return false;
        }

        $this->dispatch('cart-updated', count: $cart->count());
        if ($openDrawer) {
            $this->dispatch('open-cart');
        }

        return true;
    }

    public function addBundle(int $dealId): void
    {
        $cart = app(CartService::class);

        if ($error = $cart->addBundle($dealId)) {
            $this->dispatch('store-toast', type: 'error', message: $error);

            return;
        }

        $this->dispatch('cart-updated', count: $cart->count());
        $this->dispatch('open-cart');
    }

    public function toggleWishlist(int $productId)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            session()->flash('store_toast', ['type' => 'info', 'message' => 'Sign in to save items to your wishlist.']);
            session()->put('url.intended', url()->previous());

            return $this->redirectRoute('login', navigate: true);
        }

        $product = Product::online()->findOrFail($productId);
        $changes = $customer->wishlist()->toggle($product->id);
        $added = ! empty($changes['attached']);

        $this->dispatch('store-toast', type: 'success', message: $added ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
        $this->dispatch('wishlist-updated');
    }

    /** IDs of products in the signed-in customer's wishlist. */
    public function wishlistIds(): array
    {
        return auth('customer')->check() ? auth('customer')->user()->wishlist()->pluck('products.id')->all() : [];
    }
}
