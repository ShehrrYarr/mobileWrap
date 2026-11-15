<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Database\Seeders\Support\ProductArt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalog: device models (with approximate laser cut presets — verify
 * against your cutter templates), categories, brands, ~35 products with
 * colour variants + generated images, and laser raw materials.
 */
class CatalogSeeder extends Seeder
{
    protected int $barcodeSeq = 100000;

    public function run(): void
    {
        $devices = $this->seedDevices();
        $categories = $this->seedCategories();
        $brands = $this->seedBrands();
        $attributes = $this->seedAttributes();

        $art = new ProductArt;
        $inventory = app(InventoryService::class);
        mt_srand(42);

        foreach ($this->products() as $i => $p) {
            $this->command?->getOutput()->write('.');

            $product = Product::create([
                'category_id' => $categories[$p['cat']]->id,
                'brand_id' => isset($p['brand']) ? $brands[$p['brand']]->id : null,
                'name' => $p['name'],
                'type' => $p['type'] ?? 'standard',
                'material_kind' => $p['material'] ?? null,
                'wastage_percent' => $p['wastage'] ?? 0,
                'unit' => isset($p['material']) ? 'cm2' : 'pcs',
                'short_description' => $p['short'],
                'description' => $p['desc'] ?? $this->description($p),
                'sku_prefix' => $p['sku'],
                'is_active' => true,
                'show_online' => ! isset($p['material']),
                'is_featured' => $p['featured'] ?? false,
                'track_stock' => true,
                'low_stock_threshold' => isset($p['material']) ? 5000 : 5,
                'created_at' => now()->subDays(60 - $i),
            ]);

            // Device compatibility
            if (isset($p['devices'])) {
                $product->deviceModels()->sync($this->deviceIds($devices, $p['devices']));
            }

            // Colours + generated images
            $colors = [];
            foreach ($p['colors'] as $ci => $color) {
                [$cname, $hex] = $color;
                $opts = array_merge($p['opts'] ?? [], $color[2] ?? []);
                $pc = $product->colors()->create([
                    'name' => $cname,
                    'hex_code' => $hex,
                    'sort_order' => $ci,
                    'is_default' => $ci === 0,
                ]);
                $views = ($p['views'] ?? 2);
                for ($v = 0; $v < $views; $v++) {
                    $rel = 'products/'.$product->slug.'/'.Str::slug($cname).'-'.($v + 1).'.png';
                    $abs = public_path('uploads/'.$rel);
                    if (! file_exists($abs)) {
                        $art->save($p['shape'], $hex, $abs, $v, $opts);
                    }
                    $pc->images()->create(['path' => $rel, 'alt' => $product->name.' — '.$cname, 'sort_order' => $v]);
                }
                $colors[] = $pc;
            }

            // Variants = colour × attribute values
            $attrCombos = [[]];
            foreach ($p['attrs'] ?? [] as $attrName => $values) {
                $next = [];
                foreach ($attrCombos as $combo) {
                    foreach ($values as $value => $delta) {
                        $next[] = $combo + [$attrName => [$value, $delta]];
                    }
                }
                $attrCombos = $next;
            }

            $first = true;
            foreach ($colors as $pc) {
                foreach ($attrCombos as $combo) {
                    $delta = array_sum(array_map(fn ($a) => $a[1], $combo));
                    $retail = $p['price'] + $delta;
                    $isMaterial = isset($p['material']);

                    $skuParts = [$p['sku'], strtoupper(Str::substr(Str::slug($pc->name, ''), 0, 4))];
                    foreach ($combo as [$value]) {
                        $skuParts[] = strtoupper(Str::slug($value, ''));
                    }

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'product_color_id' => $pc->id,
                        'sku' => $this->uniqueSku(implode('-', $skuParts)),
                        'barcode' => $this->ean13(),
                        'cost_price' => $isMaterial ? $p['cost'] : round($retail * 0.55),
                        'retail_price' => $isMaterial ? 0 : $retail,
                        'wholesale_price' => $isMaterial ? 0 : round($retail * 0.8 / 10) * 10,
                        'compare_at_price' => isset($p['compare']) ? $retail + $p['compare'] : null,
                        'is_default' => $first,
                        'is_active' => true,
                    ]);

                    $valueIds = [];
                    foreach ($combo as $attrName => [$value]) {
                        $valueIds[] = $attributes[$attrName]->values()->firstOrCreate(['value' => $value])->id;
                    }
                    $variant->attributeValues()->sync($valueIds);
                    $variant->load('color', 'attributeValues');
                    $variant->update(['label' => $variant->buildLabel()]);

                    // Opening stock (a few deliberately low / out of stock)
                    if ($isMaterial) {
                        $qty = $p['stock'] ?? 60000;
                    } else {
                        $roll = mt_rand(1, 100);
                        $qty = $roll <= 5 ? 0 : ($roll <= 14 ? mt_rand(1, 4) : mt_rand(8, 45));
                    }
                    if ($qty > 0) {
                        $inventory->adjust($variant, $qty, 'opening', null, 'Opening stock', (float) $variant->cost_price);
                    } else {
                        $variant->stock()->create(['quantity' => 0]);
                    }

                    $first = false;
                }
            }
        }

        // Category cover images = first product image in that category
        foreach (Category::all() as $category) {
            $product = Product::whereIn('category_id', $category->familyIds())->where('show_online', true)
                ->with('colors.images')->orderByDesc('is_featured')->first();
            if ($product && ($path = $product->colors->first()?->images->first()?->path)) {
                $category->update(['image' => $path]);
            }
        }

        $this->command?->getOutput()->writeln('');
    }

    // ── Reference data ───────────────────────────────────────────────

    protected function seedDevices(): array
    {
        // [brand, model, year, body width mm, body height mm]
        $list = [
            ['Apple', 'iPhone 17 Pro Max', 2025, 78.0, 163.4], ['Apple', 'iPhone 17 Pro', 2025, 71.9, 150.0],
            ['Apple', 'iPhone Air', 2025, 74.7, 156.2], ['Apple', 'iPhone 17', 2025, 71.5, 149.6],
            ['Apple', 'iPhone 16 Pro Max', 2024, 77.6, 163.0], ['Apple', 'iPhone 16 Pro', 2024, 71.5, 149.6],
            ['Apple', 'iPhone 16', 2024, 71.6, 147.6], ['Apple', 'iPhone 15 Pro Max', 2023, 76.7, 159.9],
            ['Apple', 'iPhone 15 Pro', 2023, 70.6, 146.6], ['Apple', 'iPhone 15', 2023, 71.6, 147.6],
            ['Apple', 'iPhone 14', 2022, 71.5, 146.7], ['Apple', 'iPhone 13', 2021, 71.5, 146.7],
            ['Samsung', 'Galaxy S25 Ultra', 2025, 77.6, 162.8], ['Samsung', 'Galaxy S25', 2025, 70.5, 146.9],
            ['Samsung', 'Galaxy S24 Ultra', 2024, 79.0, 162.3], ['Samsung', 'Galaxy A56', 2025, 77.5, 162.2],
            ['Samsung', 'Galaxy A36', 2025, 78.2, 162.9], ['Samsung', 'Galaxy A16', 2024, 77.9, 164.4],
            ['Samsung', 'Galaxy A55', 2024, 77.4, 161.1],
            ['Xiaomi', 'Redmi Note 14 Pro', 2025, 74.4, 162.3], ['Xiaomi', 'Redmi Note 13', 2024, 75.6, 162.2],
            ['Xiaomi', 'Xiaomi 15', 2024, 71.2, 152.3], ['Xiaomi', 'Poco X7 Pro', 2025, 75.2, 160.8],
            ['Infinix', 'Note 40 Pro', 2024, 74.6, 164.3], ['Infinix', 'Hot 50', 2024, 77.1, 165.7],
            ['Tecno', 'Camon 30', 2024, 74.6, 164.6], ['Tecno', 'Spark 30 Pro', 2024, 76.6, 168.0],
            ['Oppo', 'Reno 13', 2025, 74.7, 157.9], ['Vivo', 'V40', 2024, 74.9, 164.2],
            ['Google', 'Pixel 9 Pro', 2024, 72.0, 152.8],
        ];

        $brandOrder = ['Apple', 'Samsung', 'Xiaomi', 'Infinix', 'Tecno', 'Oppo', 'Vivo', 'Google'];
        $brands = [];
        foreach ($brandOrder as $i => $name) {
            $brands[$name] = DeviceBrand::create(['name' => $name, 'sort_order' => $i]);
        }

        $models = [];
        foreach ($list as $i => [$brand, $model, $year, $w, $h]) {
            $dm = new DeviceModel([
                'name' => $model,
                'release_year' => $year,
                'front_cut_width_mm' => round($w - 2.4, 1),
                'front_cut_height_mm' => round($h - 2.6, 1),
                'back_cut_width_mm' => round($w - 1.0, 1),
                'back_cut_height_mm' => round($h - 1.0, 1),
                'sort_order' => $i,
            ]);
            $dm->device_brand_id = $brands[$brand]->id;
            $dm->setRelation('brand', $brands[$brand]);
            $dm->save();
            $models[] = ['brand' => $brand, 'name' => $model, 'year' => $year, 'id' => $dm->id];
        }

        return $models;
    }

    protected function deviceIds(array $devices, string|array $group): array
    {
        if (is_array($group)) {
            return collect($devices)->whereIn('name', $group)->pluck('id')->all();
        }

        return collect($devices)->filter(fn ($d) => match ($group) {
            'iphone' => $d['brand'] === 'Apple',
            'iphone_recent' => $d['brand'] === 'Apple' && $d['year'] >= 2023,
            'samsung' => $d['brand'] === 'Samsung',
            'android' => $d['brand'] !== 'Apple',
            'budget' => in_array($d['brand'], ['Samsung', 'Xiaomi', 'Infinix', 'Tecno', 'Oppo', 'Vivo']) && ! str_contains($d['name'], 'Ultra') && ! str_contains($d['name'], 'S25'),
            'flagship' => str_contains($d['name'], 'Pro') || str_contains($d['name'], 'Ultra') || str_contains($d['name'], 'Air'),
            default => true,
        })->pluck('id')->all();
    }

    protected function seedCategories(): array
    {
        $tree = [
            ['Cases & Covers', 'cases-covers', 'ri-smartphone-line', true, [
                ['Silicone Cases', 'silicone-cases'], ['Clear Cases', 'clear-cases'], ['Rugged & Wallet', 'rugged-wallet'],
            ]],
            ['Screen Protection', 'screen-protection', 'ri-shield-check-line', true, [
                ['Tempered Glass', 'tempered-glass'], ['Hydrogel & Film', 'hydrogel-film'], ['Camera Protection', 'camera-protection'],
            ]],
            ['Wraps & Skins', 'wraps-skins', 'ri-palette-line', true, []],
            ['Chargers', 'chargers', 'ri-flashlight-line', true, []],
            ['Cables', 'cables', 'ri-usb-line', true, []],
            ['Power Banks', 'power-banks', 'ri-battery-2-charge-line', true, []],
            ['Audio', 'audio', 'ri-headphone-line', false, []],
            ['Holders & Stands', 'holders-stands', 'ri-tablet-line', false, []],
            ['Laser Raw Materials', 'raw-materials', 'ri-stack-line', false, []],
        ];

        $map = [];
        foreach ($tree as $i => [$name, $slug, $icon, $featured, $children]) {
            $cat = Category::create([
                'name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $i,
                'is_featured' => $featured, 'show_in_menu' => $slug !== 'raw-materials',
                'description' => $slug === 'raw-materials' ? 'Sheets and rolls used by the laser cutter (not sold online).' : null,
            ]);
            $map[$slug] = $cat;
            foreach ($children as $j => [$cname, $cslug]) {
                $map[$cslug] = Category::create(['parent_id' => $cat->id, 'name' => $cname, 'slug' => $cslug, 'icon' => $icon, 'sort_order' => $j]);
            }
        }

        return $map;
    }

    protected function seedBrands(): array
    {
        $map = [];
        foreach (['MW Originals', 'Anker', 'Baseus', 'Ugreen', 'Spigen', 'Joyroom', 'Remax', 'Xiaomi'] as $i => $name) {
            $map[Str::slug($name)] = Brand::create(['name' => $name, 'sort_order' => $i]);
        }

        return $map;
    }

    protected function seedAttributes(): array
    {
        $map = [];
        foreach (['Capacity' => ['5000mAh', '10000mAh', '20000mAh'], 'Length' => ['1m', '2m'], 'Wattage' => ['20W', '35W', '45W', '65W']] as $i => $values) {
            $attr = Attribute::create(['name' => $i]);
            foreach ($values as $k => $v) {
                $attr->values()->create(['value' => $v, 'sort_order' => $k]);
            }
            $map[$i] = $attr;
        }

        return $map;
    }

    // ── Products ─────────────────────────────────────────────────────

    protected function products(): array
    {
        $black = ['Black', '#1E1F22'];
        $white = ['White', '#F3F4F6'];

        return [
            // Cases
            ['name' => 'Liquid Silicone Case', 'cat' => 'silicone-cases', 'brand' => 'mw-originals', 'sku' => 'MW-LSC', 'shape' => 'case', 'price' => 1499, 'featured' => true, 'devices' => 'iphone_recent',
                'short' => 'Soft-touch silicone with a microfibre lining and raised camera lip.',
                'colors' => [['Midnight', '#1F2633'], ['Sage', '#9CAF88'], ['Lavender', '#B9A7D8'], ['Sand', '#D9C4A3'], ['Coral', '#F27B6B']]],
            ['name' => 'Crystal Clear MagSafe Case', 'cat' => 'clear-cases', 'brand' => 'spigen', 'sku' => 'SPG-CLR', 'shape' => 'case', 'opts' => ['clear' => true], 'price' => 1999, 'featured' => true, 'devices' => 'iphone_recent',
                'short' => 'Anti-yellowing polycarbonate with built-in MagSafe magnets.',
                'colors' => [['Crystal Clear', '#E8EEF2'], ['Frost Black', '#3A3D42']]],
            ['name' => 'Armor Rugged Case', 'cat' => 'rugged-wallet', 'brand' => 'spigen', 'sku' => 'SPG-ARM', 'shape' => 'case', 'opts' => ['camera' => 'pill'], 'price' => 2799, 'compare' => 500, 'devices' => 'flagship',
                'short' => 'Dual-layer drop protection with air-cushion corners.',
                'colors' => [['Matte Black', '#1B1C1E'], ['Gunmetal', '#4B5258'], ['Navy', '#23324A']]],
            ['name' => 'Leather Folio Wallet Case', 'cat' => 'rugged-wallet', 'brand' => 'mw-originals', 'sku' => 'MW-FOL', 'shape' => 'case', 'price' => 3499, 'devices' => 'flagship',
                'short' => 'Vegan leather folio with three card slots and a stand.',
                'colors' => [['Cognac', '#8A4B2A'], ['Black', '#1A1A1A'], ['Olive', '#5B6044']]],
            ['name' => 'Slim Matte Case', 'cat' => 'silicone-cases', 'brand' => 'joyroom', 'sku' => 'JR-SLM', 'shape' => 'case', 'opts' => ['camera' => 'pill'], 'price' => 999, 'devices' => 'budget',
                'short' => '1.2 mm featherweight shell with a fingerprint-proof matte finish.',
                'colors' => [['Black', '#202124'], ['Cloud White', '#F2F2F2'], ['Cherry Red', '#C8102E'], ['Ocean Blue', '#2D5DA8']]],
            ['name' => 'Kickstand Hybrid Case', 'cat' => 'rugged-wallet', 'brand' => 'remax', 'sku' => 'RMX-KCK', 'shape' => 'case', 'price' => 2299, 'devices' => 'samsung',
                'short' => 'Built-in metal kickstand for hands-free landscape viewing.',
                'colors' => [['Black', '#232426'], ['Titanium Silver', '#C9CDD2']]],

            // Screen protection
            ['name' => 'UV Tempered Glass (Full Glue)', 'cat' => 'tempered-glass', 'brand' => 'mw-originals', 'sku' => 'MW-UVG', 'shape' => 'glass', 'price' => 1299, 'devices' => 'all',
                'short' => '9H UV-cured glass with full liquid adhesive — perfect for curved screens.',
                'colors' => [['Crystal Clear', '#DCEBF5']]],
            ['name' => 'Privacy Tempered Glass', 'cat' => 'tempered-glass', 'brand' => 'mw-originals', 'sku' => 'MW-PRV', 'shape' => 'glass', 'price' => 1499, 'featured' => true, 'devices' => 'all',
                'short' => '28° privacy filter keeps your screen visible only to you.',
                'colors' => [['Privacy Black', '#2A2D33']]],
            ['name' => 'Hydrogel Screen Film (Laser-Cut)', 'cat' => 'hydrogel-film', 'brand' => 'mw-originals', 'sku' => 'MW-HYD', 'shape' => 'glass', 'price' => 899, 'featured' => true, 'devices' => 'all',
                'short' => 'Self-healing hydrogel cut to your exact model on our laser cutter.',
                'colors' => [['Clear Gloss', '#DDEEF7'], ['Anti-Glare Matte', '#E9ECEF']]],
            ['name' => 'Camera Lens Protector Set', 'cat' => 'camera-protection', 'brand' => 'joyroom', 'sku' => 'JR-LNS', 'shape' => 'lens', 'price' => 799, 'devices' => 'iphone_recent', 'views' => 1,
                'short' => 'Individual aluminium-ring lens guards with sapphire-coated glass.',
                'colors' => [['Graphite', '#2B2D31'], ['Silver', '#C0C4C8'], ['Deep Purple', '#4B3B63'], ['Desert Gold', '#C8A97E']]],
            ['name' => 'Anti-Glare Matte Glass', 'cat' => 'tempered-glass', 'brand' => 'remax', 'sku' => 'RMX-AGM', 'shape' => 'glass', 'price' => 1199, 'devices' => 'android',
                'short' => 'Matte etched surface that kills reflections — great for gaming.',
                'colors' => [['Matte Frost', '#E6E8EA']]],

            // Wraps & skins
            ['name' => 'Carbon Fiber Back Wrap', 'cat' => 'wraps-skins', 'brand' => 'mw-originals', 'sku' => 'MW-CRB', 'shape' => 'wrap', 'opts' => ['pattern' => 'carbon'], 'price' => 1200, 'featured' => true, 'devices' => 'all',
                'short' => '3D-textured carbon vinyl, laser-cut for a bubble-free full back wrap.',
                'colors' => [['Carbon Black', '#202124'], ['Carbon Red', '#8B1E22'], ['Carbon Blue', '#1E3A8B']]],
            ['name' => 'Matte Color Skin', 'cat' => 'wraps-skins', 'brand' => 'mw-originals', 'sku' => 'MW-MAT', 'shape' => 'wrap', 'opts' => ['pattern' => 'solid'], 'price' => 999, 'devices' => 'all',
                'short' => 'Velvety matte vinyl that resists fingerprints and scratches.',
                'colors' => [['Matte Black', '#232323'], ['Military Green', '#4B5320'], ['Sky Blue', '#87B8E0'], ['Blush', '#E8B4B8']]],
            ['name' => 'Neon Gradient Wrap', 'cat' => 'wraps-skins', 'brand' => 'mw-originals', 'sku' => 'MW-NEO', 'shape' => 'wrap', 'price' => 1350, 'compare' => 250, 'featured' => true, 'devices' => 'all',
                'short' => 'Ultra-saturated gradient wraps with a high-gloss finish.',
                'colors' => [
                    ['Sunset', '#FF7E5F', ['pattern' => 'gradient', 'stops' => ['#FF5F6D', '#FFC371']]],
                    ['Cyber', '#9B2CFF', ['pattern' => 'gradient', 'stops' => ['#7F00FF', '#E100FF']]],
                    ['Aqua', '#2ED3C6', ['pattern' => 'gradient', 'stops' => ['#00C9FF', '#92FE9D']]],
                ]],
            ['name' => 'Marble Skin', 'cat' => 'wraps-skins', 'brand' => 'mw-originals', 'sku' => 'MW-MRB', 'shape' => 'wrap', 'opts' => ['pattern' => 'marble'], 'price' => 1250, 'devices' => 'all',
                'short' => 'Stone-look vinyl with a satin topcoat.',
                'colors' => [['White Marble', '#EDEDED'], ['Black Marble', '#2B2B2B']]],
            ['name' => 'Wood Grain Skin', 'cat' => 'wraps-skins', 'brand' => 'mw-originals', 'sku' => 'MW-WOD', 'shape' => 'wrap', 'opts' => ['pattern' => 'wood'], 'price' => 1250, 'devices' => 'all',
                'short' => 'Warm natural grain, cut precisely around every lens.',
                'colors' => [['Walnut', '#6B4226'], ['Oak', '#B08A5B']]],

            // Chargers
            ['name' => '20W USB-C Fast Charger', 'cat' => 'chargers', 'brand' => 'anker', 'sku' => 'ANK-20W', 'shape' => 'charger', 'opts' => ['ports' => 1], 'price' => 2199, 'featured' => true,
                'short' => 'Compact PD charger that fills an iPhone to 50% in ~30 minutes.',
                'colors' => [$white, $black]],
            ['name' => '65W GaN 3-Port Charger', 'cat' => 'chargers', 'brand' => 'baseus', 'sku' => 'BSU-65W', 'shape' => 'charger', 'opts' => ['ports' => 3, 'wide' => true], 'price' => 6499, 'compare' => 1000,
                'short' => 'Charge a laptop, phone and earbuds at once from one tiny GaN brick.',
                'colors' => [$white, $black]],
            ['name' => 'Nano Charger', 'cat' => 'chargers', 'brand' => 'ugreen', 'sku' => 'UGR-NAN', 'shape' => 'charger', 'opts' => ['ports' => 1], 'price' => 2499,
                'attrs' => ['Wattage' => ['20W' => 0, '35W' => 1500]],
                'short' => 'Pick your power: 20W for phones or 35W for tablets & MacBook Air.',
                'colors' => [$white, $black]],
            ['name' => 'Car Charger 45W Dual', 'cat' => 'chargers', 'brand' => 'baseus', 'sku' => 'BSU-CAR', 'shape' => 'charger', 'opts' => ['ports' => 2], 'price' => 2999,
                'short' => 'USB-C + USB-A fast charging for the road.',
                'colors' => [['Black', '#222326'], ['Silver', '#BFC3C8']]],
            ['name' => 'Magnetic Wireless Charger 15W', 'cat' => 'chargers', 'brand' => 'anker', 'sku' => 'ANK-MAG', 'shape' => 'puck', 'price' => 3499, 'views' => 1, 'devices' => 'iphone',
                'short' => 'Snap-on MagSafe-compatible charging pad.',
                'colors' => [$white, $black]],

            // Cables
            ['name' => 'Braided USB-C to USB-C Cable', 'cat' => 'cables', 'brand' => 'ugreen', 'sku' => 'UGR-CC', 'shape' => 'cable', 'opts' => ['left' => 'c', 'right' => 'c'], 'price' => 799, 'featured' => true,
                'attrs' => ['Length' => ['1m' => 0, '2m' => 300]],
                'short' => '100W nylon-braided cable tested to 25,000 bends.',
                'colors' => [['Black', '#1F1F1F'], ['White', '#EDEDED'], ['Red', '#D7263D'], ['Blue', '#2563EB']]],
            ['name' => 'USB-C to Lightning Cable', 'cat' => 'cables', 'brand' => 'anker', 'sku' => 'ANK-CL', 'shape' => 'cable', 'opts' => ['left' => 'c', 'right' => 'l'], 'price' => 1199, 'devices' => 'iphone',
                'attrs' => ['Length' => ['1m' => 0, '2m' => 300]],
                'short' => 'MFi-certified fast-charge cable for older iPhones.',
                'colors' => [['White', '#EFEFEF'], ['Black', '#202020']]],
            ['name' => '3-in-1 Charging Cable', 'cat' => 'cables', 'brand' => 'remax', 'sku' => 'RMX-3IN1', 'shape' => 'cable', 'opts' => ['left' => 'a', 'right' => 'c'], 'price' => 999,
                'short' => 'USB-C, Lightning and Micro-USB in one tangle-free cable.',
                'colors' => [['Black', '#202124'], ['Grey', '#6B7280']]],
            ['name' => 'Magnetic Charging Cable', 'cat' => 'cables', 'brand' => 'joyroom', 'sku' => 'JR-MAG', 'shape' => 'cable', 'opts' => ['left' => 'a', 'right' => 'c', 'braided' => false], 'price' => 1299,
                'short' => 'Swappable magnetic tips with an LED charging indicator.',
                'colors' => [['Black', '#1E1F22'], ['Silver', '#BFC3C8']]],

            // Power banks
            ['name' => 'Slim Power Bank', 'cat' => 'power-banks', 'brand' => 'baseus', 'sku' => 'BSU-PB', 'shape' => 'powerbank', 'price' => 3999, 'featured' => true,
                'attrs' => ['Capacity' => ['10000mAh' => 0, '20000mAh' => 2000]],
                'short' => '22.5W fast charging with a digital battery display.',
                'colors' => [['Black', '#1E1F22'], ['White', '#F1F2F4'], ['Royal Blue', '#3B5BDB']]],
            ['name' => 'MagSafe Power Bank 5000mAh', 'cat' => 'power-banks', 'brand' => 'anker', 'sku' => 'ANK-MPB', 'shape' => 'powerbank', 'opts' => ['square' => true], 'price' => 4499, 'devices' => 'iphone',
                'short' => 'Magnetic snap-on battery that charges while you scroll.',
                'colors' => [['Lavender', '#B9A7D8'], ['Black', '#202124'], ['White', '#F1F2F4']]],
            ['name' => '65W Laptop Power Bank 20000mAh', 'cat' => 'power-banks', 'brand' => 'anker', 'sku' => 'ANK-LPB', 'shape' => 'powerbank', 'price' => 11999,
                'short' => 'Charges laptops, tablets and phones — airline friendly.',
                'colors' => [['Black', '#1C1D20'], ['Silver', '#C4C8CD']]],

            // Audio
            ['name' => 'Wireless Earbuds Pro', 'cat' => 'audio', 'brand' => 'joyroom', 'sku' => 'JR-EBP', 'shape' => 'earbuds', 'price' => 5999, 'compare' => 1500, 'featured' => true,
                'short' => 'Active noise cancellation with 30-hour battery and low-latency game mode.',
                'colors' => [['White', '#F5F5F5'], ['Black', '#1E1E1E'], ['Mint', '#A8E6CF']]],
            ['name' => 'Sport Earbuds', 'cat' => 'audio', 'brand' => 'remax', 'sku' => 'RMX-SPT', 'shape' => 'earbuds', 'price' => 3999,
                'short' => 'IPX5 sweat-proof buds with secure ear-wings.',
                'colors' => [['Black', '#202124'], ['Red', '#D7263D']]],

            // Holders & stands
            ['name' => 'Magnetic Car Mount', 'cat' => 'holders-stands', 'brand' => 'baseus', 'sku' => 'BSU-CMT', 'shape' => 'ring', 'price' => 1999, 'views' => 1,
                'short' => 'Strong N52 magnets with a 360° ball joint.',
                'colors' => [['Black', '#232426'], ['Silver', '#C9CDD2']]],
            ['name' => 'Foldable Desk Stand', 'cat' => 'holders-stands', 'brand' => 'ugreen', 'sku' => 'UGR-STD', 'shape' => 'stand', 'price' => 1799, 'views' => 1,
                'short' => 'Aluminium stand with adjustable angle and height.',
                'colors' => [['Silver', '#C9CDD2'], ['Space Grey', '#4A4E55'], ['Pink', '#F4B6C2']]],
            ['name' => 'Ring Grip Holder', 'cat' => 'holders-stands', 'brand' => 'mw-originals', 'sku' => 'MW-RNG', 'shape' => 'ring', 'price' => 599, 'views' => 1,
                'short' => '360° rotating finger ring that doubles as a kickstand.',
                'colors' => [['Black', '#232426'], ['Gold', '#C9A44C'], ['Rose Gold', '#E0A899'], ['Silver', '#C9CDD2']]],
            ['name' => 'Selfie Stick Tripod', 'cat' => 'holders-stands', 'brand' => 'xiaomi', 'sku' => 'XMI-SST', 'shape' => 'selfie', 'price' => 2499, 'views' => 1,
                'short' => 'Bluetooth remote, extendable to 100 cm, stands on any surface.',
                'colors' => [['Black', '#1E1E1E'], ['White', '#F3F3F3']]],

            // Laser raw materials (stock in cm², not sold online)
            ['name' => 'Hydrogel Film Roll', 'type' => 'raw_material', 'material' => 'hydrogel', 'wastage' => 8, 'cat' => 'raw-materials', 'sku' => 'RM-HYD', 'shape' => 'roll', 'opts' => ['clear' => true], 'price' => 0, 'cost' => 0.06, 'stock' => 80000, 'views' => 1,
                'short' => '30 cm × 25 m hydrogel roll for the laser cutter.', 'colors' => [['Clear Gloss', '#DDEEF7'], ['Matte', '#E9ECEF']]],
            ['name' => 'Privacy Glass Sheet', 'type' => 'raw_material', 'material' => 'privacy_glass', 'wastage' => 5, 'cat' => 'raw-materials', 'sku' => 'RM-PRV', 'shape' => 'roll', 'price' => 0, 'cost' => 0.18, 'stock' => 30000, 'views' => 1,
                'short' => 'Privacy film sheets (A4) for custom cuts.', 'colors' => [['Privacy Black', '#2A2D33']]],
            ['name' => 'UV Glass Sheet', 'type' => 'raw_material', 'material' => 'uv_glass', 'wastage' => 5, 'cat' => 'raw-materials', 'sku' => 'RM-UVG', 'shape' => 'roll', 'opts' => ['clear' => true], 'price' => 0, 'cost' => 0.15, 'stock' => 30000, 'views' => 1,
                'short' => 'UV-curable glass sheets.', 'colors' => [['Clear', '#DCEBF5']]],
            ['name' => 'Carbon Wrap Roll', 'type' => 'raw_material', 'material' => 'carbon_wrap', 'wastage' => 10, 'cat' => 'raw-materials', 'sku' => 'RM-CRB', 'shape' => 'roll', 'price' => 0, 'cost' => 0.05, 'stock' => 60000, 'views' => 1,
                'short' => '3D carbon vinyl roll.', 'colors' => [['Carbon Black', '#202124'], ['Carbon Red', '#8B1E22'], ['Carbon Blue', '#1E3A8B']]],
            ['name' => 'Matte Wrap Roll', 'type' => 'raw_material', 'material' => 'matte_wrap', 'wastage' => 10, 'cat' => 'raw-materials', 'sku' => 'RM-MAT', 'shape' => 'roll', 'price' => 0, 'cost' => 0.04, 'stock' => 60000, 'views' => 1,
                'short' => 'Matte vinyl roll.', 'colors' => [['Matte Black', '#232323'], ['Military Green', '#4B5320'], ['Sky Blue', '#87B8E0'], ['Blush', '#E8B4B8']]],
            ['name' => 'Skin Wrap Roll', 'type' => 'raw_material', 'material' => 'skin_wrap', 'wastage' => 10, 'cat' => 'raw-materials', 'sku' => 'RM-SKN', 'shape' => 'roll', 'price' => 0, 'cost' => 0.05, 'stock' => 4500, 'views' => 1,
                'short' => 'Patterned skin vinyl roll (marble, wood).', 'colors' => [['White Marble', '#EDEDED'], ['Walnut', '#6B4226']]],
        ];
    }

    protected function description(array $p): string
    {
        $lines = [
            $p['short'],
            '',
            'Why you’ll like it:',
            '• Precise fit for the listed devices — check compatibility before ordering.',
            '• Quality-checked in our Lahore store before it ships.',
            '• Cash on Delivery available nationwide.',
        ];

        return implode("\n", $lines);
    }

    protected function uniqueSku(string $sku): string
    {
        $base = $sku;
        $i = 2;
        while (ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }

    /** Internal EAN-13 barcodes in the 2xx (in-store) range. */
    protected function ean13(): string
    {
        $digits = '20'.str_pad((string) $this->barcodeSeq++, 10, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $d) {
            $sum += (int) $d * ($i % 2 ? 3 : 1);
        }

        return $digits.((10 - $sum % 10) % 10);
    }
}
