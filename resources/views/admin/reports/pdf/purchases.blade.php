@extends('admin.reports.pdf.layout')

@section('content')
    @include('admin.reports.pdf.cards', ['cards' => [
        ['Goods received', money($s['received']), 'Stock booked in this period'],
        ['Returned to vendors', money($s['returned']), null],
        ['Net purchases', money($s['net']), null],
        ['Paid to vendors', money($s['paid']), null],
        ['Bills entered', number_format($s['bills']), money($s['billed']).' billed'],
        ['Awaiting delivery', money($s['awaiting']), 'Ordered, not yet received'],
        ['Payable now', money($s['payable_now']), 'What you owe vendors today'],
    ]])

    <h2>By vendor</h2>
    <table class="data">
        <thead><tr><th>Vendor</th><th class="r">Received</th><th class="r">Returned</th><th class="r">Net</th><th class="r">Paid</th><th class="r">Balance today</th></tr></thead>
        <tbody>
            @forelse ($vendors as $v)
                <tr><td>{{ $v['name'] }}</td><td class="r">{{ money($v['received']) }}</td><td class="r">{{ (float) $v['returned'] ? money($v['returned']) : '—' }}</td>
                    <td class="r b">{{ money($v['received'] - $v['returned']) }}</td><td class="r">{{ money($v['paid']) }}</td>
                    <td class="r">{{ (float) $v['balance'] > 0 ? money($v['balance']).' owed' : ((float) $v['balance'] < 0 ? money(abs($v['balance'])).' advance' : 'settled') }}</td></tr>
            @empty
                <tr><td colspan="6" class="c muted">No vendor activity in this period.</td></tr>
            @endforelse
            @if (count($vendors) > 1)
                <tr class="total"><td>Total</td><td class="r">{{ money($s['received']) }}</td><td class="r">{{ money($s['returned']) }}</td><td class="r">{{ money($s['net']) }}</td><td class="r">{{ money($s['paid']) }}</td><td></td></tr>
            @endif
        </tbody>
    </table>

    <h2>Purchase bills</h2>
    <table class="data">
        <thead><tr><th>Bill date</th><th>Purchase</th><th>Vendor</th><th>Status</th><th class="r">Bill total</th><th class="r">Received</th><th class="r">Returned</th><th class="r">Paid</th><th class="r">Due</th></tr></thead>
        <tbody>
            @forelse ($bills as $po)
                <tr @if (in_array($po->status, ['draft', 'cancelled'])) class="muted" @endif>
                    <td class="nowrap">{{ $po->order_date->format('d M Y') }}</td>
                    <td class="nowrap">{{ $po->number }}@if ($po->bill_number)<br><span class="muted">bill {{ $po->bill_number }}</span>@endif</td>
                    <td>{{ $po->vendor?->name }}</td><td>{{ $po->statusLabel() }}</td>
                    <td class="r">{{ money($po->total) }}</td><td class="r">{{ money($po->received_total) }}</td>
                    <td class="r">{{ (float) $po->returned_total ? money($po->returned_total) : '—' }}</td><td class="r">{{ money($po->paid_amount) }}</td>
                    <td class="r b">{{ $po->balanceDue() < 0 ? money(abs($po->balanceDue())).' adv.' : money($po->balanceDue()) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="c muted">No purchase bills dated in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Stock received by product</h2>
    <table class="data">
        <thead><tr><th>Item</th><th class="r">Qty in</th><th class="r">Avg cost</th><th class="r">Value</th><th class="r">Returned</th><th class="r">Returned value</th></tr></thead>
        <tbody>
            @forelse ($products as $pr)
                @php $area = $pr['unit'] === 'cm2'; @endphp
                <tr>
                    <td>{{ $pr['name'] }}@if ($pr['label'] && $pr['label'] !== 'Standard') <span class="muted">— {{ $pr['label'] }}</span>@endif<br><span class="muted">{{ $pr['sku'] }}</span></td>
                    <td class="r">{{ qty($pr['qty']) }}{{ $area ? ' cm²' : '' }}</td>
                    <td class="r">{{ (float) $pr['qty'] ? ($area ? 'Rs. '.number_format($pr['value'] / $pr['qty'], 4) : money($pr['value'] / $pr['qty'])) : '—' }}</td>
                    <td class="r b">{{ money($pr['value']) }}</td>
                    <td class="r">{{ (float) $pr['returned_qty'] ? qty($pr['returned_qty']).($area ? ' cm²' : '') : '—' }}</td>
                    <td class="r">{{ (float) $pr['returned_value'] ? money($pr['returned_value']) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c muted">No stock received in this period.</td></tr>
            @endforelse
            @if (count($products))
                @php
                    $atCost = array_sum(array_column($products, 'value'));
                    $billExtras = round($s['received'] - $atCost, 2);
                @endphp
                <tr class="total"><td colspan="3">Total at stock cost</td><td class="r">{{ money($atCost) }}</td><td></td><td class="r">{{ money(array_sum(array_column($products, 'returned_value'))) }}</td></tr>
                @if (abs($billExtras) >= 0.01)
                    <tr><td colspan="3" class="muted">Bill discounts / freight (on vendor bills, not in stock cost)</td><td class="r muted">{{ $billExtras > 0 ? '+' : '' }}{{ money($billExtras) }}</td><td colspan="2"></td></tr>
                    <tr><td colspan="3" class="b">Goods received (as billed)</td><td class="r b">{{ money($s['received']) }}</td><td colspan="2"></td></tr>
                @endif
            @endif
        </tbody>
    </table>

    <div class="note">Purchases count when goods are received (that's when stock and the vendor payable are booked). Product values are at the bill's unit cost; bill discounts and freight change what you owe the vendor, not stock cost.</div>
@endsection
