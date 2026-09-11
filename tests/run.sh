#!/usr/bin/env bash
# اجرای همهٔ آزمون‌ها. نیازی به وردپرس یا دیتابیس نیست — فقط PHP.
set -u

cd "$(dirname "$0")/.."

status=0

echo "── بررسی نحو PHP ──"
while IFS= read -r file; do
	if ! php -l "$file" > /dev/null 2>&1; then
		echo "خطای نحوی: $file"
		php -l "$file"
		status=1
	fi
done < <(find plugins tests -name '*.php')
[ "$status" -eq 0 ] && echo "همهٔ فایل‌های PHP سالم‌اند."

for test in tests/test-*.php; do
	echo
	echo "── $(basename "$test") ──"
	if ! php "$test"; then
		status=1
	fi
done

echo
if [ "$status" -eq 0 ]; then
	echo "✅ همهٔ آزمون‌ها موفق بودند."
else
	echo "❌ بعضی آزمون‌ها شکست خوردند."
fi

exit "$status"
