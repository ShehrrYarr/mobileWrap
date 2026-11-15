@if ($data['bundles']->isNotEmpty())
    <section class="sf-section">
        <div class="container">
            @include('themes.base.partials.section-head', ['link' => route('deals'), 'linkText' => 'All deals'])
            <div class="row g-4">
                @foreach ($data['bundles'] as $deal)
                    @include('themes.base.partials.bundle-card', ['deal' => $deal])
                @endforeach
            </div>
        </div>
    </section>
@endif
