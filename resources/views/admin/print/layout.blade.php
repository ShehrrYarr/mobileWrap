{{-- Shared shell for printable documents. $paper: 'a4' | '80mm' --}}
@php($is80 = ($paper ?? 'a4') === '80mm')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title') — {{ store_name() }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #111; background: #f2f2f2; }
        .sheet { background: #fff; margin: 16px auto; box-shadow: 0 4px 24px rgba(0,0,0,.12); }
        .r80 { width: 80mm; padding: 4mm 4mm 6mm; font-size: 12px; }
        .a4 { width: 210mm; min-height: 297mm; padding: 14mm 16mm; font-size: 12.5px; }
        h1 { margin: 0; font-size: 18px; }
        .a4 h1 { font-size: 22px; }
        h2 { margin: 0 0 2px; font-size: 18px; letter-spacing: .02em; }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #555; }
        .small { font-size: 11px; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .rule { border-top: 1px dashed #999; margin: 6px 0; }
        .a4 .rule { border-top: 1px solid #ddd; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #555; }
        .a4 th, .a4 td { padding: 6px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .a4 thead th { background: #f6f6f6; border-bottom: 1px solid #ccc; }
        .a4 tfoot td { border-top: 2px solid #111; border-bottom: 0; font-weight: 700; }
        .r80 td { padding: 3px 0; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .big { font-size: 20px; font-weight: 800; }
        .box { border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .grid .box b { display: block; font-size: 15px; margin-top: 2px; }
        .tag { display: inline-block; padding: 2px 8px; border: 1px solid #111; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .sign { margin-top: 48px; display: flex; justify-content: space-between; gap: 40px; }
        .sign div { flex: 1; border-top: 1px solid #999; padding-top: 4px; text-align: center; color: #555; font-size: 11px; }
        .actions { text-align: center; margin: 12px; }
        .actions button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; }
            .actions { display: none; }
            @page { size: {{ $is80 ? '80mm auto' : 'A4' }}; margin: {{ $is80 ? '0' : '10mm' }}; }
            .a4 { padding: 0; min-height: 0; width: auto; }
        }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></div>

<div class="sheet {{ $is80 ? 'r80' : 'a4' }}">
    @unless ($is80)
        <div class="row" style="align-items: flex-start">
            <div>
                @if (setting('store.logo'))<img src="{{ upload_url(setting('store.logo')) }}" alt="" style="max-height: 44px; max-width: 180px; margin-bottom: 6px"><br>@endif
                <h1>{{ store_name() }}</h1>
                <div class="small muted">{{ setting('store.address') }}, {{ setting('store.city') }} · {{ setting('store.phone') }}</div>
            </div>
            <div class="right">@yield('heading')</div>
        </div>
        <div class="rule"></div>
    @endunless

    @yield('body')
</div>

@if ($autoprint ?? true)
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
