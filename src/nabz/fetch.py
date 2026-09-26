from __future__ import annotations

import re
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta, timezone

import httpx

from nabz.fa import TEHRAN
from nabz.models import ChannelSnapshot, Post
from nabz.parse_bale import parse_bale_html
from nabz.parse_eitaa import EITAA_ORIGIN, parse_eitaa_html, views_look_unpublished

USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36"
)


class PageSource:
    def get(self, url: str) -> str:
        raise NotImplementedError


class HttpxSource(PageSource):
    def __init__(self, proxy: str | None = None) -> None:
        self._client = httpx.Client(
            proxy=proxy,
            timeout=httpx.Timeout(12.0, connect=5.0),
            follow_redirects=True,
            trust_env=proxy is None,
            limits=httpx.Limits(max_connections=32, max_keepalive_connections=16),
            headers={
                "User-Agent": USER_AGENT,
                "Accept-Language": "fa,en;q=0.8",
            },
        )

    def get(self, url: str) -> str:
        response = self._client.get(url)
        response.raise_for_status()
        return response.text

    def post_json(self, url: str, body: dict) -> dict:
        response = self._client.post(
            url,
            json=body,
            headers={"Origin": "https://ble.ir", "Referer": "https://ble.ir/"},
        )
        response.raise_for_status()
        data = response.json()
        if not isinstance(data, dict):
            raise FetchError("پاسخ صفحهٔ عمومی بله قابل خواندن نبود.")
        return data

    def close(self) -> None:
        self._client.close()


class FetchError(Exception):
    def __init__(self, message: str) -> None:
        super().__init__(message)
        self.message = message


def channel_url(username: str) -> str:
    clean = username.strip().lstrip("@").strip("/")
    if not clean or "/" in clean or clean.startswith("."):
        raise FetchError("نام کاربری ایتا درست نیست.")
    return f"{EITAA_ORIGIN}/{clean}"


def load_eitaa(username: str, source: PageSource, *, limit: int = 40) -> ChannelSnapshot:
    url = channel_url(username)
    return load_eitaa_url(url, source, limit=limit)


def load_eitaa_url(url: str, source: PageSource, *, limit: int = 40) -> ChannelSnapshot:
    if not url.startswith(EITAA_ORIGIN + "/"):
        raise FetchError("فقط صفحهٔ عمومی ایتا خوانده می‌شود.")
    pages = 0
    seen: set[str] = set()
    posts = []
    skipped = 0
    name = None
    username = None
    members = None
    members_label = None
    current = url.split("?")[0]
    while current and pages < 6 and len(posts) < limit:
        pages += 1
        try:
            html = source.get(current)
        except httpx.HTTPError as exc:
            raise FetchError(_network_message()) from exc
        parsed = parse_eitaa_html(html, current)
        if pages == 1:
            name = parsed.name
            username = parsed.username
            members = parsed.members
            members_label = parsed.members_label
        skipped += parsed.skipped_service_posts
        for post in parsed.posts:
            if post.id in seen:
                continue
            seen.add(post.id)
            posts.append(post)
            if len(posts) >= limit:
                break
        nxt = parsed.next_url
        current = nxt if nxt and nxt != current else None
    if name is None and not posts:
        raise FetchError("این صفحه کانال عمومی ایتا نیست یا کانال پیدا نشد.")
    posts.sort(key=lambda post: post.published_at)
    reliable = not views_look_unpublished(posts)
    return ChannelSnapshot(
        platform="eitaa",
        name=name or username or "کانال ایتا",
        username=username,
        url=url.split("?")[0],
        members=members,
        members_label=members_label,
        posts=posts,
        fetched_at=datetime.now(timezone.utc),
        views_reliable=reliable,
        source="public_page",
        skipped_service_posts=skipped,
    )


HISTORY_URL = "https://api.ble.ir/api/v1/LoadHistory"


def load_bale(username: str, source: PageSource, *, days: int = 30) -> ChannelSnapshot:
    clean = username.strip().lstrip("@").strip("/")
    if not clean or "/" in clean:
        raise FetchError("نام کانال بله درست نیست.")
    url = f"https://ble.ir/s/{clean}"
    try:
        html = source.get(url)
    except httpx.HTTPError as exc:
        raise FetchError(_network_message("بله")) from exc
    parsed = parse_bale_html(html)
    peer = _peer(html)
    exact_members = _exact_members(html)
    if parsed.name is None and parsed.members is None and peer is None:
        raise FetchError("این صفحه کانال عمومی بله نیست یا کانال پیدا نشد.")
    posts = _history(source, peer, days=days) if peer else parsed.posts
    if not posts:
        posts = parsed.posts
    return ChannelSnapshot(
        platform="bale",
        name=parsed.name or clean,
        username=clean,
        url=url,
        members=exact_members if exact_members is not None else parsed.members,
        members_label=parsed.members_label,
        posts=posts,
        fetched_at=datetime.now(timezone.utc),
        views_reliable=True,
        source="public_page",
    )


def _peer(html: str) -> dict | None:
    match = re.search(r'\\?"peer\\?":\{\\?"type\\?":(\d+),\\?"id\\?":(\d+)\}', html)
    if match is None:
        return None
    return {"type": int(match.group(1)), "id": int(match.group(2))}


def _exact_members(html: str) -> int | None:
    match = re.search(r'\\?"members\\?":(\d+)', html)
    return int(match.group(1)) if match else None


def _history(source: PageSource, peer: dict, *, days: int) -> list[Post]:
    now = datetime.now(TEHRAN)
    cutoff = now - timedelta(days=days)
    slices = _time_slices(cutoff, now, min(12, max(1, days)))
    found: dict[str, Post] = {}
    with ThreadPoolExecutor(max_workers=len(slices)) as pool:
        batches = pool.map(lambda bounds: _history_slice(source, peer, bounds[0], bounds[1]), slices)
        for batch in batches:
            for post in batch:
                found[post.id] = post
    return sorted(found.values(), key=lambda post: post.published_at)


def _time_slices(start: datetime, end: datetime, parts: int) -> list[tuple[datetime, datetime]]:
    span = (end - start) / parts
    return [(start + span * index, start + span * (index + 1)) for index in range(parts)]


def _history_slice(source: PageSource, peer: dict, start: datetime, end: datetime) -> list[Post]:
    cursor = int(end.timestamp() * 1000)
    floor = int(start.timestamp() * 1000)
    posts = []
    seen: set[str] = set()
    for _ in range(40):
        try:
            payload = source.post_json(HISTORY_URL, {"peer": peer, "date": cursor})
        except httpx.HTTPError as exc:
            raise FetchError(_network_message("بله")) from exc
        except AttributeError as exc:
            raise FetchError("این منبع تاریخچهٔ عمومی بله را برنمی‌گرداند.") from exc
        history = payload.get("history") or []
        dates = [item.get("date") for item in history if isinstance(item.get("date"), int)]
        if not dates:
            break
        oldest = min(dates)
        for item in history:
            post = _history_post(item)
            if post is None or post.id in seen or post.published_at < start:
                continue
            seen.add(post.id)
            posts.append(post)
        if oldest >= cursor or oldest <= floor:
            break
        cursor = oldest - 1
    return posts


def _history_post(item: dict) -> Post | None:
    rid = str(item.get("rid") or "")
    date = item.get("date")
    views = item.get("viewCount")
    if not rid or not isinstance(date, int) or not isinstance(views, int):
        return None
    reactions = item.get("reactions") or []
    total = 0
    for reaction in reactions:
        count = reaction.get("cardinality") if isinstance(reaction, dict) else None
        if isinstance(count, int):
            total += count
    text = ""
    message = item.get("message") or {}
    if isinstance(message, dict):
        text_message = message.get("textMessage") or {}
        if isinstance(text_message, dict):
            text = str(text_message.get("text") or "")
    return Post(
        id=rid,
        published_at=datetime.fromtimestamp(date / 1000, TEHRAN),
        views=views,
        reactions=total,
        text_excerpt=" ".join(text.split())[:120],
    )


def _network_message(app: str = "ایتا") -> str:
    return (
        f"به صفحهٔ عمومی {app} وصل نشدم. اگر از این شبکه بسته است، پراکسی بدهید:\n"
        "  nabz LINK --proxy socks5://127.0.0.1:1080\n"
        "یا متغیر NABZ_PROXY را تنظیم کنید."
    )
