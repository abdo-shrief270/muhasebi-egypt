# رفع محاسبي على سيرفر VPS

الطريقة دي بتشغّل كل حاجة بـ **Docker** على سيرفر واحد، ومعاها **HTTPS تلقائي** (شهادة Let's Encrypt من غير أي إعداد).

```
الإنترنت ──443──▶ Caddy ──┬── /           → واجهة Nuxt (ملفات ثابتة)
                          ├── /api, /up   → Laravel (FrankenPHP)
                          └── /app        → Reverb (WebSockets)
         Laravel ⇄ PostgreSQL 17 · Redis · Horizon (الـ Queue) · Scheduler
```

## المطلوب
- سيرفر **Ubuntu 22.04 / 24.04**، أقل حاجة **2 vCPU و 4GB RAM** (Hetzner CX22 / DigitalOcean 4GB كفاية للبداية).
- **دومين أو sub-domain** (مثلاً `app.muhasebi.com`) عامل له **A record** بيشاور على الـ IP بتاع السيرفر. **لازم يكون شغال قبل التشغيل** عشان شهادة الـ HTTPS.
- البورتات **80 و 443** مفتوحة.

## أول مرة (حوالي 15 دقيقة)

**1) على السيرفر: Docker والجدار الناري**
```bash
ssh root@YOUR_SERVER_IP

curl -fsSL https://get.docker.com | sh
apt-get install -y git ufw
ufw allow OpenSSH && ufw allow 80 && ufw allow 443/tcp && ufw allow 443/udp && ufw --force enable
```

**2) نزّل الكود**
```bash
git clone https://github.com/abdo-shrief270/muhasebi-egypt.git /opt/muhasebi
cd /opt/muhasebi
git checkout dev/sleepy-newton-v10sxx      # أو main بعد الـ merge
```
> الـ repo لو Private: اعمل **Deploy key** (GitHub ← Settings ← Deploy keys) أو استخدم Personal Access Token في الـ clone.

**3) الإعدادات السرية** (بتتعمل مرة واحدة، وبتولّد باسووردات قوية لوحدها)
```bash
cd infra/production
./init.sh app.muhasebi.com you@email.com
```
- بيعمل ملف `.env` فيه `APP_KEY` وباسوورد الداتابيز وRedis.
- **خد نسخة من `.env` واحفظها في مكان آمن** (مدير باسووردات). من غيرها مش هتعرف ترجّع الداتا لو السيرفر ضاع.

**4) شغّل**
```bash
./deploy.sh
```
- بيبني الصور، ويشغّل الداتابيز، ويعمل الـ migrations، ويشغّل كل حاجة.
- أول مرة بياخد 5–10 دقايق (بناء الصور).
- افتح `https://app.muhasebi.com/up` لازم يطلع صفحة خضرا، وبعدين `https://app.muhasebi.com/register` وسجّل أول محل.

**5) النسخ الاحتياطي اليومي: شغال لوحده**
مفيش حاجة تعملها: خدمة `backup` في الـ stack بتاخد نسخة من الداتابيز والملفات المتخزّنة **كل يوم الساعة 3 الفجر** (بتوقيت القاهرة)، وأول ما تشتغل لو مفيش نسخة من آخر 24 ساعة. بس **لازم تظبط النسخة برا السيرفر** (تحت) — نسخة على نفس الهارد مش كفاية.

## النسخ الاحتياطي
- **فين:** `infra/production/backups/`، ملفين لكل نسخة بنفس الوقت:
  - `muhasebi-YYYYmmdd-HHMMSS.dump`: الداتابيز (صيغة `pg_dump -Fc`، مضغوطة، وبتتأكد إنها بتتقري قبل ما تتحفظ).
  - `muhasebi-YYYYmmdd-HHMMSS-files.tar.gz`: الملفات اللي البرنامج بيخزّنها (صور بطايق وأجهزة المستعمل وهي مشفّرة، إثباتات دفع الاشتراك، صور الملاحظات). `BACKUP_FILES=0` يخليها داتابيز بس.
- **إمتى:** كل يوم في `BACKUP_TIME` (افتراضي `03:00`) بتوقيت `BACKUP_TZ` (افتراضي `Africa/Cairo`)، وكمان **قبل كل نشر** (`deploy.sh` قبل الـ migrations؛ `SKIP_BACKUP=1 ./deploy.sh` يتخطاها).
- **قد إيه:** آخر `BACKUP_KEEP_DAYS` يوم (افتراضي 14)، والأقدم بيتمسح لوحده.
- **هل شغال؟** `docker compose ps backup` لازم يبقى `healthy` (بيبقى `unhealthy` لو آخر نسخة ناجحة أقدم من 25 ساعة)، واللوج: `docker compose logs backup --tail 20`.
- الإعدادات في `.env` (موجودة في `.env.example`)، وبعد أي تغيير: `docker compose up -d backup`.
- لو كنت حاطط سطر `crontab` قديم لـ `backup.sh`، امسحه: مبقاش لازم (لو فضل، هيعمل نسخة زيادة بس).

### نسخة برا السيرفر (مهم)
الخدمة فيها [rclone](https://rclone.org)، فتقدر تبعت كل نسخة لأي تخزين: **Cloudflare R2** (10GB ببلاش)، Backblaze B2، AWS S3، Google Drive… الإعداد كله متغيرات في `.env`. مثال R2:
1. من Cloudflare: R2 ← Create bucket (مثلاً `muhasebi-backups`)، وبعدين Manage R2 API Tokens ← Create token بصلاحية **Object Read & Write** على الـ bucket ده بس.
2. زوّد في `.env`:
```bash
BACKUP_RCLONE_REMOTE=offsite:muhasebi-backups
BACKUP_REMOTE_KEEP_DAYS=30          # اختياري (0 = متمسحش حاجة من برا)
RCLONE_CONFIG_OFFSITE_TYPE=s3
RCLONE_CONFIG_OFFSITE_PROVIDER=Cloudflare
RCLONE_CONFIG_OFFSITE_ACCESS_KEY_ID=...
RCLONE_CONFIG_OFFSITE_SECRET_ACCESS_KEY=...
RCLONE_CONFIG_OFFSITE_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
RCLONE_CONFIG_OFFSITE_NO_CHECK_BUCKET=true
```
3. `docker compose up -d backup && ./backup.sh` — لازم تشوف `copied off-site: ...`.

أي تخزين تاني: اسم الـ remote هو اللي بعد `RCLONE_CONFIG_` (هنا `OFFSITE`)، والإعدادات نفس اللي في [توثيق rclone](https://rclone.org/docs/#config-file) بس بحروف كبيرة. المفاتيح دي خليها بصلاحية على الـ bucket بس، وخد نسخة من `.env` في مكان آمن زي ما قلنا.

### الاسترجاع
```bash
./restore.sh backups/muhasebi-20261005-030000.dump
```
بيوقف التطبيق، ويرجّع الداتابيز كلها للنسخة دي، ويرجّع ملفات نفس النسخة لو `-files.tar.gz` جنبها (أو اديله مسارها كـ argument تاني)، ويشغّل كل حاجة تاني. **أي حاجة اتسجلت بعد النسخة دي بتضيع.**
لو السيرفر نفسه ضاع: جهّز سيرفر جديد بالخطوات فوق **بنفس ملف `.env` القديم** (عشان `APP_KEY`: من غيره أكواد التحقق بخطوتين وأكواد فتح الأجهزة وصور البطايق المشفّرة مش هتتقري)، وبعدين نزّل النسخة من برا وارجّعها:
```bash
docker compose run --rm -T backup rclone copy offsite:muhasebi-backups/ /backups/ --include 'muhasebi-20261005-030000*'
./restore.sh backups/muhasebi-20261005-030000.dump
```

## لو السيرفر عليه nginx ومواقع تانية
الإعداد الافتراضي بيخلّي Caddy بتاع محاسبي ياخد البورتات 80 و443. لو nginx شغال بالفعل على البورتات دي لمواقع تانية، فيه وضع مخصوص: **nginx يفضل ماسك 80 و443 وشهادات HTTPS**، وبيحوّل دومين محاسبي بس لـ Caddy على `127.0.0.1:8088`، اللي مش مكشوف على الإنترنت.

```
الإنترنت ──443──▶ nginx ──┬── مواقعك التانية (زي ما هي)
                          └── app.muhasebi.com → 127.0.0.1:8088 → Caddy → Laravel / Nuxt / Reverb
```

**تشغيل جديد:** زوّد كلمة `nginx` في آخر أمر `init.sh`:
```bash
./init.sh app.muhasebi.com you@email.com nginx
```

**لو كنت شغّلت `init.sh` قبل كده** (ملف `.env` موجود):
```bash
cd /opt/muhasebi/infra/production
echo 'COMPOSE_FILE=compose.yml:compose.nginx.yml' >> .env
```

**بعد كده (في الحالتين):**
```bash
# 1) موقع nginx لمحاسبي (غيّر الدومين)
sed "s/app.example.com/app.muhasebi.com/" nginx-site.conf > /etc/nginx/sites-available/muhasebi
ln -s /etc/nginx/sites-available/muhasebi /etc/nginx/sites-enabled/muhasebi
nginx -t && systemctl reload nginx

# 2) شهادة HTTPS (certbot بيعدّل ملف الموقع لوحده)
apt-get install -y certbot python3-certbot-nginx   # لو مش متسطّب
certbot --nginx -d app.muhasebi.com

# 3) شغّل محاسبي
./deploy.sh
```
- اتأكد إن `ss -ltnp | grep 8088` مش بيطلّع حاجة قبل التشغيل. لو البورت ده مستخدم، اختار غيره: ضيف `EDGE_PORT=8090` في `.env` وغيّر `8088` في ملف nginx.
- التجديد التلقائي لشهادة HTTPS بيبقى مسؤولية certbot (بيتظبط لوحده مع التسطيب).

## كل تحديث بعد كده
```bash
cd /opt/muhasebi/infra/production && ./deploy.sh
```
بيسحب آخر كود، ويبني، ويعمل الـ migrations، ويعيد تشغيل الـ workers. الداتا مش بتتلمس.

## النشر التلقائي (CI/CD)
أي push على `main` بيشغّل الـ CI على GitHub. لو التستات والـ typecheck وبناء الصور عدّوا، الـ job اللي اسمها `deploy` بتدخل على السيرفر بـ SSH وتنزّل **نفس الـ commit اللي اتجرّب بالظبط**، وبعدين تستنى لحد ما الـ API يبقى healthy. لو أي خطوة فشلت، الـ job بتبقى حمرا ومفيش حاجة بتتنشر.

```
push على main ──▶ api (تستات) + web (typecheck/build) + docker (بناء الصور)
                         └── كلهم نجحوا ──▶ deploy: ssh ──▶ ci-deploy.sh <sha> ──▶ deploy.sh ──▶ فحص /up
```

**مفتاح الـ SSH بتاع GitHub مقفول على سكريبت واحد** (`ci-deploy.sh`). اللي معاه المفتاح ميقدرش يفتح shell ولا يشغّل أوامر، هو بس يطلب نشر commit موجود فعلاً على `main`.

### الإعداد (مرة واحدة)
**1) على السيرفر: مفتاح مخصوص لـ GitHub**
```bash
ssh-keygen -t ed25519 -N "" -C github-deploy -f /root/.ssh/github_deploy
echo "restrict,command=\"/opt/muhasebi/infra/production/ci-deploy.sh\" $(cat /root/.ssh/github_deploy.pub)" >> /root/.ssh/authorized_keys

cat /root/.ssh/github_deploy          # انسخه كله (ده DEPLOY_SSH_KEY)
ssh-keyscan -t ed25519 YOUR_SERVER_IP 2>/dev/null   # انسخ السطر (ده DEPLOY_KNOWN_HOSTS)
```
بعد ما تحط المفتاح في GitHub (الخطوة 2)، امسحه من السيرفر: `rm /root/.ssh/github_deploy`، وسيب الـ `.pub`.

**2) على GitHub:** Settings ← Secrets and variables ← Actions
| النوع | الاسم | القيمة |
|---|---|---|
| Secret | `DEPLOY_HOST` | IP السيرفر |
| Secret | `DEPLOY_USER` | `root` |
| Secret | `DEPLOY_SSH_KEY` | محتوى `github_deploy` (المفتاح الخاص كله، من `-----BEGIN` لـ `-----END`) |
| Secret | `DEPLOY_KNOWN_HOSTS` | ناتج `ssh-keyscan` |
| Secret (اختياري) | `DEPLOY_PORT` | لو SSH مش على 22 |
| Variable | `PRODUCTION_URL` | `https://app.muhasebi.com` |

**3) branch اسمه `main`:** ده اللي بيتنشر منه. لو مش موجود، اعمله من الـ branch الحالي واختاره default branch (Settings ← General ← Default branch).

**4) جرّب:** Actions ← CI ← Run workflow ← `main`. أو اعمل أي push على `main`.

### ملاحظات
- **موافقة قبل النشر (اختياري):** Settings ← Environments ← `production` ← Required reviewers. كده كل نشر بيستنى زرار Approve منك.
- **الـ Pull Requests:** بتشغّل التستات بس، ومش بتنشر.
- **نشر يدوي من السيرفر:** `./deploy.sh` لسه شغال زي ما هو، وبيسحب آخر `main`.
- **الرجوع لنسخة قديمة:** اعمل `git revert` للـ commit اللي فيه المشكلة واعمله push على `main`، فبيتنشر لوحده. الـ migrations مش بترجع لوحدها، فخلّي بالك من أي migration بتمسح أو بتغيّر أعمدة.

## أوامر مفيدة
| عايز | الأمر (من `infra/production`) |
|---|---|
| حالة الخدمات | `docker compose ps` |
| اللوجز | `docker compose logs -f api` (أو `worker` / `caddy`) |
| أمر artisan | `docker compose exec api php artisan <command>` |
| نسخة احتياطية دلوقتي | `./backup.sh` |
| النسخ الموجودة | `ls -lh backups/` |
| استرجاع نسخة | `./restore.sh backups/muhasebi-XXXX.dump` |
| إيقاف كله | `docker compose down` (الداتا بتفضل) |
| حساب الإدارة (Super Admin) | `docker compose exec api php artisan billing:admin you@example.com "اسمك"` (بيسأل على كلمة السر؛ نفس الأمر بيغيّرها)، وبعده **لازم** `docker compose exec api php artisan billing:admin-2fa you@example.com` (بيطلع مفتاح تضيفه في Google Authenticator وتكتب الكود) — وبعدين `https://<ADMIN_DOMAIN>` بالإيميل + كلمة السر + كود التطبيق. لو ضيّعت الموبايل: شغّل `billing:admin-2fa` تاني من السيرفر |
| مزامنة أقسام المشتركين | `docker compose exec api php artisan billing:sync-modules` (بعد ما قسم «قريباً» يبقى متاح) |

**الموقع التعريفي (muhasebi.com):** اعمل A record للدومين الأساسي (`@`) ولـ `www` على IP السيرفر (بدل صفحة الـ parking لو موجودة)، وبعدين `cd infra/production && ./setup-landing.sh` — بيسأل على الدومين، يتأكد من الـ DNS، يكتب `LANDING_DOMAIN` و`LANDING_SITE_ADDRESS` في `.env`، يضيف موقع nginx + شهادة certbot لو السيرفر عليه nginx، ينشر، ويتأكد إن الصفحة والأسعار شغالين. الموقع صفحات ثابتة (`apps/site`) والأسعار بتيجي من `/api/v1/public/plans` فبتتحدث لوحدها مع `config/billing.php`.

**الظهور على جوجل:** الموقع جاهز للأرشفة (عنوان ووصف وcanonical لكل صفحة، بيانات schema.org، `sitemap.xml` بالصور، `robots.txt`، و`llms.txt` / `llms-full.txt` لمساعدات الذكاء الاصطناعي، والأسعار مكتوبة في الـ HTML وقت البناء). خطوة واحدة عليك:
1. ادخل [Google Search Console](https://search.google.com/search-console) → Add property → **URL prefix** `https://muhasebi.com` → طريقة **HTML tag** → انسخ قيمة `content="…"` بس.
2. حطها في `.env` كـ `GOOGLE_SITE_VERIFICATION=...` و`./deploy.sh`، وبعدين Verify.
3. من Sitemaps ابعت `sitemap.xml`، ومن URL Inspection اطلب Indexing للصفحة الرئيسية و`/pricing`.
4. (اختياري) [Bing Webmaster](https://www.bing.com/webmasters) نفس الكلام بـ `BING_SITE_VERIFICATION` (أو Import من Search Console).

**أسهل طريقة:** `cd infra/production && ./setup-admin.sh` — بيسألك على دومين الإدارة والـ IPs المسموحة وبيانات InstaPay وإيميلك وكلمة السر، وبعدين يعمل كل اللي تحت لوحده (‏`.env`، موقع nginx + شهادة certbot لو السيرفر عليه nginx، النشر، حساب الإدارة، وربط تطبيق Authenticator) ويتأكد إن اللوحة شغالة. ينفع تشغّله تاني لتغيير أي حاجة.

**لوحة الإدارة (دومين لوحدها):** اعمل DNS لدومين تاني (مثلاً `admin.example.com`) على نفس السيرفر، وحطه في `.env` كـ `ADMIN_DOMAIN`، واختياري `ADMIN_ALLOWED_IPS` (IPs أو نطاقات مفصولة بفاصلة) عشان محدش غيرك يوصل لها، وبعدين `./deploy.sh`. Caddy بيطلع لها شهادة HTTPS لوحده (ولو السيرفر عليه nginx: فيه `server` تاني للدومين ده في `nginx-site.conf` + `certbot --nginx -d admin.example.com`). الـ API بتاع الإدارة مش بيرد غير على الدومين ده؛ على دومين المحلات بيرجّع 404. الدخول: 5 محاولات غلط بتقفل 15 دقيقة، والجلسة بتخلص بعد `ADMIN_TOKEN_HOURS` (8 ساعات)، وكل حاجة بتتسجل في «سجل الإدارة».

**الاشتراكات:** الدفع بـ InstaPay. حط في `.env` بتاع السيرفر `BILLING_INSTAPAY_ADDRESS` و`BILLING_INSTAPAY_NAME` و`BILLING_INSTAPAY_PHONE` (بيظهروا لصاحب المحل في صفحة الاشتراك)، وبعدين `./deploy.sh`. الأسعار والباقات في `apps/api/config/billing.php`.

## التطبيق على الموبايل والكمبيوتر
محاسبي بيتنزّل كتطبيق (PWA) من المتصفح نفسه: مفيش حاجة تتعمل على السيرفر غير HTTPS (Caddy بيعمله). الـ manifest في `apps/web/public/site.webmanifest` والأيقونات في `public/icons`؛ Caddy بيبعت الـ manifest بـ `application/manifest+json` ومن غير كاش، والأيقونات بكاش سنة (لو غيّرت اللوجو: `node scripts/pwa-icons.mjs` في `apps/web` وزوّد `?v=` في الـ manifest و`nuxt.config.ts`). كل نشر بيطلّع للي منزّلين التطبيق «فيه نسخة جديدة — حدّث». لو nginx قدام Caddy، هو بيمرر بس ومش محتاج حاجة.

**Google Play (اختياري):** نفس التطبيق يتحط على Play Store كـ Trusted Web Activity من غير ما نكتب تطبيق أندرويد:
1. `npx @bubblewrap/cli init --manifest https://<الدومين>/site.webmanifest` (اسم الـ package مثلاً `com.muhasebi.app`)، ثم `bubblewrap build` بيطلّع `.aab` ترفعه على Play Console (حساب مطوّر 25 دولار مرة واحدة).
2. من Play Console ← App signing خد بصمة SHA-256 بتاعة مفتاح التوقيع، وحطها في `.env` على السيرفر:
   ```
   TWA_PACKAGE=com.muhasebi.app
   TWA_SHA256=AB:CD:…        # لو أكتر من بصمة افصلهم بفاصلة
   ```
3. `./deploy.sh`، واتأكد: `curl https://<الدومين>/.well-known/assetlinks.json` يرجّع JSON فيه الـ package (الـ API بيكتبه من `.env`). من غيره التطبيق بيفتح بشريط المتصفح فوق.

iPhone مالوش طريقة زي دي: هناك بيتنزّل من سفاري بـ «إضافة إلى الشاشة الرئيسية».

## الإشعارات على الموبايل (Push)
مرة واحدة على السيرفر:
```bash
cd /opt/muhasebi/infra/production
docker compose run --rm api php artisan notifications:vapid-keys   # بيطبع سطرين
nano .env      # الصق السطرين: VAPID_PUBLIC_KEY=… و VAPID_PRIVATE_KEY=…
./deploy.sh
```
بعدها أي حد يدخل «الإشعارات» من قايمة حسابه ويدوس «شغّل الإشعارات» على جهازه. **متغيّرش المفاتيح دي بعد كده**: لو اتغيّرت، الإشعارات بتقف على كل الأجهزة لحد ما كل واحد يشغّلها تاني. على الآيفون بتشتغل بس من التطبيق المنزّل على الشاشة الرئيسية (iOS 16.4 أو أحدث). ملخص آخر اليوم بيتبعت من الـ scheduler (`notifications:daily-summary` كل 10 دقايق بيشوف مين ساعته جت).

## المتجر الأونلاين (اسم-المحل.<الدومين>)
كل محل بيبقى ليه متجر على دومين فرعي باسمه، مثلاً `elnour.muhasebi.com`. صاحب المحل بيختار الاسم من «المتجر الأونلاين» جوه البرنامج، ومحل جديد مش محتاج منك أي حاجة. على السيرفر **مرة واحدة**:
1. في Cloudflare ← DNS: سجل واحد A، الاسم `*`، القيمة IP السيرفر، والـ Proxy **مقفول** (السحابة رمادي). السجلات الموجودة (`app`، `admin`، `www`) بتفضل زي ما هي وبتكسب على الـ `*`.
2. في Cloudflare ← My Profile ← API Tokens ← Create Token: اختار قالب «Edit zone DNS» على الدومين بتاعك، وانسخ الـ token.
3. شغّل `./setup-store.sh`، وهو:
   - بيسأل عن الدومين ويتأكد إن الـ `*` بيشاور على السيرفر.
   - بيكتب `STORE_HOST` و`STORE_URL` و`STORE_SITE_ADDRESS` و`STORE_TLS` في `.env`.
   - لو nginx قدام Caddy: بيسطّب `certbot` و`python3-certbot-dns-cloudflare`، ويحفظ الـ token في `/root/.secrets/muhasebi-cloudflare.ini` (للـ root بس)، ويطلّع **شهادة wildcard واحدة** `*.<الدومين>` بتتجدد لوحدها، ويضيف موقع nginx ليها.
   - لو Caddy لوحده: Caddy بيطلّع شهادة لكل متجر أول ما حد يفتحه، وبعد ما يسأل الـ API إن المتجر موجود (لو المحلات كترت أوي، Let's Encrypt بيحدد 50 شهادة جديدة في الأسبوع، فالأحسن nginx + wildcard).
   - بيعمل deploy ويتأكد إن دومين فرعي بيرد.

المتجر نفسه حاوية `store` (Nuxt بـ SSR) ورا Caddy، وبيعرف المحل من الدومين الفرعي. لينكات الشكل القديم `store.<الدومين>/اسم-المحل` بتتحول لوحدها (301) للدومين الفرعي. صور الأصناف واللوجوهات بتتخزن في `storage/app` (فبتدخل في النسخة الاحتياطية)، وCaddy بيقدّمها من الـ API بكاش سنة. لو متجر مش بيفتح: `docker compose logs store caddy --tail 50`.

### دومين المحل الخاص (www.elnour-mobile.com)
صاحب المحل بيكتب الدومين من «المتجر الأونلاين» ← «دومين خاص بيك»، وبيضيف عند شركة الدومين سجلين بيظهروله: `TXT` على `_muhasebi.<الدومين>` (يثبت إن الدومين بتاعه) و`CNAME` لـ `اسم-المحل.<الدومين بتاعنا>` (يوصّله بالسيرفر)، ويدوس «اتأكد». بعدها المتجر بيفتح على دومينه، والدومين الفرعي بيحوّل عليه (301).
- **Caddy لوحده:** مفيش حاجة تعملها؛ `setup-store.sh` بيحط `STORE_CUSTOM_ADDRESS=https://` وCaddy بيطلّع الشهادة أول فتحة (بيسأل الـ API الأول إن الدومين اتأكد لمحل متجره مفتوح).
- **nginx قدام Caddy:** `setup-store.sh` بيعرض يحط cron كل 10 دقايق بيشغّل `./sync-store-domains.sh`: لكل دومين اتأكد وبيشاور على السيرفر بيضيف موقع nginx ويطلّعله شهادة بـ `certbot --nginx`، والدومين اللي اتشال موقعه بيتشال. تقدر تشغّله بإيدك وتشوف بيعمل إيه. لستة الدومينات: `docker compose exec api php artisan online-store:domains`.

## التحديث اللحظي (WebSockets) والموافقات
شاشة المالك وطلبات الموافقة (خصم كبير، بيع تحت التكلفة، مرتجع أو مصروف فوق حد) بتوصل لحظياً عن طريق Reverb (حاوية `reverb` في الـ compose، وCaddy بيوجّه `/app/*` ليها). `init.sh` بيعمل المفاتيح؛ اتأكد إن `.env` فيه:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=…  REVERB_APP_KEY=…  REVERB_APP_SECRET=…   # مش فاضيين
REVERB_HOST=<الدومين>   REVERB_PORT=443   REVERB_SCHEME=https
```
لو حاجة منهم ناقصة: `REVERB_APP_KEY=$(openssl rand -hex 16)` و`REVERB_APP_SECRET=$(openssl rand -hex 24)` و`REVERB_APP_ID` أي رقم، وبعدين `./deploy.sh`. التطبيق بيبعت الأحداث للحاوية جوه الشبكة (`REVERB_PUBLISH_*` في الـ compose)، والمتصفح بيتصل بـ `wss://<الدومين>/app/…`. لو nginx قدام Caddy، `nginx-site.conf` فيه الـ upgrade بتاع `/app/`. التأكد: `docker compose logs reverb --tail 20`، وفي المتصفح (DevTools ← Network ← WS) اتصال `app/…` شغال. لو Reverb واقع، الشاشات بترجع تعمل تحديث كل شوية لوحدها ومفيش حاجة بتقف.

الموافقات نفسها بتتشغّل من «المميزات» ← تطبيق صاحب المحل (كلها مقفولة في الأول)، وكل مدير يحط رقمه السري (PIN) من «الأمان» عشان يوافق من جهاز الكاشير.

**قفل التطبيق بالبصمة (passkeys):** بيشتغل لوحده على `APP_URL` (لازم HTTPS). لو التطبيق بيتفتح من أكتر من عنوان، اكتبهم في `WEBAUTHN_ORIGINS` (مفصولين بفاصلة)، و`WEBAUTHN_RP_ID` = الدومين نفسه. **متغيّرش الدومين** بعد ما الناس تسجّل بصماتها: البصمات مربوطة بيه، ولو اتغيّر كل واحد يسجّل بصمته تاني (الـ PIN بيفضل شغال).

## المراقبة والتنبيهات (Telegram)
شغّل `./setup-monitoring.sh` مرة واحدة: بيطلب توكن بوت Telegram (من @BotFather) ورقم الشات، ويحطهم في `.env`، ويبعت رسالة تجربة، ويحط cron كل دقيقتين بيشغّل `./monitor.sh`.
- **أخطاء السيرفر:** أي خطأ بيحصل في التطبيق بيتسجل في لوحة الإدارة ← «السيرفر» (متجمع بعدد المرات)، وبيوصلك عليه رسالة أول مرة وبعدها مرة كل نص ساعة بالكتير.
- **`monitor.sh`:** بيشيّك على قاعدة البيانات والكاش والطوابير والمهام المجدولة والمساحة (`php artisan monitoring:check`)، وإن `https://<الدومين>/up` بيرد، وإن مفيش حاوية واقفة أو unhealthy (زي النسخة الاحتياطية لو اتأخرت). بيبعت رسالة لما حاجة تقع، ورسالة لما ترجع، مش كل مرة.
- **لو السيرفر كله وقع** محدش من جوه يقدر يبلّغ: ضيف مراقب مجاني من برّه (UptimeRobot أو Better Stack) على `https://<الدومين>/api/v1/health` يبلّغك لو مارجعش 200.
- تشيّك بإيدك: `docker compose exec api php artisan monitoring:check` (و `--test` يبعت رسالة تجربة).

## مشاكل شائعة
- **الموقع مش بيفتح / مفيش HTTPS:** اتأكد إن الـ A record بيشاور على السيرفر (`dig app.muhasebi.com`) وإن 80 و443 مفتوحين، وبص على `docker compose logs caddy`.
- **Server Error:** `docker compose logs api --tail 100`.
- **الأحداث مش بتتنفّذ (مثلاً قوايم الأعطال مظهرتش):** `docker compose logs worker` و `docker compose logs scheduler`.

## اللي اتجرّب قبل التسليم
الـ stack ده اتشغّل كامل بـ `DOMAIN=localhost`: HTTPS، تحويل HTTP → HTTPS، تسجيل محل من المتصفح، الـ Queue worker نفّذ الأحداث، الـ migrations، والنسخ الاحتياطي والاسترجاع.
**ما اتجرّبش:** خطوة تثبيت إضافات PHP جوه `Dockerfile.api` (بيئة الاختبار كانت ممنوعة من Debian mirrors) — دي خطوة قياسية وبتشتغل عادي على أي سيرفر عليه إنترنت، وبتتأكد منها كمان في الـ CI على GitHub.

**خدمة `backup`:** السكريبتات (`backup/dump.sh` و`backup/schedule.sh`) اتجرّبت على PostgreSQL محلي: النسخ، التأكد منها، مسح القديم، الـ rclone (بأمر وهمي)، ووقف الخدمة. صورة `Dockerfile.backup` نفسها بتتبني في الـ CI (job `docker`) مع `shellcheck` لكل السكريبتات.
