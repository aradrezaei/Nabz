"""گزارش یک‌صفحه‌ای ترمینال، راست‌به‌چپ."""

from __future__ import annotations

from nabz.fa import fa_decimal, fa_int, format_age
from nabz.models import Report

RLE = "\u202b"
PDF = "\u202c"


def render_text(report: Report) -> str:
    lines = [
        _line("نبض"),
        _line(report.verdict_sentence),
        _line(report.disclaimer),
        _line(f"{report.channel_name}  ·  {report.platform_label}"),
        "",
        _line(_live_line(report)),
        _line(
            "دنبال‌کننده "
            + _members(report)
            + "    میانهٔ ویو "
            + _num(report.median_views)
            + "    میانگین ویو "
            + _num(report.mean_views)
        ),
        _line(
            "نرخ واکنش "
            + _reaction(report)
            + "    پست در هفته "
            + _num(report.posts_per_week)
            + "    سن آخرین پست "
            + (format_age(report.hours_since_last_post) if report.hours_since_last_post is not None else "نیست")
        ),
        _line(_score_line(report)),
    ]
    for part in report.score_parts:
        lines.append(_line(f"  {part.label}: {fa_int(part.points)} از {fa_int(part.maximum)}"))
    lines.append("")
    lines.append(_line("پست‌های اخیر"))
    lines.append(_line("تاریخ                    ویو         واکنش      نرخ        نقش"))
    for row in report.posts:
        views = fa_int(row.views) if row.views is not None else "—"
        reactions = fa_int(row.reactions) if row.reactions is not None else "—"
        rate = fa_decimal(row.view_rate_percent) + "٪" if row.view_rate_percent is not None else "—"
        lines.append(_line(f"{row.published_label}    {views}    {reactions}    {rate}    {row.role}"))
    lines.append("")
    lines.append(_line("هشدارها"))
    if report.warnings:
        lines.extend(_line("• " + warning) for warning in report.warnings)
    else:
        lines.append(_line("• هشداری نیست"))
    lines.append("")
    lines.append(_line(_price_line(report)))
    lines.append(_line(report.price_note))
    return "\n".join(lines)


def _live_line(report: Report) -> str:
    if report.live_percent is None:
        return "درصد زنده: داده کافی نیست"
    certainty = "قطعی" if report.live_percent_definitive else "برآورد، قطعی نیست"
    band = ""
    if report.live_p25 is not None and report.live_p75 is not None:
        band = f"  بازه {fa_decimal(report.live_p25)}٪ تا {fa_decimal(report.live_p75)}٪"
    expected = ""
    if report.expected_live_percent is not None and report.live_vs_expected is not None:
        expected = (
            f"  انتظار هم‌اندازه {fa_decimal(report.expected_live_percent)}٪"
            f"  نسبت {fa_decimal(report.live_vs_expected)}"
        )
    return f"درصد زنده: {fa_decimal(report.live_percent)}٪ ({certainty}){band}{expected}"


def _score_line(report: Report) -> str:
    if report.score is None:
        return "امتیاز: حساب نشد"
    return f"امتیاز {fa_int(report.score)} از ۱۰۰"


def _price_line(report: Report) -> str:
    if report.suggested_price_toman is None:
        return "قیمت پیشنهادی: بدون میانهٔ ویو حساب نمی‌شود"
    band = ""
    if report.price_low_toman is not None and report.price_high_toman is not None:
        band = f"  بازه {fa_int(report.price_low_toman)} تا {fa_int(report.price_high_toman)} تومان"
    return f"قیمت پیشنهادی از میانهٔ ویو: {fa_int(report.suggested_price_toman)} تومان{band}"


def _members(report: Report) -> str:
    if report.members is None:
        return "نیست"
    label = f" ({report.members_label})" if report.members_label else ""
    return fa_int(report.members) + label


def _reaction(report: Report) -> str:
    if not report.reactions_known or report.reaction_rate_percent is None:
        return "در صفحه نیست"
    return fa_decimal(report.reaction_rate_percent) + "٪"


def _num(value: float | None) -> str:
    if value is None:
        return "—"
    return fa_decimal(value, 1)


def _line(text: str) -> str:
    return f"{RLE}{text}{PDF}"
