<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Dashboard' }} | {{ store_name() }} Admin</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/Favicon.png') }}">
    <script>window.DEFAULT_VALUES = window.DEFAULT_VALUES || {}; </script>
    @include('partials.head-css')
    <link rel="stylesheet" href="{{ asset('assets/admin/admin.css') }}?v={{ @filemtime(public_path('assets/admin/admin.css')) }}">
    @livewireStyles
    @stack('styles')
</head>

<body>
    @persist('admin-header')
        @include('admin.partials.header')
    @endpersist

    @persist('admin-sidebar')
        @include('admin.partials.sidebar')
    @endpersist

    <main class="app-wrapper">
        <div class="app-container">
            {{ $slot }}
        </div>
    </main>

    <footer class="footer d-flex align-items-center text-center">
        <div class="container-fluid">
            <p class="mb-0 fs-13">© {{ date('Y') }} {{ store_name() }} · Back office</p>
        </div>
    </footer>

    @include('admin.partials.toasts')

    <script src="{{ asset('assets/js/sidebar.js') }}?v={{ @filemtime(public_path('assets/js/sidebar.js')) }}" data-navigate-once></script>
    <script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/libs/sweetalert2/sweetalert2.min.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/admin/admin.js') }}?v={{ @filemtime(public_path('assets/admin/admin.js')) }}" data-navigate-once></script>
    @livewireScripts
    @stack('scripts')
</body>

</html>
