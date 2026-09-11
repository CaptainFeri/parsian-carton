# پارسیان کارتن — فروشگاه وردپرسی کارتن و ملزومات بسته‌بندی

یک وب‌سایت کامل وردپرس (الهام‌گرفته از کارتنچی، زودپک و کارتن‌کده) که با سایت آنلاین **همگام (Sync)** شده است:

- `wordpress/` — کل نصب وردپرس (هسته + قالب + افزونه‌ها + آپلودها) — پایه ساختار پروژه
- `parsiancartoncom-site.zip` — بسته استقرار روی سرور: وب‌روت کامل + `parsiancartoncom_db.sql` (خروجی دیتابیس) + `INSTALL.txt`
- `exported-beta.sql` — خروجی دیتابیس سایت (`parsiancartoncom_db`)
- قالب اختصاصی فارسی راست‌چین (اسلایدر، دسته‌بندی‌ها، کارت محصول، نظرات، وبلاگ، فوتر، فرم تماس)
- فروشگاه کامل **ووکامرس** (فقط ۱۵ محصول فایل اکسل «اطلاعات محصولات.xlsx»، سبد خرید، پرداخت، حساب کاربری)
- دیتابیس قابل مدیریت با **phpMyAdmin**
- `اطلاعات محصولات.xlsx` و `رزومه شرکت.xlsx` — منبع اصلی داده‌ها (محصولات و اطلاعات شرکت)

## چه چیزی داخل گیت است؟

مخزن گیت فقط این‌ها را نگه می‌دارد — بقیهٔ فایل‌های بالا روی کامپیوتر خودتان
(از باز کردن آرشیو) ساخته می‌شوند و در گیت نیستند:

```
README.md            این فایل
docker-compose.yml   تعریف سرویس‌ها
wordpress.zip        آرشیو کل نصب وردپرس (۵۶ مگابایت)
plugins/             افزونه‌های اختصاصی پروژه
docs/                راهنماها + قالب اکسل نمونه
tests/  tools/       آزمون‌ها و اسکریپت‌های کمکی
```

برای ساختن پوشهٔ `wordpress/` از آرشیو:

```powershell
# ویندوز
Expand-Archive wordpress.zip -DestinationPath .

# لینوکس / مک
unzip wordpress.zip
```

> آرشیو تا پیش از این `wordpress.rar` بود. از این نسخه zip است تا بدون نرم‌افزار
> جانبی روی هر سیستمی باز شود — محتوا مو‌به‌مو همان است.
> جزئیات در [راهنمای نصب و استقرار](docs/نصب-و-استقرار.md).

## پیش‌نیاز

- [Docker](https://www.docker.com/products/docker-desktop/) + Docker Compose

## نصب (۳ مرحله)

```powershell
# ۱) ساخت و راه‌اندازی سرویس‌ها
docker compose up -d db redis wordpress

# ۲) ایمپورت دیتابیس سایت (فقط بار اول؛ خروجی کد شده‌ی صحیح UTF-8)
cmd /c "docker compose exec -T db mysql -uroot -prootpass123 < exported-beta.sql"
docker compose exec db mysql -uroot -prootpass123 -e "GRANT ALL PRIVILEGES ON parsiancartoncom_db.* TO 'cartonpak'@'%'; FLUSH PRIVILEGES;"

# ۳) (اختیاری) ابزارها — phpMyAdmin
docker compose up -d phpmyadmin
```

> ⚠️ دقت کنید ایمپورت حتماً با `cmd /c "..." < exported-beta.sql` انجام شود؛
> اگر فایل با PowerShell (`Get-Content | mysql`) وارد شود، متون فارسی به `؟` تبدیل می‌شوند.

### نصب تمیز (بدون بکاپ دیتابیس) — جایگزین

اگر دیتابیسی ندارید، اسکریپت نصب خودکار (وردپرس + ووکامرس + صفحات + محصولات + منوها) را اجرا کنید:

```powershell
docker compose run --rm wpcli bash /setup/init.sh
```

## دسترسی‌ها

| بخش | آدرس |
|---|---|
| سایت | http://localhost:8080 |
| پیشخوان وردپرس | http://localhost:8080/wp-admin |
| phpMyAdmin | http://localhost:8081 (کاربر `root`، رمز `rootpass123`) |

- کاربران پیشخوان (از دیتابیس سایت): `admin` و `saeed` — رمز همان رمز سایت آنلاین است.
- در نصب تمیز (init.sh): کاربر `admin` — رمز `admin123`

## ساختار پروژه

```
├── docker-compose.yml      سرویس‌ها: mysql، redis، wordpress، wpcli، phpmyadmin
├── wordpress.zip           آرشیو کل نصب وردپرس (تنها نسخهٔ داخل گیت)
├── wordpress/              کل نصب وردپرس — از باز کردن wordpress.zip ساخته می‌شود:
│   ├── wp-admin, wp-includes, ...      هسته وردپرس
│   └── wp-content/themes/cartonpak     قالب اختصاصی سایت
│   └── wp-content/plugins              ووکامرس، پارسیان OTP، آک‌یسمت
│   └── wp-content/uploads              تصاویر محصولات (SVG)
├── parsiancartoncom-site.zip   بسته استقرار سرور (وب‌روت + خروجی دیتابیس + راهنما)
├── exported-beta.sql       خروجی دیتابیس سایت
├── setup/                  اسکریپت‌ها و داکرفایل‌ها
│   ├── excel-to-json.py        اکسل → products.json + import-products.php (منبع اصلی همگام‌سازی)
│   ├── products.json           داده‌های ۱۵ محصول (خروجی generator)
│   ├── import-products.php     ایمپورت لوکال (wp eval-file — یک فرآیند)
│   ├── sync-server-info.py     اطلاعات شرکت → سایت آنلاین (Customizer changeset)
│   ├── company.sh              اطلاعات شرکت + صفحات → لوکال (بدون ایجاد محصول)
│   ├── init.sh / populate.sh   نصب تمیز (قدیمی — محصولات نمونه می‌سازد؛ فقط برای نصب اولیه)
│   └── ...داکرفایل‌ها
├── plugins/                افزونه‌های اختصاصی پروژه
│   ├── parsian-shop-filters/   فیلتر و جستجوی فروشگاه
│   └── parsian-catalog-sync/   همگام‌سازی کاتالوگ با فایل اکسل
├── docs/                   راهنماها + قالب اکسل نمونه
├── tests/                  آزمون‌ها (بدون نیاز به وردپرس)
└── mysql-data/             دیتابیس (mysql)
```

## بسته استقرار روی سرور

`parsiancartoncom-site.zip` برای آپلود روی هاست ساخته شده است:

- ریشه‌ی zip = کل نصب وردپرس (همان `wordpress/`) — مستقیم در `public_html` Extract کنید
- `parsiancartoncom_db.sql` = خروجی کامل دیتابیس (UTF-8 صحیح، شامل CREATE DATABASE)
- `INSTALL.txt` = راهنمای نصب، ایمپورت دیتابیس و جایگزینی آدرس‌ها

بازسازی بسته پس از تغییرات:

```powershell
# ۱) خروجی تازه دیتابیس
cmd /c "docker compose exec -T db mysqldump -uroot -prootpass123 --databases parsiancartoncom_db --default-character-set=utf8mb4 --routines --events --triggers > exported-beta.sql"

# ۲) ساخت zip (فایل‌های site-upload/wordpress قدیمی حذف شده‌اند؛ مبنای ساخت، پوشه wordpress/ است)
tar -a -c -f parsiancartoncom-site.zip -C wordpress (Get-ChildItem wordpress -Force -Name) -C <tmp> parsiancartoncom_db.sql -C <tmp> INSTALL.txt
```

## همگام‌سازی (Sync) با سایت آنلاین

### الف) محصولات و اطلاعات شرکت از فایل‌های اکسل

دو فایل اکسل، منبع اصلی داده‌ها هستند:
- `اطلاعات محصولات.xlsx` → ۱۵ محصول (کارتن پستی ۱ تا ۹ + کارتن اسباب‌کشی ۳ سایز، ساده و چاپ‌دار)
- `رزومه شرکت.xlsx` → تلفن‌ها، اینستاگرام، آدرس

**۱) تولید داده‌ها از اکسل** (بعد از هر تغییر در فایل اکسل دوباره اجرا کنید):

```powershell
$env:PYTHONIOENCODING='utf-8'
python -X utf8 setup\excel-to-json.py
```

خروجی: `setup/products.json` (داده‌ها) + `setup/import-products.php` (اسکریپت ایمپورت لوکال).

**۲) همگام‌سازی لوکال** (حذف کامل محصولات قبلی و ایجاد ۱۵ محصول از اکسل — ایدم‌پوتنت):

```powershell
docker compose run --rm wpcli wp eval-file /setup/import-products.php
```

> ⚠️ از اسکریپت‌های قدیمی `populate.sh` و `company.sh` فقط بخش‌های بدون «ایجاد محصول» استفاده می‌شود؛
> محصولات نمونه آن‌ها (میوه، پسته و…) دیگر نباید اضافه شوند چون فقط ۱۵ محصول اکسل معتبر است.
> ⚠️ به‌دلیل کندی شدید wp-cli در ویندوز (هر فراخوانی ~۱۰-۲۰ ثانیه)، ایمپورت با **یک** فرآیند `eval-file` انجام می‌شود؛
> از حلقه‌های چند فراخوانی (مثل `wc product create`) استفاده نکنید — بسیار کند است و اگر دستور به‌خاطر Timeout قطع شود،
> کانتینر `wpcli` در پس‌زمینه زنده می‌ماند و محصولات تکراری می‌سازد (مورد مشاهده‌شده: اسلاگ‌های `-2`).
> اگر چنین شد: `docker kill` همه کانتینرهای `ali-khedri-wpcli-run-*` را بزنید و سپس دوباره ایمپورت کنید.

**۳) اطلاعات شرکت (تماس، آدرس، درباره ما) روی لوکال:**

```powershell
docker compose run --rm wpcli bash /setup/company.sh
```

**۴) همگام‌سازی اطلاعات شرکت روی سایت آنلاین** (با Customizer changeset — بدون SSH/افزونه):

```powershell
$env:PYTHONIOENCODING='utf-8'
python -X utf8 setup\sync-server-info.py
```

### ب) به‌روزرسانی پروژه از بکاپ جدید سایت آنلاین

```powershell
# ۱) فایل‌ها — جایگزینی پوشه wordpress/
#     (پس از استخراج، wp-config.php پروژه را برگردانید؛ نسخه داخل بکاپ برای هاست است)
Remove-Item -Recurse wordpress
tar -xf parsiancartoncom-site.zip -C wordpress
Copy-Item wordpress\wp-config-sample.php wordpress\wp-config.php  # یا نسخه‌ی سازگار با داکر

# ۲) دیتابیس — ایمپورت مجدد
cmd /c "docker compose exec -T db mysql -uroot -prootpass123 < exported-beta.sql"
```

نکته: اگر در دیتابیس، قالب فعال با نام پوشه‌ی قالب در بکاپ فرق داشت (مثلاً `parsiancartoncom` در DB ولی `cartonpak` روی دیسک):

```powershell
docker compose run --rm wpcli wp theme activate cartonpak
```

## افزونه‌های اختصاصی پروژه (پوشهٔ `plugins/`)

دو افزونهٔ اختصاصی، جدا از قالب و مستقل از بکاپ `wordpress.zip` نگهداری می‌شوند تا
با هر به‌روزرسانی سایت از بین نروند.

**استقرار روی سرور** (بدون داکر):

```bash
./tools/build-plugin-zips.sh     # خروجی در dist/
```

فایل `dist/deploy-plugins.zip` را در ریشهٔ وب (`public_html`) اکسترکت کنید؛ مسیرها از
`wp-content/plugins/` شروع می‌شوند و فقط همین دو پوشه اضافه می‌شوند. بعد از پیشخوان
فعالشان کنید.

> ⚠️ برای این کار `wordpress.zip` را روی سایت در حال کار اکسترکت **نکنید** — ممکن است
> نسخهٔ وردپرس و ووکامرس سرور را به عقب برگرداند. دلیلش و روش درست در
> [راهنمای نصب و استقرار](docs/نصب-و-استقرار.md).

### ۱) فیلتر و جستجوی فروشگاه — `plugins/parsian-shop-filters`

پنل فیلتر و نوار جستجو برای صفحهٔ فروشگاه و بایگانی هر دسته‌بندی:
دسته‌بندی، بازهٔ قیمت (به تومان)، ویژگی‌ها (سایز، تعداد لایه، نوع چاپ)، فقط موجود،
فقط حراج، و جستجوی درون‌دسته‌ای با پیشنهاد زنده.

- بدون جاوااسکریپت هم کار می‌کند (فرم `GET` ساده)، با جاوااسکریپت شبکهٔ محصولات
  بدون بارگذاری دوبارهٔ صفحه به‌روز می‌شود
- واحد قیمت را خودکار تشخیص می‌دهد (دیتابیس ریالی، نمایش تومانی)
- تنظیمات: پیشخوان ← محصولات ← **فیلترهای فروشگاه**

📄 [راهنمای کامل](docs/فیلتر-و-جستجو.md)

### ۲) همگام‌سازی کاتالوگ با اکسل — `plugins/parsian-catalog-sync`

یک فایل اکسل را مرجع محصولات، قیمت‌ها، موجودی و تصاویر می‌کند. فایل را اصلاح
می‌کنید، **پیش‌نمایش تغییرات** را می‌بینید و با یک کلیک اعمال می‌شود.

- کلید یکتا: ستون «کد محصول» (SKU) — همگام‌سازی ایدم‌پوتنت است
- خوانندهٔ xlsx و csv بدون هیچ وابستگی بیرونی (بدون Composer)
- تبدیل خودکار واحد قیمت تومان ⟷ ریال
- ارقام فارسی، جداکنندهٔ هزارگان و نیم‌فاصله را درست می‌خواند
- همگام‌سازی خودکار زمان‌بندی‌شده از یک نشانی (مثلاً گوگل‌شیت)
- تنظیمات: پیشخوان ← محصولات ← **همگام‌سازی با اکسل**

📄 [راهنمای کامل](docs/همگام-سازی-با-اکسل.md) — شامل **روش پیشنهادی با گوگل‌شیت**
📊 [قالب اکسل نمونه](docs/قالب-محصولات.xlsx)

> این افزونه جایگزین اسکریپت‌های `setup/excel-to-json.py` و `import-products.php`
> می‌شود: کار از داخل پیشخوان انجام می‌شود، به Python و wp-cli نیازی نیست و پیش از
> اعمال، تغییرات را می‌بینید.

## آزمون‌ها

```bash
./tests/run.sh
```

بررسی نحو همهٔ فایل‌های PHP + آزمون خوانندهٔ اکسل، نگاشت ستون‌ها، تبدیل واحد قیمت و
ساخت نقشهٔ تغییرات. به وردپرس، داکر یا دیتابیس نیازی نیست.

## شخصی‌سازی

- **اطلاعات تماس، شبکه‌های اجتماعی:** پیشخوان ← نمایش ← سفارشی‌سازی ← «اطلاعات تماس کارتن‌پک»
- **لوگو:** سفارشی‌سازی ← هویت سایت (توصیه: PNG شفاف با ارتفاع ۸۰px)
- **اسلایدها و متن‌ها:** `wp-content/themes/cartonpak/front-page.php`
- **رنگ‌ها:** متغیرهای CSS ابتدای `wp-content/themes/cartonpak/assets/css/main.css`

## نکات

- **سرعت:** کل وردپرس (`./wordpress`) به‌صورت Bind Mount از هاست وصل است؛ در ویندوز اولین باز شدن صفحات کند است (~۱۰ تا ۲۰ ثانیه) و پس از آن با کش صفحات سریع‌تر می‌شود. wp-cli هم در همین محیط بسیار کند است (~۱۰ تا ۲۰ ثانیه به ازای هر فراخوانی) — به همین دلیل اسکریپت‌ها تا حد امکان در یک فرآیند اجرا می‌شوند.
- **کش:** یک سرویس Redis + افزونه redis-cache فعال است؛ برای پاک‌سازی کش: `docker compose run --rm wpcli wp cache flush`
- **سایت آنلاین:** هاست یک WAF ضد-ربات دارد (چالش «یک لحظه، لطفاً…»). اگر اسکریپت‌ها به‌طور مکرر اجرا شوند، IP شما چند دقیقه بلاک می‌شود؛ پس از چند دقیقه استراحت دوباره امتحان کنید و تعداد درخواست‌ها را کم نگه دارید.
- توقف: `docker compose down` — حذف کامل (به‌همراه دیتابیس): `docker compose down -v` و حذف پوشه‌های `wordpress` و `mysql-data`.
- قیمت‌ها به تومان نمایش داده می‌شوند و می‌توانید از پیشخوان آنها را ویرایش کنید.
