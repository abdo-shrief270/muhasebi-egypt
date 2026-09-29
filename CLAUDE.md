# Muhasebi — notes for AI assistants

SaaS for mobile-phone parts/accessories/repair shops in Egypt. Product plan: `PLAN.md`. Technical plan: `TECH_PLAN.md`. UI copy is Egyptian Arabic, RTL.

## Layout
- `apps/api` — Laravel 13, PHP 8.4, PostgreSQL. Modular monolith under `app/Modules/*`, shared kernel in `app/Support/*`.
- `apps/web` — Nuxt 4 SPA (`ssr: false`), Nuxt UI 4, Pinia. Talks to `/api/v1` with a Sanctum bearer token.

## Rules that tests enforce (`apps/api/tests/Architecture`)
- A module may only reference another module's `Contracts\` and `Events\` namespaces.
- Every model under `app/Modules/*/Models` uses `BelongsToTenant` (one shop) or `SharedBetweenTenants` (rows owned by two shops, e.g. inter-shop orders), except the allowlist (Tenant, User).
- Every route of an `Optional` module carries `module:{key}` middleware.
- Every signed-in API route checks a permission: `can:` middleware, a Form Request with its own `authorize()`, or an `abort_unless(...->can(...))` in the action (`RouteAuthorizationTest`; the few exceptions are listed there).

## Conventions
- New module: create `app/Modules/{Name}/module.php` returning a `ModuleManifest`; routes in `routes.php` (auto-prefixed `/api/v1`), migrations in `Database/migrations`, listeners registered via a `ModuleServiceProvider` `$listen` array.
- Cross-module side effects: if it must succeed with the change → call a `Contracts\` interface inside the transaction; otherwise → `EventRecorder::record(new SomethingHappened(...))` and a `ModuleListener` elsewhere.
- Permissions are declared in module manifests (`permissions: ['key' => 'Arabic label']`) and enforced with `can:{key}` route middleware / `$user->can()`. Owners have every permission of the modules the shop can use; others get their role's permissions ∩ usable modules (`Identity\PermissionResolver`). Owner-only screens use the `owner` gate.
- Routes that act inside a branch add the `branch` middleware and read `Support\Tenancy\CurrentBranch` (from the `X-Branch-Id` header).
- Sensitive actions call `Support\Audit\Auditor::record()` inside their transaction (shows in سجل العمليات).
- Controllers are thin: Form Request → Action → API Resource. Business rule failures throw `DomainRuleException` (rendered as `{message, code}`).
- Money is stored as integer piasters. IDs for shop data are UUIDv7 (`HasUuids`).
- Don't cache Eloquent models (Laravel 13 cache refuses to unserialize objects); cache arrays.
- Other shops' public info (name, code, phone) comes from `Identity\Contracts\ShopDirectory`, never from Identity's models.
- UI follows design direction A (`docs/design/option-a-*.png`): theme tokens in `apps/web/app/assets/css/main.css`. Dates/numbers use Latin digits (`ar-EG-u-nu-latn`, `.num`).
- Catalog: what is sold/stocked is a `ProductVariant` (own barcode, prices in piasters); compatibility (`device_model_product`) is per product. Text search goes through `Support\Text\SearchText` (Arabic normalisation: أإآ→ا، ة→ه، ى→ي) against stored `search_name` columns with `pg_trgm` indexes; `Product::search()` ANDs the words across name, SKU, barcode, brand and compatible models. New shops get `DefaultCatalog` via `TenantRegistered`.
- Stock: only through `Inventory\Contracts\StockLedger` (`receive` = new lot + weighted `avg_cost`; `issue` = FIFO over lots, may go negative and is then costed at `avg_cost`), called inside the caller's transaction. `stock_movements` is append-only; `stock_levels`/`stock_lots` are balances locked per (branch, variant). Stock is per branch (`branch` middleware). Other modules read variants via `Catalog\Contracts\VariantCatalog`, never Catalog models. Costs are shown only with `products.view_cost`.
- Purchases (Suppliers module): `CreatePurchaseAction` receives each line into stock at its cost after the invoice discount and posts the total to the supplier account; `SupplierAccount::post()` keeps `suppliers.balance` (> 0 = the shop owes) and the append-only `supplier_transactions` in step under a row lock. Returns issue from the purchase's own lot (`StockLedger::issue(..., fromLotId:)`). Per-shop document numbers come from `Support\Numbering\DocumentNumbers` inside the creating transaction. Ledgers order by their `seq` identity column, never by timestamps.
- Sales (POS): `CompleteSaleAction` prices lines from the catalog (`VariantSummary::priceFor(level)`), issues stock FIFO and stores each line's `unit_cost`; the client sends the sale `id` (the cart id) so a retried checkout is saved once. Only cash gives change. Returns refund each unit's share after the invoice discount and restock sound units at the line's cost. Receipts have a `public_token` → public page `/r/{token}` (QR on the receipt, WhatsApp share); pages opt out of login with `definePageMeta({ public: true })`.
- Cash (Cash module): selling needs the cashier's open shift (`cash_shifts`, one open per user per branch). Money moves only through `Cash\Contracts\CashDrawer::record()` inside the caller's transaction (cash always needs an open shift; card/wallet join one if open); `cash_movements` is append-only. Expenses / deposits / withdrawals are cash on the user's shift; closing stores expected vs counted per method. Shift numbers `SH-00001`.
- Customers: `customers.balance` (> 0 = owes the shop) and the append-only `customer_transactions` move together under a row lock (`CustomerLedger`). Credit (آجل) is the `credit` sale payment method: needs a customer and `customers.credit`, respects `credit_limit` via `Customers\Contracts\CustomerAccounts::chargeSale()`; a return with `refund_method: credit` goes back to the account. Phones are stored E.164; show them with `localPhone()`.
- Printing: wrap printable output in `<PrintSheet page-size margin>` and trigger with `usePrint()`; `@media print` shows only `.print-root`. Receipts (`PrintReceipt`, 80mm) and labels (`ProductLabel`, sized in mm; `/products/labels`) use `PrintBarcode` (EAN-13 when valid, else CODE128) and `PrintQrCode`. In-store barcodes are EAN-13 with prefix `2` (`GenerateBarcodesAction`).
- Dashboard: `/sales/stats` (reports.view; profit only with reports.profit) feeds the home page; charts use ECharts via `components/dashboard/*`, colours read from the `--app-chart` / `--ui-*` tokens and re-read on theme change, with a table view. Quick actions live in `composables/useQuickActions.ts` (home tiles + Ctrl+K). Icons named only in `.ts`/script code must be listed in `icon.clientBundle.icons` — the scan misses them.
- Reports module: read-only SQL over the other modules' tables (never their models). Each report implements `Reports\Support\Report` and returns a `ReportResult` (summary, typed columns, rows, totals, optional chart); register it in `ReportRegistry`. One page (`/reports/[key]`), the `.xlsx` export (`XlsxReport`) and the A4 print layout (PDF via the browser) serve every report. Profit needs `reports.profit`, costs `products.view_cost`; branches come from `Identity\Contracts\BranchDirectory`.
- Modules whose screens aren't built yet have `available: false` in their manifest: `ModuleAccess::enabled()` is false for them whatever the shop's row says (no menu, permissions, routes), trials can't start (`module_coming_soon`; registration skips them so the trial isn't burnt) and the modules page shows «قريباً». A single unbuilt screen: `new MenuItem(..., ready: false)`.
- Quick price check (استعلام سعر): `GET /inventory/price-check?q=` (products.view; wholesale/technician prices with sales.discount or products.manage, cost with products.view_cost, stock per branch with inventory.view), opened by `usePriceCheck().show()` — top bar, F8, Ctrl+K, home tile. Quick actions may `run` something instead of `to` a page.
- Menu entries come from module manifests (`new MenuItem(to, label, icon, permission, group: 'sales'|'stock'|'services'|'reports'|'settings')`); the sidebar groups them by `group` (titles in `apps/web/app/utils/navigation.ts`). Add new icons to `icon.clientBundle.icons` in `apps/web/nuxt.config.ts`.
- Light and dark themes: colours only through the `--ui-*` / `--app-*` tokens in `main.css` (`:root` and `.dark`), never hard-coded. Default is light; the top bar switches light / dark / follow device.

## Deployment
- `infra/production/`: Docker Compose stack (Caddy edge with auto-HTTPS serving the Nuxt static build and proxying `/api` to FrankenPHP; Horizon worker, scheduler, Reverb, Postgres 17, Redis). `init.sh` once, `deploy.sh` per update, `backup.sh`/`restore.sh`. Guide: `docs/DEPLOY.md`. On a server whose nginx already owns 80/443, `COMPOSE_FILE=compose.yml:compose.nginx.yml` (set by `./init.sh <domain> <email> nginx`) binds Caddy to `127.0.0.1:8088` over plain HTTP and trusts nginx's `X-Forwarded-*`; `nginx-site.conf` is the matching nginx site.
- CD: the `deploy` job in `.github/workflows/ci.yml` runs after api/web/docker pass on `main`, SSHes with a key pinned (`restrict,command=`) to `infra/production/ci-deploy.sh`, which only accepts `deploy <sha>` for a commit on `origin/main`, checks it out, runs `deploy.sh` and waits for the API healthcheck.

## Commands
- API: `php artisan test`, `vendor/bin/pint`
- Web: `npx nuxi typecheck`, `npm run build`
