# Mobile Wrap — Getting started (Phase 1)

## Run it locally (WAMP)

Use WAMP's PHP 8.4 (the `php` on your PATH is 8.2). MySQL must be running in WAMP.

```bash
C:/wamp64/bin/php/php8.4.0/php.exe artisan serve --host=127.0.0.1 --port=8000
```

- Storefront (landing page): http://127.0.0.1:8000
- Admin / back office: http://127.0.0.1:8000/admin

Demo logins are listed at the top of `database/seeders/RolesAndStaffSeeder.php` (staff) and `database/seeders/CustomerSeeder.php` (storefront customer). Change them before going live.

## Reset demo data

```bash
C:/wamp64/bin/php/php8.4.0/php.exe artisan migrate:fresh --seed
```

The first run draws ~170 product images with GD (about 2 minutes); later runs reuse them from `public/uploads/products` and take seconds.

## Storefront themes (CSS)

Theme styles are SCSS compiled with Dart Sass (Bootstrap 5 per theme):

```bash
npm run store:css
```

`npm run store:watch` rebuilds on save. Sources:

| Path | What it is |
|---|---|
| `resources/store/scss/_core.scss` | Shared storefront components (cards, gallery, cart drawer, forms…) driven by `--sf-*` CSS variables |
| `resources/store/scss/themes/{key}.scss` | One file per theme: Bootstrap variables, palette, fonts, theme-only components |
| `resources/views/themes/base/` | Default layout, partials and homepage sections every theme inherits |
| `resources/views/themes/{key}/` | A theme's overrides (header, footer, product card, hero…) |
| `config/themes.php` | Theme registry + each theme's default homepage sections |

To add a 6th theme: add an entry to `config/themes.php`, create `resources/store/scss/themes/{key}.scss`, override any views under `resources/views/themes/{key}/`, run `npm run store:css`, then use **Admin → Themes → Reset homepage to default** for the new theme.

## Where things live

| Area | Files |
|---|---|
| Admin pages (Volt) | `resources/views/livewire/admin/**` |
| Storefront pages (Volt) | `resources/views/livewire/store/**` |
| Admin layout (Herozi) | `resources/views/admin/layouts/app.blade.php`, `resources/views/admin/partials/*` |
| Business logic | `app/Services/` (Cart, Pricing, Inventory, Finance, Order), `app/Services/Storefront/SectionData.php` |
| Settings & theme resolver | `app/Support/Settings.php`, `app/Support/Theme.php`, `app/helpers.php` |
| Schema | `database/migrations/2026_11_15_1000*` (all modules, incl. POS/ledger/laser tables for later phases) |

## Phase 1 — what works

- **Catalog:** products with colours (hex swatch + per-colour image gallery, drag-and-drop upload), unlimited variant options (Capacity, Length, Wattage…), variant matrix with SKU/EAN-13 barcode generation, retail/wholesale/compare-at prices, device compatibility, laser raw materials stocked in cm².
- **Inventory:** stock per variant, add/remove/wastage/stock-count adjustments, full stock ledger, low-stock alerts in the admin bell.
- **Storefront:** 5 themes (Noir Tech, Deal Mart, Studio Minimal, Street Drop, Classic Retail), admin preview before activating, brand-colour overrides, homepage builder (toggle / reorder / edit 11 section types per theme).
- **Shopping:** shop with filters (category, phone model, colour, price, brand, sale, stock), product page where swatches swap the gallery without a reload, cart drawer, free-delivery progress, coupons, flash sales / category discounts / bundles, popups, guest checkout with Cash on Delivery or bank transfer + receipt upload, order tracking, customer accounts (orders, wishlist, profile, khata balance).
- **Orders:** admin order list/detail, status timeline (cancel restocks), verify COD / bank transfers → posts to the cash drawer or bank ledger.
- **Settings:** store info & logo, delivery fee + free-delivery threshold, checkout methods, bank accounts (shown on checkout), info pages, staff with preset roles.

## Coming next (per `PROJECT_DECISIONS.md`)

Phase 2: POS + laser cutter queue + returns + barcode label printing. Phase 3: vendors, purchases and ledgers. Phase 4: reports.
