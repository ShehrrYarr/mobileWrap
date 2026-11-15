@if ($data['brands']->isNotEmpty())
    <section class="sf-section-sm">
        <div class="container">
            @include('themes.base.partials.section-head')
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                @foreach ($data['brands'] as $brand)
                    <a href="{{ route('shop', ['brand' => $brand->slug]) }}" wire:navigate class="sf-surface d-flex align-items-center justify-content-center px-4" style="min-width: 150px; height: 74px; font-family: var(--sf-font-display); font-weight: 700; font-size: 19px; letter-spacing: -.01em">
                        @if ($brand->logo)<img src="{{ upload_url($brand->logo) }}" alt="{{ $brand->name }}" style="max-height: 36px">@else{{ $brand->name }}@endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
