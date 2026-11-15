# Mobile Wrap — Getting started

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

## Phase 2 — POS, laser queue, returns, labels

- **POS** (`/admin/pos`, full screen): USB barcode scanner or SKU entry, product grid by category, variant picker, editable prices and line discounts, cart discount (Rs or %), coupons, walk-in or saved customers (wholesale customers get wholesale prices automatically), hold/resume, split payment across cash (with change), bank transfer, khata and exchange credit. Shortcuts: F2 search, F4 laser cut, F8 hold, F9 pay.
- **Ledgers on every sale:** cash → Cash In Hand, bank → that bank account, khata → customer ledger; stock leaves via the stock ledger.
- **Laser cuts:** added at the POS (phone model presets fill the cut size, cashier types the fee). On payment the job goes to `/admin/laser` and the material area (+ wastage %) is deducted from the roll. Operator board: start → done / failed → one re-cut (logged as wastage) or cancel (material returned).
- **Receipts:** 80 mm thermal and A4 invoice, each with an invoice barcode. Footer, header line, default format and auto-print in Settings → POS & receipts.
- **Returns & exchanges** (`/admin/returns`): find the invoice (scan the receipt barcode), pick items and condition (restock / damaged); refunds are spread across the invoice discount. Refund by cash, bank, khata credit, none — or exchange, which opens the POS with the credit applied (any leftover is paid back in cash).
- **Barcode labels** (`/admin/labels`, or the barcode button on a product): 38×25, 40×30 or 50×30 mm, EAN-13 (Code 128 fallback), optional price and store name.
- **POS sales list** (`/admin/pos/sales`): daily totals by payment method, reprint receipts, start a return.

## Phase 3 — purchases, ledgers, bank & cash, expenses

- **Vendors** (`/admin/vendors`): opening balance, what you owe each vendor, ledger with date filter and adjustments, pay vendor (cash or bank, optionally against a bill), printable statement.
- **Purchases** (`/admin/purchases`): enter the vendor's bill (bill no., due date, discount, freight). Save as draft, as ordered, or **save & receive** in one go. Receive partial deliveries later; stock and the payable are booked on receipt and cost prices re-average (weighted average). Return goods to the vendor, pay against the bill (or an advance before delivery), cancel unreceived POs, print the PO. Raw-material lines are in cm² and have a **roll calculator** (rolls × width × length, price per roll).
- **Customer khata** (`/admin/customers/{id}`): balance, ledger in date order, orders, receive payment (prints an 80 mm receipt), adjustments for old paper-khata balances, WhatsApp reminder, printable statement. The customer list filters by "owes money" and shows the total receivable.
- **POS → Khata payment**: collect a customer's balance at the counter (cash or bank) and print the receipt.
- **Bank & cash** (`/admin/finance/accounts`): balances with today's in/out, transfers (e.g. deposit the day's cash), add / withdraw money (owner capital or drawings), latest movements. **Account ledger** (`/admin/finance/ledger`): filter by account, type, dates and notes, with opening/closing balance and a printable statement.
- **Expenses** (`/admin/finance/expenses`): month view with category breakdown and change vs last month, receipt photo/PDF upload, editable categories. Deleting an expense puts the money back on its account.
- **Stock movements** (`/admin/reports/stock-movements`): every stock change across all products with value at cost, filters by type, product and dates.
- **Statements** print on A4 from any ledger (`/admin/statements/{customer|vendor|account}/{id}`), add `?autoprint=0` to preview without the print dialog.

Demo data (`DemoFinanceSeeder`): five purchases in every state (received, partly received, ordered, draft, one with a return), vendor payments, this month's expenses, a cash deposit and a khata collection.

## Reports (Admin → Reports)

- **Sales report** (`/admin/reports/sales`): net sales, invoices, discounts, returns, laser fees, delivery charges, cost of goods and margin (Admin/Manager), with tabs for day-by-day, products, categories, payment methods & channel, and staff & customers. Filter by period and channel.
- **Purchase report** (`/admin/reports/purchases`): goods received, returns to vendors, payments and what you owe, by vendor, by bill and by product. Filter by period and vendor.
- **Profit & loss** (`/admin/reports/profit-loss`): full statement vs the previous period — revenue, cost of sales (incl. laser material and wastage), gross profit, expenses by category, net profit.
- **Download PDF** on each report saves an A4 PDF with the same filters (`/admin/reports/{sales|purchases|profit-loss}/pdf?period=…`). Periods: today, yesterday, this week, last 7 days, this month, last month, last 30 days, this year or custom dates.

## Coming next (per `PROJECT_DECISIONS.md`)

Rest of Phase 4: marketing & reporting polish — dashboards and charts.
