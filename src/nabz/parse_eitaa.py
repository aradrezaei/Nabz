"""خواندن صفحهٔ عمومی ایتا. فقط عددهای همان HTML؛ بدون توکن data-view."""

from __future__ import annotations

from datetime import datetime
from urllib.parse import urljoin

from bs4 import BeautifulSoup

from nabz.models import Post
from nabz.numbers import parse_count

EITAA_ORIGIN = "https://eitaa.com"


class EitaaPage:
    def __init__(
        self,
        *,
        name: str | None,
        username: str | None,
        members: int | None,
        members_label: str | None,
        posts: list[Post],
        next_url: str | None,
        skipped_service_posts: int,
    ) -> None:
        self.name = name
        self.username = username
        self.members = members
        self.members_label = members_label
        self.posts = posts
        self.next_url = next_url
        self.skipped_service_posts = skipped_service_posts


def parse_eitaa_html(html: str, page_url: str = EITAA_ORIGIN) -> EitaaPage:
    soup = BeautifulSoup(html, "html.parser")
    name = _text(soup.select_one(".etme_channel_info_header_title span"))
    username = _username(soup)
    members, members_label = _members(soup)
    posts: list[Post] = []
    skipped = 0
    for node in soup.select("div.etme_widget_message"):
        classes = node.get("class") or []
        if "service_message" in classes or any("service" in item for item in classes):
            skipped += 1
            continue
        post_id = str(node.get("data-post") or "")
        published = _published(node)
        if not post_id or published is None:
            continue
        views = _views(node)
        posts.append(
            Post(
                id=post_id,
                published_at=published,
                views=views,
                reactions=_reactions(node),
                pinned=any("pin" in item for item in classes),
                text_excerpt=_excerpt(node),
            )
        )
    return EitaaPage(
        name=name,
        username=username,
        members=members,
        members_label=members_label,
        posts=posts,
        next_url=_next_url(soup, page_url),
        skipped_service_posts=skipped,
    )


def views_look_unpublished(posts: list[Post]) -> bool:
    """ایتا وقتی ویو را عمومی نمی‌نویسد، روی همهٔ پست‌ها عدد ۱ می‌گذارد."""
    counted = [post.views for post in posts if post.views is not None and not post.pinned]
    return len(counted) >= 3 and all(view == 1 for view in counted)


def _members(soup: BeautifulSoup) -> tuple[int | None, str | None]:
    for counter in soup.select(".etme_channel_info_counter"):
        kind = _text(counter.select_one(".counter_type")) or ""
        if not any(word in kind for word in ("دنبال", "مشترک", "عضو")):
            continue
        value = counter.select_one(".counter_value")
        label = _text(value)
        exact = value.get("data-count") if value else None
        parsed = parse_count(str(exact)) if exact else None
        if parsed is None:
            parsed = parse_count(label)
        return parsed, label
    return None, None


def _views(node) -> int | None:
    view = node.select_one(".etme_widget_message_views")
    if view is None:
        return None
    exact = view.get("data-count")
    if exact is not None and str(exact).strip() != "":
        parsed = parse_count(str(exact))
        if parsed is not None:
            return parsed
    return parse_count(view.get_text(" ", strip=True))


def _reactions(node) -> int | None:
    counts = node.select("[class*='reaction_count']")
    if not counts:
        return None
    total = 0
    found = False
    for count in counts:
        parsed = parse_count(count.get_text(" ", strip=True))
        if parsed is None:
            continue
        total += parsed
        found = True
    return total if found else None


def _published(node) -> datetime | None:
    stamp = node.select_one("time[datetime]")
    if stamp is None or not stamp.get("datetime"):
        return None
    text = str(stamp["datetime"])
    try:
        moment = datetime.fromisoformat(text)
    except ValueError:
        return None
    if moment.tzinfo is None:
        return None
    return moment


def _username(soup: BeautifulSoup) -> str | None:
    link = soup.select_one(".etme_channel_info_header_username a")
    if link is None:
        return None
    text = link.get_text(strip=True).lstrip("@")
    return text or None


def _next_url(soup: BeautifulSoup, page_url: str) -> str | None:
    link = soup.select_one("a.js-messages_more[href], link[rel='prev'][href]")
    if link is None or not link.get("href"):
        return None
    href = str(link["href"])
    if "before=" not in href:
        return None
    absolute = urljoin(page_url, href)
    if not absolute.startswith(EITAA_ORIGIN + "/"):
        return None
    return absolute


def _excerpt(node) -> str:
    text = node.select_one(".etme_widget_message_text")
    if text is None:
        return ""
    compact = " ".join(text.get_text(" ", strip=True).split())
    return compact[:120]


def _text(node) -> str | None:
    if node is None:
        return None
    value = " ".join(node.get_text(" ", strip=True).split())
    return value or None
