from datetime import datetime, timezone
from pathlib import Path

from nabz.cli import main
from nabz.fa import gregorian_to_jalali
from nabz.fetch import load_eitaa_url
from nabz.numbers import parse_count
from nabz.parse_eitaa import parse_eitaa_html
from nabz.score import build_report, expected_view_rate

ROOT = Path(__file__).parent / "fixtures"
NOW = datetime(2026, 9, 25, 12, 0, tzinfo=timezone.utc)


class MapSource:
    def __init__(self, pages: dict[str, str]) -> None:
        self.pages = pages

    def get(self, url: str) -> str:
        return self.pages[url]


def test_history_slices_cover_the_window_without_duplicates():
    from datetime import timedelta

    from nabz.fa import TEHRAN
    from nabz.fetch import _history

    class Clock:
        def post_json(self, url, body):
            cursor = body["date"]
            items = []
            date = cursor
            for _ in range(10):
                date -= 3_600_000
                items.append({"rid": str(date), "date": date, "viewCount": 100, "reactions": []})
            return {"history": items}

    posts = _history(Clock(), {"type": 2, "id": 1}, days=2)
    start = datetime.now(TEHRAN) - timedelta(days=2)
    assert len(posts) == len({post.id for post in posts})
    assert posts
    assert all(post.published_at >= start for post in posts)
    assert posts == sorted(posts, key=lambda post: post.published_at)


def test_html_embeds_vazirmatn():
    from datetime import timezone

    from nabz.models import ChannelSnapshot
    from nabz.render_html import render_html
    from nabz.score import build_report

    snapshot = ChannelSnapshot(
        platform="bale",
        name="نمونه",
        username="sample",
        members=1000,
        posts=[],
        fetched_at=datetime(2026, 9, 25, tzinfo=timezone.utc),
    )
    html = render_html(build_report(snapshot, now=datetime(2026, 9, 25, 12, tzinfo=timezone.utc)))
    assert "font-family:Nabz" in html
    assert "data:font/woff2;base64," in html


def test_jalali_nowruz_2025():
    assert gregorian_to_jalali(2025, 3, 21) == (1404, 1, 1)


def test_parse_counts():
    assert parse_count("2.6هزار") == 2600
    assert parse_count("242.4هزار") == 242400
    assert parse_count("۱۲٬۳۰۰") == 12300
    assert parse_count("12.4K") == 12400
    assert parse_count("2.6") is None
    assert parse_count("") is None


def test_saved_html_ignores_pin_service_and_young_post():
    page = parse_eitaa_html((ROOT / "eitaa_healthy.html").read_text(encoding="utf-8"), "https://eitaa.com/healthy")
    assert page.members == 100000
    assert page.skipped_service_posts == 1
    assert page.name == "کانال سلامت"
    ids = [post.id for post in page.posts]
    assert "healthy/s1" not in ids
    assert "healthy/pin" in ids
    report = build_report(
        _snapshot(page),
        now=NOW,
        cpm_toman=100_000,
    )
    assert report.mature_posts == 10
    assert report.median_views == 18500
    assert report.mean_views == 19200
    assert report.mean_views != report.median_views
    assert report.live_percent == 18.5
    assert report.verdict == "بخر"
    assert report.confidence == "normal"
    assert report.live_percent_definitive is True
    assert report.suggested_price_toman == 1_850_000
    assert any(row.role == "زیر ۱۲ ساعت" for row in report.posts)
    assert any(row.role == "پین" for row in report.posts)
    assert "سرشماری" in report.disclaimer


def test_placeholder_views_are_not_a_percentage():
    page = parse_eitaa_html((ROOT / "eitaa_placeholder.html").read_text(encoding="utf-8"))
    report = build_report(_snapshot(page), now=NOW)
    assert report.verdict == "داده کافی نیست"
    assert report.live_percent is None


def test_missing_members_does_not_guess():
    page = parse_eitaa_html((ROOT / "eitaa_no_members.html").read_text(encoding="utf-8"))
    report = build_report(_snapshot(page), now=NOW)
    assert report.verdict == "داده کافی نیست"
    assert report.members is None


def test_stale_channel_is_dont_buy_even_with_views():
    html = (ROOT / "eitaa_healthy.html").read_text(encoding="utf-8").replace("2026-09-", "2026-08-")
    page = parse_eitaa_html(html)
    report = build_report(_snapshot(page), now=NOW)
    assert report.verdict == "نخر"
    assert "۱۴ روز" in report.verdict_sentence


def test_expected_rate_falls_as_channel_grows():
    assert expected_view_rate(1_000) > expected_view_rate(100_000) > expected_view_rate(1_000_000)
    assert 0.20 < expected_view_rate(1_000) < 0.40
    assert 0.04 < expected_view_rate(1_000_000) < 0.10


def test_pagination_stops_at_limit_without_network():
    first = (ROOT / "eitaa_healthy.html").read_text(encoding="utf-8")
    second = """
    <div class="etme_channel_info_header_title"><span>کانال سلامت</span></div>
    <div class="etme_widget_message" data-post="healthy/old">
      <span class="etme_widget_message_views" data-count="9000">9هزار</span>
      <time datetime="2026-08-20T10:00:00+00:00"></time>
      <span class="etme_reaction_count">40</span>
    </div>
    """
    source = MapSource({
        "https://eitaa.com/healthy": first,
        "https://eitaa.com/healthy?before=1": second,
    })
    snapshot = load_eitaa_url("https://eitaa.com/healthy", source, limit=40)
    assert any(post.id == "healthy/old" for post in snapshot.posts)


def test_manual_and_telegram_refusal(tmp_path, capsys):
    example = Path("examples/manual-rubika.json")
    out = tmp_path / "out"
    assert main(["manual", str(example), "--out", str(out), "--cpm", "100000"]) == 0
    text = capsys.readouterr().out
    assert "بخر" in text
    assert (out / "sample.json").exists()
    assert (out / "sample.html").exists()
    assert main(["https://t.me/durov"]) == 2
    assert "تلگرام" in capsys.readouterr().err
    assert main(["https://rubika.ir/sample"]) == 2


def _snapshot(page):
    from datetime import timezone as tz
    from nabz.models import ChannelSnapshot
    from nabz.parse_eitaa import views_look_unpublished

    return ChannelSnapshot(
        platform="eitaa",
        name=page.name or "کانال",
        username=page.username,
        members=page.members,
        members_label=page.members_label,
        posts=page.posts,
        fetched_at=datetime(2026, 9, 25, tzinfo=tz.utc),
        views_reliable=not views_look_unpublished(page.posts),
        skipped_service_posts=page.skipped_service_posts,
    )
