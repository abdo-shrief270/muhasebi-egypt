# محاسبي (Muhasebi)

سيستم SaaS لمحلات قطع غيار وإكسسوارات وصيانة الموبايلات في مصر.

- الخطة العامة (Features + Billing): [`PLAN.md`](PLAN.md)
- الخطة التقنية: [`TECH_PLAN.md`](TECH_PLAN.md)

## الهيكل

```
apps/api   Laravel 13 — API (Modular Monolith + Event-Driven)
apps/web   Nuxt 4 + Nuxt UI — لوحة المحل والكاشير (RTL)
infra      Docker compose للتطوير (Postgres 17, Redis, Mailpit, MinIO)
```

## التشغيل محلياً

```bash
# 1) الخدمات
docker compose -f infra/compose.yml up -d

# 2) الـ API
cd apps/api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # محل تجريبي: 01000000000 / password
php artisan serve                   # http://localhost:8000
php artisan horizon                 # الـ Queue (أو: php artisan queue:work)
php artisan schedule:work           # الـ Relay الاحتياطي للأحداث

# 3) الواجهة
cd apps/web
npm install
npm run dev                         # http://localhost:3000
```

## الاختبارات

```bash
cd apps/api && php artisan test && vendor/bin/pint --test
cd apps/web && npx nuxi typecheck && npm run build
```

## أهم المفاهيم في الكود

| المفهوم | فين |
|---|---|
| **Module** = مجلد في `apps/api/app/Modules/{Name}` فيه `module.php` (الـ Manifest) | `app/Support/Modules` |
| تفعيل الـ Modules لكل محل (تجربة / مفعّل / مخفي / قراءة فقط) | `app/Modules/ModuleManager` |
| حماية الـ Routes: `module:repairs` | `EnsureModuleEnabled` |
| عزل بيانات المحلات: `BelongsToTenant` (بيرجّع صفر نتايج لو مفيش محل) | `app/Support/Tenancy` |
| الأحداث: `EventRecorder` بيكتب في `domain_events` جوه نفس الـ Transaction، والـ Relay بينشرها للـ Listeners | `app/Support/Events` |
| Listener لـ Module تاني: `extends ModuleListener` (بيتنفذ مرة واحدة، وبيتجاهل المحلات اللي الـ Module مقفول عندها) | `app/Modules/Repairs/Listeners` |
| الواجهة بتبني القايمة من `/api/v1/auth/me`، والصفحة بتعلن الـ Module بتاعها: `definePageMeta({ module: 'repairs' })` | `apps/web/app` |
