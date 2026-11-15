# Mobile Wrap — Project Decisions

Agreed scope and decisions for the Accessory Management System, POS, Laser Cutter workflow and Storefront, built on the Herozi Laravel 12 template. Decided 2026-11-15.

## Environment

| Item | Decision |
|---|---|
| Server stack | WAMP (`C:\wamp64`) |
| PHP | 8.4.0 from WAMP (`C:\wamp64\bin\php\php8.4.0\php.exe`); CLI default on PATH is 8.2 |
| Database | MySQL 9.1 on `127.0.0.1:3306`, user `root`, new database `mobile_wrap` (existing `accessory` / `mobilehub` DBs are left untouched) |
| Framework | Laravel 12, Livewire 3 + Volt (class-based components) |
| CSS | Bootstrap 5 everywhere. Admin = Herozi as-is. Storefront themes = Bootstrap + per-theme SCSS, built with the existing Laravel Mix pipeline |
| URLs | Storefront at `/` (landing page = storefront home). Admin/POS at `/admin`. Herozi demo pages kept for reference under `/admin/demo/{page}` in local env only |

## Business

- Single shop (one stock pool, one cash drawer, unlimited bank accounts).
- Pakistan, PKR (Rs.), English only. No sales tax.
- Store name: **Mobile Wrap** (editable in Admin → Settings with logo and colors). No existing brand assets; logo/palette to be designed per theme.
- Staff: preset roles — Admin, Manager, Cashier, Laser Operator.
- No loyalty program.

## Catalog & inventory

- Categories (with subcategories), brands, products.
- Variants: **Color + any number of other attributes** (capacity, length, wattage…). Each variant has its own SKU, barcode, stock, **retail price, wholesale price** and optional compare-at price.
- Colors have name, hex swatch and multiple images.
- Shared **device catalog** (Device Brand → Device Model) used for storefront “Shop by device”, product compatibility, and laser jobs. Each device model stores front/back cut sizes (mm).
- Raw materials (glass, hydrogel, wraps) stocked **by area from rolls**.
- Stock movements ledger for every in/out; low-stock thresholds and alerts.
- Barcode labels: thermal label printer, label size set in Settings.

## POS & laser cutter

- Touch + barcode-scanner friendly POS, hold/resume, split payments (Cash, Bank Transfer, Customer Credit/khata).
- Wholesale customers get wholesale prices automatically.
- Receipts via browser print: 80mm thermal and A4 layouts.
- Returns and exchanges against an invoice (restock or mark damaged; refund to cash/bank/customer credit).
- Laser cutter = **job tracker only** (machine software holds templates). Queue: queued → cutting → done / failed.
- Cut size = device preset, staff can override per job. Area × qty (+ wastage %) deducted from the material roll on sale completion; failed cuts logged as wastage.
- Laser jobs are **POS only** (not orderable on the storefront).

## Storefront

- **5+ full themes** (own header, footer, homepage layout, product card, product page), selected in Admin → Settings. Same data, cart and checkout logic underneath.
- **Homepage section builder** in admin: toggle, reorder and edit sections (hero slides, featured categories, flash sale, shop by device, bundles, testimonials, etc.).
- Product page: color swatches switch the gallery images instantly (Livewire, no reload).
- Customer accounts: register/login with email or phone + password; **guest checkout allowed**.
- Payments: **Cash on Delivery** and **manual bank transfer** (customer uploads receipt, admin verifies, posts to that bank ledger).
- Shipping: flat fee set in Admin → Settings + **free delivery above an amount** set in Settings.
- Coupons (percent/fixed, usage limits, expiry), deals/bundles/flash sales, configurable popups.
- Customer portal: order history and status, wishlist, ledger balance.
- Notifications: admin in-app alerts only (new online orders, low stock).

## Proposed themes (to confirm)

1. **Noir Tech** — dark premium, single neon accent, large device imagery.
2. **Deal Mart** — bright, deal-driven, flash-sale countdowns, dense grids.
3. **Studio Minimal** — off-white, editorial headings, generous whitespace.
4. **Street Drop** — bold Gen-Z, loud colors, sticker badges, wrap-culture vibe.
5. **Classic Retail** — clean trust-focused shop, mega menu, sidebar filters.

## Status

- **Phase 1 delivered 2026-11-15** — see `docs/GETTING_STARTED.md` for how to run it and what's included. The five themes above were built as proposed.
- **Phase 2 delivered 2026-11-16** — POS, laser queue, returns/exchanges, receipts, barcode labels. Decisions made at the start of Phase 2:
  - Laser service fee: the cashier types it for each job (no price list; the last fee charged is shown as a hint).
  - Discounts: any POS user can discount any amount (cart Rs/% and per line); discounts are recorded on the invoice.
  - Khata: any saved customer can buy on credit with no limit checks.
  - No cash register shifts; cash sales post straight to the Cash In Hand ledger.
  - POS allows selling when the system shows 0 stock (shows a warning; stock goes negative) so a miscount never blocks a sale.

## Build phases

1. **Foundation + storefront** — install Livewire/Volt + roles, full database schema for all modules, models, demo seed data (~40 products with colors/images, devices, materials), admin catalog/color/image/device manager, settings (store, theme picker, shipping, bank accounts), homepage section builder, then the storefront: landing, shop with filters, product page with live swatches, cart, checkout, customer account, coupons, popups.
2. **POS + laser cutter** — POS screen, holds, split payments, receipts, returns/exchanges, laser job queue, barcode label printing.
3. **Ledgers & finance** — vendors, purchases, vendor ledger, customer khata ledger, bank and cash ledgers, expenses, stock ledger reports.
4. **Marketing & reporting polish** — deals/bundles/flash sales admin, dashboards and reports.
