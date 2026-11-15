@extends('admin.print.layout', ['paper' => '80mm'])

@section('title', 'Payment receipt PR-'.$entry->id)

@section('body')
    <div class="center">
        @if (setting('store.logo'))<img src="{{ upload_url(setting('store.logo')) }}" alt="" style="max-height: 40px; max-width: 60%"><br>@endif
        <h1>{{ store_name() }}</h1>
        <div class="small muted">{{ setting('store.address') }}, {{ setting('store.city') }}<br>{{ setting('store.phone') }}</div>
        <div style="margin-top: 6px"><span class="tag">PAYMENT RECEIPT</span></div>
    </div>
    <div class="rule"></div>
    <div class="row small"><span>Receipt <b>PR-{{ $entry->id }}</b></span><span>{{ $entry->created_at->format('d/m/Y h:i a') }}</span></div>
    <div class="small">Received by: {{ $entry->user?->name ?? '—' }}</div>
    <div class="rule"></div>
    <div class="small muted">Received from</div>
    <div><b>{{ $entry->customer?->name }}</b></div>
    @if ($entry->customer?->phone)<div class="small">{{ $entry->customer->phone }}</div>@endif
    <div class="rule"></div>
    <div class="row"><span>Paid by</span><span>{{ $account instanceof \App\Models\CashDrawer ? 'Cash' : ($account ? 'Bank — '.$account->bank_name : '—') }}</span></div>
    @if ($entry->note)<div class="small muted" style="margin: 2px 0">{{ $entry->note }}</div>@endif
    <div class="row big" style="margin-top: 6px"><span>AMOUNT</span><span>{{ money($entry->credit) }}</span></div>
    <div class="rule"></div>
    <div class="row"><span>Previous balance</span><span>{{ money((float) $entry->balance_after + (float) $entry->credit) }}</span></div>
    <div class="row"><b>{{ $entry->balance_after > 0 ? 'Balance due' : ($entry->balance_after < 0 ? 'Advance / credit' : 'Balance') }}</b><b>{{ money(abs((float) $entry->balance_after)) }}</b></div>
    <div class="rule"></div>
    <div class="center small" style="margin-top: 8px">Thank you!<br>{{ setting('receipt.footer') }}</div>
@endsection
