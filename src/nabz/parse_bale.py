"""خواندن صفحهٔ عمومی پست‌های بله در ble.ir/s/شناسه. بدون لاگین."""

from __future__ import annotations

import re
from datetime import datetime, timedelta

from bs4 import BeautifulSoup

from nabz.fa import TEHRAN, gregorian_to_jalali, jalali_to_gregorian
from nabz.models import Post
from nabz.numbers import parse_count

_MONTHS = {
    "فروردین": 1,
    "اردیبهشت": 2,
    "خرداد": 3,
    "تیر": 4,
    "مرداد": 5,
    "شهریور": 6,
    "مهر": 7,
    "آبان": 8,
    "آذر": 9,
    "دی": 10,
    "بهمن": 11,
    "اسفند": 12,
}
_DATE = re.compile(r"(?P<day>\d+)\s+(?P<month>" + "|".join(_MONTHS) + r")")
_TIME = re.compile(r"(?P<hour>\d{1,2}):(?P<minute>\d{2})")


class BalePage:
    def __init__(
        self,
        name: str | None,
        members: int | None,
        members_label: str | None,
        posts: list[Post],
    ) -> None:
        self.name = name
        self.members = members
        self.members_label = members_label
        self.posts = posts


def parse_bale_html(html: str, *, now: datetime | None = None) -> BalePage:
    soup = BeautifulSoup(html, "html.parser")
    title = soup.select_one("h1")
    name = title.get_text(" ", strip=True) if title else None
    members, members_label = _members(soup)
    moment = now or datetime.now(TEHRAN)
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=TEHRAN)
    posts = _posts(soup, moment)
    return BalePage(name=name or None, members=members, members_label=members_label, posts=posts)


def _members(soup: BeautifulSoup) -> tuple[int | None, str | None]:
    node = soup.select_one("[class*='Profile_memberCount']")
    if node is None:
        for span in soup.find_all("span"):
            text = " ".join(span.get_text(" ", strip=True).split())
            if text.endswith("عضو"):
                return parse_count(text.removesuffix("عضو").strip()), text
        return None, None
    label = " ".join(node.get_text(" ", strip=True).split())
    return parse_count(label.removesuffix("عضو").strip()), label or None


def _posts(soup: BeautifulSoup, now: datetime) -> list[Post]:
    nodes = soup.select("[class*='DateDivider_Date__'], [class*='MessageItem_messageWrapper']")
    current_day: tuple[int, int] | None = None
    posts: list[Post] = []
    jy, _, _ = gregorian_to_jalali(now.astimezone(TEHRAN).year, now.astimezone(TEHRAN).month, now.astimezone(TEHRAN).day)
    for node in nodes:
        classes = " ".join(node.get("class") or [])
        if "DateDivider_Date__" in classes:
            current_day = _jalali_day(node.get_text(" ", strip=True))
            continue
        view_node = node.select_one("[class*='Info_Text']")
        time_node = node.select_one("[class*='Info_date']")
        if view_node is None or time_node is None or current_day is None:
            continue
        views = parse_count(view_node.get_text(" ", strip=True))
        published = _published(jy, current_day, time_node.get_text(" ", strip=True), now)
        if views is None or published is None:
            continue
        counts = [
            parse_count(item.get_text(" ", strip=True))
            for item in node.select("[class*='Reactions_count']")
        ]
        numbers = [count for count in counts if count is not None]
        posts.append(
            Post(
                id=str(node.get("data-sid") or f"bale-{len(posts)+1}"),
                published_at=published,
                views=views,
                reactions=sum(numbers) if numbers else 0,
                pinned="pin" in classes.lower(),
                text_excerpt=_excerpt(node),
            )
        )
    return posts


def _jalali_day(text: str) -> tuple[int, int] | None:
    translated = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789"))
    match = _DATE.search(translated)
    if match is None:
        return None
    return int(match.group("day")), _MONTHS[match.group("month")]


def _published(year: int, day_month: tuple[int, int], clock: str, now: datetime) -> datetime | None:
    translated = clock.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789"))
    match = _TIME.search(translated)
    if match is None:
        return None
    day, month = day_month
    gy, gm, gd = jalali_to_gregorian(year, month, day)
    moment = datetime(gy, gm, gd, int(match.group("hour")), int(match.group("minute")), tzinfo=TEHRAN)
    if moment - now > timedelta(days=2):
        gy, gm, gd = jalali_to_gregorian(year - 1, month, day)
        moment = datetime(gy, gm, gd, int(match.group("hour")), int(match.group("minute")), tzinfo=TEHRAN)
    return moment


def _excerpt(node) -> str:
    text = node.select_one("[class*='Text_text']")
    if text is None:
        return ""
    compact = " ".join(text.get_text(" ", strip=True).split())
    return compact[:120]
