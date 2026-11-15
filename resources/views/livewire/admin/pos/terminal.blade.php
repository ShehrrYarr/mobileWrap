<?php

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\LaserCutterJob;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use App\Services\Pos\PosCart;
use App\Services\Pos\PosService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.pos')] #[Title('POS')] class extends Component
{
    /** Cart lines keyed by line key (v{variantId} | l{random}) */
    public array $lines = [];

    public ?int $customerId = null;

    public string $search = '';

    public ?int $categoryId = null;

    public array $discount = ['type' => 'amount', 'value' => ''];

    public string $coupon = '';

    public string $note = '';

    #[Url(as: 'exchange')]
    public ?int $exchangeReturnId = null;

    public ?int $pickerProductId = null;

    public bool $showLaser = false;

    public array $laser = [];

    public bool $showCustomer = false;

    public string $customerSearch = '';

    public array $newCustomer = ['name' => '', 'phone' => '', 'type' => 'retail'];

    public bool $showPay = false;

    public array $pay = ['cash' => '', 'bank' => '', 'bank_account_id' => null, 'bank_reference' => '', 'credit' => ''];

    public bool $showHeld = false;

    public ?int $completedOrderId = null;

    /** Khata collection (customer pays off their balance at the counter) */
    public bool $showCollect = false;

    public string $collectSearch = '';

    public array $collect = [];

    protected const STATE_KEY = 'pos.state';

    public function mount(): void
    {
        $state = session(self::STATE_KEY, []);
        foreach (['lines', 'customerId', 'discount', 'coupon', 'note'] as $prop) {
            if (array_key_exists($prop, $state)) {
                $this->{$prop} = $state[$prop];
            }
        }

        if ($this->exchangeReturnId) {
            $return = $this->exchangeReturn();
            if (! $return) {
                $this->exchangeReturnId = null;
            } elseif ($return->order?->customer_id && ! $this->customerId) {
                $this->customerId = $return->order->customer_id;
            }
        }

        $this->resetLaser();
        $this->pay['bank_account_id'] = BankAccount::active()->value('id');
    }

    /** Keep the sale safe across page reloads. */
    public function dehydrate(): void
    {
        session([self::STATE_KEY => [
            'lines' => $this->lines, 'customerId' => $this->customerId,
            'discount' => $this->discount, 'coupon' => $this->coupon, 'note' => $this->note,
        ]]);
    }

    public function updated(string $property): void
    {
        if (preg_match('/^lines\.([^.]+)\.(price|qty|discount|fee)$/', $property, $m) && isset($this->lines[$m[1]])) {
            $line = &$this->lines[$m[1]];
            if ($m[2] === 'price') {
                $line['price'] = max(0, (float) $line['price']);
                $line['manual'] = true;
            }
            if ($m[2] === 'qty') {
                $line['qty'] = max(1, (int) $line['qty']);
            }
            if ($m[2] === 'discount') {
                $line['discount'] = max(0, (float) $line['discount']);
            }
            if ($m[2] === 'fee') {
                $line['fee'] = max(0, (float) $line['fee']);
            }
        }
    }

    // ── Catalogue & scanning ─────────────────────────────────────────

    protected function customer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    protected function tier(): string
    {
        return $this->customer()?->isWholesale() ? 'wholesale' : 'retail';
    }

    /** Barcode scanners type fast then press Enter, so the input value is passed in directly. */
    public function scan(?string $typed = null): bool
    {
        $term = trim($typed ?? $this->search);
        if ($term === '') {
            return true;
        }

        $variant = ProductVariant::active()
            ->whereHas('product', fn ($q) => $q->where('is_active', true)->where('type', 'standard'))
            ->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))
            ->first();

        if (! $variant) {
            $matches = Product::active()->where('type', 'standard')->search($term)->with('activeVariants')->limit(2)->get();
            if ($matches->count() === 1 && $matches->first()->activeVariants->count() === 1) {
                $variant = $matches->first()->activeVariants->first();
            }
        }

        if ($variant) {
            $this->addVariant($variant->id);
            $this->search = '';

            return true;
        }

        $this->search = $term; // show matching products so the cashier can pick
        $this->dispatch('toast', type: 'warning', message: 'No exact match for “'.$term.'” — pick from the results.');

        return false;
    }

    public function pickProduct(int $productId): void
    {
        $product = Product::with('activeVariants')->findOrFail($productId);
        if ($product->activeVariants->count() === 1) {
            $this->addVariant($product->activeVariants->first()->id);
        } else {
            $this->pickerProductId = $productId;
        }
    }

    public function addVariant(int $variantId, int $qty = 1): void
    {
        $variant = ProductVariant::with(['product.category', 'color.images', 'stock'])->findOrFail($variantId);
        $key = 'v'.$variant->id;

        if (isset($this->lines[$key])) {
            $this->lines[$key]['qty'] += $qty;
        } else {
            $price = app(PricingService::class)->variantPrice($variant, $this->tier(), $variant->product)['price'];
            $this->lines[$key] = [
                'key' => $key,
                'type' => 'product',
                'variant_id' => $variant->id,
                'name' => $variant->product->name,
                'label' => $variant->label !== 'Standard' ? $variant->label : null,
                'sku' => $variant->sku,
                'image' => $variant->color?->images->first() ? upload_url($variant->color->images->first()->path) : $variant->product->imageUrl(),
                'qty' => $qty,
                'price' => $price,
                'discount' => 0,
                'manual' => false,
                'track' => (bool) $variant->product->track_stock,
            ];
        }

        $this->pickerProductId = null;
        $this->dispatch('pos-focus');
    }

    public function changeQty(string $key, int $delta): void
    {
        if (! isset($this->lines[$key])) {
            return;
        }
        $this->lines[$key]['qty'] = max(0, (int) $this->lines[$key]['qty'] + $delta);
        if ($this->lines[$key]['qty'] === 0) {
            unset($this->lines[$key]);
        }
    }

    public function removeLine(string $key): void
    {
        unset($this->lines[$key]);
    }

    public function clearSale(): void
    {
        $this->reset('lines', 'customerId', 'discount', 'coupon', 'note', 'exchangeReturnId', 'pay');
        $this->pay['bank_account_id'] = BankAccount::active()->value('id');
        $this->dispatch('pos-focus');
    }

    // ── Customer ─────────────────────────────────────────────────────

    public function selectCustomer(?int $id): void
    {
        $this->customerId = $id;
        $this->showCustomer = false;
        $this->customerSearch = '';
        $this->repriceLines();
    }

    public function createCustomer(): void
    {
        $this->newCustomer['phone'] = Customer::normalizePhone($this->newCustomer['phone']) ?? '';
        $this->validate([
            'newCustomer.name' => 'required|string|max:120',
            'newCustomer.phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')],
            'newCustomer.type' => 'required|in:retail,wholesale',
        ], [], ['newCustomer.name' => 'name', 'newCustomer.phone' => 'phone']);

        $customer = Customer::create([
            'name' => $this->newCustomer['name'],
            'phone' => $this->newCustomer['phone'] ?: null,
            'type' => $this->newCustomer['type'],
        ]);
        $this->newCustomer = ['name' => '', 'phone' => '', 'type' => 'retail'];
        $this->selectCustomer($customer->id);
        $this->dispatch('toast', type: 'success', message: $customer->name.' added.');
    }

    /** Switching retail ↔ wholesale re-prices lines the cashier hasn't edited. */
    protected function repriceLines(): void
    {
        $pricing = app(PricingService::class);
        $tier = $this->tier();
        foreach ($this->lines as $key => $line) {
            if ($line['type'] === 'product' && empty($line['manual'])) {
                $variant = ProductVariant::with('product.category')->find($line['variant_id']);
                if ($variant) {
                    $this->lines[$key]['price'] = $pricing->variantPrice($variant, $tier, $variant->product)['price'];
                }
            }
        }
    }

    // ── Laser cut lines ──────────────────────────────────────────────

    protected function resetLaser(): void
    {
        $this->laser = ['device_brand_id' => '', 'device_model_id' => '', 'side' => 'front', 'material_variant_id' => '', 'width' => '', 'height' => '', 'qty' => 1, 'fee' => '', 'notes' => ''];
    }

    public function openLaser(): void
    {
        $this->resetLaser();
        $this->resetErrorBag();
        $this->showLaser = true;
    }

    public function updatedLaserDeviceBrandId(): void
    {
        $this->laser['device_model_id'] = '';
    }

    public function updatedLaserDeviceModelId(): void
    {
        $this->prefillCutSize();
    }

    public function updatedLaserSide(): void
    {
        $this->prefillCutSize();
    }

    protected function prefillCutSize(): void
    {
        $device = $this->laser['device_model_id'] ? DeviceModel::find($this->laser['device_model_id']) : null;
        if ($device) {
            $size = $device->cutSize($this->laser['side'] === 'front' ? 'front' : 'back');
            $this->laser['width'] = $size['w'] ?: '';
            $this->laser['height'] = $size['h'] ?: '';
        }
    }

    public function addLaser(): void
    {
        $this->validate([
            'laser.device_model_id' => 'required|exists:device_models,id',
            'laser.side' => 'required|in:front,back,full',
            'laser.material_variant_id' => 'required|exists:product_variants,id',
            'laser.width' => 'required|numeric|min:10|max:400',
            'laser.height' => 'required|numeric|min:10|max:400',
            'laser.qty' => 'required|integer|min:1|max:20',
            'laser.fee' => 'required|numeric|min:0',
            'laser.notes' => 'nullable|string|max:255',
        ], [], [
            'laser.device_model_id' => 'phone model', 'laser.material_variant_id' => 'material', 'laser.width' => 'width',
            'laser.height' => 'height', 'laser.fee' => 'service fee', 'laser.qty' => 'pieces',
        ]);

        $device = DeviceModel::with('brand')->find($this->laser['device_model_id']);
        $material = ProductVariant::with('product')->find($this->laser['material_variant_id']);
        $area = LaserCutterJob::calculateArea((float) $this->laser['width'], (float) $this->laser['height'], (int) $this->laser['qty'], (float) $material->product->wastage_percent);
        $sides = ['front' => 'Front', 'back' => 'Back', 'full' => 'Full body'];

        $key = 'l'.Str::lower(Str::random(6));
        $this->lines[$key] = [
            'key' => $key,
            'type' => 'laser',
            'name' => 'Laser cut — '.$material->product->name,
            'label' => $device->full_name.' · '.$sides[$this->laser['side']].' · '.$material->label,
            'device_model_id' => (int) $device->id,
            'material_variant_id' => (int) $material->id,
            'side' => $this->laser['side'],
            'width' => (float) $this->laser['width'],
            'height' => (float) $this->laser['height'],
            'qty' => (int) $this->laser['qty'],
            'fee' => (float) $this->laser['fee'],
            'notes' => $this->laser['notes'] ?: null,
            'area' => $area['total'],
        ];

        $this->showLaser = false;
        $this->dispatch('pos-focus');
    }

    // ── Hold / resume ────────────────────────────────────────────────

    public function hold(PosService $pos): void
    {
        if (! $this->lines) {
            $this->dispatch('toast', type: 'warning', message: 'Nothing to hold.');

            return;
        }
        $order = $pos->hold(array_values($this->lines), $this->customer(), $this->discount, $this->note ?: null);
        $this->clearSale();
        $this->dispatch('toast', type: 'info', message: 'Sale held as '.$order->number.'.');
    }

    public function resume(int $orderId, PosService $pos): void
    {
        if ($this->lines) {
            $this->dispatch('toast', type: 'warning', message: 'Hold or clear the current sale first.');

            return;
        }
        $data = $pos->resume(Order::where('status', 'held')->findOrFail($orderId));
        $this->lines = collect($data['lines'])->keyBy('key')->all();
        $this->discount = $data['discount'] ?: ['type' => 'amount', 'value' => ''];
        $this->customerId = $data['customerId'];
        $this->note = (string) $data['note'];
        $this->showHeld = false;
        $this->dispatch('toast', type: 'success', message: 'Sale resumed.');
    }

    public function deleteHeld(int $orderId): void
    {
        $order = Order::where('status', 'held')->findOrFail($orderId);
        $order->items()->delete();
        $order->forceDelete();
    }

    // ── Payment ──────────────────────────────────────────────────────

    protected function exchangeReturn(): ?OrderReturn
    {
        return $this->exchangeReturnId
            ? OrderReturn::with('order')->where('type', 'exchange')->whereNull('exchange_order_id')->find($this->exchangeReturnId)
            : null;
    }

    /** Split-payment breakdown from the inputs in the pay modal. */
    protected function payBreakdown(float $total): array
    {
        $exchange = min((float) ($this->exchangeReturn()?->refund_amount ?? 0), $total);
        $bank = max(0, (float) $this->pay['bank']);
        $credit = max(0, (float) $this->pay['credit']);
        $nonCash = round($exchange + $bank + $credit, 2);
        $cashDue = max(0, round($total - $nonCash, 2));
        $tendered = max(0, (float) $this->pay['cash']);
        $cash = min($tendered, $cashDue);

        return [
            'exchange' => $exchange,
            'bank' => $bank,
            'credit' => $credit,
            'cash_due' => $cashDue,
            'cash' => $cash,
            'tendered' => $tendered,
            'change' => max(0, round($tendered - $cash, 2)),
            'remaining' => round($total - $nonCash - $cash, 2),
            'over' => $nonCash > $total + 0.009,
        ];
    }

    public function openPay(): void
    {
        if (! $this->lines) {
            $this->dispatch('toast', type: 'warning', message: 'Add items first.');

            return;
        }
        $this->pay = array_merge($this->pay, ['cash' => '', 'bank' => '', 'credit' => '', 'bank_reference' => '']);
        $this->resetErrorBag();
        $this->showPay = true;
    }

    public function quickPay(string $method, ?float $amount = null): void
    {
        $total = PosCart::totals(array_values($this->lines), $this->discount, $this->coupon ?: null, $this->customer())['total'];
        $b = $this->payBreakdown($total);
        $remaining = max(0, $b['remaining'] + ($method === 'cash' ? $b['cash'] : 0));

        match ($method) {
            'cash' => $this->pay['cash'] = $amount ?? $b['cash_due'],
            'bank' => $this->pay['bank'] = round((float) $this->pay['bank'] + max(0, $b['remaining']), 2),
            'credit' => $this->pay['credit'] = round((float) $this->pay['credit'] + max(0, $b['remaining']), 2),
            default => null,
        };
    }

    public function complete(PosService $pos): void
    {
        $customer = $this->customer();
        $totals = PosCart::totals(array_values($this->lines), $this->discount, $this->coupon ?: null, $customer);
        $b = $this->payBreakdown($totals['total']);

        if ($b['over']) {
            $this->addError('pay', 'Bank / khata / exchange amounts are more than the total.');

            return;
        }
        if ($b['remaining'] > 0.009) {
            $this->addError('pay', money($b['remaining']).' still to pay.');

            return;
        }

        $payments = array_filter([
            $b['exchange'] > 0 ? ['method' => 'exchange_credit', 'amount' => $b['exchange'], 'return_id' => $this->exchangeReturnId] : null,
            $b['bank'] > 0 ? ['method' => 'bank_transfer', 'amount' => $b['bank'], 'bank_account_id' => $this->pay['bank_account_id'], 'reference' => $this->pay['bank_reference'] ?: null] : null,
            $b['credit'] > 0 ? ['method' => 'customer_credit', 'amount' => $b['credit']] : null,
            $b['cash'] > 0 ? ['method' => 'cash', 'amount' => $b['cash'], 'tendered' => $b['tendered']] : null,
        ]);

        try {
            $order = $pos->checkout(array_values($this->lines), array_values($payments), $customer, $this->discount, $this->coupon ?: null, $this->note ?: null);
        } catch (ValidationException $e) {
            $this->addError('pay', collect($e->errors())->flatten()->first());

            return;
        }

        $this->showPay = false;
        $this->clearSale();
        $this->completedOrderId = $order->id;

        if (setting('pos.auto_print', false)) {
            $this->dispatch('pos-print', url: route('admin.pos.receipt', ['order' => $order, 'format' => setting('pos.receipt_format', '80mm')]));
        }
    }

    public function openCollect(): void
    {
        $this->resetErrorBag();
        $customer = $this->customer();
        $owes = $customer && (float) $customer->current_balance > 0;
        $this->collectSearch = '';
        $this->collect = [
            'customer_id' => $owes ? $customer->id : null,
            'method' => 'cash',
            'bank_account_id' => BankAccount::active()->value('id'),
            'amount' => $owes ? (float) $customer->current_balance : '',
            'note' => '',
        ];
        $this->showCollect = true;
    }

    public function pickCollectCustomer(int $id): void
    {
        $this->collect['customer_id'] = $id;
        $this->collect['amount'] = max(0, (float) Customer::find($id)?->current_balance);
    }

    public function saveCollect(\App\Services\CustomerLedgerService $ledger): void
    {
        $this->validate([
            'collect.customer_id' => 'required|exists:customers,id',
            'collect.method' => 'required|in:cash,bank',
            'collect.bank_account_id' => 'required_if:collect.method,bank|nullable|exists:bank_accounts,id',
            'collect.amount' => 'required|numeric|min:1',
        ], ['collect.customer_id.required' => 'Choose the customer who is paying.'], ['collect.amount' => 'amount', 'collect.bank_account_id' => 'bank account']);

        $customer = Customer::findOrFail($this->collect['customer_id']);
        $account = $this->collect['method'] === 'bank' ? BankAccount::findOrFail($this->collect['bank_account_id']) : \App\Models\CashDrawer::main();
        $entry = $ledger->receivePayment($customer, $account, (float) $this->collect['amount'], $this->collect['note'] ?: null);

        $this->showCollect = false;
        $this->dispatch('toast', type: 'success', message: money($entry->credit).' received from '.$customer->name.'. Khata now '.money($customer->current_balance).'.');
        $this->dispatch('pos-print', url: route('admin.payment-receipt', $entry));
    }

    public function with(): array
    {
        $customer = $this->customer();
        $lines = array_values($this->lines);
        $totals = PosCart::totals($lines, $this->discount, $this->coupon ?: null, $customer);
        $variantIds = collect($lines)->where('type', 'product')->pluck('variant_id');
        $stock = ProductVariant::with('stock')->whereIn('id', $variantIds)->get()->mapWithKeys(fn ($v) => [$v->id => $v->availableQuantity()]);
        $materials = ProductVariant::active()->with(['product', 'stock'])
            ->whereHas('product', fn ($q) => $q->rawMaterials()->where('is_active', true))->get()->sortBy('product.name');

        $laserMaterial = $this->laser['material_variant_id'] ? $materials->firstWhere('id', (int) $this->laser['material_variant_id']) : null;
        $laserArea = $laserMaterial && $this->laser['width'] && $this->laser['height']
            ? LaserCutterJob::calculateArea((float) $this->laser['width'], (float) $this->laser['height'], max(1, (int) $this->laser['qty']), (float) $laserMaterial->product->wastage_percent)
            : null;
        $lastFee = $this->laser['material_variant_id']
            ? LaserCutterJob::where('material_variant_id', $this->laser['material_variant_id'])->where('side', $this->laser['side'])->where('service_fee', '>', 0)->latest('id')->first()
            : null;

        return [
            'products' => Product::active()->where('type', 'standard')
                ->with(['activeVariants.stock', 'colors.images', 'category'])
                ->when($this->categoryId, fn ($q) => $q->whereIn('category_id', Category::find($this->categoryId)?->familyIds() ?? [0]))
                ->search($this->search)
                ->orderByDesc('is_featured')->orderBy('name')->limit(60)->get(),
            'categories' => Category::active()->roots()->where('show_in_menu', true)->orderBy('sort_order')->get(),
            'customer' => $customer,
            'customers' => strlen($this->customerSearch) >= 2
                ? Customer::active()->where(fn ($q) => $q->where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', '%'.(Customer::normalizePhone($this->customerSearch) ?: $this->customerSearch).'%'))->limit(8)->get()
                : Customer::active()->whereNotNull('last_order_at')->latest('last_order_at')->limit(6)->get(),
            'totals' => $totals,
            'stock' => $stock,
            'tier' => $this->tier(),
            'picker' => $this->pickerProductId ? Product::with(['activeVariants.stock', 'activeVariants.color'])->find($this->pickerProductId) : null,
            'deviceBrands' => DeviceBrand::active()->get(),
            'deviceModels' => $this->laser['device_brand_id'] ? DeviceModel::active()->where('device_brand_id', $this->laser['device_brand_id'])->orderBy('sort_order')->get() : collect(),
            'materials' => $materials,
            'laserMaterial' => $laserMaterial,
            'laserArea' => $laserArea,
            'lastFee' => $lastFee ? (float) $lastFee->service_fee / max(1, $lastFee->quantity) : null,
            'banks' => BankAccount::active()->get(),
            'calc' => $this->payBreakdown($totals['total']),
            'exchange' => $this->exchangeReturn(),
            'held' => Order::where('status', 'held')->withCount('items')->latest('id')->get(),
            'queued' => LaserCutterJob::whereIn('status', ['queued', 'cutting'])->count(),
            'completed' => $this->completedOrderId ? Order::find($this->completedOrderId) : null,
            'collectCustomer' => $this->showCollect && ($this->collect['customer_id'] ?? null) ? Customer::find($this->collect['customer_id']) : null,
            'debtors' => $this->showCollect
                ? Customer::where('current_balance', '>', 0)
                    ->when(strlen($this->collectSearch) >= 2, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->collectSearch}%")->orWhere('phone', 'like', '%'.(Customer::normalizePhone($this->collectSearch) ?: $this->collectSearch).'%')))
                    ->orderByDesc('current_balance')->limit(8)->get()
                : collect(),
        ];
    }
}; ?>

<div class="pos" x-data
     x-on:keydown.window.f2.prevent="$refs.scan.focus(); $refs.scan.select()"
     x-on:keydown.window.f4.prevent="$wire.openLaser()"
     x-on:keydown.window.f8.prevent="$wire.hold()"
     x-on:keydown.window.f9.prevent="$wire.openPay()"
     x-on:pos-focus.window="$nextTick(() => $refs.scan?.focus())"
     x-on:pos-print.window="window.open($event.detail.url ?? $event.detail[0]?.url, 'mw-receipt', 'width=440,height=720')">

    {{-- Top bar --}}
    <div class="pos-top">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-light btn-sm" title="Back to admin"><i class="ri-arrow-left-line"></i></a>
        <span class="mw-logo-mark">MW</span>
        <div class="lh-sm">
            <div class="fw-bold">{{ store_name() }} POS</div>
            <div class="fs-12 text-muted">{{ auth('web')->user()->name }} · {{ now()->format('D, d M Y') }}</div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-light btn-sm position-relative" wire:click="$set('showHeld', true)">
                <i class="ri-pause-circle-line me-1"></i>Held
                @if ($held->count())<span class="badge bg-warning text-dark ms-1">{{ $held->count() }}</span>@endif
            </button>
            <button type="button" class="btn btn-light btn-sm" wire:click="openCollect"><i class="ri-hand-coin-line me-1"></i>Khata payment</button>
            <a href="{{ route('admin.pos.sales') }}" class="btn btn-light btn-sm"><i class="ri-receipt-line me-1"></i>Sales</a>
            <a href="{{ route('admin.returns.create') }}" class="btn btn-light btn-sm"><i class="ri-arrow-go-back-line me-1"></i>Return / exchange</a>
            @can('laser.manage')
                <a href="{{ route('admin.laser') }}" class="btn btn-light btn-sm"><i class="ri-scissors-cut-line me-1"></i>Laser queue @if ($queued)<span class="badge bg-primary ms-1">{{ $queued }}</span>@endif</a>
            @endcan
        </div>
    </div>

    <div class="pos-main">
        {{-- Catalogue --}}
        <section class="pos-catalog">
            <div class="d-flex gap-2">
                <div class="pos-scan flex-grow-1">
                    <i class="ri-barcode-line"></i>
                    {{-- Not wire:model: a scanner types + Enter faster than a debounced sync, so Enter grabs and clears the box itself --}}
                    <input type="search" x-ref="scan" class="form-control" placeholder="Scan barcode, type SKU or search products…  (F2)" autofocus autocomplete="off" aria-label="Scan or search"
                           value="{{ $search }}"
                           x-on:input.debounce.350ms="$wire.set('search', $el.value)"
                           x-on:search="$wire.set('search', $el.value)"
                           x-on:keydown.enter.prevent="const v = $el.value; $el.value = ''; $wire.scan(v).then(ok => { if (! ok) $el.value = v })">
                </div>
                <button type="button" class="btn btn-dark px-3" style="height: 54px; border-radius: 14px" wire:click="openLaser"><i class="ri-scissors-cut-line me-1"></i>Laser cut<span class="pos-kbd">F4</span></button>
            </div>
            <div class="pos-cats">
                <button type="button" class="btn btn-sm {{ ! $categoryId ? 'btn-primary' : 'btn-light' }}" wire:click="$set('categoryId', null)">All</button>
                @foreach ($categories as $cat)
                    <button type="button" class="btn btn-sm {{ $categoryId === $cat->id ? 'btn-primary' : 'btn-light' }}" wire:click="$set('categoryId', {{ $cat->id }})"><i class="{{ $cat->icon }} me-1"></i>{{ $cat->name }}</button>
                @endforeach
            </div>
            <div class="pos-grid" wire:loading.class="opacity-50" wire:target="search,categoryId">
                @forelse ($products as $p)
                    @php($qtyOnHand = $p->activeVariants->sum(fn ($v) => $v->availableQuantity()))
                    @php($prices = $p->activeVariants->map(fn ($v) => app(\App\Services\PricingService::class)->variantPrice($v, $tier, $p)['price']))
                    <button type="button" class="pos-tile {{ $p->track_stock && $qtyOnHand <= 0 ? 'is-out' : '' }}" wire:click="pickProduct({{ $p->id }})" wire:key="tile-{{ $p->id }}">
                        <img src="{{ $p->imageUrl() }}" alt="" loading="lazy">
                        <span class="stock badge {{ $qtyOnHand <= 0 ? 'bg-danger' : ($qtyOnHand <= 5 ? 'bg-warning text-dark' : 'bg-light text-muted') }}">{{ qty($qtyOnHand) }}</span>
                        <span class="name">{{ $p->name }}</span>
                        <span class="d-flex justify-content-between align-items-center">
                            <span class="price">{{ $prices->min() == $prices->max() ? money($prices->min()) : money($prices->min()).'+' }}</span>
                            @if ($p->activeVariants->count() > 1)<span class="fs-11 text-muted">{{ $p->activeVariants->count() }} options</span>@endif
                        </span>
                    </button>
                @empty
                    <div class="text-center text-muted py-5" style="grid-column: 1 / -1"><i class="ri-search-eye-line fs-1 d-block"></i>No products match “{{ $search }}”.</div>
                @endforelse
            </div>
        </section>

        {{-- Cart --}}
        <aside class="pos-cart">
            <div class="pos-cart-head">
                <button type="button" class="btn btn-light w-100 d-flex align-items-center gap-2 text-start" wire:click="$set('showCustomer', true)">
                    <span class="avatar avatar-sm avatar-item {{ $customer ? 'bg-primary text-white' : 'bg-light text-muted' }} rounded-circle"><i class="ri-user-3-line"></i></span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="fw-semibold d-block text-truncate">{{ $customer?->name ?? 'Walk-in customer' }}</span>
                        <span class="fs-12 text-muted">
                            @if ($customer)
                                {{ $customer->phone }} · <span class="{{ $customer->isWholesale() ? 'text-warning fw-semibold' : '' }}">{{ ucfirst($customer->type) }} prices</span>
                                @if ((float) $customer->current_balance != 0) · Khata {{ money($customer->current_balance) }}@endif
                            @else
                                Tap to choose or add a customer
                            @endif
                        </span>
                    </span>
                    <i class="ri-arrow-right-s-line text-muted"></i>
                </button>
                @if ($exchange)
                    <div class="alert alert-info py-2 px-3 mt-2 mb-0 fs-13 d-flex align-items-center gap-2">
                        <i class="ri-exchange-line"></i><span>Exchange credit <strong>{{ money($exchange->refund_amount) }}</strong> from {{ $exchange->number }}</span>
                        <button type="button" class="btn-close btn-sm ms-auto" wire:click="$set('exchangeReturnId', null)" aria-label="Remove exchange credit"></button>
                    </div>
                @endif
            </div>

            <div class="pos-lines">
                @forelse ($lines as $key => $line)
                    <div class="pos-line" wire:key="line-{{ $key }}">
                        <div class="d-flex gap-2">
                            @if ($line['type'] === 'laser')
                                <span class="mw-thumb d-inline-flex align-items-center justify-content-center flex-shrink-0 bg-dark text-white"><i class="ri-scissors-cut-line fs-18"></i></span>
                            @else
                                <img src="{{ $line['image'] }}" class="mw-thumb flex-shrink-0" alt="">
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="title text-truncate">{{ $line['name'] }}</div>
                                <div class="meta text-truncate">{{ $line['label'] ?? $line['sku'] ?? '' }}</div>
                                @if ($line['type'] === 'laser')
                                    <div class="meta">{{ (float) $line['width'] }}×{{ (float) $line['height'] }} mm · {{ qty($line['area']) }} cm² material</div>
                                @elseif (($line['track'] ?? true) && ($stock[$line['variant_id']] ?? 0) < $line['qty'])
                                    <div class="meta text-danger"><i class="ri-error-warning-line"></i> Only {{ qty($stock[$line['variant_id']] ?? 0) }} in stock</div>
                                @endif
                            </div>
                            <button type="button" class="btn btn-link text-muted p-0 align-self-start" wire:click="removeLine('{{ $key }}')" aria-label="Remove"><i class="ri-close-line fs-18"></i></button>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                            <div class="pos-qty">
                                <button type="button" wire:click="changeQty('{{ $key }}', -1)" aria-label="Decrease"><i class="ri-subtract-line"></i></button>
                                <input type="number" min="1" wire:model.blur="lines.{{ $key }}.qty" aria-label="Quantity">
                                <button type="button" wire:click="changeQty('{{ $key }}', 1)" aria-label="Increase"><i class="ri-add-line"></i></button>
                            </div>
                            <span class="text-muted">×</span>
                            @if ($line['type'] === 'laser')
                                <div class="input-group input-group-sm pos-price-input"><span class="input-group-text">Rs</span><input type="number" min="0" class="form-control" wire:model.blur="lines.{{ $key }}.fee" title="Service fee per piece"></div>
                            @else
                                <div class="input-group input-group-sm pos-price-input"><span class="input-group-text">Rs</span><input type="number" min="0" class="form-control {{ ! empty($line['manual']) ? 'border-warning' : '' }}" wire:model.blur="lines.{{ $key }}.price" title="Unit price"></div>
                                <div class="input-group input-group-sm" style="width: 104px" title="Line discount (Rs.)"><span class="input-group-text"><i class="ri-price-tag-3-line"></i></span><input type="number" min="0" class="form-control" placeholder="0" wire:model.blur="lines.{{ $key }}.discount"></div>
                            @endif
                            <span class="ms-auto fw-bold">{{ money(\App\Services\Pos\PosCart::lineTotal($line)) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="ri-shopping-basket-2-line fs-1 d-block mb-2"></i>
                        Scan a barcode or tap a product to start.
                        <div class="fs-12 mt-3"><span class="pos-kbd">F2</span> search · <span class="pos-kbd">F4</span> laser cut · <span class="pos-kbd">F8</span> hold · <span class="pos-kbd">F9</span> pay</div>
                    </div>
                @endforelse
            </div>

            <div class="pos-totals">
                <div class="d-flex gap-2 mb-2">
                    <div class="input-group input-group-sm">
                        <select class="form-select" style="max-width: 70px" wire:model.live="discount.type" aria-label="Discount type"><option value="amount">Rs</option><option value="percent">%</option></select>
                        <input type="number" min="0" class="form-control" placeholder="Discount" wire:model.live.debounce.500ms="discount.value" aria-label="Discount">
                    </div>
                    <input type="text" class="form-control form-control-sm text-uppercase" placeholder="Coupon" wire:model.live.debounce.600ms="coupon" aria-label="Coupon" style="max-width: 130px">
                </div>
                @if ($coupon && $totals['coupon_error'])<div class="text-danger fs-12 mb-1">{{ $totals['coupon_error'] }}</div>@endif
                <div class="row-t"><span class="text-muted">Items ({{ $totals['items'] }})</span><span>{{ money($totals['product_gross']) }}</span></div>
                @if ($totals['line_discounts'] > 0)<div class="row-t"><span class="text-muted">Line discounts</span><span class="text-danger">−{{ money($totals['line_discounts']) }}</span></div>@endif
                @if ($totals['laser'] > 0)<div class="row-t"><span class="text-muted">Laser services</span><span>{{ money($totals['laser']) }}</span></div>@endif
                @if ($totals['cart_discount'] > 0)<div class="row-t"><span class="text-muted">Discount</span><span class="text-danger">−{{ money($totals['cart_discount']) }}</span></div>@endif
                @if ($totals['coupon_discount'] > 0)<div class="row-t"><span class="text-muted">Coupon {{ $totals['coupon']?->code }}</span><span class="text-danger">−{{ money($totals['coupon_discount']) }}</span></div>@endif
                <div class="d-flex justify-content-between align-items-baseline mt-1"><span class="fw-semibold">Total</span><span class="grand">{{ money($totals['total']) }}</span></div>
            </div>

            <div class="pos-actions">
                <button type="button" class="btn btn-light" x-data @click="confirmAction('Clear this sale?', () => $wire.clearSale(), { confirmText: 'Clear sale' })" @disabled(! $lines)><i class="ri-delete-bin-line d-block"></i>Clear</button>
                <button type="button" class="btn btn-warning" wire:click="hold" @disabled(! $lines)><i class="ri-pause-line d-block"></i>Hold<span class="pos-kbd">F8</span></button>
                <button type="button" class="btn btn-success pos-pay-btn" wire:click="openPay" @disabled(! $lines)>Pay {{ money($totals['total']) }}<span class="pos-kbd">F9</span></button>
            </div>
        </aside>
    </div>

    {{-- Variant picker --}}
    <x-admin.modal :show="(bool) $picker" close="$set('pickerProductId', null)" :title="$picker?->name ?? ''" subtitle="Choose the variant">
        @if ($picker)
            <div class="row g-2">
                @foreach ($picker->activeVariants as $v)
                    <div class="col-sm-6" wire:key="pv-{{ $v->id }}">
                        <button type="button" class="pos-variant" wire:click="addVariant({{ $v->id }})">
                            <div class="d-flex align-items-center gap-2">
                                @if ($v->color?->hex_code)<span class="mw-swatch" style="background: {{ $v->color->hex_code }}"></span>@endif
                                <span class="fw-semibold">{{ $v->label }}</span>
                            </div>
                            <div class="d-flex justify-content-between mt-1 fs-13">
                                <span class="text-primary fw-bold">{{ money(app(\App\Services\PricingService::class)->variantPrice($v, $tier, $picker)['price']) }}</span>
                                <span class="{{ $v->availableQuantity() <= 0 ? 'text-danger' : 'text-muted' }}">{{ qty($v->availableQuantity()) }} in stock</span>
                            </div>
                            <div class="fs-11 text-muted">{{ $v->sku }}</div>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-admin.modal>

    {{-- Laser cut --}}
    <x-admin.modal :show="$showLaser" close="$set('showLaser', false)" title="Laser cut job" subtitle="Added to this sale; the job goes to the laser queue when the sale is paid." size="lg">
        <form wire:submit="addLaser" id="laser-form" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Phone brand</label>
                <select class="form-select" wire:model.live="laser.device_brand_id">
                    <option value="">Choose…</option>
                    @foreach ($deviceBrands as $db)<option value="{{ $db->id }}">{{ $db->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Model</label>
                <select class="form-select @error('laser.device_model_id') is-invalid @enderror" wire:model.live="laser.device_model_id" @disabled($deviceModels->isEmpty())>
                    <option value="">Choose…</option>
                    @foreach ($deviceModels as $dm)<option value="{{ $dm->id }}">{{ $dm->name }}</option>@endforeach
                </select>
                @error('laser.device_model_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Side</label>
                <select class="form-select" wire:model.live="laser.side"><option value="front">Front</option><option value="back">Back</option><option value="full">Full body</option></select>
            </div>
            <div class="col-12">
                <label class="form-label">Material</label>
                <select class="form-select @error('laser.material_variant_id') is-invalid @enderror" wire:model.live="laser.material_variant_id">
                    <option value="">Choose material…</option>
                    @foreach ($materials->groupBy('product.name') as $name => $group)
                        <optgroup label="{{ $name }}">
                            @foreach ($group as $m)<option value="{{ $m->id }}">{{ $m->label }} — {{ qty($m->availableQuantity()) }} cm² left</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('laser.material_variant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3"><label class="form-label">Width (mm)</label><input type="number" step="0.1" class="form-control @error('laser.width') is-invalid @enderror" wire:model.live.debounce.400ms="laser.width"></div>
            <div class="col-6 col-md-3"><label class="form-label">Height (mm)</label><input type="number" step="0.1" class="form-control @error('laser.height') is-invalid @enderror" wire:model.live.debounce.400ms="laser.height"></div>
            <div class="col-6 col-md-2"><label class="form-label">Pieces</label><input type="number" min="1" class="form-control" wire:model.live="laser.qty"></div>
            <div class="col-6 col-md-4">
                <label class="form-label">Service fee (per piece)</label>
                <div class="input-group"><span class="input-group-text">Rs</span><input type="number" min="0" class="form-control @error('laser.fee') is-invalid @enderror" wire:model="laser.fee" placeholder="{{ $lastFee !== null ? 'Last: '.number_format($lastFee) : 'Enter fee' }}"></div>
                @error('laser.fee')<div class="text-danger fs-12">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><label class="form-label">Notes for the operator</label><input class="form-control" wire:model="laser.notes" placeholder="e.g. leave camera cut-out wider"></div>
            @if ($laserArea)
                @php($left = $laserMaterial->availableQuantity())
                <div class="col-12">
                    <div class="alert {{ $laserArea['total'] > $left ? 'alert-danger' : 'alert-light' }} mb-0 py-2 fs-13">
                        <i class="ri-ruler-2-line me-1"></i> Uses <strong>{{ qty($laserArea['total']) }} cm²</strong> ({{ qty($laserArea['piece']) }} cm² per piece + {{ (float) $laserMaterial->product->wastage_percent }}% wastage) · {{ qty($left) }} cm² left on this roll
                        @if ($laserArea['total'] > $left)<strong class="d-block">Not enough material in stock — the sale can still go through but stock will go negative.</strong>@endif
                    </div>
                </div>
            @endif
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showLaser', false)">Cancel</button>
            <button type="submit" form="laser-form" class="btn btn-dark"><i class="ri-scissors-cut-line me-1"></i>Add to sale</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Customer --}}
    <x-admin.modal :show="$showCustomer" close="$set('showCustomer', false)" title="Customer" size="lg">
        <div class="row g-4">
            <div class="col-md-7">
                <input type="search" class="form-control mb-2" placeholder="Search name or phone…" wire:model.live.debounce.300ms="customerSearch" autofocus>
                <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" wire:click="selectCustomer(null)"><span><i class="ri-walk-line me-2"></i>Walk-in customer</span>@unless ($customer)<i class="ri-check-line text-primary"></i>@endunless</button>
                    @foreach ($customers as $c)
                        <button type="button" class="list-group-item list-group-item-action" wire:click="selectCustomer({{ $c->id }})" wire:key="cs-{{ $c->id }}">
                            <div class="d-flex justify-content-between"><span class="fw-semibold">{{ $c->name }}</span>@if ($c->isWholesale())<span class="badge bg-warning-subtle text-warning">Wholesale</span>@endif</div>
                            <div class="fs-12 text-muted">{{ $c->phone }} @if ((float) $c->current_balance != 0)· Khata {{ money($c->current_balance) }}@endif</div>
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="col-md-5">
                <h6>New customer</h6>
                <form wire:submit="createCustomer" class="d-grid gap-2">
                    <input class="form-control @error('newCustomer.name') is-invalid @enderror" placeholder="Name *" wire:model="newCustomer.name">
                    <input class="form-control @error('newCustomer.phone') is-invalid @enderror" placeholder="Phone (03XX…)" wire:model="newCustomer.phone">
                    @error('newCustomer.phone')<div class="text-danger fs-12">{{ $message }}</div>@enderror
                    <select class="form-select" wire:model="newCustomer.type"><option value="retail">Retail</option><option value="wholesale">Wholesale</option></select>
                    <button class="btn btn-primary" type="submit"><i class="ri-user-add-line me-1"></i>Add & select</button>
                </form>
            </div>
        </div>
    </x-admin.modal>

    {{-- Payment --}}
    <x-admin.modal :show="$showPay" close="$set('showPay', false)" :title="'Payment — '.money($totals['total'])" size="lg">
        <div class="row g-4">
            <div class="col-md-7">
                @if ($calc['exchange'] > 0)
                    <div class="alert alert-info py-2 fs-13"><i class="ri-exchange-line me-1"></i>Exchange credit applied: <strong>{{ money($calc['exchange']) }}</strong>
                        @if ($exchange && (float) $exchange->refund_amount > $calc['exchange'])<br>{{ money((float) $exchange->refund_amount - $calc['exchange']) }} will be returned to the customer in cash.@endif
                    </div>
                @endif

                <label class="form-label fw-semibold"><i class="ri-money-dollar-circle-line me-1"></i>Cash received</label>
                <div class="input-group input-group-lg mb-2">
                    <span class="input-group-text">Rs</span>
                    <input type="number" min="0" class="form-control" wire:model.live.debounce.250ms="pay.cash" placeholder="{{ number_format($calc['cash_due']) }}" x-init="$nextTick(() => $el.focus())" wire:keydown.enter="complete">
                </div>
                <div class="pos-quick-cash d-flex flex-wrap gap-2 mb-4">
                    <button type="button" class="btn btn-light" wire:click="quickPay('cash')">Exact</button>
                    @foreach (collect([500, 1000, 5000])->map(fn ($r) => (int) (ceil(max($calc['cash_due'], 1) / $r) * $r))->unique()->filter(fn ($v) => $v > $calc['cash_due']) as $round)
                        <button type="button" class="btn btn-light" wire:click="quickPay('cash', {{ $round }})">{{ number_format($round) }}</button>
                    @endforeach
                </div>

                <label class="form-label fw-semibold"><i class="ri-bank-line me-1"></i>Bank transfer</label>
                <div class="row g-2 mb-4">
                    <div class="col-5"><div class="input-group"><span class="input-group-text">Rs</span><input type="number" min="0" class="form-control" wire:model.live.debounce.300ms="pay.bank" placeholder="0"></div></div>
                    <div class="col-7"><select class="form-select" wire:model="pay.bank_account_id">@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->bank_name }} …{{ substr($b->account_number, -4) }}</option>@endforeach</select></div>
                    <div class="col-8"><input class="form-control form-control-sm" placeholder="Transaction ref (optional)" wire:model="pay.bank_reference"></div>
                    <div class="col-4"><button type="button" class="btn btn-sm btn-light w-100" wire:click="quickPay('bank')">Rest by bank</button></div>
                </div>

                <label class="form-label fw-semibold"><i class="ri-book-2-line me-1"></i>Khata (customer credit)</label>
                @if ($customer)
                    <div class="row g-2">
                        <div class="col-5"><div class="input-group"><span class="input-group-text">Rs</span><input type="number" min="0" class="form-control" wire:model.live.debounce.300ms="pay.credit" placeholder="0"></div></div>
                        <div class="col-7"><button type="button" class="btn btn-light w-100" wire:click="quickPay('credit')">Rest on {{ Str::before($customer->name, ' ') }}’s khata</button></div>
                    </div>
                @else
                    <p class="text-muted fs-13 mb-0">Choose a customer to sell on credit.</p>
                @endif
            </div>
            <div class="col-md-5">
                <div class="bg-light rounded-3 p-3 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between fs-14 mb-1"><span>Total</span><strong>{{ money($totals['total']) }}</strong></div>
                    @if ($calc['exchange'] > 0)<div class="d-flex justify-content-between fs-14"><span>Exchange</span><span>{{ money($calc['exchange']) }}</span></div>@endif
                    @if ($calc['bank'] > 0)<div class="d-flex justify-content-between fs-14"><span>Bank</span><span>{{ money($calc['bank']) }}</span></div>@endif
                    @if ($calc['credit'] > 0)<div class="d-flex justify-content-between fs-14"><span>Khata</span><span>{{ money($calc['credit']) }}</span></div>@endif
                    @if ($calc['cash'] > 0)<div class="d-flex justify-content-between fs-14"><span>Cash</span><span>{{ money($calc['cash']) }}</span></div>@endif
                    <hr>
                    @if ($calc['remaining'] > 0.009)
                        <div class="text-muted fs-13">Still to pay</div>
                        <div class="pos-change text-danger">{{ money($calc['remaining']) }}</div>
                    @else
                        <div class="text-muted fs-13">Change to give</div>
                        <div class="pos-change text-success">{{ money($calc['change']) }}</div>
                    @endif
                    @error('pay')<div class="alert alert-danger py-2 fs-13 mt-2 mb-0">{{ $message }}</div>@enderror
                    <button type="button" class="btn btn-success btn-lg w-100 mt-auto" style="height: 60px" wire:click="complete" wire:loading.attr="disabled" wire:target="complete" @disabled($calc['remaining'] > 0.009 || $calc['over'])>
                        <span wire:loading.remove wire:target="complete"><i class="ri-check-double-line me-1"></i>Complete sale</span>
                        <span wire:loading wire:target="complete"><span class="spinner-border spinner-border-sm"></span></span>
                    </button>
                </div>
            </div>
            <div class="col-12"><input class="form-control form-control-sm" placeholder="Note on this sale (optional)" wire:model="note"></div>
        </div>
    </x-admin.modal>

    {{-- Khata collection --}}
    <x-admin.modal :show="$showCollect" close="$set('showCollect', false)" title="Receive khata payment" subtitle="A customer paying off what they owe. A payment receipt prints automatically.">
        <form wire:submit="saveCollect" id="collect-form">
            @if ($collectCustomer)
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light mb-3">
                    <span class="avatar avatar-sm avatar-item bg-primary text-white rounded-circle"><i class="ri-user-3-line"></i></span>
                    <div class="flex-grow-1"><div class="fw-semibold">{{ $collectCustomer->name }}</div><div class="fs-12 text-muted">{{ $collectCustomer->phone }}</div></div>
                    <div class="text-end"><div class="fs-12 text-muted">Owes</div><div class="fw-bold text-danger">{{ money($collectCustomer->current_balance) }}</div></div>
                    <button type="button" class="btn btn-sm btn-light" wire:click="$set('collect.customer_id', null)">Change</button>
                </div>
            @else
                <div class="form-icon mb-2"><input type="search" class="form-control form-control-icon" placeholder="Search name or phone" wire:model.live.debounce.300ms="collectSearch"><i class="ri-search-2-line text-muted"></i></div>
                <div class="list-group mb-3">
                    @forelse ($debtors as $d)
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" wire:click="pickCollectCustomer({{ $d->id }})" wire:key="debtor-{{ $d->id }}">
                            <span><span class="fw-semibold">{{ $d->name }}</span> <span class="text-muted fs-12">{{ $d->phone }}</span></span>
                            <span class="text-danger fw-semibold">{{ money($d->current_balance) }}</span>
                        </button>
                    @empty
                        <div class="list-group-item text-muted fs-13">No customers with a khata balance found.</div>
                    @endforelse
                </div>
                @error('collect.customer_id')<div class="text-danger fs-13 mb-2">{{ $message }}</div>@enderror
            @endif
            <div class="row g-3">
                <div class="col-12">
                    <div class="btn-group w-100">
                        <input type="radio" class="btn-check" id="col-cash" value="cash" wire:model.live="collect.method"><label class="btn btn-outline-primary" for="col-cash"><i class="ri-money-dollar-circle-line me-1"></i>Cash</label>
                        <input type="radio" class="btn-check" id="col-bank" value="bank" wire:model.live="collect.method"><label class="btn btn-outline-primary" for="col-bank"><i class="ri-bank-line me-1"></i>Bank transfer</label>
                    </div>
                </div>
                @if (($collect['method'] ?? 'cash') === 'bank')
                    <div class="col-12"><select class="form-select @error('collect.bank_account_id') is-invalid @enderror" wire:model="collect.bank_account_id">@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->displayName() }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" min="0" step="0.01" class="form-control form-control-lg @error('collect.amount') is-invalid @enderror" wire:model="collect.amount">@error('collect.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Note</label><input class="form-control" wire:model="collect.note" placeholder="Optional"></div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showCollect', false)">Cancel</button>
            <button type="submit" form="collect-form" class="btn btn-primary" wire:loading.attr="disabled"><i class="ri-printer-line me-1"></i>Receive & print</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Held sales --}}
    <x-admin.modal :show="$showHeld" close="$set('showHeld', false)" title="Held sales">
        @forelse ($held as $h)
            <div class="d-flex align-items-center gap-3 py-2 border-bottom" wire:key="held-{{ $h->id }}">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $h->number }} · {{ $h->customer_name }}</div>
                    <div class="fs-12 text-muted">{{ $h->items_count }} items · {{ money($h->grand_total) }} · {{ $h->created_at->diffForHumans() }}@if ($h->staff_notes) · {{ $h->staff_notes }}@endif</div>
                </div>
                <button type="button" class="btn btn-sm btn-primary" wire:click="resume({{ $h->id }})">Resume</button>
                <button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete held sale {{ $h->number }}?', () => $wire.deleteHeld({{ $h->id }}))"><i class="ri-delete-bin-line"></i></button>
            </div>
        @empty
            <p class="text-muted text-center py-4 mb-0">No held sales.</p>
        @endforelse
    </x-admin.modal>

    {{-- Sale completed --}}
    <x-admin.modal :show="(bool) $completed" close="$set('completedOrderId', null)" title="Sale complete">
        @if ($completed)
            <div class="text-center">
                <div class="avatar avatar-xl avatar-item bg-success text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center"><i class="ri-check-line fs-1"></i></div>
                <h4 class="mb-1">{{ $completed->number }}</h4>
                <p class="text-muted">{{ money($completed->grand_total) }} · {{ $completed->paymentMethodLabel() }}</p>
                @if ((float) $completed->change_due > 0)<div class="pos-change text-success mb-3">Change: {{ money($completed->change_due) }}</div>@endif
                @if ((float) $completed->due_total > 0)<p class="text-warning fw-semibold">{{ money($completed->due_total) }} added to {{ $completed->customer_name }}’s khata</p>@endif
                @if ($completed->service_fee_total > 0)<p class="fs-13 text-muted"><i class="ri-scissors-cut-line"></i> Laser job(s) sent to the queue.</p>@endif
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button type="button" class="btn btn-primary" @click="window.open('{{ route('admin.pos.receipt', ['order' => $completed, 'format' => '80mm']) }}', 'mw-receipt', 'width=440,height=720')"><i class="ri-printer-line me-1"></i>Receipt 80mm</button>
                    <button type="button" class="btn btn-light" @click="window.open('{{ route('admin.pos.receipt', ['order' => $completed, 'format' => 'a4']) }}', 'mw-receipt-a4', 'width=900,height=900')"><i class="ri-file-paper-2-line me-1"></i>A4 invoice</button>
                </div>
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-success w-100" wire:click="$set('completedOrderId', null)" x-on:click="$dispatch('pos-focus')">New sale</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
