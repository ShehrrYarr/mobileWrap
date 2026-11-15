@php($regular = $deal->bundleRegularTotal())
@php($save = max(0, $regular - (float) $deal->bundle_price))
<div class="col-md-6 col-xl-4" wire:key="bundle-{{ $deal->id }}">
    <div class="sf-card-panel h-100 d-flex flex-column">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="sf-badge sf-badge--new"><i class="ri-gift-line"></i> Bundle</span>
            @if ($save > 0)<span class="sf-off">Save {{ money($save) }}</span>@endif
        </div>
        <div class="d-flex align-items-center justify-content-center gap-1 mb-3" style="min-height: 120px">
            @foreach ($deal->items as $item)
                @if (! $loop->first)<i class="ri-add-line sf-muted"></i>@endif
                <div style="width: 30%; aspect-ratio: 1; background: var(--sf-media-bg); border-radius: var(--sf-radius-sm); padding: 6px">
                    <img src="{{ $item->product?->imageUrl() }}" alt="{{ $item->product?->name }}" style="width: 100%; height: 100%; object-fit: contain" loading="lazy">
                </div>
            @endforeach
        </div>
        <h4 class="mb-1" style="font-size: 20px">{{ $deal->title }}</h4>
        <p class="sf-muted mb-3" style="font-size: 14px">{{ $deal->description }}</p>
        <ul class="list-unstyled mb-3" style="font-size: 14px">
            @foreach ($deal->items as $item)
                <li class="d-flex gap-2"><i class="ri-check-line text-primary-sf"></i>{{ $item->quantity > 1 ? $item->quantity.' × ' : '' }}{{ $item->product?->name }}</li>
            @endforeach
        </ul>
        <div class="d-flex align-items-center justify-content-between mt-auto">
            <div class="sf-price"><span class="sf-price-now">{{ money($deal->bundle_price) }}</span>@if ($save > 0)<span class="sf-price-was">{{ money($regular) }}</span>@endif</div>
            <button type="button" class="btn btn-primary" wire:click="addBundle({{ $deal->id }})" wire:loading.attr="disabled" wire:target="addBundle({{ $deal->id }})"><i class="ri-shopping-bag-3-line me-1"></i>Add bundle</button>
        </div>
    </div>
</div>
