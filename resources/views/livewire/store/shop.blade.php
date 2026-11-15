<?php

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithCart, WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: '')]
    public string $device = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: '')]
    public string $min = '';

    #[Url(except: '')]
    public string $max = '';

    #[Url(except: '')]
    public string $color = '';

    #[Url(except: false)]
    public bool $sale = false;

    #[Url(except: false)]
    public bool $instock = false;

    public string $deviceBrand = '';

    public function mount(): void
    {
        if ($this->device && ($m = DeviceModel::where('slug', $this->device)->first())) {
            $this->deviceBrand = (string) $m->device_brand_id;
        }
    }

    public function updated($prop): void
    {
        if ($prop !== 'deviceBrand') {
            $this->resetPage();
        }
    }

    public function clear(string $filter): void
    {
        $this->reset($filter === 'price' ? ['min', 'max'] : [$filter]);
        if ($filter === 'device') {
            $this->deviceBrand = '';
        }
        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset('q', 'category', 'brand', 'device', 'sort', 'min', 'max', 'color', 'sale', 'instock', 'deviceBrand');
        $this->resetPage();
    }

    public function rendering($view): void
    {
        $view->title($this->pageTitle());
    }

    protected function pageTitle(): string
    {
        if ($this->q) {
            return 'Search: '.$this->q;
        }
        if ($this->device && ($m = DeviceModel::with('brand')->where('slug', $this->device)->first())) {
            return 'Accessories for '.$m->full_name;
        }
        if ($this->category && ($c = Category::where('slug', $this->category)->first())) {
            return $c->name;
        }

        return $this->sale ? 'On sale' : 'Shop all';
    }

    public function with(): array
    {
        $cat = $this->category ? Category::with('children', 'parent')->where('slug', $this->category)->first() : null;
        $brand = $this->brand ? Brand::where('slug', $this->brand)->first() : null;
        $model = $this->device ? DeviceModel::with('brand')->where('slug', $this->device)->first() : null;

        $base = Product::online()
            ->search($this->q)
            ->when($cat, fn ($q) => $q->whereIn('category_id', $cat->familyIds()))
            ->when($brand, fn ($q) => $q->where('brand_id', $brand->id))
            ->when($model, fn ($q) => $q->whereHas('deviceModels', fn ($d) => $d->whereKey($model->id)))
            ->when($this->color, fn ($q) => $q->whereHas('colors', fn ($c) => $c->where('name', 'like', '%'.$this->color.'%')))
            ->when($this->min !== '', fn ($q) => $q->whereHas('activeVariants', fn ($v) => $v->where('retail_price', '>=', (float) $this->min)))
            ->when($this->max !== '', fn ($q) => $q->whereHas('activeVariants', fn ($v) => $v->where('retail_price', '<=', (float) $this->max)))
            ->when($this->instock, fn ($q) => $q->whereHas('activeVariants.stock', fn ($s) => $s->whereRaw('quantity - reserved_quantity > 0')))
            ->when($this->sale, function ($q) {
                $deals = app(PricingService::class)->liveDeals();
                $ids = $deals->flatMap(fn ($d) => $d->items->pluck('product_id'))->all();
                $catIds = $deals->where('type', 'category_discount')->pluck('category_id')->filter()->all();
                $catIds = Category::whereIn('id', $catIds)->orWhereIn('parent_id', $catIds ?: [0])->pluck('id')->all();
                $q->where(fn ($q) => $q->whereIn('id', $ids ?: [0])->orWhereIn('category_id', $catIds ?: [0])->orWhereHas('activeVariants', fn ($v) => $v->whereColumn('compare_at_price', '>', 'retail_price')));
            });

        $minPrice = ProductVariant::selectRaw('MIN(retail_price)')->whereColumn('product_id', 'products.id')->where('is_active', true);

        $products = (clone $base)->withCardData()
            ->addSelect(['products.*', 'min_price' => $minPrice])
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('min_price'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('min_price'))
            ->when($this->sort === 'new', fn ($q) => $q->latest('id'))
            ->when($this->sort === 'popular', fn ($q) => $q->orderByDesc('views')->orderByDesc('is_featured'))
            ->when($this->sort === '', fn ($q) => $q->orderByDesc('is_featured')->latest('id'))
            ->paginate(12);

        $onlineIds = Product::online()->pluck('id');

        return [
            'products' => $products,
            'cat' => $cat,
            'model' => $model,
            'title' => $this->pageTitle(),
            'categories' => Category::active()->roots()->where('show_in_menu', true)->with(['children' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->get()
                ->each(fn ($c) => $c->setAttribute('online_count', Product::online()->whereIn('category_id', $c->familyIds())->count())),
            'brands' => Brand::active()->withCount(['products' => fn ($q) => $q->whereIn('id', $onlineIds)])->orderBy('name')->get()->where('products_count', '>', 0),
            'deviceBrands' => DeviceBrand::active()->get(),
            'deviceModels' => $this->deviceBrand ? DeviceModel::active()->where('device_brand_id', $this->deviceBrand)->orderBy('sort_order')->get() : collect(),
            'colorOptions' => ProductColor::whereIn('product_id', $onlineIds)->whereNotNull('hex_code')
                ->select('name', DB::raw('MIN(hex_code) as hex'), DB::raw('COUNT(*) as c'))->groupBy('name')->orderByDesc('c')->limit(14)->get(),
            'wishlist' => $this->wishlistIds(),
            'activeCount' => collect([$this->category, $this->brand, $this->device, $this->color, $this->min.$this->max, $this->sale, $this->instock, $this->q])->filter()->count(),
        ];
    }
}; ?>

<div x-data="{ filters: false }">
    <section class="sf-section-sm pb-0">
        <div class="container">
            <nav class="sf-breadcrumb pt-0" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" wire:navigate>Home</a><i class="ri-arrow-right-s-line"></i>
                <a href="{{ route('shop') }}" wire:navigate>Shop</a>
                @if ($cat?->parent)<i class="ri-arrow-right-s-line"></i><a href="{{ route('shop', ['category' => $cat->parent->slug]) }}" wire:navigate>{{ $cat->parent->name }}</a>@endif
                @if ($cat)<i class="ri-arrow-right-s-line"></i><span>{{ $cat->name }}</span>@endif
            </nav>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                <div>
                    <h1 class="sf-title">{{ $title }}</h1>
                    <p class="sf-sub mb-0">
                        @if ($model) Cases, glass, wraps and more that fit your {{ $model->full_name }}.
                        @elseif ($cat?->description) {{ $cat->description }}
                        @else {{ $products->total() }} {{ Str::plural('product', $products->total()) }} · Cash on Delivery nationwide @endif
                    </p>
                </div>
            </div>
            @if ($cat && $cat->children->isNotEmpty())
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @foreach ($cat->children as $child)
                        <a href="{{ route('shop', ['category' => $child->slug]) }}" wire:navigate class="sf-chip">{{ $child->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="sf-section pt-4">
        <div class="container">
            <div class="row g-4">
                {{-- Filters --}}
                <aside class="col-lg-3">
                    <div class="sf-filters" :class="{ 'is-open': filters }">
                        <div class="sf-filters-inner">
                            <div class="d-flex d-lg-none justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Filters</h5>
                                <button type="button" class="sf-icon-btn" @click="filters = false" aria-label="Close filters"><i class="ri-close-line"></i></button>
                            </div>

                            <div class="sf-filter-group">
                                <div class="sf-filter-title">Category</div>
                                <div class="sf-filter-list">
                                    <button type="button" class="sf-filter-item {{ $category === '' ? 'fw-bold' : '' }}" wire:click="$set('category', '')">All products</button>
                                    @foreach ($categories as $c)
                                        <button type="button" class="sf-filter-item {{ $category === $c->slug ? 'fw-bold' : '' }}" wire:click="$set('category', '{{ $c->slug }}')">
                                            <i class="{{ $c->icon }} opacity-75"></i>{{ $c->name }}<span class="count">{{ $c->online_count }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="sf-filter-group">
                                <div class="sf-filter-title">Your phone</div>
                                <select class="form-select mb-2" wire:model.live="deviceBrand" aria-label="Phone brand">
                                    <option value="">Choose brand…</option>
                                    @foreach ($deviceBrands as $db)<option value="{{ $db->id }}">{{ $db->name }}</option>@endforeach
                                </select>
                                @if ($deviceModels->isNotEmpty())
                                    <select class="form-select" wire:model.live="device" aria-label="Phone model">
                                        <option value="">Any model</option>
                                        @foreach ($deviceModels as $dm)<option value="{{ $dm->slug }}">{{ $dm->name }}</option>@endforeach
                                    </select>
                                @endif
                            </div>

                            <div class="sf-filter-group">
                                <div class="sf-filter-title">Price (Rs.)</div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="number" class="form-control" placeholder="Min" wire:model.live.debounce.600ms="min" min="0" aria-label="Minimum price">
                                    <span class="sf-muted">–</span>
                                    <input type="number" class="form-control" placeholder="Max" wire:model.live.debounce.600ms="max" min="0" aria-label="Maximum price">
                                </div>
                            </div>

                            @if ($colorOptions->isNotEmpty())
                                <div class="sf-filter-group">
                                    <div class="sf-filter-title">Colour</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($colorOptions as $co)
                                            <button type="button" class="sf-swatch {{ $color === $co->name ? 'is-active' : '' }}" style="width: 30px; height: 30px; background: {{ $co->hex }}" title="{{ $co->name }}" aria-label="{{ $co->name }}"
                                                    wire:click="$set('color', '{{ $color === $co->name ? '' : addslashes($co->name) }}')"></button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($brands->isNotEmpty())
                                <div class="sf-filter-group">
                                    <div class="sf-filter-title">Brand</div>
                                    <div class="sf-filter-list">
                                        @foreach ($brands as $b)
                                            <label><input type="radio" class="form-check-input mt-0" name="brand" value="{{ $b->slug }}" wire:model.live="brand">{{ $b->name }}<span class="count">{{ $b->products_count }}</span></label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="sf-filter-group border-0">
                                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="f-sale" wire:model.live="sale"><label class="form-check-label" for="f-sale">On sale</label></div>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-stock" wire:model.live="instock"><label class="form-check-label" for="f-stock">In stock only</label></div>
                            </div>
                            <button type="button" class="btn btn-ink w-100 d-lg-none mt-2" @click="filters = false">Show {{ $products->total() }} results</button>
                        </div>
                    </div>
                </aside>

                {{-- Results --}}
                <div class="col-lg-9">
                    <div class="sf-toolbar">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-ink btn-sm d-lg-none" @click="filters = true"><i class="ri-equalizer-line me-1"></i>Filters @if ($activeCount)<span class="badge rounded-pill" style="background: var(--sf-primary); color: var(--sf-primary-contrast)">{{ $activeCount }}</span>@endif</button>
                            <span class="sf-muted" style="font-size: 14px">{{ $products->total() }} results</span>
                        </div>
                        <select class="form-select w-auto" wire:model.live="sort" aria-label="Sort">
                            <option value="">Featured</option>
                            <option value="new">Newest</option>
                            <option value="popular">Most popular</option>
                            <option value="price_asc">Price: low to high</option>
                            <option value="price_desc">Price: high to low</option>
                        </select>
                    </div>

                    @if ($activeCount)
                        <div class="sf-active-filters">
                            @if ($q)<button class="sf-chip" wire:click="clear('q')">“{{ $q }}” <i class="ri-close-line"></i></button>@endif
                            @if ($cat)<button class="sf-chip" wire:click="clear('category')">{{ $cat->name }} <i class="ri-close-line"></i></button>@endif
                            @if ($model)<button class="sf-chip" wire:click="clear('device')"><i class="ri-smartphone-line"></i>{{ $model->full_name }} <i class="ri-close-line"></i></button>@endif
                            @if ($brand)<button class="sf-chip" wire:click="clear('brand')">{{ $brands->firstWhere('slug', $brand)?->name ?? $brand }} <i class="ri-close-line"></i></button>@endif
                            @if ($color)<button class="sf-chip" wire:click="clear('color')">{{ $color }} <i class="ri-close-line"></i></button>@endif
                            @if ($min !== '' || $max !== '')<button class="sf-chip" wire:click="clear('price')">{{ $min !== '' ? money($min) : 'Any' }} – {{ $max !== '' ? money($max) : 'Any' }} <i class="ri-close-line"></i></button>@endif
                            @if ($sale)<button class="sf-chip" wire:click="clear('sale')">On sale <i class="ri-close-line"></i></button>@endif
                            @if ($instock)<button class="sf-chip" wire:click="clear('instock')">In stock <i class="ri-close-line"></i></button>@endif
                            <button class="btn btn-link sf-muted p-0 ms-1" style="font-size: 14px" wire:click="clearAll">Clear all</button>
                        </div>
                    @endif

                    <div wire:loading.class="opacity-50" style="transition: opacity .2s">
                        @if ($products->isEmpty())
                            <div class="sf-empty">
                                <i class="ri-search-eye-line"></i>
                                <h4>No products match</h4>
                                <p>Try removing a filter — or ask us on WhatsApp, we can laser-cut protection for almost any phone.</p>
                                <button class="btn btn-primary" wire:click="clearAll">Clear filters</button>
                            </div>
                        @else
                            <div class="sf-grid sf-grid-3">
                                @foreach ($products as $product)
                                    @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                                @endforeach
                            </div>
                            <div class="mt-5 d-flex justify-content-center">{{ $products->onEachSide(1)->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
