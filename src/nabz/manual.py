"""ورود دستی ویو و ممبر برای پلتفرمی که صفحهٔ عمومی ندارد."""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

from pydantic import BaseModel, Field, ValidationError

from nabz.fa import TEHRAN
from nabz.models import ChannelSnapshot, Platform, Post

MANUAL_ONLY = {
    "rubika": "روبیکا",
    "soroush": "سروش",
}

SCHEMA_EXAMPLE = """{
  "platform": "rubika",
  "name": "نام کانال",
  "username": "name",
  "members": 120000,
  "posts": [
    {
      "id": "1",
      "published_at": "2026-09-20T10:00:00+03:30",
      "views": 15000,
      "reactions": 120,
      "pinned": false
    }
  ]
}"""


class ManualPost(BaseModel):
    id: str
    published_at: datetime
    views: int | None = None
    reactions: int | None = None
    pinned: bool = False
    text_excerpt: str = ""


class ManualChannel(BaseModel):
    platform: Platform
    name: str
    username: str | None = None
    url: str | None = None
    members: int | None = None
    members_label: str | None = None
    posts: list[ManualPost] = Field(default_factory=list)


class ManualError(Exception):
    def __init__(self, message: str) -> None:
        super().__init__(message)
        self.message = message


def load_manual(path: Path) -> ChannelSnapshot:
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise ManualError(f"JSON خوانده نشد: {exc}") from exc
    try:
        raw = ManualChannel.model_validate(payload)
    except ValidationError as exc:
        raise ManualError("ساختار JSON دستی با طرح نبض یکی نیست.\n" + SCHEMA_EXAMPLE) from exc
    posts: list[Post] = []
    for item in raw.posts:
        moment = item.published_at
        if moment.tzinfo is None:
            moment = moment.replace(tzinfo=TEHRAN)
        if item.views is not None and item.views < 0:
            raise ManualError("ویو منفی قبول نیست.")
        posts.append(
            Post(
                id=item.id,
                published_at=moment,
                views=item.views,
                reactions=item.reactions,
                pinned=item.pinned,
                text_excerpt=item.text_excerpt[:120],
            )
        )
    if raw.members is not None and raw.members < 0:
        raise ManualError("تعداد دنبال‌کننده منفی قبول نیست.")
    return ChannelSnapshot(
        platform=raw.platform,
        name=raw.name,
        username=raw.username,
        url=raw.url,
        members=raw.members,
        members_label=raw.members_label,
        posts=posts,
        fetched_at=datetime.now(timezone.utc),
        views_reliable=True,
        source="manual",
    )
