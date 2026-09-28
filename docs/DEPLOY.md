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

## أوامر مفيدة
| عايز | الأمر (من `infra/production`) |
|---|---|
| حالة الخدمات | `docker compose ps` |
| اللوجز | `docker compose logs -f api` (أو `worker` / `caddy`) |
| أمر artisan | `docker compose exec api php artisan <command>` |
| نسخة احتياطية دلوقتي | `./backup.sh` |
| استرجاع نسخة | `./restore.sh backups/muhasebi-XXXX.dump` |
| إيقاف كله | `docker compose down` (الداتا بتفضل) |

## مشاكل شائعة
- **الموقع مش بيفتح / مفيش HTTPS:** اتأكد إن الـ A record بيشاور على السيرفر (`dig app.muhasebi.com`) وإن 80 و443 مفتوحين، وبص على `docker compose logs caddy`.
- **Server Error:** `docker compose logs api --tail 100`.
- **الأحداث مش بتتنفّذ (مثلاً قوايم الأعطال مظهرتش):** `docker compose logs worker` و `docker compose logs scheduler`.

## اللي اتجرّب قبل التسليم
الـ stack ده اتشغّل كامل بـ `DOMAIN=localhost`: HTTPS، تحويل HTTP → HTTPS، تسجيل محل من المتصفح، الـ Queue worker نفّذ الأحداث، الـ migrations، والنسخ الاحتياطي والاسترجاع.
**ما اتجرّبش:** خطوة تثبيت إضافات PHP جوه `Dockerfile.api` (بيئة الاختبار كانت ممنوعة من Debian mirrors) — دي خطوة قياسية وبتشتغل عادي على أي سيرفر عليه إنترنت، وبتتأكد منها كمان في الـ CI على GitHub.
