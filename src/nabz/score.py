"""حکم خرید تبلیغ از روی ویوی پست بالغ، نه از روی عدد دنبال‌کننده."""

from __future__ import annotations

import math
import statistics
from datetime import datetime, timedelta

from nabz.fa import format_jalali
from nabz.models import ChannelSnapshot, Post, PostRow, Report, ScorePart

MATURE_AFTER = timedelta(hours=12)
STALE_AFTER = timedelta(days=14)
FRESH_WINDOW = timedelta(days=7)
MIN_MATURE = 8
DEFAULT_CPM_TOMAN = 100_000
DISCLAIMER = (
    "درصد زنده برآورد از روی ویو است، نه سرشماری اکانت‌ها. "
    "میانهٔ ویوی پست‌های بالغ تقسیم بر عدد دنبال‌کنندهٔ نمایشی صفحه."
)
PRICE_NOTE = (
    "قیمت فقط از میانهٔ ویو است، نه از عدد دنبال‌کننده. "
    "فرمول: میانهٔ ویو ÷ ۱۰۰۰ × نرخ هر هزار ویو. "
    "نرخ پیش‌فرض یک فرض محاسباتی است و با --cpm عوض می‌شود."
)
PLATFORM_LABELS = {
    "eitaa": "ایتا",
    "rubika": "روبیکا",
    "bale": "بله",
    "soroush": "سروش",
}


def expected_view_rate(members: int) -> float:
    """سهم مورد انتظار ویو. کانال بزرگ ذاتاً درصد پایین‌تری دارد.

    منحنی توانی: حدود ۳۰٪ در هزار نفر، حدود ۱۰٪ در صد هزار، حدود ۶٪ در یک میلیون.
    """
    if members <= 0:
        return 0.0
    rate = 1.5 * (members ** -0.233)
    return min(0.55, max(0.02, rate))


def build_report(
    snapshot: ChannelSnapshot,
    *,
    now: datetime,
    cpm_toman: int = DEFAULT_CPM_TOMAN,
    topic: str | None = None,
    window_days: int = 30,
) -> Report:
    if now.tzinfo is None:
        raise ValueError("زمان باید منطقهٔ زمانی داشته باشد.")
    real_posts = [post for post in snapshot.posts if not post.pinned]
    real_posts.sort(key=lambda post: post.published_at)
    mature = [
        post
        for post in real_posts
        if post.views is not None and now - post.published_at >= MATURE_AFTER
    ]
    warnings: list[str] = []
    if snapshot.platform == "bale" and not any(post.views is not None for post in snapshot.posts):
        warnings.append("ویوی پست در صفحهٔ عمومی بله نیست؛ از روی عدد عضو حکم خرید داده نمی‌شود.")
    if not snapshot.views_reliable:
        warnings.append(
            "ویوی صفحهٔ عمومی قابل اتکا نیست: روی پست‌ها فقط عدد ۱ نوشته شده و درصد زنده حساب نمی‌شود."
        )
    if snapshot.members is None:
        warnings.append("تعداد دنبال‌کننده در صفحه نیست.")
    if any(post.views is None for post in real_posts):
        warnings.append("بعضی پست‌ها ویو ندارند و در محاسبه نیامده‌اند.")
    if real_posts and len(mature) < MIN_MATURE:
        warnings.append(
            "پست بالغ یعنی پستی که دست‌کم ۱۲ ساعت از انتشارش گذشته و ویویش جا افتاده است. "
            f"اینجا فقط {len(mature)} پست بالغ در بازه هست. با کمتر از ۸ پست، درصد زنده را قطعی نگیرید."
        )
    hours_since = _hours_since(real_posts, now)
    posts_per_week = _posts_per_week(real_posts)
    _gap_warnings(real_posts, warnings)
    if hours_since is not None and hours_since > 14 * 24:
        warnings.append("قطع فعالیت: آخرین پست قدیمی‌تر از ۱۴ روز است.")

    base = dict(
        channel_name=snapshot.name,
        platform=snapshot.platform,
        platform_label=PLATFORM_LABELS[snapshot.platform],
        username=snapshot.username,
        url=snapshot.url,
        source=snapshot.source,
        members=snapshot.members,
        members_label=snapshot.members_label,
        cpm_toman=cpm_toman,
        price_note=PRICE_NOTE,
        topic=topic,
        window_days=window_days,
        disclaimer=DISCLAIMER,
        hours_since_last_post=hours_since,
        last_post_at=real_posts[-1].published_at if real_posts else None,
        posts_per_week=posts_per_week,
        posts=_rows(snapshot.posts, snapshot.members, now),
        mature_posts=len(mature),
    )

    if (
        not snapshot.views_reliable
        or snapshot.members is None
        or snapshot.members <= 0
        or not mature
    ):
        return Report(
            verdict="داده کافی نیست",
            verdict_sentence=_insufficient_sentence(snapshot, mature),
            confidence="none",
            live_percent_definitive=False,
            warnings=warnings,
            **base,
        )

    views = [post.views for post in mature if post.views is not None]
    median_views = _percentile(views, 50)
    mean_views = statistics.fmean(views)
    live = median_views / snapshot.members * 100
    live_p25 = _percentile(views, 25) / snapshot.members * 100
    live_p75 = _percentile(views, 75) / snapshot.members * 100
    expected = expected_view_rate(snapshot.members) * 100
    ratio = live / expected if expected else None
    reactions_known = all(post.reactions is not None for post in mature)
    reaction_rate = _reaction_rate(mature) if reactions_known else None
    if not reactions_known:
        warnings.append("نرخ واکنش در صفحه نیست و در امتیاز به‌جای صفر، کنار گذاشته شد.")
    if ratio is not None and ratio < 0.35:
        warnings.append("جهش بدون ویو: دنبال‌کننده خیلی بیشتر از ویوی مورد انتظار همین اندازه است.")
    stability_points, flat = _stability(views, reaction_rate, len(mature))
    if flat:
        warnings.append(flat)
    parts, score = _score(
        ratio=ratio or 0,
        hours=hours_since if hours_since is not None else 10_000,
        posts_per_week=posts_per_week or 0,
        reaction_rate=reaction_rate,
        stability_points=stability_points,
        reactions_known=reactions_known,
    )
    definitive = len(mature) >= MIN_MATURE
    confidence = "normal" if definitive else "low"
    if not definitive:
        warnings.append("درصد را قطعی نگیرید.")
    verdict, sentence = _verdict(
        score=score,
        live=live,
        hours=hours_since,
        confidence=confidence,
        topic=topic,
        mature_posts=len(mature),
    )
    low_price = int(round(_percentile(views, 25) / 1000 * cpm_toman))
    high_price = int(round(_percentile(views, 75) / 1000 * cpm_toman))
    return Report(
        verdict=verdict,
        verdict_sentence=sentence,
        confidence=confidence,
        live_percent=round(live, 2),
        live_percent_definitive=definitive,
        live_p25=round(live_p25, 2),
        live_p75=round(live_p75, 2),
        expected_live_percent=round(expected, 2),
        live_vs_expected=round(ratio, 2) if ratio is not None else None,
        median_views=round(median_views, 1),
        mean_views=round(mean_views, 1),
        reaction_rate_percent=round(reaction_rate * 100, 2) if reaction_rate is not None else None,
        reactions_known=reactions_known,
        score=score,
        score_parts=parts,
        warnings=warnings,
        suggested_price_toman=int(round(median_views / 1000 * cpm_toman)),
        price_low_toman=low_price,
        price_high_toman=high_price,
        **base,
    )


def _insufficient_sentence(snapshot: ChannelSnapshot, mature: list[Post]) -> str:
    if snapshot.platform == "bale" and not any(post.views is not None for post in snapshot.posts):
        return (
            "داده کافی نیست. صفحهٔ عمومی بله فقط عدد عضو را نشان می‌دهد و ویوی پست را نمی‌نویسد. "
            "درصد زنده اعلام نمی‌شود."
        )
    if not snapshot.views_reliable:
        return "داده کافی نیست. صفحهٔ عمومی ویوی واقعی نشان نداده و درصد زنده اعلام نمی‌شود."
    if snapshot.members is None or snapshot.members <= 0:
        return "داده کافی نیست. تعداد دنبال‌کننده در صفحه نیست و درصد زنده حساب نمی‌شود."
    if not mature:
        return "داده کافی نیست. پست بالغ با ویو نیست. پست تازه‌تر از ۱۲ ساعت در میانه نمی‌آید."
    return "داده کافی نیست."


def _verdict(
    *,
    score: int,
    live: float,
    hours: float | None,
    confidence: str,
    topic: str | None,
    mature_posts: int,
) -> tuple[str, str]:
    prefix = ""
    if confidence == "low":
        prefix = f"اعتماد پایین است چون فقط {mature_posts} پست بالغ هست و درصد قطعی نیست. "
    if hours is not None and hours > 14 * 24:
        return "نخر", prefix + "نخر. آخرین پست قدیمی‌تر از ۱۴ روز است و کانال مرده حساب می‌شود."
    if live < 1:
        return "نخر", prefix + "نخر. درصد زنده زیر ۱ است و کانال از نظر بازدید مرده است."
    if live < 5:
        return "نخر", prefix + "نخر. درصد زنده زیر ۵ است و معمولاً دنبال‌کننده بادشده است."
    if score < 45:
        return "نخر", prefix + f"نخر. امتیاز {score} از ۱۰۰ پایین‌تر از ۴۵ است."
    audience = (
        f"فقط اگر موضوع «{topic}» واقعاً مخاطب همین کانال باشد."
        if topic
        else "فقط اگر موضوع تبلیغ با مخاطب کانال یکی باشد."
    )
    alive = hours is not None and hours <= 7 * 24 and live > 15
    if score >= 75 and live > 15:
        if alive:
            return "بخر", prefix + f"بخر. کانال زنده است و امتیاز {score} از ۱۰۰."
        return "بخر", prefix + f"بخر. امتیاز {score} از ۱۰۰ است، ولی پست ۷ روز اخیر ندارد."
    return "مشروط", prefix + f"مشروط. امتیاز {score} از ۱۰۰. {audience}"


def _score(
    *,
    ratio: float,
    hours: float,
    posts_per_week: float,
    reaction_rate: float | None,
    stability_points: int,
    reactions_known: bool,
) -> tuple[list[ScorePart], int]:
    raw = {
        "reach": (45 * min(1.0, max(0.0, ratio / 1.5)), 45, "نرخ بازدید نسبت به هم‌اندازه"),
        "fresh": (_fresh_points(hours), 15, "تازگی"),
        "rhythm": (_rhythm_points(posts_per_week), 10, "ریتم"),
        "stability": (stability_points, 15, "ثبات ویو"),
    }
    if reactions_known:
        raw["reaction"] = (_reaction_points(reaction_rate or 0), 15, "واکنش")
        weights = {key: item[0] for key, item in raw.items()}
        maxima = {key: item[1] for key, item in raw.items()}
    else:
        scale = 100 / 85
        weights = {key: item[0] * scale for key, item in raw.items()}
        maxima = {key: int(round(item[1] * scale)) for key, item in raw.items()}
    rounded = {key: int(math.floor(value + 0.5)) for key, value in weights.items()}
    total = sum(rounded.values())
    target = int(math.floor(sum(weights.values()) + 0.5))
    drift = target - total
    rounded["reach"] = max(0, min(maxima["reach"], rounded["reach"] + drift))
    order = ["reach", "fresh", "rhythm", "reaction", "stability"]
    parts = [
        ScorePart(key=key, label=raw[key][2], points=rounded[key], maximum=maxima[key])
        for key in order
        if key in rounded
    ]
    return parts, sum(part.points for part in parts)


def _fresh_points(hours: float) -> float:
    if hours <= 24:
        return 15
    if hours <= 72:
        return 12
    if hours <= 24 * 7:
        return 9
    if hours <= 24 * 14:
        return 4
    return 0


def _rhythm_points(posts_per_week: float) -> float:
    if posts_per_week < 0.25:
        return 0
    if posts_per_week < 1:
        return 3
    if posts_per_week < 2:
        return 6
    if posts_per_week <= 21:
        return 10
    if posts_per_week <= 50:
        return 8
    return 6


def _reaction_points(rate: float) -> float:
    if rate >= 0.02:
        return 15
    if rate >= 0.01:
        return 12
    if rate >= 0.005:
        return 8
    if rate >= 0.002:
        return 5
    if rate > 0:
        return 3
    return 1


def _stability(views: list[int], reaction_rate: float | None, count: int) -> tuple[int, str | None]:
    if count < 2:
        return 8, None
    mean = statistics.fmean(views)
    if mean <= 0:
        return 0, None
    cv = statistics.pstdev(views) / mean
    if cv < 0.08 and count >= MIN_MATURE:
        if reaction_rate is not None and reaction_rate < 0.003:
            return 2, "ویوی غیرعادی یکدست با واکنش خیلی پایین؛ احتمال ویوی خریده‌شده."
        if reaction_rate is None:
            return 6, "ویو بیش از حد یکدست است و واکنش صفحه برای سنجیدن آن نیست."
        return 6, "ویو بیش از حد یکدست است."
    if cv <= 0.85:
        return 15, None
    if cv <= 1.4:
        return 10, None
    return 7, None


def _reaction_rate(mature: list[Post]) -> float:
    rates = []
    for post in mature:
        if post.views and post.reactions is not None:
            rates.append(post.reactions / post.views)
    if not rates:
        return 0.0
    return _percentile(rates, 50)


def _posts_per_week(posts: list[Post]) -> float | None:
    if len(posts) < 2:
        return None
    span = posts[-1].published_at - posts[0].published_at
    weeks = span.total_seconds() / (7 * 24 * 3600)
    if weeks <= 0:
        return None
    return (len(posts) - 1) / weeks


def _hours_since(posts: list[Post], now: datetime) -> float | None:
    if not posts:
        return None
    return (now - posts[-1].published_at).total_seconds() / 3600


def _gap_warnings(posts: list[Post], warnings: list[str]) -> None:
    for older, newer in zip(posts, posts[1:]):
        if newer.published_at - older.published_at > STALE_AFTER:
            warnings.append("بین پست‌ها بیش از ۱۴ روز فاصله است.")
            return


def _rows(posts: list[Post], members: int | None, now: datetime) -> list[PostRow]:
    ordered = sorted(posts, key=lambda post: post.published_at, reverse=True)
    rows = []
    for post in ordered:
        if post.pinned:
            role = "پین"
        elif now - post.published_at < MATURE_AFTER:
            role = "زیر ۱۲ ساعت"
        else:
            role = "بالغ"
        rate = None
        if post.views is not None and members:
            rate = round(post.views / members * 100, 2)
        rows.append(
            PostRow(
                id=post.id,
                published_at=post.published_at,
                published_label=format_jalali(post.published_at),
                views=post.views,
                reactions=post.reactions,
                view_rate_percent=rate,
                role=role,
            )
        )
    return rows


def _percentile(values: list[float] | list[int], percent: float) -> float:
    ordered = sorted(values)
    if len(ordered) == 1:
        return float(ordered[0])
    rank = (len(ordered) - 1) * (percent / 100)
    low = math.floor(rank)
    high = math.ceil(rank)
    if low == high:
        return float(ordered[low])
    weight = rank - low
    return ordered[low] * (1 - weight) + ordered[high] * weight
