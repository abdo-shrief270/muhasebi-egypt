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
مفيش حاجة تعملها: خدمة `backup` في الـ stack بتاخد نسخة من الداتابيز **كل يوم الساعة 3 الفجر** (بتوقيت القاهرة)، وأول ما تشتغل لو مفيش نسخة من آخر 24 ساعة. بس **لازم تظبط النسخة برا السيرفر** (تحت) — نسخة على نفس الهارد مش كفاية.

## النسخ الاحتياطي
- **فين:** `infra/production/backups/muhasebi-YYYYmmdd-HHMMSS.dump` (صيغة `pg_dump -Fc`، مضغوطة، وبتتأكد إنها بتتقري قبل ما تتحفظ).
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
بيوقف التطبيق، ويرجّع الداتابيز كلها للنسخة دي، ويشغّل كل حاجة تاني. **أي حاجة اتسجلت بعد النسخة دي بتضيع.**
لو السيرفر نفسه ضاع: جهّز سيرفر جديد بالخطوات فوق **بنفس ملف `.env` القديم** (عشان `APP_KEY`: من غيره أكواد التحقق بخطوتين وأكواد فتح الأجهزة المشفّرة مش هتتقري)، وبعدين نزّل النسخة من برا وارجّعها:
```bash
docker compose run --rm -T backup rclone copy offsite:muhasebi-backups/muhasebi-20261005-030000.dump /backups/
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

## مشاكل شائعة
- **الموقع مش بيفتح / مفيش HTTPS:** اتأكد إن الـ A record بيشاور على السيرفر (`dig app.muhasebi.com`) وإن 80 و443 مفتوحين، وبص على `docker compose logs caddy`.
- **Server Error:** `docker compose logs api --tail 100`.
- **الأحداث مش بتتنفّذ (مثلاً قوايم الأعطال مظهرتش):** `docker compose logs worker` و `docker compose logs scheduler`.

## اللي اتجرّب قبل التسليم
الـ stack ده اتشغّل كامل بـ `DOMAIN=localhost`: HTTPS، تحويل HTTP → HTTPS، تسجيل محل من المتصفح، الـ Queue worker نفّذ الأحداث، الـ migrations، والنسخ الاحتياطي والاسترجاع.
**ما اتجرّبش:** خطوة تثبيت إضافات PHP جوه `Dockerfile.api` (بيئة الاختبار كانت ممنوعة من Debian mirrors) — دي خطوة قياسية وبتشتغل عادي على أي سيرفر عليه إنترنت، وبتتأكد منها كمان في الـ CI على GitHub.

**خدمة `backup`:** السكريبتات (`backup/dump.sh` و`backup/schedule.sh`) اتجرّبت على PostgreSQL محلي: النسخ، التأكد منها، مسح القديم، الـ rclone (بأمر وهمي)، ووقف الخدمة. صورة `Dockerfile.backup` نفسها بتتبني في الـ CI (job `docker`) مع `shellcheck` لكل السكريبتات.
