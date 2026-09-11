#!/usr/bin/env bash
# ساخت فایل zip قابل نصب برای هر افزونه.
# خروجی در dist/ قرار می‌گیرد و مستقیم از «پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه» نصب می‌شود.
set -euo pipefail

cd "$(dirname "$0")/.."

mkdir -p dist
rm -f dist/*.zip

for plugin in plugins/*/; do
	name="$(basename "$plugin")"
	# ریشهٔ zip باید خودِ پوشهٔ افزونه باشد تا وردپرس آن را بشناسد.
	( cd plugins && zip -rq "../dist/${name}.zip" "$name" -x '*.DS_Store' '*/.*' )
	echo "ساخته شد: dist/${name}.zip"
done
