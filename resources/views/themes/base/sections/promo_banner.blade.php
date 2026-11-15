@php($banners = $section->setting('banners', []))
@if ($banners)
    <section class="sf-section-sm">
        <div class="container">
            <div class="row g-4">
                @foreach ($banners as $i => $b)
                    @php($img = ! empty($b['image']) ? upload_url($b['image']) : $data['showcase']->get($i)?->imageUrl())
                    <div class="col-md-6">
                        <a href="{{ $b['button_url'] ?? route('shop') }}" wire:navigate class="sf-promo {{ $i % 2 ? 'alt' : '' }} d-flex text-decoration-none" style="color: inherit">
                            @if ($img)<img src="{{ $img }}" alt="" loading="lazy">@endif
                            <h3>{{ $b['title'] ?? '' }}</h3>
                            <p class="sf-muted mb-3" style="max-width: 60%; position: relative">{{ $b['subtitle'] ?? '' }}</p>
                            @if (! empty($b['button_text']))<span class="btn btn-ink align-self-start position-relative">{{ $b['button_text'] }} <i class="ri-arrow-right-line ms-1"></i></span>@endif
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
