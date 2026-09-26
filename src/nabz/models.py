"""مدل دادهٔ کانال و گزارش. عدد حدسی در این مدل جا ندارد."""

from __future__ import annotations

from datetime import datetime
from typing import Literal

from pydantic import BaseModel, Field

Platform = Literal["eitaa", "rubika", "bale", "soroush"]
Confidence = Literal["normal", "low", "none"]
Verdict = Literal["بخر", "مشروط", "نخر", "داده کافی نیست"]


class Post(BaseModel):
    id: str
    published_at: datetime
    views: int | None = None
    reactions: int | None = None
    pinned: bool = False
    text_excerpt: str = ""


class ChannelSnapshot(BaseModel):
    platform: Platform
    name: str
    username: str | None = None
    url: str | None = None
    members: int | None = None
    members_label: str | None = None
    posts: list[Post] = Field(default_factory=list)
    fetched_at: datetime
    views_reliable: bool = True
    source: Literal["public_page", "manual"] = "public_page"
    skipped_service_posts: int = 0


class ScorePart(BaseModel):
    key: str
    label: str
    points: int
    maximum: int


class PostRow(BaseModel):
    id: str
    published_at: datetime
    published_label: str
    views: int | None
    reactions: int | None
    view_rate_percent: float | None
    role: Literal["بالغ", "زیر ۱۲ ساعت", "پین"]


class Report(BaseModel):
    tool: str = "nabz"
    version: str = "1.0.0"
    disclaimer: str
    channel_name: str
    platform: Platform
    platform_label: str
    username: str | None
    url: str | None
    source: Literal["public_page", "manual"]
    verdict: Verdict
    verdict_sentence: str
    confidence: Confidence
    live_percent: float | None = None
    live_percent_definitive: bool = False
    live_p25: float | None = None
    live_p75: float | None = None
    expected_live_percent: float | None = None
    live_vs_expected: float | None = None
    members: int | None = None
    members_label: str | None = None
    median_views: float | None = None
    mean_views: float | None = None
    reaction_rate_percent: float | None = None
    reactions_known: bool = False
    posts_per_week: float | None = None
    hours_since_last_post: float | None = None
    last_post_at: datetime | None = None
    mature_posts: int = 0
    score: int | None = None
    score_parts: list[ScorePart] = Field(default_factory=list)
    warnings: list[str] = Field(default_factory=list)
    suggested_price_toman: int | None = None
    price_low_toman: int | None = None
    price_high_toman: int | None = None
    cpm_toman: int
    price_note: str
    posts: list[PostRow] = Field(default_factory=list)
    topic: str | None = None
    window_days: int = 30
