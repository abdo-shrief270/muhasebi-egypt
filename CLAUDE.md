# Muhasebi — notes for AI assistants

SaaS for mobile-phone parts/accessories/repair shops in Egypt. Product plan: `PLAN.md`. Technical plan: `TECH_PLAN.md`. UI copy is Egyptian Arabic, RTL.

## Layout
- `apps/api` — Laravel 13, PHP 8.4, PostgreSQL. Modular monolith under `app/Modules/*`, shared kernel in `app/Support/*`.
- `apps/web` — Nuxt 4 SPA (`ssr: false`), Nuxt UI 4, Pinia. Talks to `/api/v1` with a Sanctum bearer token.

## Rules that tests enforce (`apps/api/tests/Architecture`)
- A module may only reference another module's `Contracts\` and `Events\` namespaces.
- Every model under `app/Modules/*/Models` uses `BelongsToTenant`, except the allowlist (Tenant, User).
- Every route of an `Optional` module carries `module:{key}` middleware.

## Conventions
- New module: create `app/Modules/{Name}/module.php` returning a `ModuleManifest`; routes in `routes.php` (auto-prefixed `/api/v1`), migrations in `Database/migrations`, listeners registered via a `ModuleServiceProvider` `$listen` array.
- Cross-module side effects: if it must succeed with the change → call a `Contracts\` interface inside the transaction; otherwise → `EventRecorder::record(new SomethingHappened(...))` and a `ModuleListener` elsewhere.
- Controllers are thin: Form Request → Action → API Resource. Business rule failures throw `DomainRuleException` (rendered as `{message, code}`).
- Money is stored as integer piasters. IDs for shop data are UUIDv7 (`HasUuids`).
- Don't cache Eloquent models (Laravel 13 cache refuses to unserialize objects); cache arrays.
- Menu icons come from module manifests; add new ones to `icon.clientBundle.icons` in `apps/web/nuxt.config.ts`.

## Commands
- API: `php artisan test`, `vendor/bin/pint`
- Web: `npx nuxi typecheck`, `npm run build`
