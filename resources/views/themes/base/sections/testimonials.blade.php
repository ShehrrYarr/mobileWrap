@php($items = $section->setting('items', []))
@if ($items)
    <section class="sf-section">
        <div class="container">
            @include('themes.base.partials.section-head')
            <div class="row g-4">
                @foreach ($items as $item)
                    <div class="col-md-6 col-lg-4">
                        <figure class="sf-card-panel h-100 mb-0 d-flex flex-column">
                            <div class="sf-stars mb-3">{{ str_repeat('★', (int) ($item['rating'] ?? 5)) }}<span class="sf-muted">{{ str_repeat('★', 5 - (int) ($item['rating'] ?? 5)) }}</span></div>
                            <blockquote class="mb-4" style="font-size: 17px; line-height: 1.6">“{{ $item['text'] ?? '' }}”</blockquote>
                            <figcaption class="mt-auto d-flex align-items-center gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px; border-radius: 50%; background: var(--sf-primary); color: var(--sf-primary-contrast)">{{ mb_substr($item['name'] ?? '?', 0, 1) }}</span>
                                <span><b class="d-block">{{ $item['name'] ?? '' }}</b><span class="sf-muted" style="font-size: 13px">{{ $item['city'] ?? '' }} · Verified buyer</span></span>
                            </figcaption>
                        </figure>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
