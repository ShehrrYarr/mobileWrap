@php($items = $section->setting('items', []))
@if ($items)
    <section class="sf-section-sm">
        <div class="container">
            <div class="row g-3">
                @foreach ($items as $item)
                    <div class="col-sm-6 col-lg-3">
                        <div class="cl-feature h-100">
                            <i class="{{ $item['icon'] ?? 'ri-star-line' }}"></i>
                            <div><div class="fw-bold">{{ $item['title'] ?? '' }}</div><div class="sf-muted" style="font-size: 14px">{{ $item['text'] ?? '' }}</div></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
