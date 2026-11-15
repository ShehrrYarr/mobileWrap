<div class="sf-price">
    <span class="sf-price-now">@if ($p['max'] > $p['min'])<small class="fw-normal sf-muted" style="font-size: .7em">from </small>@endif{{ money($p['min']) }}</span>
    @if ($p['compare'])
        <span class="sf-price-was">{{ money($p['compare']) }}</span>
    @endif
</div>
