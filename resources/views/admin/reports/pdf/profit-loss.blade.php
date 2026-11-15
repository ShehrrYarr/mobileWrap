@extends('admin.reports.pdf.layout')
@php $R = \App\Services\ReportService::class; @endphp

@section('content')
    @php
        $prevRange = $range->previous();
        $row = fn ($label, $now, $before, $class = '', $sign = '') => compact('label', 'now', 'before', 'class', 'sign');
        $prevExpenses = collect($prev['expenses'])->keyBy('name');
    @endphp

    @include('admin.reports.pdf.cards', ['cards' => [
        ['Net revenue', money($pl['revenue']), $R::pct($R::change($pl['revenue'], $prev['revenue'])).' vs previous'],
        ['Gross profit', money($pl['gross_profit']), $pl['gross_margin'] === null ? null : number_format($pl['gross_margin'], 1).'% margin'],
        ['Expenses', money($pl['expense_total']), null],
        [$pl['net_profit'] < 0 ? 'Net loss' : 'Net profit', money($pl['net_profit']), $pl['net_margin'] === null ? null : number_format($pl['net_margin'], 1).'% of revenue'],
    ]])

    <h2>Statement</h2>
    <table class="data">
        <thead><tr><th></th><th class="r" style="width: 22%">{{ $range->label() }}</th><th class="r" style="width: 22%">Previous ({{ $prevRange->label() }})</th><th class="r" style="width: 11%">Change</th></tr></thead>
        <tbody>
            <tr class="sub"><td colspan="4">Revenue</td></tr>
            @foreach ([
                $row('Product sales (before discounts)', $pl['gross_sales'], $prev['gross_sales']),
                $row('Less: discounts', -$pl['discounts'], -$prev['discounts']),
                $row('Laser cutting fees', $pl['laser'], $prev['laser']),
                $row('Delivery charges', $pl['delivery'], $prev['delivery']),
                $row('Less: customer returns', -$pl['returns'], -$prev['returns']),
            ] as $r)
                <tr><td style="padding-left: 14px">{{ $r['label'] }}</td><td class="r">{{ money($r['now']) }}</td><td class="r muted">{{ money($r['before']) }}</td><td class="r muted">{{ $R::pct($R::change($r['now'], $r['before'])) }}</td></tr>
            @endforeach
            <tr class="total"><td>Net revenue</td><td class="r">{{ money($pl['revenue']) }}</td><td class="r">{{ money($prev['revenue']) }}</td><td class="r">{{ $R::pct($R::change($pl['revenue'], $prev['revenue'])) }}</td></tr>

            <tr class="sub"><td colspan="4">Cost of sales</td></tr>
            @foreach ([
                $row('Cost of goods sold', $pl['cogs'], $prev['cogs']),
                $row('Less: cost of restocked returns', -$pl['restocked_cost'], -$prev['restocked_cost']),
                $row('Laser material used', $pl['laser_material'], $prev['laser_material']),
                $row('Wastage (failed cuts, damaged stock)', $pl['wastage'], $prev['wastage']),
                $row('Stock adjustments (net)', $pl['adjustments'], $prev['adjustments']),
            ] as $r)
                <tr><td style="padding-left: 14px">{{ $r['label'] }}</td><td class="r">{{ money($r['now']) }}</td><td class="r muted">{{ money($r['before']) }}</td><td class="r muted">{{ $R::pct($R::change($r['now'], $r['before'])) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total cost of sales</td><td class="r">{{ money($pl['cost']) }}</td><td class="r">{{ money($prev['cost']) }}</td><td class="r">{{ $R::pct($R::change($pl['cost'], $prev['cost'])) }}</td></tr>

            <tr class="grand"><td>Gross profit @if ($pl['gross_margin'] !== null)<span class="muted" style="font-weight: normal; font-size: 8px">({{ number_format($pl['gross_margin'], 1) }}% margin)</span>@endif</td>
                <td class="r {{ $pl['gross_profit'] < 0 ? 'neg' : 'pos' }}">{{ money($pl['gross_profit']) }}</td><td class="r">{{ money($prev['gross_profit']) }}</td><td class="r">{{ $R::pct($R::change($pl['gross_profit'], $prev['gross_profit'])) }}</td></tr>

            <tr class="sub"><td colspan="4">Expenses</td></tr>
            @forelse ($pl['expenses'] as $e)
                @php $before = (float) ($prevExpenses[$e['name']]['amount'] ?? 0); @endphp
                <tr><td style="padding-left: 14px">{{ $e['name'] }}</td><td class="r">{{ money($e['amount']) }}</td><td class="r muted">{{ money($before) }}</td><td class="r muted">{{ $R::pct($R::change((float) $e['amount'], $before)) }}</td></tr>
            @empty
                <tr><td style="padding-left: 14px" class="muted">No expenses recorded</td><td class="r">{{ money(0) }}</td><td class="r muted">{{ money($prev['expense_total']) }}</td><td></td></tr>
            @endforelse
            @foreach ($prev['expenses'] as $e)
                @continue(collect($pl['expenses'])->contains('name', $e['name']))
                <tr><td style="padding-left: 14px">{{ $e['name'] }}</td><td class="r">{{ money(0) }}</td><td class="r muted">{{ money($e['amount']) }}</td><td class="r muted">{{ $R::pct(-100) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total expenses</td><td class="r">{{ money($pl['expense_total']) }}</td><td class="r">{{ money($prev['expense_total']) }}</td><td class="r">{{ $R::pct($R::change($pl['expense_total'], $prev['expense_total'])) }}</td></tr>

            <tr class="grand"><td>{{ $pl['net_profit'] < 0 ? 'Net loss' : 'Net profit' }}</td>
                <td class="r {{ $pl['net_profit'] < 0 ? 'neg' : 'pos' }}">{{ money($pl['net_profit']) }}</td><td class="r">{{ money($prev['net_profit']) }}</td><td class="r">{{ $R::pct($R::change($pl['net_profit'], $prev['net_profit'])) }}</td></tr>
        </tbody>
    </table>

    <div class="note">
        Sales count when paid at the POS or when an online order is delivered; returns on the day they were made. Cost of goods uses the weighted-average cost saved on each sale.
        Laser material, wastage and stock adjustments are valued at cost from the stock ledger. Expenses are those dated in the period. Vendor payments are not expenses (stock is costed when sold).
    </div>
@endsection
