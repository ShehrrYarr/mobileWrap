@extends('admin.reports.pdf.layout')
@php $R = \App\Services\ReportService::class; @endphp

@section('content')
    @php
        $chg = $R::change($s['net'], $prev['net']);
        $cards = [
            ['Invoices', number_format($s['invoices']), number_format($s['units'], 0).' items sold'],
            ['Net sales', money($s['net']), $R::pct($chg).' vs previous period'],
            ['Discounts given', money($s['discounts']), null],
            ['Returns', money($s['returns']), $s['returns_count'].' return(s)'],
            ['Laser cutting fees', money($s['laser']), $s['laser_pieces'].' piece(s)'],
            ['Delivery charges', money($s['delivery']), null],
            ['Average invoice', money($s['average']), null],
        ];
        if ($canCost) {
            $cards[] = ['Margin on goods', money($s['goods_margin']), $s['goods_revenue'] > 0 ? number_format($s['goods_margin'] / $s['goods_revenue'] * 100, 1).'% of goods sales' : null];
        }
    @endphp
    @include('admin.reports.pdf.cards', ['cards' => $cards])
    @if ($pending['count'])
        <div class="note">Not included: {{ $pending['count'] }} online order(s) worth {{ money($pending['amount']) }} not yet delivered.</div>
    @endif

    <h2>{{ $range->groupsByMonth() ? 'Month by month' : 'Day by day' }}</h2>
    <table class="data">
        <thead><tr><th>{{ $range->groupsByMonth() ? 'Month' : 'Date' }}</th><th class="r">Invoices</th><th class="r">Sales</th><th class="r">Discounts</th><th class="r">Returns</th><th class="r">Net sales</th>@if ($canCost)<th class="r">Cost of goods</th>@endif</tr></thead>
        <tbody>
            @foreach ($days as $d)
                <tr @if (! $d['invoices'] && ! $d['returns']) class="muted" @endif>
                    <td>{{ $d['label'] }}</td><td class="r">{{ $d['invoices'] }}</td><td class="r">{{ money($d['sales']) }}</td>
                    <td class="r">{{ $d['discounts'] ? money($d['discounts']) : '—' }}</td><td class="r">{{ $d['returns'] ? money($d['returns']) : '—' }}</td>
                    <td class="r b">{{ money($d['net']) }}</td>@if ($canCost)<td class="r">{{ money($d['cost']) }}</td>@endif
                </tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="r">{{ $s['invoices'] }}</td><td class="r">{{ money($s['sales_total']) }}</td><td class="r">{{ money($s['discounts']) }}</td><td class="r">{{ money($s['returns']) }}</td><td class="r">{{ money($s['net']) }}</td>@if ($canCost)<td class="r">{{ money($s['goods_cost']) }}</td>@endif</tr>
        </tbody>
    </table>

    <h2>Sales by category</h2>
    <table class="data">
        <thead><tr><th>Category</th><th class="r">Qty</th><th class="r">Amount</th>@if ($canCost)<th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th>@endif</tr></thead>
        <tbody>
            @forelse ($categories as $c)
                <tr><td>{{ $c['name'] }}</td><td class="r">{{ qty($c['qty']) }}</td><td class="r">{{ money($c['amount']) }}</td>
                    @if ($canCost)<td class="r">{{ $c['cost'] === null ? '—' : money($c['cost']) }}</td><td class="r">{{ $c['profit'] === null ? '—' : money($c['profit']) }}</td><td class="r">{{ $c['profit'] === null || ! (float) $c['amount'] ? '—' : number_format($c['profit'] / $c['amount'] * 100, 1).'%' }}</td>@endif</tr>
            @empty
                <tr><td colspan="6" class="c muted">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Top products</h2>
    <table class="data">
        <thead><tr><th style="width: 18px">#</th><th>Item</th><th class="r">Sold</th><th class="r">Returned</th><th class="r">Amount</th>@if ($canCost)<th class="r">Cost</th><th class="r">Profit</th>@endif</tr></thead>
        <tbody>
            @forelse ($products as $i => $pr)
                <tr><td class="muted">{{ $i + 1 }}</td><td>{{ $pr['name'] }}@if ($pr['label'] && $pr['label'] !== 'Standard') <span class="muted">— {{ $pr['label'] }}</span>@endif<br><span class="muted">{{ $pr['sku'] }}</span></td>
                    <td class="r">{{ qty($pr['qty']) }}</td><td class="r">{{ (float) $pr['returned_qty'] ? qty($pr['returned_qty']) : '—' }}</td><td class="r">{{ money($pr['amount']) }}</td>
                    @if ($canCost)<td class="r">{{ money($pr['cost']) }}</td><td class="r {{ $pr['profit'] < 0 ? 'neg' : '' }}">{{ money($pr['profit']) }}</td>@endif</tr>
            @empty
                <tr><td colspan="7" class="c muted">No products sold in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($canCost)<div class="note">Product amounts are after line discounts but before invoice-level discounts and returns.</div>@endif

    <table class="split" style="margin-top: 4px"><tr>
        <td class="col col-l">
            <h2>Payment methods</h2>
            <table class="data">
                <thead><tr><th>Method</th><th class="r">Invoices</th><th class="r">Amount</th></tr></thead>
                <tbody>
                    @forelse ($payments as $row)<tr><td>{{ $row['name'] }}</td><td class="r">{{ $row['invoices'] }}</td><td class="r">{{ money($row['amount']) }}</td></tr>
                    @empty<tr><td colspan="3" class="c muted">—</td></tr>@endforelse
                </tbody>
            </table>
        </td>
        <td class="col col-r">
            @if ($channels)
                <h2>Channels</h2>
                <table class="data">
                    <thead><tr><th>Channel</th><th class="r">Invoices</th><th class="r">Amount</th></tr></thead>
                    <tbody>@foreach ($channels as $row)<tr><td>{{ $row['name'] }}</td><td class="r">{{ $row['invoices'] }}</td><td class="r">{{ money($row['amount']) }}</td></tr>@endforeach</tbody>
                </table>
            @endif
            <h2>Sales by staff</h2>
            <table class="data">
                <thead><tr><th>Staff</th><th class="r">Invoices</th><th class="r">Amount</th></tr></thead>
                <tbody>
                    @forelse ($staff as $row)<tr><td>{{ $row['name'] }}</td><td class="r">{{ $row['invoices'] }}</td><td class="r">{{ money($row['amount']) }}</td></tr>
                    @empty<tr><td colspan="3" class="c muted">—</td></tr>@endforelse
                </tbody>
            </table>
        </td>
    </tr></table>

    <h2>Top customers</h2>
    <table class="data">
        <thead><tr><th>Customer</th><th>Type</th><th>Phone</th><th class="r">Invoices</th><th class="r">Amount</th></tr></thead>
        <tbody>
            @forelse ($customers as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['phone'] }}</td><td class="r">{{ $row['invoices'] }}</td><td class="r">{{ money($row['amount']) }}</td></tr>
            @empty<tr><td colspan="5" class="c muted">—</td></tr>@endforelse
        </tbody>
    </table>

    <div class="note">Sales count when paid at the POS or when an online order is delivered. Returns count on the day they were made. Net sales = sales − returns.</div>
@endsection
