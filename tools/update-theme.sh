#!/usr/bin/env bash
# به‌روزرسانی قالب از یک zip تازه، بدون از دست رفتن اصلاحات ما.
#
#     ./tools/update-theme.sh ~/Downloads/cartonpak.zip
#
# قالب گاهی به‌صورت کامل با نسخهٔ تازه جایگزین می‌شود. اگر همین‌طوری رویش
# بنویسیم، هر اصلاحی که در قالب زده‌ایم بی‌صدا از بین می‌رود. این اسکریپت
# بلوک نشانه‌دارِ اصلاحات را برمی‌دارد، قالب را عوض می‌کند و بلوک را دوباره
# سوار می‌کند — و مهم‌تر، می‌گوید کدام قاعده در نسخهٔ تازه دیگر هدفی ندارد.
set -euo pipefail

cd "$(dirname "$0")/.."

ZIP="${1:-}"
THEME_DIR="theme/cartonpak"
CSS="$THEME_DIR/assets/css/main.css"
START="PARSIAN-MOBILE-PATCH:START"
END="PARSIAN-MOBILE-PATCH:END"
MANIFEST=".parsian-patches"
CSS_REL="assets/css/main.css"

if [ -z "$ZIP" ] || [ ! -f "$ZIP" ]; then
	echo "استفاده: $0 <مسیر فایل zip قالب>" >&2
	exit 1
fi

if [ ! -f "$CSS" ]; then
	echo "قالب فعلی در $THEME_DIR پیدا نشد." >&2
	exit 1
fi

# ---------- ۱) برداشتن بلوک اصلاحات ----------
PATCH="$(mktemp)"
if grep -q "$START" "$CSS"; then
	awk "/$START/,/$END/" "$CSS" > "$PATCH"
	echo "بلوک اصلاحات برداشته شد: $(wc -l < "$PATCH") خط"
else
	echo "هشدار: بلوک نشانه‌دار در قالب فعلی نیست — چیزی برای حفظ کردن پیدا نشد."
	: > "$PATCH"
fi

# ---------- ۲) باز کردن نسخهٔ تازه ----------
NEW="$(mktemp -d)"
unzip -q "$ZIP" -d "$NEW"

SRC="$NEW/cartonpak"
[ -d "$SRC" ] || SRC="$(find "$NEW" -maxdepth 2 -name 'style.css' -printf '%h\n' | head -1)"

if [ ! -d "$SRC" ]; then
	echo "پوشهٔ قالب داخل zip پیدا نشد." >&2
	exit 1
fi

# ---------- ۳) گزارش تفاوت‌ها، پیش از جایگزینی ----------
echo
echo "── تفاوت نسخهٔ تازه با نسخهٔ فعلی ──"
# diff با کد ۱ خارج می‌شود وقتی تفاوتی باشد؛ زیر pipefail این خودِ اسکریپت را
# می‌کشد. پس خروجی را اول می‌گیریم و بعد رویش راه می‌رویم.
DIFF_OUT="$(diff -rq "$THEME_DIR" "$SRC" 2>/dev/null || true)"

if [ -z "$DIFF_OUT" ]; then
	echo "  تفاوتی نیست — همین نسخه است."
else
	while IFS= read -r line; do
		[ -n "$line" ] || continue
		case "$line" in
			Files*differ)
				f="${line#Files }"; f="${f%% and *}"
				echo "  تغییر:   ${f#"$THEME_DIR"/}" ;;
			"Only in $THEME_DIR"*)
				f="${line#Only in }"; d="${f%%: *}"; n="${f#*: }"
				d="${d#"$THEME_DIR"}"; d="${d#/}"
				echo "  حذف‌شده: ${d:+$d/}$n" ;;
			"Only in $SRC"*)
				f="${line#Only in }"; d="${f%%: *}"; n="${f#*: }"
				d="${d#"$SRC"}"; d="${d#/}"
				echo "  تازه:    ${d:+$d/}$n" ;;
			*) echo "  $line" ;;
		esac
	done <<< "$DIFF_OUT"
fi

# ---------- ۴) جایگزینی و سوار کردن دوبارهٔ بلوک ----------
# فهرست فایل‌های تغییرداده‌شده مالِ ماست و در zip بالادست نیست؛ کنار می‌گذاریم.
KEPT_MANIFEST=""
if [ -f "$THEME_DIR/$MANIFEST" ]; then
	KEPT_MANIFEST="$(mktemp)"
	cp "$THEME_DIR/$MANIFEST" "$KEPT_MANIFEST"
fi

# کدام فایلِ فهرست‌شده را نسخهٔ تازه عوض کرده؟ پیش از پاک کردن بسنجیم.
TOUCHED="$(mktemp)"
if [ -n "$KEPT_MANIFEST" ]; then
	while IFS="$(printf '\t')" read -r rel note; do
		case "$rel" in ''|\#*) continue ;; esac
		[ -f "$THEME_DIR/$rel" ] || continue
		if ! cmp -s "$THEME_DIR/$rel" "$SRC/$rel" 2>/dev/null; then
			printf '%s\t%s\n' "$rel" "$note" >> "$TOUCHED"
		fi
	done < "$KEPT_MANIFEST"
fi

rm -rf "${THEME_DIR:?}"
cp -r "$SRC" "$THEME_DIR"

if [ -n "$KEPT_MANIFEST" ]; then
	cp "$KEPT_MANIFEST" "$THEME_DIR/$MANIFEST"
	chmod 644 "$THEME_DIR/$MANIFEST"   # mktemp مجوز 600 می‌دهد
fi

if [ -s "$PATCH" ]; then
	if grep -q "$START" "$CSS"; then
		echo
		echo "هشدار: نسخهٔ تازه خودش بلوک نشانه‌دار دارد؛ بلوک قبلی دوباره اضافه نشد."
	else
		# قالب با پایان خط CRLF می‌آید. اگر جداکنندهٔ LF بگذاریم، فایل مخلوط
		# می‌شود و دیف بعدی کل فایل را تغییرکرده نشان می‌دهد. پس از خود فایل
		# می‌پرسیم چه پایان خطی دارد.
		if head -c 8000 "$CSS" | grep -q $'\r'; then
			printf '\r\n' >> "$CSS"
		else
			printf '\n' >> "$CSS"
		fi
		cat "$PATCH" >> "$CSS"
		echo
		echo "بلوک اصلاحات دوباره سوار شد."
	fi
fi

# ---------- ۵) کدام قاعده دیگر هدفی ندارد؟ ----------
# مهم‌ترین بخش: قالب که بازنویسی شود، نام کلاس‌ها عوض می‌شوند و قاعده‌های ما
# بی‌صدا بی‌اثر می‌مانند. اینجا هر کلاسِ داخل بلوک در خود قالب جستجو می‌شود.
if [ -s "$PATCH" ]; then
	echo
	echo "── بررسی: آیا کلاس‌های بلوک هنوز در قالب وجود دارند؟ ──"
	MISS="$(mktemp)"
	# خودِ بلوک داخل main.css است؛ اگر آن را هم بگردیم، هر کلاسی خودش را
	# پیدا می‌کند و بررسی بی‌معنا می‌شود. پس main.css را بدون بلوک می‌گردیم.
	BARE="$(mktemp)"
	sed "/$START/,/$END/d" "$CSS" > "$BARE"
	for class in $(grep -oE '\.[a-z][a-z0-9-]{2,}' "$PATCH" | sort -u); do
		name="${class#.}"
		if grep -qF "$name" "$BARE" 2>/dev/null; then
			continue
		fi
		if grep -rqF "$name" "$THEME_DIR" --include='*.php' --include='*.css' \
			--exclude="$(basename "$CSS")" 2>/dev/null; then
			continue
		fi
		echo "  ⚠ $class دیگر در قالب نیست — این قاعده بی‌اثر است" >> "$MISS"
	done
	if [ -s "$MISS" ]; then
		cat "$MISS"
		echo
		echo "  این قاعده‌ها را از بلوک پاک کنید یا با نام تازهٔ کلاس بازنویسی کنید،"
		echo "  وگرنه بی‌صدا بی‌اثر می‌مانند."
	else
		echo "  همهٔ کلاس‌ها هنوز در قالب هستند."
	fi
	rm -f "$MISS" "$BARE"
fi

# ---------- ۶) تغییرات دستی که ابزار نمی‌تواند برگرداند ----------
if [ -s "$TOUCHED" ]; then
	echo
	echo "── این فایل‌ها را ما تغییر داده بودیم و نسخهٔ تازه رویشان نوشت ──"
	while IFS="$(printf '\t')" read -r rel note; do
		if [ "$rel" = "$CSS_REL" ]; then
			echo "  ✓ $rel — خودکار برگردانده شد"
		else
			echo "  ✗ $rel — دستی برگردانید"
			[ -n "$note" ] && echo "      $note"
		fi
	done < "$TOUCHED"
	echo
	echo "  تغییر خودتان را از تاریخچه بگیرید:"
	echo "    git diff HEAD -- theme/cartonpak"
	echo "  و اگر فایلی را می‌خواهید کامل برگردانید:"
	echo "    git checkout HEAD -- theme/cartonpak/<مسیر>"
fi

rm -rf "$NEW" "$PATCH" "$TOUCHED"
if [ -n "$KEPT_MANIFEST" ]; then
	rm -f "$KEPT_MANIFEST"
fi

echo
echo "تمام شد. حالا تغییرات را بازبینی کنید:  git diff --stat theme/"
