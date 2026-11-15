@php
    $size = $job['size'] ?? '38x25';
    [$w, $h] = array_map('floatval', explode('x', $size));
    $variants = \App\Models\ProductVariant::with('product')->whereIn('id', array_keys($job['items'] ?? []))->get()->keyBy('id');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Labels — {{ store_name() }}</title>
    <style>
        @page { size: {{ $w }}mm {{ $h }}mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #e9e9e9; }
        .label { width: {{ $w }}mm; height: {{ $h }}mm; padding: 1.2mm 1.6mm; background: #fff; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; page-break-after: always; break-after: page; margin: 4mm auto; }
        .store { font-size: {{ $h >= 30 ? 7 : 6 }}pt; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .name { font-size: {{ $h >= 30 ? 8 : 7 }}pt; font-weight: 700; line-height: 1.1; max-height: 2.2em; overflow: hidden; }
        .variant { font-size: 6pt; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bc { display: flex; justify-content: center; }
        .bc svg { width: 100%; height: {{ $h >= 30 ? 10 : 8 }}mm; }
        .foot { display: flex; justify-content: space-between; align-items: baseline; font-size: 6pt; }
        .price { font-size: {{ $h >= 30 ? 10 : 9 }}pt; font-weight: 800; }
        .bar { text-align: center; margin: 6px; font-family: system-ui, sans-serif; }
        @media print { body { background: #fff; } .label { margin: 0; } .bar { display: none; } }
    </style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></div>
@forelse ($job['items'] ?? [] as $variantId => $copies)
    @php($v = $variants[$variantId] ?? null)
    @continue(! $v)
    @php($code = $v->barcode ?: $v->sku)
    @for ($i = 0; $i < (int) $copies; $i++)
        <div class="label">
            @if ($job['store'] ?? true)<div class="store">{{ store_name() }}</div>@endif
            <div>
                <div class="name">{{ $v->product?->name }}</div>
                @if ($v->label && $v->label !== 'Standard')<div class="variant">{{ $v->label }}</div>@endif
            </div>
            <div class="bc">{!! \App\Support\Barcode::svg($code, $w * 3.4, $h * 1.2) !!}</div>
            <div class="foot"><span>{{ $code }}</span>@if ($job['price'] ?? true)<span class="price">{{ money($v->retail_price) }}</span>@endif</div>
        </div>
    @endfor
@empty
    <p class="bar">Nothing to print. Go back and add items.</p>
@endforelse
@if (request()->boolean('autoprint', true))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
