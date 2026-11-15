{{-- Base product card. Themes override this file; all share $product->cardData(). --}}
@php($c = $product->cardData())
@php($p = $c['price'])
<article class="sf-card {{ $cardClass ?? '' }} {{ $c['in_stock'] ? '' : 'is-oos' }}" wire:key="card-{{ $product->id }}-{{ $loop->index ?? 0 }}"
         x-data="{ img: @js($c['image']), alt: @js($c['image_alt']), sw: null }">
    <a href="{{ $c['url'] }}" :href="sw ? '{{ $c['url'] }}?colour=' + sw : '{{ $c['url'] }}'" wire:navigate class="sf-card-media {{ $c['image_alt'] ? 'has-alt' : '' }}">
        <img src="{{ $c['image'] }}" :src="img" alt="{{ $product->name }}" class="sf-card-img" loading="lazy">
        @if ($c['image_alt'])
            <img src="{{ $c['image_alt'] }}" :src="alt || img" alt="" class="sf-card-img-alt" loading="lazy" aria-hidden="true">
        @endif
    </a>
    <div class="sf-badges">
        @if (! $c['in_stock'])
            <span class="sf-badge sf-badge--oos">Sold out</span>
        @elseif ($p['percent'] > 0)
            <span class="sf-badge">-{{ $p['percent'] }}%</span>
        @elseif ($c['is_new'])
            <span class="sf-badge sf-badge--new">New</span>
        @endif
    </div>
    <button type="button" class="sf-wish {{ in_array($product->id, $wishlist ?? []) ? 'is-on' : '' }}" wire:click="toggleWishlist({{ $product->id }})" aria-label="Save to wishlist">
        <i class="{{ in_array($product->id, $wishlist ?? []) ? 'ri-heart-3-fill' : 'ri-heart-3-line' }}"></i>
    </button>
    <div class="sf-card-body">
        <span class="sf-card-cat">{{ $c['category'] }}</span>
        <a href="{{ $c['url'] }}" wire:navigate class="sf-card-title">{{ $product->name }}</a>
        @if (count($c['swatches']) > 1)
            <div class="sf-dots">
                @foreach (array_slice($c['swatches'], 0, 5) as $s)
                    <button type="button" class="sf-dot" :class="{ 'is-active': sw === {{ $s['id'] }} }" style="background: {{ $s['hex'] }}" title="{{ $s['name'] }}" aria-label="{{ $s['name'] }}"
                            @mouseenter="img = @js($s['image']); alt = @js($s['image_alt']); sw = {{ $s['id'] }}" @click="img = @js($s['image']); alt = @js($s['image_alt']); sw = {{ $s['id'] }}"></button>
                @endforeach
                @if (count($c['swatches']) > 5)<span class="sf-dots-more">+{{ count($c['swatches']) - 5 }}</span>@endif
            </div>
        @endif
        <div class="sf-card-foot">
            @include('themes.base.partials.price', ['p' => $p])
            @if ($c['single_variant_id'] && $c['in_stock'])
                <button type="button" class="sf-add" wire:click="addToCart({{ $c['single_variant_id'] }})" wire:loading.attr="disabled" wire:target="addToCart({{ $c['single_variant_id'] }})" aria-label="Add to cart"><i class="ri-add-line"></i></button>
            @else
                <a href="{{ $c['url'] }}" wire:navigate class="sf-add" aria-label="Choose options"><i class="ri-arrow-right-up-line"></i></a>
            @endif
        </div>
    </div>
</article>
