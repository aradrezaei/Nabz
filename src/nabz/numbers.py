"""خواندن عددهایی که خود صفحه نوشته؛ بدون حدس وقتی عدد نیست."""

from __future__ import annotations

import re

_DIGIT_MAP = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
_COUNT = re.compile(
    r"""
    ^\s*
    (?P<num>\d+(?:[.,]\d+)?)
    \s*
    (?P<suffix>هزار|میلیون|میلیارد|k|m|b)?
    \s*$
    """,
    re.IGNORECASE | re.VERBOSE,
)
_SUFFIX = {
    "k": 1_000,
    "هزار": 1_000,
    "m": 1_000_000,
    "میلیون": 1_000_000,
    "b": 1_000_000_000,
    "میلیارد": 1_000_000_000,
}


def parse_count(raw: str | None) -> int | None:
    """عدد نمایشی را به عدد صحیح برمی‌گرداند.

    اگر متن خالی باشد یا اعشار بدون واحد باشد، None می‌دهد تا عدد ساخته نشود.
    """
    if raw is None:
        return None
    text = raw.translate(_DIGIT_MAP)
    text = (
        text.replace("٬", "")
        .replace(",", "")
        .replace("،", "")
        .replace("\u200c", "")
        .replace("\u00a0", "")
        .replace("\u202f", "")
        .strip()
    )
    text = text.replace("٫", ".")
    if not text:
        return None
    # جداکنندهٔ هزارگان انگلیسی بعد از حذف ویرگول رفته است.
    # اگر هم ویرگول انگلیسی و هم نقطه مانده، نقطه اعشار است.
    match = _COUNT.match(text)
    if match is None:
        # «12.4K» بعد از حذف ویرگول؛ پسوند چسبیده را جدا کن
        stuck = re.match(r"^(\d+(?:[.,]\d+)?)(هزار|میلیون|میلیارد|[kKmMbB])$", text)
        if stuck is None:
            return None
        number = stuck.group(1).replace(",", ".")
        suffix = stuck.group(2)
    else:
        number = match.group("num").replace(",", ".")
        suffix = match.group("suffix")
    try:
        value = float(number)
    except ValueError:
        return None
    if suffix:
        return int(round(value * _SUFFIX[suffix.lower()]))
    if "." in number:
        return None
    return int(value)
