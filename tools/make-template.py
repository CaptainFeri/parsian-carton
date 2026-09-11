# -*- coding: utf-8 -*-
"""ساخت فایل اکسل نمونه (قالب مرجع محصولات) برای افزونهٔ همگام‌سازی."""
import sys
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

OUT = sys.argv[1] if len(sys.argv) > 1 else "docs/قالب-محصولات.xlsx"

HEADERS = [
    ("کد محصول", 14, "کلید یکتا. تغییرش ندهید — با همین کد محصول پیدا می‌شود."),
    ("نام محصول", 30, "عنوان محصول در فروشگاه."),
    ("دسته بندی", 26, "چند دسته با کاما. زیردسته با «>» مثل: کارتن اسباب‌کشی>۵ لایه"),
    ("قیمت", 14, "قیمت اصلی به تومان."),
    ("قیمت حراج", 14, "خالی یعنی بدون حراج."),
    ("موجودی", 10, "تعداد. صفر یعنی ناموجود."),
    ("وضعیت موجودی", 14, "موجود / ناموجود / پیش‌سفارش — خالی یعنی از روی «موجودی» حساب شود."),
    ("تصویر", 30, "نام فایل در کتابخانهٔ رسانه یا آدرس کامل تصویر."),
    ("گالری", 30, "چند تصویر با کاما."),
    ("توضیح کوتاه", 34, "یکی دو جمله زیر عنوان محصول."),
    ("توضیحات", 40, "متن کامل صفحهٔ محصول."),
    ("ویژگی: سایز", 16, "ستون‌های «ویژگی: …» به ویژگی محصول تبدیل می‌شوند."),
    ("ویژگی: تعداد لایه", 16, "چند مقدار با کاما."),
    ("ویژگی: نوع چاپ", 16, ""),
    ("وزن", 10, "به کیلوگرم."),
    ("وضعیت", 12, "منتشر / پیش‌نویس — خالی یعنی منتشر."),
]

ROWS = [
    ["PC-01", "کارتن پستی سایز ۱", "کارتن پستی", 8500, "", 120, "موجود",
     "postal-1.svg", "", "کارتن پستی سه لایه، مناسب ارسال کالاهای کوچک",
     "کارتن پستی سایز ۱ از مقوای سه لایه با مقاومت بالا…",
     "سایز ۱", "۳ لایه", "ساده", 0.12, "منتشر"],
    ["PC-02", "کارتن پستی سایز ۲", "کارتن پستی", 9700, 8900, 80, "موجود",
     "postal-2.svg", "", "یک سایز بزرگ‌تر، همان مقاومت",
     "", "سایز ۲", "۳ لایه", "ساده", 0.15, "منتشر"],
    ["MV-L", "کارتن اسباب‌کشی بزرگ", "کارتن اسباب‌کشی>۵ لایه", 45000, "", 30, "موجود",
     "moving-large.svg", "", "کارتن پنج لایه مخصوص اسباب‌کشی",
     "", "بزرگ", "۵ لایه", "چاپ‌دار", 0.9, "منتشر"],
]

wb = Workbook()
ws = wb.active
ws.title = "محصولات"
ws.sheet_view.rightToLeft = True

head_fill = PatternFill("solid", fgColor="F5B301")
head_font = Font(bold=True, color="121212")

for col, (label, width, note) in enumerate(HEADERS, start=1):
    cell = ws.cell(row=1, column=col, value=label)
    cell.fill = head_fill
    cell.font = head_font
    cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
    if note:
        cell.comment = None  # راهنما در برگهٔ «راهنما» آمده است
    ws.column_dimensions[get_column_letter(col)].width = width

ws.row_dimensions[1].height = 30
ws.freeze_panes = "A2"

for row in ROWS:
    ws.append(row)

# فهرست مجاز برای ستون‌های وضعیت
stock_dv = DataValidation(type="list", formula1='"موجود,ناموجود,پیش‌سفارش"', allow_blank=True)
status_dv = DataValidation(type="list", formula1='"منتشر,پیش‌نویس"', allow_blank=True)
ws.add_data_validation(stock_dv)
ws.add_data_validation(status_dv)
stock_dv.add(f"G2:G1000")
status_dv.add(f"P2:P1000")

# برگهٔ راهنما
guide = wb.create_sheet("راهنما")
guide.sheet_view.rightToLeft = True
guide.append(["ستون", "توضیح"])
guide.cell(row=1, column=1).font = Font(bold=True)
guide.cell(row=1, column=2).font = Font(bold=True)
for label, _, note in HEADERS:
    guide.append([label, note])
guide.column_dimensions["A"].width = 22
guide.column_dimensions["B"].width = 80
for r in range(2, guide.max_row + 1):
    guide.cell(row=r, column=2).alignment = Alignment(wrap_text=True, vertical="top")

guide.append([])
guide.append(["نکته", "سلول خالی یعنی «این فیلد را تغییر نده»؛ برای خالی کردن یک مقدار، آن را در پیشخوان ویرایش کنید."])
guide.append(["نکته", "ستون «کد محصول» کلید یکتاست. اگر عوضش کنید، محصول جدید ساخته می‌شود."])
guide.append(["نکته", "قیمت‌ها به تومان وارد شوند (قابل تغییر در تنظیمات افزونه)."])

wb.save(OUT)
print("ساخته شد:", OUT)
