@extends('admin.print.layout', ['paper' => 'a4', 'autoprint' => request()->boolean('autoprint', true)])

@section('title', $po->number)

@section('heading')
    <h2>PURCHASE ORDER</h2>
    <div style="font-size: 15px; font-weight: 700">{{ $po->number }}</div>
    <div class="small muted">Date: {{ $po->order_date->format('d M Y') }}</div>
    @if ($po->due_date)<div class="small muted">Payment due: {{ $po->due_date->format('d M Y') }}</div>@endif
    @if ($po->bill_number)<div class="small muted">Vendor bill: {{ $po->bill_number }}</div>@endif
    <div style="margin-top: 4px"><span class="tag">{{ strtoupper($po->statusLabel()) }}</span></div>
@endsection

@section('body')
    <div class="row" style="margin-bottom: 14px">
        <div class="box" style="flex: 1">
            <div class="small muted">Vendor</div>
            <div style="font-size: 15px; font-weight: 700">{{ $po->vendor->name }}</div>
            @if ($po->vendor->company)<div>{{ $po->vendor->company }}</div>@endif
            <div class="small muted">{{ trim($po->vendor->address.' '.$po->vendor->city) }}</div>
            <div class="small muted">{{ $po->vendor->phone }}</div>
        </div>
        <div class="box" style="flex: 1">
            <div class="small muted">Deliver to</div>
            <div style="font-size: 15px; font-weight: 700">{{ store_name() }}</div>
            <div class="small muted">{{ setting('store.address') }}, {{ setting('store.city') }}</div>
            <div class="small muted">{{ setting('store.phone') }}</div>
        </div>
    </div>

    <table>
        <thead><tr><th style="width: 28px">#</th><th>Item</th><th>SKU</th><th class="num">Qty</th><th class="num">Received</th><th class="num">Unit cost</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($po->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->variant?->displayName() ?? 'Deleted item' }}</td>
                    <td class="small">{{ $item->variant?->sku }}</td>
                    <td class="num">{{ qty($item->quantity) }}</td>
                    <td class="num">{{ qty($item->received_quantity) }}@if ((float) $item->returned_quantity > 0)<div class="small muted">−{{ qty($item->returned_quantity) }} returned</div>@endif</td>
                    <td class="num">{{ money($item->unit_cost) }}</td>
                    <td class="num">{{ money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row" style="margin-top: 12px; align-items: flex-start">
        <div style="flex: 1" class="small muted">{{ $po->notes }}</div>
        <table style="width: 280px">
            <tr><td>Subtotal</td><td class="num">{{ money($po->subtotal) }}</td></tr>
            @if ((float) $po->discount > 0)<tr><td>Discount</td><td class="num">−{{ money($po->discount) }}</td></tr>@endif
            @if ((float) $po->shipping > 0)<tr><td>Freight / other charges</td><td class="num">{{ money($po->shipping) }}</td></tr>@endif
            <tr><td><b>Total</b></td><td class="num"><b>{{ money($po->total) }}</b></td></tr>
            @if ($po->status === 'partial')<tr><td>Received so far</td><td class="num">{{ money($po->received_total) }}</td></tr>@endif
            @if ((float) $po->returned_total > 0)<tr><td>Returned</td><td class="num">−{{ money($po->returned_total) }}</td></tr>@endif
            @if ((float) $po->paid_amount > 0)<tr><td>Paid</td><td class="num">−{{ money($po->paid_amount) }}</td></tr>@endif
            @if (in_array($po->status, ['partial', 'received']) || (float) $po->paid_amount > 0)
                <tr><td><b>{{ $po->balanceDue() < 0 ? 'Advance paid' : 'Balance due' }}</b></td><td class="num"><b>{{ money(abs($po->balanceDue())) }}</b></td></tr>
            @endif
        </table>
    </div>

    <div class="sign"><div>Prepared by {{ $po->user?->name }}</div><div>Vendor signature</div></div>
@endsection
