{{-- A4 report shell for dompdf (tables + simple CSS only: no flexbox/grid). --}}
@php
    $logo = setting('store.logo');
    $logoPath = $logo && preg_match('/\.(png|jpe?g|gif)$/i', $logo) && is_file(public_path('uploads/'.$logo)) ? public_path('uploads/'.$logo) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ store_name() }}</title>
    <style>
        @page { margin: 26px 30px 46px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9px; color: #1f2937; line-height: 1.35; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 10.5px; margin: 16px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #111827; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .muted { color: #6b7280; }
        .r { text-align: right; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .nowrap { white-space: nowrap; }
        .data th { background: #f3f4f6; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; color: #4b5563; text-align: left; padding: 5px 6px; border-bottom: 1px solid #d1d5db; }
        .data th.r { text-align: right; }
        .data td { padding: 4px 6px; border-bottom: 1px solid #edf0f3; vertical-align: top; }
        .data tr.total td { border-top: 1.5px solid #111827; border-bottom: 0; font-weight: bold; background: #fff; }
        .data tr.sub td { font-weight: bold; background: #f9fafb; }
        .data tr.grand td { border-top: 1.5px solid #111827; font-weight: bold; font-size: 10.5px; background: #f3f4f6; }
        .cards td.card { border: 1px solid #e5e7eb; padding: 7px 8px; width: 25%; vertical-align: top; }
        .cards .k { font-size: 7px; text-transform: uppercase; color: #6b7280; letter-spacing: .4px; }
        .cards .v { font-size: 12.5px; font-weight: bold; margin-top: 2px; }
        .cards .s { font-size: 7.5px; color: #6b7280; margin-top: 1px; }
        .pos { color: #067647; }
        .neg { color: #b42318; }
        .split td.col { width: 50%; vertical-align: top; }
        .split td.col-l { padding-right: 8px; }
        .split td.col-r { padding-left: 8px; }
        .note { margin-top: 12px; font-size: 7.5px; color: #6b7280; }
        .rule { border-top: 2px solid #111827; margin: 8px 0 2px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 58%">
                @if ($logoPath)<img src="{{ $logoPath }}" alt="" style="max-height: 32px; max-width: 150px; margin-bottom: 4px"><br>@endif
                <h1>{{ store_name() }}</h1>
                <div class="muted">{{ trim(setting('store.address').', '.setting('store.city'), ', ') }}@if (setting('store.phone')) · {{ setting('store.phone') }}@endif</div>
            </td>
            <td class="r">
                <div style="font-size: 14px; font-weight: bold; letter-spacing: .5px">{{ strtoupper($title) }}</div>
                <div class="b" style="font-size: 10px">{{ $range->label() }}</div>
                @foreach ($filters as $k => $v)<div class="muted">{{ $k }}: {{ $v }}</div>@endforeach
                <div class="muted">Generated {{ now()->format('d M Y, h:i a') }} · {{ $generatedBy }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    @yield('content')
</body>
</html>
