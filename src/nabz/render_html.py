from __future__ import annotations

import base64
from functools import cache
from html import escape
from pathlib import Path

from nabz.fa import fa_decimal, fa_digits, fa_int, format_age
from nabz.models import Report


@cache
def _font_css() -> str:
    root = Path(__file__).resolve().parent / "fonts"
    faces = []
    for filename, weight in (("Vazirmatn-Regular.woff2", 400), ("Vazirmatn-Bold.woff2", 700)):
        payload = base64.b64encode((root / filename).read_bytes()).decode("ascii")
        faces.append(
            "@font-face{"
            f"font-family:Nabz;font-style:normal;font-weight:{weight};font-display:swap;"
            f"src:url(data:font/woff2;base64,{payload}) format('woff2');"
            "}"
        )
    return "".join(faces)


def render_html(report: Report) -> str:
    tone = _tone(report)
    color = {"good": "#0cce6b", "ok": "#ffa400", "bad": "#ff4e42", "none": "#9aa0a6"}[tone]
    score = "—" if report.score is None else fa_digits(str(report.score))
    offset = _gauge_offset(report.score)
    parts = "\n".join(_part(part) for part in report.score_parts)
    rows = "\n".join(_row(row) for row in report.posts) or "<tr><td colspan='5'>پستی در این بازه نیست.</td></tr>"
    warnings = "\n".join(f"<li>{escape(item)}</li>" for item in report.warnings) or "<li>هشدار خاصی نیست.</li>"
    return f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نبض — {escape(report.channel_name)}</title>
<style>
  {_font_css()}
  :root {{ --ink:#202124; --muted:#5f6368; --line:#e8eaed; --paper:#fff; --bg:#f8f9fa; --good:#0cce6b; --ok:#ffa400; --bad:#ff4e42; }}
  * {{ box-sizing:border-box; }}
  body {{ margin:0; background:var(--bg); color:var(--ink); font:15px/1.7 Nabz,Tahoma,sans-serif; }}
  b, strong, h1, h2, .stamp {{ font-weight:700; }}
  main {{ max-width:980px; margin:0 auto; padding:28px 16px 72px; }}
  header, section {{ background:var(--paper); border:1px solid var(--line); border-radius:12px; padding:22px; margin-bottom:16px; }}
  h1 {{ font-size:22px; margin:0 0 4px; }}
  h2 {{ font-size:16px; margin:0 0 12px; }}
  .muted {{ color:var(--muted); }}
  .hero {{ display:flex; gap:28px; align-items:center; }}
  .gauge {{ position:relative; width:132px; height:132px; flex:none; }}
  .gauge svg {{ width:132px; height:132px; transform:rotate(-90deg); }}
  .gauge strong {{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:36px; }}
  .stamp {{ display:inline-block; border:2px solid {color}; color:{color}; border-radius:999px; padding:2px 12px; font-weight:700; }}
  .lead {{ font-size:18px; margin:12px 0; }}
  .grid {{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }}
  .card {{ border:1px solid var(--line); border-radius:10px; padding:12px; }}
  .card b {{ display:block; font-size:22px; font-variant-numeric:tabular-nums; }}
  .card span {{ color:var(--muted); font-size:13px; }}
  .part {{ display:grid; grid-template-columns:180px 1fr 72px; gap:10px; align-items:center; margin:8px 0; }}
  .bar {{ height:8px; background:#e8eaed; border-radius:99px; overflow:hidden; }}
  .bar i {{ display:block; height:100%; background:{color}; }}
  table {{ width:100%; border-collapse:collapse; font-variant-numeric:tabular-nums; }}
  th, td {{ text-align:right; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; }}
  th {{ color:var(--muted); font-weight:600; }}
  ul {{ margin:0; padding:0 18px 0 0; }}
  @media (max-width:720px) {{ .hero,.grid,.part {{ display:block; }} .gauge {{ margin-bottom:12px; }} }}
  @media print {{ body {{ background:#fff; }} header, section {{ break-inside:avoid; }} }}
</style>
</head>
<body>
<main>
  <header>
    <div class="hero">
      <div class="gauge" aria-label="امتیاز">
        <svg viewBox="0 0 120 120">
          <circle cx="60" cy="60" r="52" fill="none" stroke="#e8eaed" stroke-width="10"/>
          <circle cx="60" cy="60" r="52" fill="none" stroke="{color}" stroke-width="10" stroke-linecap="round"
            stroke-dasharray="326.7" stroke-dashoffset="{offset}"/>
        </svg>
        <strong>{score}</strong>
      </div>
      <div>
        <p class="stamp">{escape(report.verdict)}</p>
        <h1>نبض · {escape(report.channel_name)}</h1>
        <p class="muted">{escape(report.platform_label)} · بازهٔ {fa_digits(str(report.window_days))} روز اخیر · شناسه {escape(report.username or "—")}</p>
        <p class="lead">{escape(report.verdict_sentence)}</p>
      </div>
    </div>
  </header>
  <section>
    <h2>این گزارش چه می‌گوید</h2>
    <p>{escape(report.disclaimer)}</p>
    <p>پست بالغ پستی است که حداقل ۱۲ ساعت از انتشارش گذشته باشد. ویوی پست تازه‌تر هنوز کامل نشده و اگر داخل میانه بیاید، کانال را ضعیف‌تر از واقعیت نشان می‌دهد. درصد زنده یعنی میانهٔ ویوی همین پست‌های بالغ، تقسیم بر عدد دنبال‌کننده. این سرشماری آدم‌های واقعی نیست.</p>
    <p>اگر پست بالغ کمتر از ۸ تا باشد، درصد را قطعی اعلام نمی‌کنیم. یک یا دو پست غیرعادی در نمونهٔ کوچک می‌تواند حکم را عوض کند.</p>
  </section>
  <section>
    <h2>عددهای اصلی</h2>
    <div class="grid">
      {_card("درصد زنده", _live(report), "میانهٔ ویو ÷ دنبال‌کننده")}
      {_card("بازهٔ درصد", _band(report), "صدک ۲۵ تا ۷۵ ویو، نه فقط یک عدد")}
      {_card("انتظار هم‌اندازه", _maybe(report.expected_live_percent, "%"), "کانال بزرگ ذاتاً درصد کمتری دارد")}
      {_card("نسبت به انتظار", _maybe(report.live_vs_expected, ""), "۱ یعنی دقیقاً به اندازهٔ کانال هم‌قد")}
      {_card("دنبال‌کننده", _members(report), "عدد دقیق صفحه، نه حدس")}
      {_card("میانهٔ ویو", _maybe(report.median_views, ""), "نصف پست‌ها زیر این عددند")}
      {_card("میانگین ویو", _maybe(report.mean_views, ""), "یک پست وایرال این را بالا می‌برد")}
      {_card("نرخ واکنش", _reaction(report), "جمع واکنش ÷ ویو")}
      {_card("پست در هفته", _maybe(report.posts_per_week, ""), "ریتم همین بازه")}
      {_card("سن آخرین پست", escape(format_age(report.hours_since_last_post)) if report.hours_since_last_post is not None else "نیست", "اگر از ۱۴ روز بگذرد، حکم نخر است")}
      {_card("پست بالغ", fa_int(report.mature_posts), "فقط این‌ها وارد درصد می‌شوند")}
      {_card("قیمت پیشنهادی", _price(report), "فقط از میانهٔ ویو، نه از عدد عضو")}
    </div>
    <p class="muted">{escape(report.price_note)}</p>
  </section>
  <section>
    <h2>امتیاز از کجا آمده</h2>
    <p class="muted">بالای ۷۵ بخر، ۴۵ تا ۷۵ مشروط، زیر ۴۵ نخر. اگر آخرین پست از ۱۴ روز قدیمی‌تر باشد، حتی با امتیاز بالاتر هم نخر است.</p>
    {parts or "<p>امتیاز ساخته نشد چون ویو یا دنبال‌کننده کافی نبود.</p>"}
  </section>
  <section>
    <h2>هشدارها</h2>
    <ul>{warnings}</ul>
  </section>
  <section>
    <h2>پست‌های {fa_digits(str(report.window_days))} روز اخیر</h2>
    <p class="muted">{fa_int(len(report.posts))} پست. ردیف «زیر ۱۲ ساعت» در میانهٔ ویو نیست.</p>
    <table>
      <thead><tr><th>تاریخ</th><th>ویو</th><th>واکنش</th><th>نرخ ویو</th><th>نقش</th></tr></thead>
      <tbody>{rows}</tbody>
    </table>
  </section>
</main>
</body>
</html>
"""


def _tone(report: Report) -> str:
    if report.score is None:
        return "none"
    if report.verdict == "نخر" or report.score < 45:
        return "bad"
    if report.verdict == "بخر" and report.score >= 75:
        return "good"
    return "ok"


def _gauge_offset(score: int | None) -> str:
    if score is None:
        return "326.7"
    filled = 326.7 * max(0, min(100, score)) / 100
    return f"{326.7 - filled:.1f}"


def _part(part) -> str:
    width = 0 if part.maximum == 0 else round(100 * part.points / part.maximum)
    return (
        f"<div class='part'><span>{escape(part.label)}</span>"
        f"<div class='bar'><i style='width:{width}%'></i></div>"
        f"<b>{fa_int(part.points)} از {fa_int(part.maximum)}</b></div>"
    )


def _card(title: str, value: str, note: str) -> str:
    return f"<div class='card'><span>{escape(title)}</span><b>{value}</b><span>{escape(note)}</span></div>"


def _live(report: Report) -> str:
    if report.live_percent is None:
        return "کافی نیست"
    tag = "قطعی" if report.live_percent_definitive else "برآورد"
    return f"{fa_decimal(report.live_percent)}٪ · {tag}"


def _band(report: Report) -> str:
    if report.live_p25 is None or report.live_p75 is None:
        return "—"
    return f"{fa_decimal(report.live_p25)}٪ تا {fa_decimal(report.live_p75)}٪"


def _members(report: Report) -> str:
    if report.members is None:
        return "در صفحه نیست"
    label = f" · نوشتهٔ گرد: {escape(report.members_label)}" if report.members_label else ""
    return fa_int(report.members) + label


def _reaction(report: Report) -> str:
    if not report.reactions_known or report.reaction_rate_percent is None:
        return "در صفحه نیست"
    return fa_decimal(report.reaction_rate_percent) + "٪"


def _price(report: Report) -> str:
    if report.suggested_price_toman is None:
        return "حساب نمی‌شود"
    return fa_int(report.suggested_price_toman) + " تومان"


def _maybe(value: float | None, suffix: str) -> str:
    if value is None:
        return "—"
    text = fa_decimal(value)
    if suffix == "%":
        return text + "٪"
    return text


def _row(row) -> str:
    views = fa_int(row.views) if row.views is not None else "—"
    reactions = fa_int(row.reactions) if row.reactions is not None else "—"
    rate = fa_decimal(row.view_rate_percent) + "٪" if row.view_rate_percent is not None else "—"
    return (
        "<tr>"
        f"<td>{escape(row.published_label)}</td><td>{views}</td><td>{reactions}</td>"
        f"<td>{rate}</td><td>{escape(row.role)}</td></tr>"
    )
