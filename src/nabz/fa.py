"""ارقام و تاریخ شمسی برای گزارش فارسی."""

from __future__ import annotations

from datetime import datetime, timedelta, timezone

TEHRAN = timezone(timedelta(hours=3, minutes=30))
_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
_MONTHS = (
    "",
    "فروردین",
    "اردیبهشت",
    "خرداد",
    "تیر",
    "مرداد",
    "شهریور",
    "مهر",
    "آبان",
    "آذر",
    "دی",
    "بهمن",
    "اسفند",
)


def fa_digits(value: str) -> str:
    return value.translate(_DIGITS)


def fa_int(value: float | int) -> str:
    rounded = int(round(value))
    text = f"{rounded:,}".replace(",", "٬")
    return fa_digits(text)


def fa_decimal(value: float, digits: int = 1) -> str:
    text = f"{value:,.{digits}f}".replace(",", "٬")
    return fa_digits(text)


def gregorian_to_jalali(gy: int, gm: int, gd: int) -> tuple[int, int, int]:
    """تبدیل گاه‌شماری میلادی به جلالی. الگوریتم استاندارد روزهای سپری‌شده."""
    g_days_in_month = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
    if gy > 1600:
        jy = 979
        gy -= 1600
    else:
        jy = 0
        gy -= 621
    gy2 = gy + 1 if gm > 2 else gy
    days = (
        365 * gy
        + (gy2 + 3) // 4
        - (gy2 + 99) // 100
        + (gy2 + 399) // 400
        - 80
        + gd
        + g_days_in_month[gm - 1]
    )
    jy += 33 * (days // 12053)
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        jy += (days - 1) // 365
        days = (days - 1) % 365
    if days < 186:
        jm = 1 + days // 31
        jd = 1 + (days % 31)
    else:
        jm = 7 + (days - 186) // 30
        jd = 1 + ((days - 186) % 30)
    return jy, jm, jd


def jalali_to_gregorian(jy: int, jm: int, jd: int) -> tuple[int, int, int]:
    jy += 1595
    days = -355668 + (365 * jy) + (jy // 33) * 8 + ((jy % 33) + 3) // 4 + jd
    if jm < 7:
        days += (jm - 1) * 31
    else:
        days += ((jm - 7) * 30) + 186
    gy = 400 * (days // 146097)
    days %= 146097
    if days > 36524:
        days -= 1
        gy += 100 * (days // 36524)
        days %= 36524
        if days >= 365:
            days += 1
    gy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        gy += (days - 1) // 365
        days = (days - 1) % 365
    gd = days + 1
    leap = gy % 4 == 0 and (gy % 100 != 0 or gy % 400 == 0)
    month_lengths = [0, 31, 29 if leap else 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    gm = 1
    while gm <= 12 and gd > month_lengths[gm]:
        gd -= month_lengths[gm]
        gm += 1
    return gy, gm, gd


def format_jalali(moment: datetime) -> str:
    local = moment.astimezone(TEHRAN)
    jy, jm, jd = gregorian_to_jalali(local.year, local.month, local.day)
    hour = fa_digits(f"{local.hour:02d}")
    minute = fa_digits(f"{local.minute:02d}")
    return f"{fa_digits(str(jy))}/{fa_digits(f'{jm:02d}')}/{fa_digits(f'{jd:02d}')} {hour}:{minute}"


def format_jalali_date(moment: datetime) -> str:
    local = moment.astimezone(TEHRAN)
    jy, jm, jd = gregorian_to_jalali(local.year, local.month, local.day)
    return f"{fa_digits(str(jd))} {_MONTHS[jm]} {fa_digits(str(jy))}"


def format_age(hours: float) -> str:
    if hours < 1:
        minutes = max(1, int(round(hours * 60)))
        return f"{fa_int(minutes)} دقیقه"
    if hours < 48:
        whole = int(hours)
        minutes = int(round((hours - whole) * 60))
        if minutes and whole < 24:
            return f"{fa_int(whole)} ساعت و {fa_int(minutes)} دقیقه"
        return f"{fa_int(whole)} ساعت"
    days = hours / 24
    return f"{fa_decimal(days, 1)} روز"
