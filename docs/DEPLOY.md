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

**5) نسخة احتياطية يومية**
```bash
crontab -e
# زوّد السطر ده (كل يوم الساعة 3 الفجر):
0 3 * * * /opt/muhasebi/infra/production/backup.sh >> /var/log/muhasebi-backup.log 2>&1
```
- النسخ بتتحفظ في `infra/production/backups/` لمدة 14 يوم.
- **مهم:** انسخها برا السيرفر كمان (Cloudflare R2 / Google Drive بـ `rclone`)؛ نسخة على نفس الهارد مش كفاية.

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
| استرجاع نسخة | `./restore.sh backups/muhasebi-XXXX.dump` |
| إيقاف كله | `docker compose down` (الداتا بتفضل) |
| حساب الإدارة (Super Admin) | `docker compose exec api php artisan billing:admin you@example.com "اسمك"` (بيسأل على كلمة السر؛ نفس الأمر بيغيّرها) — وبعدين `https://<الدومين>/admin` |
| مزامنة أقسام المشتركين | `docker compose exec api php artisan billing:sync-modules` (بعد ما قسم «قريباً» يبقى متاح) |

**الاشتراكات:** الدفع بـ InstaPay. حط في `.env` بتاع السيرفر `BILLING_INSTAPAY_ADDRESS` و`BILLING_INSTAPAY_NAME` و`BILLING_INSTAPAY_PHONE` (بيظهروا لصاحب المحل في صفحة الاشتراك)، وبعدين `./deploy.sh`. الأسعار والباقات في `apps/api/config/billing.php`.

## مشاكل شائعة
- **الموقع مش بيفتح / مفيش HTTPS:** اتأكد إن الـ A record بيشاور على السيرفر (`dig app.muhasebi.com`) وإن 80 و443 مفتوحين، وبص على `docker compose logs caddy`.
- **Server Error:** `docker compose logs api --tail 100`.
- **الأحداث مش بتتنفّذ (مثلاً قوايم الأعطال مظهرتش):** `docker compose logs worker` و `docker compose logs scheduler`.

## اللي اتجرّب قبل التسليم
الـ stack ده اتشغّل كامل بـ `DOMAIN=localhost`: HTTPS، تحويل HTTP → HTTPS، تسجيل محل من المتصفح، الـ Queue worker نفّذ الأحداث، الـ migrations، والنسخ الاحتياطي والاسترجاع.
**ما اتجرّبش:** خطوة تثبيت إضافات PHP جوه `Dockerfile.api` (بيئة الاختبار كانت ممنوعة من Debian mirrors) — دي خطوة قياسية وبتشتغل عادي على أي سيرفر عليه إنترنت، وبتتأكد منها كمان في الـ CI على GitHub.
