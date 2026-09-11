#!/usr/bin/env bash
# ساخت فایل zip قابل نصب برای هر افزونه.
# خروجی در dist/ قرار می‌گیرد و مستقیم از «پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه» نصب می‌شود.
set -euo pipefail

cd "$(dirname "$0")/.."

mkdir -p dist
rm -f dist/*.zip

# ۱) یک zip جدا برای هر افزونه — برای «افزونه‌ها ← افزودن ← بارگذاری افزونه»
for plugin in plugins/*/; do
	name="$(basename "$plugin")"
	# ریشهٔ zip باید خودِ پوشهٔ افزونه باشد تا وردپرس آن را بشناسد.
	( cd plugins && zip -rq "../dist/${name}.zip" "$name" -x '*.DS_Store' '*/.*' )
	echo "ساخته شد: dist/${name}.zip"
done

# ۲) یک بستهٔ واحد با ساختار مسیر وردپرس — برای اکسترکت مستقیم در ریشهٔ وب سرور.
#    هیچ فایل دیگری غیر از دو پوشهٔ افزونه داخلش نیست، پس چیزی از سایت را
#    بازنویسی نمی‌کند.
staging="$(mktemp -d)"
mkdir -p "$staging/wp-content/plugins"
cp -r plugins/parsian-shop-filters plugins/parsian-catalog-sync "$staging/wp-content/plugins/"
( cd "$staging" && zip -rq "$OLDPWD/dist/deploy-plugins.zip" wp-content -x '*.DS_Store' '*/.*' )
rm -rf "$staging"
echo "ساخته شد: dist/deploy-plugins.zip  (در ریشهٔ وب اکسترکت شود)"
