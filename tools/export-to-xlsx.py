# -*- coding: utf-8 -*-
"""تبدیل خروجی CSV ووکامرس به یک فایل اکسل آمادهٔ ویرایش.

فقط ستون‌هایی نگه داشته می‌شوند که افزونهٔ همگام‌سازی آن‌ها را مدیریت می‌کند؛
بقیهٔ ستون‌ها (مالیات، دانلود، Cross-Sells و…) حذف می‌شوند تا فایل خوانا بماند.
حذفشان بی‌خطر است: افزونه هر ستونی را که نشناسد نادیده می‌گیرد و مقدار فعلی
محصول را دست‌نخورده می‌گذارد.

    python3 tools/export-to-xlsx.py ورودی.csv خروجی.xlsx
"""
import csv
import io
import sys

from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

# ستون‌هایی که افزونه می‌خواند، به ترتیبی که برای ویرایش دستی منطقی است.
COLUMNS = [
    ("شناسه", 9, "شناسهٔ محصول در وردپرس. کلید اصلی تطبیق است — هرگز تغییرش ندهید."),
    ("شناسه محصول", 26, "کد کالا (SKU). اگر خالی باشد می‌توانید پرش کنید تا به محصول نسبت داده شود."),
    ("نوع", 11, "simple / variable / variation — دست نزنید."),
    ("نام", 38, "عنوان محصول."),
    ("قیمت عادی", 13, "به تومان. برای محصول variable خالی است (قیمت در واریاسیون‌هاست)."),
    ("قیمت فروش ویژه", 14, "قیمت حراج. خالی یعنی بدون حراج."),
    ("انبار", 10, "تعداد موجودی. خالی یعنی مدیریت انبار خاموش است."),
    ("در انبار؟", 11, "۱ = موجود، ۰ = ناموجود."),
    ("دسته‌بندی‌ها", 26, "چند دسته با کاما؛ زیردسته با «>»."),
    ("برچسب‌ها", 18, "چند برچسب با کاما."),
    ("توضیح کوتاه", 34, "یکی دو جمله زیر عنوان."),
    ("توضیحات", 40, "متن کامل صفحهٔ محصول."),
    ("تصاویر", 34, "اولی تصویر شاخص، بقیه گالری."),
    ("منتشر شده", 12, "۱ = منتشر، ۰ = پیش‌نویس، ‎-۱ = خصوصی."),
    ("آیا ویژه است؟", 13, "۱ = محصول ویژه."),
    ("موقعیت", 10, "ترتیب نمایش."),
    ("وزن (پوند)", 11, "وزن."),
    ("مادر", 22, "فقط برای واریاسیون: کد محصول والد. دست نزنید."),
    ("نام 1 صفت", 14, "نام صفت. تغییرش ساختار واریاسیون‌ها را به هم می‌زند."),
    ("مقدار(های) 1 صفت", 20, "مقادیر صفت با کاما."),
]

HEAD_FILL = PatternFill("solid", fgColor="F5B301")
HEAD_FONT = Font(bold=True, color="121212")
LOCK_FILL = PatternFill("solid", fgColor="EDEDED")

# ستون‌هایی که ویرایششان خطرناک است و کم‌رنگ نمایش داده می‌شوند.
LOCKED = {"شناسه", "نوع", "مادر", "نام 1 صفت", "مقدار(های) 1 صفت"}


def main(src, dest):
    with io.open(src, encoding="utf-8-sig") as fh:
        rows = list(csv.DictReader(fh))

    wb = Workbook()
    ws = wb.active
    ws.title = "محصولات"
    ws.sheet_view.rightToLeft = True

    for index, (name, width, _) in enumerate(COLUMNS, start=1):
        cell = ws.cell(row=1, column=index, value=name)
        cell.fill = LOCK_FILL if name in LOCKED else HEAD_FILL
        cell.font = HEAD_FONT
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        ws.column_dimensions[get_column_letter(index)].width = width

    ws.row_dimensions[1].height = 32
    ws.freeze_panes = "A2"

    for record in rows:
        ws.append([record.get(name, "") for name, _, _ in COLUMNS])

    # اعداد به‌صورت متن خوانده می‌شوند؛ ستون‌های عددی به عدد تبدیل می‌شوند تا در
    # اکسل قابل محاسبه باشند.
    numeric = {"قیمت عادی", "قیمت فروش ویژه", "انبار", "موقعیت", "شناسه"}
    for index, (name, _, _) in enumerate(COLUMNS, start=1):
        if name not in numeric:
            continue
        for row in range(2, ws.max_row + 1):
            cell = ws.cell(row=row, column=index)
            text = str(cell.value or "").strip()
            if text and text.replace(".", "", 1).isdigit():
                cell.value = float(text) if "." in text else int(text)
                cell.number_format = "#,##0"

    guide = wb.create_sheet("راهنما")
    guide.sheet_view.rightToLeft = True
    guide.append(["ستون", "توضیح"])
    for cell in guide[1]:
        cell.font = Font(bold=True)
    for name, _, note in COLUMNS:
        guide.append([name, note])
    guide.column_dimensions["A"].width = 22
    guide.column_dimensions["B"].width = 78
    for row in range(2, guide.max_row + 1):
        guide.cell(row=row, column=2).alignment = Alignment(wrap_text=True, vertical="top")

    for note in [
        "سلول خالی یعنی «این فیلد را تغییر نده» — مقدار فعلی محصول پاک نمی‌شود.",
        "قیمت‌ها به تومان‌اند (همان عددی که در سایت دیده می‌شود).",
        "قیمت محصول variable خالی است؛ قیمت واقعی در سطرهای variation زیر آن است.",
        "ستون‌های خاکستری (شناسه، نوع، مادر، صفت) را تغییر ندهید.",
        "تطبیق اول با «شناسه» انجام می‌شود و بعد با «شناسه محصول» — مثل درون‌ریز خود ووکامرس.",
        "چون تطبیق با شناسه است، می‌توانید کد یک محصول را عوض یا اضافه کنید بدون اینکه محصول تکراری ساخته شود.",
        "پس از ویرایش: پیشخوان ← محصولات ← همگام‌سازی با اکسل ← بارگذاری ← پیش‌نمایش.",
        "تا دکمهٔ «اعمال» را نزنید هیچ‌چیز در فروشگاه تغییر نمی‌کند.",
    ]:
        guide.append(["نکته", note])

    wb.save(dest)
    print(f"ساخته شد: {dest}  ({len(rows)} سطر)")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
