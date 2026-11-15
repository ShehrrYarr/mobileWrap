<?php

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductColorImage;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Product editor')] class extends Component
{
    use WithFileUploads;

    public ?int $productId = null;

    public array $form = [
        'name' => '', 'slug' => '', 'category_id' => '', 'brand_id' => '', 'type' => 'standard',
        'material_kind' => '', 'wastage_percent' => 0, 'short_description' => '', 'description' => '',
        'sku_prefix' => '', 'is_active' => true, 'show_online' => true, 'is_featured' => false,
        'track_stock' => true, 'low_stock_threshold' => 5, 'meta_title' => '', 'meta_description' => '',
    ];

    public array $deviceIds = [];

    /** [{key, id, name, hex, is_default, images: [{id, path}]}] */
    public array $colors = [];

    /** Temporary uploads keyed by colour key. */
    public array $uploads = [];

    /** attribute_id => [value_id, …] used by this product */
    public array $optionValues = [];

    public array $newValue = [];

    /** [{id, color_key, value_ids, label, sku, barcode, cost, retail, wholesale, compare, stock, is_active, is_default}] */
    public array $variants = [];

    public array $bulk = ['cost' => '', 'retail' => '', 'wholesale' => '', 'compare' => '', 'stock' => ''];

    public function mount(?Product $product = null): void
    {
        foreach (Attribute::pluck('id') as $attrId) {
            $this->optionValues[$attrId] = [];
        }

        if ($product && $product->exists) {
            $this->productId = $product->id;
            $this->form = array_merge($this->form, $product->only(array_keys($this->form)));
            $this->form['category_id'] = (string) $product->category_id;
            $this->form['brand_id'] = (string) ($product->brand_id ?? '');
            $this->form['material_kind'] = $product->material_kind ?? '';
            $this->form['wastage_percent'] = (float) $product->wastage_percent;
            $this->form['low_stock_threshold'] = (float) $product->low_stock_threshold;
            foreach (['short_description', 'description', 'sku_prefix', 'meta_title', 'meta_description'] as $f) {
                $this->form[$f] = (string) ($this->form[$f] ?? '');
            }

            $this->deviceIds = $product->deviceModels()->pluck('device_models.id')->map(fn ($id) => (string) $id)->all();

            $keys = [];
            foreach ($product->colors()->with('images')->get() as $color) {
                $key = 'c'.$color->id;
                $keys[$color->id] = $key;
                $this->colors[] = [
                    'key' => $key, 'id' => $color->id, 'name' => $color->name, 'hex' => $color->hex_code ?? '',
                    'is_default' => $color->is_default,
                    'images' => $color->images->map(fn ($i) => ['id' => $i->id, 'path' => $i->path])->all(),
                ];
            }

            foreach ($product->variants()->with('attributeValues', 'stock')->get() as $v) {
                foreach ($v->attributeValues as $av) {
                    $this->optionValues[$av->attribute_id] = array_values(array_unique(array_merge($this->optionValues[$av->attribute_id] ?? [], [(string) $av->id])));
                }
                $this->variants[] = [
                    'id' => $v->id,
                    'color_key' => $keys[$v->product_color_id] ?? null,
                    'value_ids' => $v->attributeValues->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all(),
                    'label' => $v->label,
                    'sku' => $v->sku,
                    'barcode' => $v->barcode ?? '',
                    'cost' => (float) $v->cost_price,
                    'retail' => (float) $v->retail_price,
                    'wholesale' => (float) $v->wholesale_price,
                    'compare' => $v->compare_at_price !== null ? (float) $v->compare_at_price : '',
                    'stock' => (float) ($v->stock?->quantity ?? 0),
                    'is_active' => $v->is_active,
                    'is_default' => $v->is_default,
                ];
            }
        } else {
            $this->addColor('Black', '#1E1F22');
            $this->generateVariants();
        }
    }

    public function updatedFormName($value): void
    {
        if (! $this->productId && blank($this->form['sku_prefix'])) {
            $words = collect(preg_split('/\s+/', Str::ascii($value)))->filter()->take(3);
            $this->form['sku_prefix'] = strtoupper($words->map(fn ($w) => Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $w), 0, 3))->implode('-'));
        }
    }

    public function updatedFormType($value): void
    {
        if ($value === 'raw_material') {
            $this->form['show_online'] = false;
            $this->form['is_featured'] = false;
            $this->form['low_stock_threshold'] = 5000;
        }
    }

    // ── Colours & images ─────────────────────────────────────────────

    public function addColor(string $name = '', string $hex = '#6B7280'): void
    {
        $this->colors[] = [
            'key' => 'n'.Str::random(6), 'id' => null, 'name' => $name, 'hex' => $hex,
            'is_default' => count($this->colors) === 0, 'images' => [],
        ];
    }

    public function removeColor(string $key): void
    {
        $this->colors = array_values(array_filter($this->colors, fn ($c) => $c['key'] !== $key));
        $this->variants = array_values(array_filter($this->variants, fn ($v) => $v['color_key'] !== $key));
        if ($this->colors && ! collect($this->colors)->contains('is_default', true)) {
            $this->colors[0]['is_default'] = true;
        }
        if (! $this->variants) {
            $this->generateVariants();
        }
    }

    public function setDefaultColor(string $key): void
    {
        foreach ($this->colors as $i => $c) {
            $this->colors[$i]['is_default'] = $c['key'] === $key;
        }
    }

    public function updatedUploads($value, $key): void
    {
        $colorKey = Str::before($key, '.');
        $files = $this->uploads[$colorKey] ?? [];
        $files = is_array($files) ? $files : [$files];

        $this->validate(["uploads.{$colorKey}.*" => 'image|mimes:jpg,jpeg,png,webp|max:4096'], [], ["uploads.{$colorKey}.*" => 'image']);

        $index = collect($this->colors)->search(fn ($c) => $c['key'] === $colorKey);
        if ($index === false) {
            return;
        }

        foreach ($files as $file) {
            $path = $file->store('products/'.now()->format('Y/m'), 'uploads');
            $this->colors[$index]['images'][] = ['id' => null, 'path' => $path];
        }

        unset($this->uploads[$colorKey]);
    }

    public function removeImage(string $colorKey, int $i): void
    {
        $index = collect($this->colors)->search(fn ($c) => $c['key'] === $colorKey);
        if ($index !== false) {
            array_splice($this->colors[$index]['images'], $i, 1);
        }
    }

    public function moveImage(string $colorKey, int $i, int $dir): void
    {
        $index = collect($this->colors)->search(fn ($c) => $c['key'] === $colorKey);
        $images = $this->colors[$index]['images'] ?? [];
        $j = $i + $dir;
        if ($index === false || ! isset($images[$i], $images[$j])) {
            return;
        }
        [$images[$i], $images[$j]] = [$images[$j], $images[$i]];
        $this->colors[$index]['images'] = $images;
    }

    // ── Devices ──────────────────────────────────────────────────────

    public function selectBrandDevices(int $brandId, bool $on): void
    {
        $ids = DeviceBrand::find($brandId)?->models()->pluck('id')->map(fn ($id) => (string) $id)->all() ?? [];
        $this->deviceIds = $on
            ? array_values(array_unique(array_merge($this->deviceIds, $ids)))
            : array_values(array_diff($this->deviceIds, $ids));
    }

    // ── Options & variants ───────────────────────────────────────────

    public function addOptionValue(int $attributeId): void
    {
        $text = trim($this->newValue[$attributeId] ?? '');
        if ($text === '') {
            return;
        }
        $attr = Attribute::findOrFail($attributeId);
        $value = $attr->values()->firstOrCreate(['value' => $text], ['sort_order' => $attr->values()->count()]);
        $this->optionValues[$attributeId] = array_values(array_unique(array_merge($this->optionValues[$attributeId] ?? [], [(string) $value->id])));
        $this->newValue[$attributeId] = '';
    }

    public function generateVariants(): void
    {
        $combos = [[]];
        foreach ($this->optionValues as $attrId => $valueIds) {
            if (! $valueIds) {
                continue;
            }
            $next = [];
            foreach ($combos as $combo) {
                foreach ($valueIds as $vid) {
                    $next[] = array_merge($combo, [(string) $vid]);
                }
            }
            $combos = $next;
        }

        $colorKeys = collect($this->colors)->pluck('key')->all() ?: [null];
        $existing = collect($this->variants)->keyBy(fn ($v) => ($v['color_key'] ?? '-').'|'.implode(',', collect($v['value_ids'])->sort()->all()));
        $first = $existing->first();
        $values = DB::table('attribute_values')->pluck('value', 'id');

        $rows = [];
        foreach ($colorKeys as $ck) {
            foreach ($combos as $combo) {
                $sorted = collect($combo)->sort()->values()->all();
                $sig = ($ck ?? '-').'|'.implode(',', $sorted);
                if ($existing->has($sig)) {
                    $rows[] = $existing[$sig];

                    continue;
                }
                $color = collect($this->colors)->firstWhere('key', $ck);
                $labelParts = array_filter(array_merge([$color && $color['hex'] ? $color['name'] : null], array_map(fn ($id) => $values[$id] ?? '', $combo)));
                $rows[] = [
                    'id' => null, 'color_key' => $ck, 'value_ids' => $sorted,
                    'label' => $labelParts ? implode(' / ', $labelParts) : 'Standard',
                    'sku' => '', 'barcode' => '',
                    'cost' => $first['cost'] ?? 0, 'retail' => $first['retail'] ?? 0, 'wholesale' => $first['wholesale'] ?? 0, 'compare' => $first['compare'] ?? '',
                    'stock' => 0, 'is_active' => true, 'is_default' => false,
                ];
            }
        }

        if ($rows && ! collect($rows)->contains('is_default', true)) {
            $rows[0]['is_default'] = true;
        }
        $this->variants = $rows;
        $this->fillMissingSkus();
    }

    public function fillMissingSkus(): void
    {
        $prefix = strtoupper(trim($this->form['sku_prefix']) ?: 'SKU');
        $used = collect($this->variants)->pluck('sku')->filter()->all();
        foreach ($this->variants as $i => $v) {
            if (filled($v['sku'])) {
                continue;
            }
            $sku = $prefix.'-'.strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii($v['label'] ?: 'STD')));
            $sku = trim(Str::limit($sku, 60, ''), '-');
            $base = $sku;
            $n = 2;
            while (in_array($sku, $used, true) || ProductVariant::withTrashed()->where('sku', $sku)->when($v['id'], fn ($q) => $q->whereKeyNot($v['id']))->exists()) {
                $sku = $base.'-'.$n++;
            }
            $this->variants[$i]['sku'] = $sku;
            $used[] = $sku;
        }
    }

    public function generateBarcodes(): void
    {
        $reserved = collect($this->variants)->pluck('barcode')->filter()->all();
        foreach ($this->variants as $i => $v) {
            if (blank($v['barcode'])) {
                $code = ProductVariant::generateBarcode($reserved);
                $this->variants[$i]['barcode'] = $code;
                $reserved[] = $code;
            }
        }
    }

    public function applyBulk(): void
    {
        foreach ($this->variants as $i => $v) {
            foreach (['cost', 'retail', 'wholesale', 'compare'] as $f) {
                if ($this->bulk[$f] !== '' && $this->bulk[$f] !== null) {
                    $this->variants[$i][$f] = (float) $this->bulk[$f];
                }
            }
            if ($this->bulk['stock'] !== '' && ! $v['id']) {
                $this->variants[$i]['stock'] = (float) $this->bulk['stock'];
            }
        }
        $this->bulk = ['cost' => '', 'retail' => '', 'wholesale' => '', 'compare' => '', 'stock' => ''];
    }

    public function setDefaultVariant(int $i): void
    {
        foreach ($this->variants as $k => $v) {
            $this->variants[$k]['is_default'] = $k === $i;
        }
    }

    public function removeVariant(int $i): void
    {
        array_splice($this->variants, $i, 1);
        if ($this->variants && ! collect($this->variants)->contains('is_default', true)) {
            $this->variants[0]['is_default'] = true;
        }
    }

    // ── Save ─────────────────────────────────────────────────────────

    public function save()
    {
        $raw = $this->form['type'] === 'raw_material';

        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.slug' => 'nullable|alpha_dash|max:255|unique:products,slug'.($this->productId ? ','.$this->productId : ''),
            'form.category_id' => 'required|exists:categories,id',
            'form.brand_id' => 'nullable|exists:brands,id',
            'form.type' => 'required|in:standard,raw_material',
            'form.material_kind' => $raw ? 'required|in:'.implode(',', array_keys(Product::MATERIAL_KINDS)) : 'nullable',
            'form.wastage_percent' => 'numeric|min:0|max:100',
            'form.short_description' => 'nullable|string|max:500',
            'form.low_stock_threshold' => 'numeric|min:0',
            'colors' => 'required|array|min:1',
            'colors.*.name' => 'required|string|max:60',
            'colors.*.hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'variants' => 'required|array|min:1',
            'variants.*.sku' => 'required|string|max:64|distinct',
            'variants.*.barcode' => 'nullable|string|max:64|distinct',
            'variants.*.cost' => 'required|numeric|min:0',
            'variants.*.retail' => 'required|numeric|min:0',
            'variants.*.wholesale' => 'nullable|numeric|min:0',
            'variants.*.compare' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|numeric|min:0',
        ], [
            'colors.*.hex.regex' => 'Use a 6-digit hex like #1E1F22.',
            'variants.*.sku.distinct' => 'SKUs must be unique.',
            'variants.min' => 'Generate at least one variant.',
        ], [
            'form.name' => 'name', 'form.category_id' => 'category', 'colors.*.name' => 'colour name', 'variants.*.sku' => 'SKU',
            'variants.*.retail' => 'retail price', 'variants.*.cost' => 'cost price', 'form.material_kind' => 'material type',
        ]);

        // SKU / barcode uniqueness against the database
        foreach ($this->variants as $i => $v) {
            if (ProductVariant::withTrashed()->where('sku', $v['sku'])->when($v['id'], fn ($q) => $q->whereKeyNot($v['id']))->exists()) {
                $this->addError("variants.$i.sku", 'SKU already used by another product.');

                return;
            }
            if (filled($v['barcode']) && ProductVariant::withTrashed()->where('barcode', $v['barcode'])->when($v['id'], fn ($q) => $q->whereKeyNot($v['id']))->exists()) {
                $this->addError("variants.$i.barcode", 'Barcode already in use.');

                return;
            }
        }

        $isNew = ! $this->productId;

        $product = DB::transaction(function () use ($raw) {
            $product = $this->productId ? Product::findOrFail($this->productId) : new Product;
            $product->fill([
                ...$this->form,
                'slug' => $this->form['slug'] ?: null,
                'brand_id' => $this->form['brand_id'] ?: null,
                'material_kind' => $raw ? $this->form['material_kind'] : null,
                'unit' => $raw ? 'cm2' : 'pcs',
                'show_online' => $raw ? false : (bool) $this->form['show_online'],
            ]);
            if (blank($product->slug)) {
                $product->slug = null;
            }
            $product->save();

            $product->deviceModels()->sync($this->deviceIds);

            // Colours + images
            $keyToId = [];
            $keepColors = [];
            foreach ($this->colors as $i => $c) {
                $color = $c['id'] ? ProductColor::where('product_id', $product->id)->findOrFail($c['id']) : new ProductColor(['product_id' => $product->id]);
                $color->fill(['name' => $c['name'], 'hex_code' => $c['hex'] ?: null, 'sort_order' => $i, 'is_default' => (bool) $c['is_default']])->save();
                $keyToId[$c['key']] = $color->id;
                $keepColors[] = $color->id;

                $keepImages = [];
                foreach ($c['images'] as $j => $img) {
                    if ($img['id']) {
                        ProductColorImage::whereKey($img['id'])->update(['sort_order' => $j, 'product_color_id' => $color->id]);
                        $keepImages[] = $img['id'];
                    } else {
                        $keepImages[] = $color->images()->create(['path' => $img['path'], 'alt' => $product->name.' — '.$c['name'], 'sort_order' => $j])->id;
                    }
                }
                $color->images()->whereNotIn('id', $keepImages)->get()->each->delete();
            }
            ProductColor::where('product_id', $product->id)->whereNotIn('id', $keepColors)->get()->each(function ($color) {
                $color->images->each->delete();
                $color->delete();
            });

            // Variants
            $inventory = app(InventoryService::class);
            $keepVariants = [];
            foreach ($this->variants as $v) {
                $variant = $v['id'] ? ProductVariant::where('product_id', $product->id)->findOrFail($v['id']) : new ProductVariant(['product_id' => $product->id]);
                $variant->fill([
                    'product_color_id' => $keyToId[$v['color_key']] ?? null,
                    'sku' => strtoupper(trim($v['sku'])),
                    'barcode' => filled($v['barcode']) ? trim($v['barcode']) : null,
                    'cost_price' => (float) $v['cost'],
                    'retail_price' => $raw ? 0 : (float) $v['retail'],
                    'wholesale_price' => $raw ? 0 : (float) ($v['wholesale'] ?: 0),
                    'compare_at_price' => ! $raw && filled($v['compare']) && (float) $v['compare'] > 0 ? (float) $v['compare'] : null,
                    'is_active' => (bool) $v['is_active'],
                    'is_default' => (bool) $v['is_default'],
                ])->save();
                $variant->attributeValues()->sync($v['value_ids']);
                $variant->load('color', 'attributeValues');
                $variant->update(['label' => $variant->buildLabel()]);

                if (! $v['id']) {
                    if ((float) $v['stock'] > 0) {
                        $inventory->adjust($variant, (float) $v['stock'], 'opening', null, 'Opening stock', (float) $v['cost']);
                    } else {
                        $variant->stock()->firstOrCreate([], ['quantity' => 0]);
                    }
                }
                $keepVariants[] = $variant->id;
            }
            $product->variants()->whereNotIn('id', $keepVariants)->get()->each->delete();

            return $product;
        });

        session()->flash('toast', ['type' => 'success', 'message' => $isNew ? 'Product created.' : 'Product saved.']);

        return $this->redirectRoute('admin.products.edit', $product, navigate: true);
    }

    public function with(): array
    {
        return [
            'categories' => Category::with('children')->roots()->orderBy('sort_order')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'attributes' => Attribute::with('values')->orderBy('sort_order')->get(),
            'deviceBrands' => DeviceBrand::with('models')->orderBy('sort_order')->get(),
            'isRaw' => $this->form['type'] === 'raw_material',
        ];
    }
}; ?>

<div>
    <x-admin.page-header :title="$productId ? 'Edit product' : 'Add product'" :crumbs="['Products' => route('admin.products.index')]">
        <x-slot:actions>
            @if ($productId && ! $isRaw)
                <a href="{{ route('product', $form['slug']) }}" target="_blank" class="btn btn-sm btn-light"><i class="ri-external-link-line me-1"></i>View in store</a>
            @endif
            <button type="button" class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save"><i class="ri-save-3-line me-1"></i>Save product</span>
                <span wire:loading wire:target="save"><i class="ri-loader-2-line"></i> Saving…</span>
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start">
            <i class="ri-error-warning-line fs-18"></i>
            <div><strong>Please fix the highlighted fields.</strong>
                <ul class="mb-0 ps-3 fs-13">@foreach (array_slice($errors->all(), 0, 5) as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            {{-- Basics --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Product details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.blur="form.name" placeholder="e.g. Liquid Silicone Case">
                            @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Product type</label>
                            <select class="form-select" wire:model.live="form.type">
                                <option value="standard">Accessory (sold)</option>
                                <option value="raw_material">Laser raw material</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select @error('form.category_id') is-invalid @enderror" wire:model="form.category_id">
                                <option value="">Choose…</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @foreach ($cat->children as $child)
                                        <option value="{{ $child->id }}">— {{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            @error('form.category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <select class="form-select" wire:model="form.brand_id">
                                <option value="">No brand</option>
                                @foreach ($brands as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($isRaw)
                            <div class="col-md-6">
                                <label class="form-label">Material type <span class="text-danger">*</span></label>
                                <select class="form-select @error('form.material_kind') is-invalid @enderror" wire:model="form.material_kind">
                                    <option value="">Choose…</option>
                                    @foreach (\App\Models\Product::MATERIAL_KINDS as $k => $label)
                                        <option value="{{ $k }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('form.material_kind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Wastage allowance</label>
                                <div class="input-group">
                                    <input type="number" step="0.5" min="0" class="form-control" wire:model="form.wastage_percent">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text fs-12">Added to each laser job’s cut area when deducting from the roll.</div>
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label">Short description</label>
                            <input type="text" class="form-control" wire:model="form.short_description" maxlength="500" placeholder="One line shown on product cards">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full description</label>
                            <textarea class="form-control" rows="5" wire:model="form.description" placeholder="Features, materials, what’s in the box…"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Colours & images --}}
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-0">Colours & images</h5>
                        <p class="text-muted fs-12 mb-0">Each colour has its own gallery — the storefront swaps images when a shopper picks a swatch.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-primary" wire:click="addColor"><i class="ri-add-line me-1"></i>Add colour</button>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @foreach ($colors as $ci => $color)
                        <div class="mw-color-card p-3 {{ $color['is_default'] ? 'is-default' : '' }}" wire:key="color-{{ $color['key'] }}">
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-md-5">
                                    <label class="form-label fs-12 mb-1">Colour name</label>
                                    <input type="text" class="form-control form-control-sm @error('colors.'.$ci.'.name') is-invalid @enderror" wire:model.blur="colors.{{ $ci }}.name" placeholder="e.g. Midnight">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs-12 mb-1">Swatch</label>
                                    <div class="input-group input-group-sm" x-data>
                                        <input type="color" class="form-control form-control-color p-1" style="max-width: 44px" wire:model.live="colors.{{ $ci }}.hex" value="{{ $color['hex'] ?: '#000000' }}">
                                        <input type="text" class="form-control @error('colors.'.$ci.'.hex') is-invalid @enderror" wire:model.blur="colors.{{ $ci }}.hex" placeholder="#1E1F22 (blank = no swatch)">
                                    </div>
                                    @error('colors.'.$ci.'.hex') <div class="text-danger fs-12">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 d-flex gap-2 justify-content-md-end">
                                    @if ($color['is_default'])
                                        <span class="badge bg-primary-subtle text-primary align-self-center">Default</span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-light" wire:click="setDefaultColor('{{ $color['key'] }}')">Make default</button>
                                    @endif
                                    @if (count($colors) > 1)
                                        <button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" title="Remove colour" x-data @click="confirmAction('Remove “{{ addslashes($color['name']) }}” and its variants?', () => $wire.removeColor('{{ $color['key'] }}'), { confirmText: 'Remove' })"><i class="ri-delete-bin-line"></i></button>
                                    @endif
                                </div>
                            </div>

                            <div class="mw-image-grid">
                                @foreach ($color['images'] as $ii => $img)
                                    <div class="mw-image-tile" wire:key="img-{{ $color['key'] }}-{{ $ii }}-{{ $img['path'] }}">
                                        <img src="{{ upload_url($img['path']) }}" alt="">
                                        @if ($ii === 0) <span class="badge bg-primary">Cover</span> @endif
                                        <div class="mw-tile-actions">
                                            <button type="button" class="btn btn-xs btn-light icon-btn-sm" wire:click="moveImage('{{ $color['key'] }}', {{ $ii }}, -1)" @disabled($ii === 0) title="Move left"><i class="ri-arrow-left-s-line"></i></button>
                                            <button type="button" class="btn btn-xs btn-danger icon-btn-sm" wire:click="removeImage('{{ $color['key'] }}', {{ $ii }})" title="Remove"><i class="ri-close-line"></i></button>
                                            <button type="button" class="btn btn-xs btn-light icon-btn-sm" wire:click="moveImage('{{ $color['key'] }}', {{ $ii }}, 1)" @disabled($ii === count($color['images']) - 1) title="Move right"><i class="ri-arrow-right-s-line"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                                <label class="mw-dropzone d-flex flex-column align-items-center justify-content-center mb-0" style="aspect-ratio: 1"
                                       x-data="{ over: false }" :class="{ 'is-over': over }"
                                       x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false"
                                       x-on:drop.prevent="over = false; $wire.uploadMultiple('uploads.{{ $color['key'] }}', $event.dataTransfer.files)">
                                    <input type="file" class="d-none" multiple accept="image/png,image/jpeg,image/webp" wire:model="uploads.{{ $color['key'] }}">
                                    <span wire:loading.remove wire:target="uploads.{{ $color['key'] }}"><i class="ri-upload-cloud-2-line fs-3 text-primary"></i></span>
                                    <span wire:loading wire:target="uploads.{{ $color['key'] }}" class="spinner-border spinner-border-sm text-primary"></span>
                                    <span class="fs-11 text-muted mt-1">Drop or click</span>
                                </label>
                            </div>
                            @error('uploads.'.$color['key'].'.*') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Variants --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Variant options & pricing</h5>
                    <p class="text-muted fs-12 mb-0">Variants = colour × the options you tick below (e.g. Black / 20000mAh). Each has its own SKU, barcode, prices and stock.</p>
                </div>
                <div class="card-body">
                    @if (! $isRaw)
                        <div class="row g-3 mb-3">
                            @foreach ($attributes as $attr)
                                <div class="col-md-4" wire:key="attr-{{ $attr->id }}">
                                    <div class="border rounded-3 p-3 h-100">
                                        <h6 class="fs-13 mb-2">{{ $attr->name }}</h6>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            @foreach ($attr->values as $val)
                                                <input type="checkbox" class="btn-check" id="av-{{ $val->id }}" value="{{ $val->id }}" wire:model="optionValues.{{ $attr->id }}">
                                                <label class="btn btn-sm btn-outline-primary py-1 px-2" for="av-{{ $val->id }}">{{ $val->value }}</label>
                                            @endforeach
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" placeholder="Add value" wire:model="newValue.{{ $attr->id }}" wire:keydown.enter.prevent="addOptionValue({{ $attr->id }})">
                                            <button class="btn btn-light" type="button" wire:click="addOptionValue({{ $attr->id }})"><i class="ri-add-line"></i></button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="generateVariants"><i class="ri-refresh-line me-1"></i>Generate / refresh variants</button>
                        <button type="button" class="btn btn-sm btn-light" wire:click="generateBarcodes"><i class="ri-barcode-line me-1"></i>Fill empty barcodes</button>
                        <button type="button" class="btn btn-sm btn-light" wire:click="fillMissingSkus"><i class="ri-price-tag-3-line me-1"></i>Fill empty SKUs</button>
                        <div class="ms-auto d-flex align-items-center gap-2">
                            <label class="fs-12 text-muted mb-0">SKU prefix</label>
                            <input type="text" class="form-control form-control-sm" style="width: 120px" wire:model.blur="form.sku_prefix">
                        </div>
                    </div>

                    {{-- Bulk edit --}}
                    <div class="bg-light rounded-3 p-2 mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <span class="fs-12 fw-medium text-muted me-1"><i class="ri-stack-line"></i> Set for all:</span>
                        <input type="number" step="0.01" class="form-control form-control-sm" style="width: 110px" placeholder="{{ $isRaw ? 'Cost/cm²' : 'Cost' }}" wire:model="bulk.cost">
                        @unless ($isRaw)
                            <input type="number" class="form-control form-control-sm" style="width: 110px" placeholder="Retail" wire:model="bulk.retail">
                            <input type="number" class="form-control form-control-sm" style="width: 110px" placeholder="Wholesale" wire:model="bulk.wholesale">
                            <input type="number" class="form-control form-control-sm" style="width: 110px" placeholder="Compare-at" wire:model="bulk.compare">
                        @endunless
                        <input type="number" class="form-control form-control-sm" style="width: 120px" placeholder="{{ $isRaw ? 'Opening cm²' : 'Opening qty' }}" wire:model="bulk.stock">
                        <button type="button" class="btn btn-sm btn-dark" wire:click="applyBulk">Apply</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-mw align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 34px">Def.</th>
                                    <th>Variant</th>
                                    <th style="min-width: 150px">SKU</th>
                                    <th style="min-width: 140px">Barcode</th>
                                    <th style="min-width: 95px">{{ $isRaw ? 'Cost / cm²' : 'Cost' }}</th>
                                    @unless ($isRaw)
                                        <th style="min-width: 95px">Retail</th>
                                        <th style="min-width: 95px">Wholesale</th>
                                        <th style="min-width: 95px">Compare-at</th>
                                    @endunless
                                    <th style="min-width: 90px">{{ $isRaw ? 'Stock cm²' : 'Stock' }}</th>
                                    <th>On</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($variants as $vi => $v)
                                    @php($vc = collect($colors)->firstWhere('key', $v['color_key']))
                                    <tr wire:key="var-{{ $v['id'] ?? 'n' }}-{{ $v['color_key'] }}-{{ implode('-', $v['value_ids']) }}">
                                        <td><input type="radio" class="form-check-input" name="defvar" @checked($v['is_default']) wire:click="setDefaultVariant({{ $vi }})"></td>
                                        <td class="text-nowrap">
                                            <span class="d-inline-flex align-items-center gap-2">
                                                @if ($vc && $vc['hex'])<span class="mw-swatch" style="width:16px;height:16px;background: {{ $vc['hex'] }}"></span>@endif
                                                <span class="fs-13">{{ $v['label'] }}</span>
                                            </span>
                                        </td>
                                        <td><input type="text" class="form-control form-control-sm text-uppercase @error('variants.'.$vi.'.sku') is-invalid @enderror" wire:model.blur="variants.{{ $vi }}.sku">
                                            @error('variants.'.$vi.'.sku') <div class="invalid-feedback">{{ $message }}</div> @enderror</td>
                                        <td><input type="text" class="form-control form-control-sm @error('variants.'.$vi.'.barcode') is-invalid @enderror" wire:model.blur="variants.{{ $vi }}.barcode" placeholder="Scan or auto">
                                            @error('variants.'.$vi.'.barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror</td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.blur="variants.{{ $vi }}.cost"></td>
                                        @unless ($isRaw)
                                            <td><input type="number" min="0" class="form-control form-control-sm @error('variants.'.$vi.'.retail') is-invalid @enderror" wire:model.blur="variants.{{ $vi }}.retail"></td>
                                            <td><input type="number" min="0" class="form-control form-control-sm" wire:model.blur="variants.{{ $vi }}.wholesale"></td>
                                            <td><input type="number" min="0" class="form-control form-control-sm" wire:model.blur="variants.{{ $vi }}.compare" placeholder="—"></td>
                                        @endunless
                                        <td>
                                            @if ($v['id'])
                                                <a href="{{ route('admin.inventory.ledger', $v['id']) }}" wire:navigate class="badge bg-light text-body fs-12" title="Open stock ledger">{{ qty($v['stock']) }} <i class="ri-arrow-right-up-line"></i></a>
                                            @else
                                                <input type="number" min="0" step="any" class="form-control form-control-sm" wire:model.blur="variants.{{ $vi }}.stock" title="Opening stock">
                                            @endif
                                        </td>
                                        <td><div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" wire:model="variants.{{ $vi }}.is_active"></div></td>
                                        <td><button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" wire:click="removeVariant({{ $vi }})" title="Remove variant"><i class="ri-close-line"></i></button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted py-4">Click “Generate variants” to build the list.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="fs-12 text-muted mt-2 mb-0"><i class="ri-information-line"></i> Stock for existing variants is changed from <strong>Inventory</strong> so every movement is logged in the stock ledger.</p>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            {{-- Visibility --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Visibility</h5></div>
                <div class="card-body d-flex flex-column gap-3">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-active" wire:model="form.is_active"><label class="form-check-label" for="f-active">Active (sellable at POS)</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-online" wire:model="form.show_online" @disabled($isRaw)><label class="form-check-label" for="f-online">Show on storefront</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-feat" wire:model="form.is_featured" @disabled($isRaw)><label class="form-check-label" for="f-feat">Featured product</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-track" wire:model="form.track_stock"><label class="form-check-label" for="f-track">Track stock</label></div>
                    <div>
                        <label class="form-label fs-13">Low-stock alert at</label>
                        <div class="input-group input-group-sm">
                            <input type="number" min="0" class="form-control" wire:model="form.low_stock_threshold">
                            <span class="input-group-text">{{ $isRaw ? 'cm²' : 'units' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Devices --}}
            <div class="card" x-data="{ q: '' }">
                <div class="card-header d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Compatible devices</h5>
                    <span class="badge bg-primary-subtle text-primary">{{ count($deviceIds) }} selected</span>
                </div>
                <div class="card-body">
                    <input type="search" class="form-control form-control-sm mb-3" placeholder="Filter models…" x-model="q">
                    <div style="max-height: 360px; overflow-y: auto" class="pe-1">
                        @foreach ($deviceBrands as $db)
                            <div class="mb-3" wire:key="db-{{ $db->id }}">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="fw-semibold fs-13 flex-grow-1">{{ $db->name }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 me-2 fs-12" wire:click="selectBrandDevices({{ $db->id }}, true)">All</button>
                                    <button type="button" class="btn btn-link btn-sm p-0 fs-12 text-muted" wire:click="selectBrandDevices({{ $db->id }}, false)">None</button>
                                </div>
                                @foreach ($db->models as $m)
                                    <div class="form-check" x-show="!q || @js(strtolower($m->name)).includes(q.toLowerCase())">
                                        <input class="form-check-input" type="checkbox" id="dm-{{ $m->id }}" value="{{ $m->id }}" wire:model.live="deviceIds">
                                        <label class="form-check-label fs-13" for="dm-{{ $m->id }}">{{ $m->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.devices') }}" wire:navigate class="fs-12"><i class="ri-settings-4-line"></i> Manage device models</a>
                </div>
            </div>

            {{-- SEO --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Search & URL</h5></div>
                <div class="card-body d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fs-13">URL slug</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">/product/</span>
                            <input type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model.blur="form.slug" placeholder="auto from name">
                        </div>
                        @error('form.slug') <div class="text-danger fs-12">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label fs-13">Meta title</label>
                        <input type="text" class="form-control form-control-sm" wire:model="form.meta_title" maxlength="255">
                    </div>
                    <div>
                        <label class="form-label fs-13">Meta description</label>
                        <textarea class="form-control form-control-sm" rows="3" wire:model="form.meta_description" maxlength="500"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
