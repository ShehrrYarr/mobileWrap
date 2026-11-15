@php($banners = $section->setting('banners', []))
@if ($banners)
    <section class="sf-section-sm">
        <div class="container">
            <div class="row g-4">
                @foreach ($banners as $i => $b)
                    @php($img = ! empty($b['image']) ? upload_url($b['image']) : $data['showcase']->get($i)?->imageUrl())
                    <div class="col-md-6">
                        <a href="{{ $b['button_url'] ?? route('shop') }}" wire:navigate class="st-promo {{ $i % 2 ? 'alt' : '' }}">
                            @if ($img)<img src="{{ $img }}" alt="" loading="lazy">@endif
                            <div class="st-promo-text">
                                <span class="st-index">N° 0{{ $i + 2 }}</span>
                                <h3 class="mt-2">{{ $b['title'] ?? '' }}</h3>
                                <p class="sf-muted mb-3">{{ $b['subtitle'] ?? '' }}</p>
                                @if (! empty($b['button_text']))<span class="sf-link-arrow">{{ $b['button_text'] }} <i class="ri-arrow-right-line"></i></span>@endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
