<?php

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Deal;
use App\Models\DeviceBrand;
use App\Models\HomeSection;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Loads the data each homepage section type needs. */
class SectionData
{
    protected ?Collection $featured = null;

    public function __construct(protected PricingService $pricing) {}

    public function for(HomeSection $section): array
    {
        $limit = (int) $section->setting('limit', 8);

        return match ($section->type) {
            'hero' => ['showcase' => $this->heroShowcase($section)],
            'categories' => ['categories' => $this->categories($section, $limit)],
            'product_rail' => ['products' => $this->rail($section, $limit)],
            'flash_sale' => $this->flashSale($section, $limit),
            'shop_by_device' => ['deviceBrands' => DeviceBrand::active()->with(['models' => fn ($q) => $q->where('is_active', true)->withCount('products')])->get(), 'limit' => $limit],
            'promo_banner' => ['showcase' => $this->featured()->shuffle(crc32((string) $section->id))->take(2)->values()],
            'brands' => ['brands' => Brand::active()->withCount('products')->orderBy('sort_order')->get()],
            'bundles' => ['bundles' => Deal::live()->ofType('bundle')->with(['items.product.colors.images', 'items.product.activeVariants', 'items.variant'])->limit($limit ?: 3)->get()],
            default => [],
        };
    }

    public function featured(): Collection
    {
        return $this->featured ??= Product::online()->withCardData()->where('is_featured', true)->latest('id')->limit(12)->get();
    }

    /**
     * Hero imagery follows each slide's button link: a slide pointing at
     * /shop?category=wraps-skins shows wraps; the rest is topped up with featured items.
     */
    protected function heroShowcase(HomeSection $section): Collection
    {
        $slides = (array) $section->setting('slides', []);
        $perSlide = count($slides) === 1 ? 2 : 1;
        $picked = collect();

        foreach ($slides as $slide) {
            parse_str((string) parse_url($slide['button_url'] ?? '', PHP_URL_QUERY), $query);
            $category = ! empty($query['category']) ? Category::where('slug', $query['category'])->first() : null;
            $matches = $category
                ? Product::online()->withCardData()->whereIn('category_id', $category->familyIds())
                    ->whereNotIn('id', $picked->pluck('id'))->orderByDesc('is_featured')->latest('id')->limit($perSlide)->get()
                : collect();
            $picked = $picked->concat($matches->isNotEmpty() ? $matches : $this->featured()->whereNotIn('id', $picked->pluck('id'))->take($perSlide));
        }

        return $picked->concat($this->featured()->whereNotIn('id', $picked->pluck('id')->all()))->values()->take(6);
    }

    protected function categories(HomeSection $section, int $limit): Collection
    {
        $ids = array_filter((array) $section->setting('category_ids', []));
        $q = Category::active()->withCount(['products' => fn ($q) => $q->where('show_online', true)->where('is_active', true)]);

        $categories = $ids
            ? $q->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids))->values()
            : $q->where('is_featured', true)->orderBy('sort_order')->get();

        if ($categories->count() < $limit && ! $ids) {
            $more = Category::active()->roots()->where('show_in_menu', true)->whereNotIn('id', $categories->pluck('id'))
                ->withCount(['products' => fn ($q) => $q->where('show_online', true)])->orderBy('sort_order')->get();
            $categories = $categories->concat($more);
        }

        $categories = $categories->take($limit)->values();
        $categories->load('children:id,parent_id');
        foreach ($categories as $category) {
            // count products in the category and its sub-categories
            $category->products_count = Product::online()->whereIn('category_id', $category->familyIds())->count();
        }

        return $categories;
    }

    protected function rail(HomeSection $section, int $limit): Collection
    {
        $q = Product::online()->withCardData();

        switch ($section->setting('source', 'featured')) {
            case 'new':
                $q->latest('id');
                break;
            case 'bestsellers':
                $sold = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.status', '!=', 'cancelled')->whereNotNull('order_items.product_id')
                    ->selectRaw('order_items.product_id, SUM(order_items.quantity) as sold')
                    ->groupBy('order_items.product_id')->orderByDesc('sold')->limit($limit)->pluck('product_id')->all();
                $q->orderByRaw($sold ? 'FIELD(id, '.implode(',', array_map('intval', array_reverse($sold))).') DESC' : 'is_featured DESC')->orderByDesc('views');
                break;
            case 'sale':
                $dealProductIds = $this->pricing->liveDeals()->flatMap(fn ($d) => $d->items->pluck('product_id'))->all();
                $catIds = $this->pricing->liveDeals()->where('type', 'category_discount')->pluck('category_id')->filter()->all();
                $q->where(fn ($q) => $q->whereIn('id', $dealProductIds ?: [0])
                    ->orWhereIn('category_id', Category::whereIn('id', $catIds)->orWhereIn('parent_id', $catIds)->pluck('id')->all() ?: [0])
                    ->orWhereHas('activeVariants', fn ($v) => $v->whereColumn('compare_at_price', '>', 'retail_price')));
                break;
            case 'category':
                $cat = Category::find($section->setting('category_id'));
                $q->whereIn('category_id', $cat ? $cat->familyIds() : [0]);
                break;
            default:
                $q->where('is_featured', true)->latest('id');
        }

        return $q->limit($limit)->get();
    }

    protected function flashSale(HomeSection $section, int $limit): array
    {
        $deal = Deal::live()->ofType('flash_sale')
            ->when($section->setting('deal_id'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('ends_at')->first();

        $products = $deal
            ? Product::online()->withCardData()->whereIn('id', $deal->items()->pluck('product_id'))->limit($limit)->get()
            : collect();

        return ['deal' => $deal, 'products' => $products];
    }
}
