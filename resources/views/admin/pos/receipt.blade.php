@php
    $is80 = $format !== 'a4';
    $logo = setting('store.logo');
    $order->loadMissing('items', 'payments.bankAccount', 'customer', 'user', 'laserJobs');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number }} — {{ store_name() }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #111; background: #f2f2f2; }
        .sheet { background: #fff; margin: 16px auto; box-shadow: 0 4px 24px rgba(0,0,0,.12); }
        .r80 { width: 80mm; padding: 4mm 4mm 6mm; font-size: 12px; }
        .a4 { width: 210mm; min-height: 297mm; padding: 16mm; font-size: 13px; }
        h1 { margin: 0; font-size: 18px; }
        .a4 h1 { font-size: 26px; }
        .center { text-align: center; }
        .muted { color: #555; }
        .small { font-size: 11px; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .rule { border-top: 1px dashed #999; margin: 6px 0; }
        .a4 .rule { border-top: 1px solid #ddd; margin: 14px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #555; }
        .a4 th, .a4 td { padding: 8px 6px; border-bottom: 1px solid #eee; }
        .r80 td { padding: 3px 0; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .total { font-size: 16px; font-weight: 800; }
        .a4 .total { font-size: 20px; }
        .barcode svg { max-width: 100%; height: 42px; }
        .tag { display: inline-block; padding: 2px 8px; border: 1px solid #111; border-radius: 4px; font-weight: 700; font-size: 11px; }
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
    @if ($is80)
        <div class="center">
            @if ($logo)<img src="{{ upload_url($logo) }}" alt="" style="max-height: 40px; max-width: 60%"><br>@endif
            <h1>{{ store_name() }}</h1>
            <div class="small muted">{{ setting('store.address') }}, {{ setting('store.city') }}<br>{{ setting('store.phone') }}</div>
            @if (setting('receipt.header'))<div class="small" style="margin-top: 4px">{{ setting('receipt.header') }}</div>@endif
        </div>
        <div class="rule"></div>
        <div class="row small"><span>Invoice <b>{{ $order->number }}</b></span><span>{{ $order->placed_at?->format('d/m/Y h:i a') }}</span></div>
        <div class="row small"><span>Cashier: {{ $order->user?->name ?? '—' }}</span><span>{{ $order->channel === 'pos' ? 'In store' : 'Online' }}</span></div>
        <div class="small">Customer: {{ $order->customer_name }} @if ($order->customer_phone)· {{ $order->customer_phone }}@endif</div>
        <div class="rule"></div>
        <table>
            @foreach ($order->items as $item)
                <tr><td colspan="2"><b>{{ $item->name }}</b>@if ($item->variant_label)<div class="small muted">{{ $item->variant_label }}</div>@endif</td></tr>
                <tr>
                    <td class="small">{{ $item->qty() }} × {{ money($item->unit_price) }}@if ((float) $item->discount > 0) <span class="muted">(−{{ money($item->discount) }})</span>@endif</td>
                    <td class="num">{{ money($item->total) }}</td>
                </tr>
            @endforeach
        </table>
        <div class="rule"></div>
        <div class="row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
        @if ((float) $order->discount_total > 0)<div class="row"><span>Discount</span><span>−{{ money($order->discount_total) }}</span></div>@endif
        @if ((float) $order->coupon_discount > 0)<div class="row"><span>Coupon {{ $order->coupon_code }}</span><span>−{{ money($order->coupon_discount) }}</span></div>@endif
        @if ((float) $order->shipping_fee > 0)<div class="row"><span>Delivery</span><span>{{ money($order->shipping_fee) }}</span></div>@endif
        <div class="row total" style="margin-top: 4px"><span>TOTAL</span><span>{{ money($order->grand_total) }}</span></div>
        <div class="rule"></div>
        @foreach ($order->payments as $p)
            <div class="row small"><span>{{ $p->methodLabel() }}@if ($p->bankAccount) ({{ $p->bankAccount->bank_name }})@endif</span><span>{{ money($p->method === 'cash' && $p->tendered ? $p->tendered : $p->amount) }}</span></div>
        @endforeach
        @if ((float) $order->change_due > 0)<div class="row"><b>Change</b><b>{{ money($order->change_due) }}</b></div>@endif
        @if ((float) $order->due_total > 0)<div class="row"><b>On khata</b><b>{{ money($order->due_total) }}</b></div>@endif
        @if ($order->laserJobs->isNotEmpty())
            <div class="rule"></div>
            <div class="small"><b>Laser jobs:</b> {{ $order->laserJobs->pluck('number')->implode(', ') }}</div>
        @endif
        <div class="rule"></div>
        <div class="center barcode">{!! \App\Support\Barcode::svg($order->number, 200, 42) !!}<div class="small">{{ $order->number }}</div></div>
        <div class="center small" style="margin-top: 6px">{{ setting('receipt.footer', 'Thank you for shopping with us!') }}</div>
    @else
        <div class="row" style="align-items: flex-start">
            <div>
                @if ($logo)<img src="{{ upload_url($logo) }}" alt="" style="max-height: 56px"><br>@endif
                <h1>{{ store_name() }}</h1>
                <div class="muted">{{ setting('store.address') }}, {{ setting('store.city') }}<br>{{ setting('store.phone') }} · {{ setting('store.email') }}</div>
            </div>
            <div style="text-align: right">
                <div class="tag">{{ $order->channel === 'pos' ? 'SALES INVOICE' : 'ORDER INVOICE' }}</div>
                <h1 style="margin-top: 8px">{{ $order->number }}</h1>
                <div class="muted">{{ $order->placed_at?->format('d M Y, h:i a') }}</div>
                <div class="barcode" style="margin-top: 6px">{!! \App\Support\Barcode::svg($order->number, 200, 40) !!}</div>
            </div>
        </div>
        <div class="rule"></div>
        <div class="row">
            <div><div class="small muted">BILL TO</div><b>{{ $order->customer_name }}</b><br>{{ $order->customer_phone }}@if ($order->shipping_address)<br>{{ $order->shipping_address }}, {{ $order->shipping_city }}@endif</div>
            <div style="text-align: right"><div class="small muted">PAYMENT</div>{{ $order->paymentMethodLabel() }} · {{ $order->paymentStatusLabel() }}<br><span class="muted">Served by {{ $order->user?->name ?? '—' }}</span></div>
        </div>
        <div class="rule"></div>
        <table>
            <thead><tr><th>#</th><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($order->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><b>{{ $item->name }}</b>@if ($item->variant_label)<div class="small muted">{{ $item->variant_label }}</div>@endif @if ($item->sku)<div class="small muted">{{ $item->sku }}</div>@endif</td>
                        <td class="num">{{ $item->qty() }}</td>
                        <td class="num">{{ money($item->unit_price) }}</td>
                        <td class="num">{{ (float) $item->discount > 0 ? '−'.money($item->discount) : '—' }}</td>
                        <td class="num">{{ money($item->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-left: auto; width: 280px; margin-top: 12px">
            <div class="row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
            @if ((float) $order->discount_total > 0)<div class="row"><span>Discount</span><span>−{{ money($order->discount_total) }}</span></div>@endif
            @if ((float) $order->coupon_discount > 0)<div class="row"><span>Coupon {{ $order->coupon_code }}</span><span>−{{ money($order->coupon_discount) }}</span></div>@endif
            @if ((float) $order->shipping_fee > 0)<div class="row"><span>Delivery</span><span>{{ money($order->shipping_fee) }}</span></div>@endif
            <div class="rule"></div>
            <div class="row total"><span>Total</span><span>{{ money($order->grand_total) }}</span></div>
            @foreach ($order->payments as $p)
                <div class="row small muted"><span>{{ $p->methodLabel() }}</span><span>{{ money($p->amount) }}</span></div>
            @endforeach
            @if ((float) $order->due_total > 0)<div class="row"><b>Balance on khata</b><b>{{ money($order->due_total) }}</b></div>@endif
        </div>
        @if ($order->laserJobs->isNotEmpty())
            <p class="small muted" style="margin-top: 18px">Laser jobs: {{ $order->laserJobs->map(fn ($j) => $j->number.' ('.$j->sideLabel().', '.(float) $j->cut_width_mm.'×'.(float) $j->cut_height_mm.' mm)')->implode(' · ') }}</p>
        @endif
        <div class="rule"></div>
        <p class="center muted small">{{ setting('receipt.footer', 'Thank you for shopping with us!') }}</p>
    @endif
</div>

@if (request()->boolean('autoprint', true))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
