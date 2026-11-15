@php($t = theme()->get())
<!DOCTYPE html>
<html lang="en" @if ($t['dark'] ?? false) data-bs-theme="dark" @endif>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) && $title ? $title.' — '.store_name() : (setting('seo.meta_title') ?: store_name()) }}</title>
    <meta name="description" content="{{ $metaDescription ?? setting('seo.meta_description', setting('store.tagline')) }}">
    <meta name="theme-color" content="{{ $t['preview']['bg'] ?? '#ffffff' }}">
    <meta property="og:site_name" content="{{ store_name() }}">
    <link rel="icon" href="{{ store_nav()->logoUrl() ?? asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $t['fonts'] }}">
    <link rel="stylesheet" href="{{ asset('assets/css/icons.min.css') }}">
    <link rel="stylesheet" href="{{ theme()->cssUrl() }}" data-navigate-track>
    @if ($overrides = theme()->colorOverrides())
        <style>{!! $overrides !!}</style>
    @endif
    @livewireStyles
</head>

<body class="sf sf-{{ $t['key'] }}">
    <script>
        window.__sf = { cartCount: {{ store_nav()->cartCount() }} };
        if (window.Alpine && Alpine.store('cart')) { Alpine.store('cart').count = window.__sf.cartCount; }
    </script>

    @include('themes.base.partials.preview-bar')
    @include(theme_view('partials.header'))

    <main id="sf-main">
        {{ $slot }}
    </main>

    @include(theme_view('partials.footer'))

    <livewire:store.cart-drawer />
    <livewire:store.popup :home="request()->routeIs('home')" />
    @include('themes.base.partials.mobile-menu')
    @include('themes.base.partials.toasts')

    @if ($wa = store_nav()->whatsappUrl('Hi '.store_name().', I have a question.'))
        <a href="{{ $wa }}" target="_blank" rel="noopener" class="sf-wa" aria-label="Chat on WhatsApp"><i class="ri-whatsapp-line"></i></a>
    @endif

    <script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/store/js/store.js') }}?v={{ @filemtime(public_path('assets/store/js/store.js')) }}" data-navigate-once></script>
    @livewireScripts
</body>

</html>
