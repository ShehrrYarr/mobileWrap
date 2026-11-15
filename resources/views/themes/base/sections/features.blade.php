@php($items = $section->setting('items', []))
@if ($items)
    <section class="sf-section-sm">
        <div class="container">
            <div class="row g-3">
                @foreach ($items as $item)
                    <div class="col-6 col-lg-3">
                        <div class="cl-feature h-100 sf-surface" style="display: flex; gap: 14px; align-items: center; padding: 16px">
                            <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px; border-radius: 12px; background: var(--sf-hover); color: var(--sf-primary-text, var(--sf-primary)); font-size: 22px"><i class="{{ $item['icon'] ?? 'ri-star-line' }}"></i></span>
                            <div>
                                <div class="fw-bold" style="font-size: 15px">{{ $item['title'] ?? '' }}</div>
                                <div class="sf-muted" style="font-size: 13px">{{ $item['text'] ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
