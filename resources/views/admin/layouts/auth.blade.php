<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('assets/shared/ux.js') }}?v={{ @filemtime(public_path('assets/shared/ux.js')) }}"></script>
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Sign in' }} | {{ store_name() }} Admin</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/Favicon.png') }}">
    <script>
        window.DEFAULT_VALUES = window.DEFAULT_VALUES || {};
        window.DEFAULT_VALUES.AUTH_LAYOUT = true;
        var AUTH_LAYOUT = true;
    </script>
    @include('partials.head-css', ['auth' => 'layout-auth'])
    <link rel="stylesheet" href="{{ asset('assets/admin/admin.css') }}">
    @livewireStyles
</head>

<body>
    {{ $slot }}
    <script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}" data-navigate-once></script>
    @livewireScripts
</body>

</html>
