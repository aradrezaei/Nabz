"""رابط خط فرمان نبض."""

from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

from nabz.fetch import FetchError, HttpxSource, load_bale, load_eitaa, load_eitaa_url
from nabz.manual import MANUAL_ONLY, SCHEMA_EXAMPLE, ManualError, load_manual
from nabz.models import Report
from nabz.render_html import render_html
from nabz.render_text import render_text
from nabz.score import DEFAULT_CPM_TOMAN, build_report

MANUAL_HOSTS = {
    "web.rubika.ir": "rubika",
    "rubika.ir": "rubika",
    "splus.ir": "soroush",
}
TELEGRAM_HOSTS = {"t.me", "telegram.me", "telegram.dog"}


def main(argv: list[str] | None = None) -> int:
    args = list(sys.argv[1:] if argv is None else argv)
    if args and args[0] in {"manual", "compare", "schema"}:
        return _dispatch_command(args)
    if not args or args[0] in {"-h", "--help"}:
        _link_parser().print_help()
        return 0
    return _run_link(_link_parser().parse_args(args))


def _dispatch_command(args: list[str]) -> int:
    parsed = _command_parser().parse_args(args)
    if parsed.command == "manual":
        return _run_manual(parsed)
    if parsed.command == "compare":
        return _compare(parsed)
    print(SCHEMA_EXAMPLE)
    return 0


def _link_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="nabz", description="سنجش ارزش تبلیغ در کانال عمومی اپ داخلی")
    parser.add_argument("link", nargs="?", help="لینک eitaa.com یا @username با --platform eitaa")
    parser.add_argument("--platform", choices=["eitaa", "rubika", "bale", "soroush"])
    parser.add_argument("--limit", type=int, default=40, help="تعداد پست صفحهٔ عمومی؛ پیش‌فرض ۴۰")
    parser.add_argument("--proxy", help="مثلاً socks5://127.0.0.1:1080")
    parser.add_argument("--cpm", type=int, default=DEFAULT_CPM_TOMAN, help="تومان برای هر هزار ویو")
    parser.add_argument("--topic", help="موضوع تبلیغ، فقط در جملهٔ حکم مشروط می‌آید")
    parser.add_argument("--days", type=int, default=30, help="بازهٔ تحلیل به روز؛ پیش‌فرض ۳۰")
    parser.add_argument("--out", default="nabz-out", help="پوشهٔ HTML و JSON")
    return parser


def _command_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="nabz")
    sub = parser.add_subparsers(dest="command", required=True)
    manual = sub.add_parser("manual", help="JSON دستی ویو و دنبال‌کننده")
    manual.add_argument("file")
    manual.add_argument("--cpm", type=int, default=DEFAULT_CPM_TOMAN)
    manual.add_argument("--topic")
    manual.add_argument("--out", default="nabz-out")
    compare = sub.add_parser("compare", help="مقایسهٔ دو گزارش JSON")
    compare.add_argument("left")
    compare.add_argument("right")
    sub.add_parser("schema", help="نمونهٔ JSON دستی")
    return parser


def _run_link(args: argparse.Namespace) -> int:
    if not args.link:
        print("لینک کانال را بدهید، یا nabz manual FILE", file=sys.stderr)
        return 2
    host, username = _classify(args.link, args.platform)
    if host == "telegram":
        print("نبض برای تلگرام نیست. لینک عمومی ایتا، یا JSON دستی برای روبیکا و بله و سروش.", file=sys.stderr)
        return 2
    if host is None:
        print("لینک شناخته نشد. ایتا را بدهید، یا برای روبیکا و بله و سروش از nabz manual استفاده کنید.", file=sys.stderr)
        return 2
    if host == "bale":
        if not username:
            print("نام کانال بله در لینک نیست.", file=sys.stderr)
            return 2
        source = HttpxSource(proxy=args.proxy or os.environ.get("NABZ_PROXY") or None)
        try:
            snapshot = load_bale(username, source, days=args.days)
        except FetchError as exc:
            print(exc.message, file=sys.stderr)
            return 3
        finally:
            source.close()
        report = build_report(
            snapshot, now=datetime.now(timezone.utc), cpm_toman=args.cpm, topic=args.topic, window_days=args.days
        )
        _emit(report, Path(args.out))
        return 0
    if host in MANUAL_ONLY:
        print(
            f"صفحهٔ عمومی {MANUAL_ONLY[host]} ویوی پست را نشان نمی‌دهد و برایش اسکرپر نوشته نمی‌شود.\n"
            "آمار را دستی بدهید:\n  nabz manual channel.json\n"
            "نمونه:\n" + SCHEMA_EXAMPLE,
            file=sys.stderr,
        )
        return 2
    proxy = args.proxy or os.environ.get("NABZ_PROXY") or None
    source = HttpxSource(proxy=proxy)
    try:
        if username:
            snapshot = load_eitaa(username, source, limit=args.limit)
        else:
            snapshot = load_eitaa_url(args.link, source, limit=args.limit)
    except FetchError as exc:
        print(exc.message, file=sys.stderr)
        return 3
    finally:
        source.close()
    report = build_report(
        snapshot, now=datetime.now(timezone.utc), cpm_toman=args.cpm, topic=args.topic, window_days=args.days
    )
    _emit(report, Path(args.out))
    return 0


def _run_manual(args: argparse.Namespace) -> int:
    try:
        snapshot = load_manual(Path(args.file))
    except ManualError as exc:
        print(exc.message, file=sys.stderr)
        return 2
    if snapshot.platform != "eitaa" and snapshot.source == "manual":
        pass
    report = build_report(snapshot, now=datetime.now(timezone.utc), cpm_toman=args.cpm, topic=args.topic)
    _emit(report, Path(args.out))
    return 0


def _compare(args: argparse.Namespace) -> int:
    left = Report.model_validate(json.loads(Path(args.left).read_text(encoding="utf-8")))
    right = Report.model_validate(json.loads(Path(args.right).read_text(encoding="utf-8")))

    def cell(report: Report) -> str:
        live = "—" if report.live_percent is None else f"{report.live_percent:.2f}"
        score = "—" if report.score is None else str(report.score)
        price = "—" if report.suggested_price_toman is None else str(report.suggested_price_toman)
        return f"{report.channel_name}\t{report.verdict}\t{live}\t{score}\t{price}"

    print("کانال\tحکم\tدرصد زنده\tامتیاز\tقیمت تومان")
    print(cell(left))
    print(cell(right))
    return 0


def _emit(report: Report, out_dir: Path) -> None:
    out_dir.mkdir(parents=True, exist_ok=True)
    slug = report.username or report.channel_name
    safe = "".join(ch if ch.isalnum() or ch in "-_" else "-" for ch in slug).strip("-") or "report"
    json_path = out_dir / f"{safe}.json"
    html_path = out_dir / f"{safe}.html"
    json_path.write_text(report.model_dump_json(indent=2), encoding="utf-8")
    html_path.write_text(render_html(report), encoding="utf-8")
    print(render_text(report))
    print(f"\nJSON: {json_path}\nHTML: {html_path}")


def _classify(link: str, platform: str | None) -> tuple[str | None, str | None]:
    text = link.strip()
    if text.startswith("@"):
        name = text[1:]
        if platform == "bale":
            return "bale", name
        if platform in MANUAL_ONLY:
            return platform, None
        return "eitaa", name
    if platform == "bale":
        return "bale", text.strip().strip("/")
    parsed = urlparse(text if "://" in text else "https://" + text)
    host = (parsed.hostname or "").lower().removeprefix("www.")
    if host in TELEGRAM_HOSTS:
        return "telegram", None
    if host == "ble.ir":
        parts = [part for part in parsed.path.split("/") if part]
        if parts and parts[0] == "s":
            return "bale", parts[1] if len(parts) > 1 else None
        return "bale", parts[0] if parts else None
    if host in MANUAL_HOSTS:
        return MANUAL_HOSTS[host], None
    if host == "eitaa.com":
        parts = [part for part in parsed.path.split("/") if part and part != "s"]
        return "eitaa", parts[0] if parts else None
    if platform == "eitaa":
        return "eitaa", text
    return None, None
