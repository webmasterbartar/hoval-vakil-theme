#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
از checkpoint.json بات (مثل run-20260429-135734) یک «بستهٔ ایمپورت سمت سرور» بسازید:

  خروجی/
    manifest.json          ← همان منطق record_to_payload در import_lawyers.py
    images/.../profile.webp ← کپی از پوشهٔ run کنار checkpoint

بعد این پوشه را به هاست ببرید (مثلاً wp-content/hovalvakil-import/) و در وردپرس:
  ابزارها → ایمپورت بستهٔ وکلا

برای خروجی CSV از همان checkpoint: tools/checkpoint_to_csv.py → ابزارها → ایمپورت CSV وکلا.

نیاز: pip install -r tools/requirements.txt (برای jdatetime و تاریخ پروانه)

مثال:
  python tools/build_import_bundle.py --checkpoint run-20260429-135734/checkpoint.json --out dist/hovalvakil-import
  python tools/build_import_bundle.py --checkpoint ... --out dist/hovalvakil-import --zip
  python tools/build_import_bundle.py ... --strict-images   # اگر image_status=ok ولی فایل نیست → خطا و خروج 3
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import sys
from pathlib import Path
from typing import Any, Dict, List, Tuple

# اجرا از ریشهٔ تم: python tools/build_import_bundle.py → sys.path[0] == tools
_TOOLS_DIR = Path(__file__).resolve().parent
if str(_TOOLS_DIR) not in sys.path:
    sys.path.insert(0, str(_TOOLS_DIR))

from hvl_checkpoint_payload import (  # noqa: E402
    jdatetime,
    local_profile_webp_path,
    public_lawyer_id,
    record_to_payload,
)

THEME_ROOT = Path(__file__).resolve().parents[1]


def anchor_path(p: Path | None) -> Path | None:
    if p is None:
        return None
    if p.is_absolute():
        return p.resolve()
    return (THEME_ROOT / p).resolve()


def safe_rel_parts(rel: str) -> List[str] | None:
    rel = rel.strip().replace("\\", "/").strip("/")
    if not rel:
        return None
    parts = [x for x in rel.split("/") if x and x != "."]
    if any(x == ".." for x in parts):
        return None
    return parts


def copy_profile_image(data_dir: Path, rel: str, out_dir: Path, *, dry_run: bool) -> Tuple[bool, str]:
    parts = safe_rel_parts(rel)
    if not parts:
        return False, "bad_rel"
    src = (data_dir / "images").joinpath(*parts, "profile.webp")
    try:
        src = src.resolve()
    except OSError as e:
        return False, str(e)
    if not src.is_file():
        return False, f"missing:{src}"
    dest = out_dir.joinpath("images", *parts, "profile.webp")
    if dry_run:
        return True, str(dest)
    dest.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(src, dest)
    return True, str(dest)


def record_to_bundle_item(rid: str, rec: Dict[str, Any], data_dir: Path) -> Dict[str, Any]:
    item = record_to_payload(
        rid,
        rec,
        data_dir,
        image_host="",
        use_sideload_url=False,
        featured_attachment_id=None,
        include_remote_url_for_meta=False,
    )
    pid = public_lawyer_id(rec, rid)
    item["slug"] = f"hvl-hub-{pid}"
    lp = local_profile_webp_path(data_dir, rec)
    if lp is not None:
        rel = (rec.get("image_relative_dir") or "").strip().replace("\\", "/").strip("/")
        if rel:
            item["bundle_featured_relative"] = f"images/{rel}/profile.webp"
    return item


def _configure_stdio_utf8() -> None:
    """Windows consoles often use cp1252; avoid UnicodeEncodeError on Persian messages."""
    for stream in (sys.stdout, sys.stderr):
        if hasattr(stream, "reconfigure"):
            try:
                stream.reconfigure(encoding="utf-8")
            except Exception:
                pass


def main() -> int:
    _configure_stdio_utf8()
    ap = argparse.ArgumentParser(
        description="ساخت manifest.json + کپی تصاویر برای ایمپورت بسته در وردپرس (بدون REST از PC)."
    )
    ap.add_argument(
        "--checkpoint",
        type=Path,
        default=THEME_ROOT / "run-20260429-135734" / "checkpoint.json",
        help="مسیر checkpoint.json",
    )
    ap.add_argument(
        "--data-dir",
        type=Path,
        default=None,
        help="پوشهٔ حاوی images/ (پیش‌فرض: همان پوشهٔ parentِ checkpoint)",
    )
    ap.add_argument(
        "--out",
        type=Path,
        required=True,
        help="پوشهٔ خروجی (ساخته می‌شود؛ اگر هست باید خالی باشد یا از --force)",
    )
    ap.add_argument("--force", action="store_true", help="اگر out وجود دارد پاک شود (خطرناک)")
    ap.add_argument("--dry-run", action="store_true", help="فقط گزارش؛ چیزی ننویسد")
    ap.add_argument(
        "--strict-images",
        action="store_true",
        help="اگر image_status=ok ولی profile.webp روی دیسک نیست → خطا و کد خروج ۳",
    )
    ap.add_argument("--zip", action="store_true", help="بعد از ساخت، ZIP هم در کنار out ساخته شود")
    ap.add_argument("--limit", type=int, default=0, help="حداکثر تعداد رکورد از done (۰=همه)")
    args = ap.parse_args()

    checkpoint = anchor_path(args.checkpoint)
    data_dir = anchor_path(args.data_dir) if args.data_dir else (checkpoint.parent if checkpoint else None)
    out_dir = anchor_path(args.out)

    if not checkpoint or not checkpoint.is_file():
        sys.stderr.write(f"checkpoint پیدا نشد: {checkpoint}\n")
        return 2
    if not data_dir or not data_dir.is_dir():
        sys.stderr.write(f"data-dir نامعتبر: {data_dir}\n")
        return 2
    if not out_dir:
        sys.stderr.write("مسیر out نامعتبر است.\n")
        return 2

    if jdatetime is None:
        sys.stderr.write("هشدار: jdatetime نصب نیست؛ تاریخ‌های شمسی خالی به میلادی تبدیل نمی‌شوند. pip install jdatetime\n")

    if not args.dry_run:
        if out_dir.exists():
            if not args.force:
                sys.stderr.write(
                    f"پوشهٔ خروجی از قبل هست: {out_dir}\n"
                    "برای پاک کردن و پر کردن دوباره --force بدهید یا مسیر دیگری انتخاب کنید.\n"
                )
                return 2
            shutil.rmtree(out_dir)
        out_dir.mkdir(parents=True, exist_ok=True)
        (out_dir / "images").mkdir(parents=True, exist_ok=True)

    print("[info] خواندن checkpoint (فایل بزرگ؛ چند دقیقه طول بکشد طبیعی است)...", flush=True)
    raw = checkpoint.read_text(encoding="utf-8-sig")
    data = json.loads(raw)
    done_block: Dict[str, Any] = data.get("done") or {}
    if not isinstance(done_block, dict):
        sys.stderr.write("ساختار checkpoint: done باید آبجکت باشد.\n")
        return 2

    keys = sorted(done_block.keys(), key=lambda k: int(k) if str(k).isdigit() else str(k))
    if args.limit and args.limit > 0:
        keys = keys[: args.limit]

    items: List[Dict[str, Any]] = []
    stats: Dict[str, Any] = {
        "checkpoint": str(checkpoint),
        "data_dir": str(data_dir),
        "out_dir": str(out_dir),
        "total_done_keys": len(done_block),
        "exported": 0,
        "skipped_empty_title": 0,
        "with_bundle_image": 0,
        "image_ok_but_missing_file": 0,
        "image_missing_status": 0,
        "copy_errors": [],
    }

    for rid in keys:
        rec = done_block.get(rid)
        if not isinstance(rec, dict):
            continue
        title = (rec.get("full_name") or "").strip()
        if not title:
            stats["skipped_empty_title"] += 1
            continue

        item = record_to_bundle_item(str(rid), rec, data_dir)
        rel = (rec.get("image_relative_dir") or "").strip().replace("\\", "/").strip("/")
        st = (rec.get("image_status") or "").strip().lower()

        parts_sp = safe_rel_parts(rel) if rel else None
        if st == "ok" and rel and parts_sp:
            src_path = (data_dir / "images").joinpath(*parts_sp, "profile.webp")
            if not src_path.is_file():
                stats["image_ok_but_missing_file"] += 1
                if args.strict_images:
                    sys.stderr.write(
                        f"[strict-images] فایل نیست ولی image_status=ok — id={rid} title={title!r}\n"
                        f"  انتظار: {src_path}\n"
                    )
                    return 3
            if "bundle_featured_relative" in item:
                ok, msg = copy_profile_image(data_dir, rel, out_dir, dry_run=args.dry_run)
                if ok:
                    stats["with_bundle_image"] += 1
                else:
                    stats["copy_errors"].append({"id": rid, "message": msg})
                    del item["bundle_featured_relative"]
        elif st != "ok":
            stats["image_missing_status"] += 1

        items.append(item)
        stats["exported"] += 1

    manifest = {"version": 1, "source_checkpoint": str(checkpoint), "items": items}

    if args.dry_run:
        rep_path = Path(str(out_dir) + ".report.json")
        rep_path.parent.mkdir(parents=True, exist_ok=True)
        rep_path.write_text(json.dumps({**stats, "dry_run": True}, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"[dry-run] می‌نوشت {len(items)} آیتم — گزارش: {rep_path}", flush=True)
        return 0

    man_path = out_dir / "manifest.json"
    man_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"[ok] manifest نوشته شد: {man_path} ({len(items)} وکیل)", flush=True)

    # اعتبارسنجی: هر bundle_featured_relative باید روی دیسک out باشد
    missing_after: List[str] = []
    for it in items:
        rel = it.get("bundle_featured_relative")
        if not rel:
            continue
        check = out_dir / str(rel).replace("\\", "/")
        if not check.is_file():
            missing_after.append(str(rel))
    if missing_after:
        sys.stderr.write(f"[خطا] {len(missing_after)} مسیر در manifest بدون فایل در out.\n")
        for m in missing_after[:20]:
            sys.stderr.write(f"  - {m}\n")
        if len(missing_after) > 20:
            sys.stderr.write(f"  ... و {len(missing_after) - 20} مورد دیگر\n")
        return 4

    rep = out_dir / "bundle_report.json"
    rep.write_text(json.dumps(stats, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"[ok] گزارش: {rep}", flush=True)

    if stats["copy_errors"]:
        sys.stderr.write(f"[هشدار] خطاهای کپی (بدون تصویر در بسته): {len(stats['copy_errors'])}\n")
        for row in stats["copy_errors"][:10]:
            sys.stderr.write(f"  {row}\n")

    if args.zip:
        base = out_dir.parent / f"{out_dir.name}-bundle"
        arc = shutil.make_archive(str(base), "zip", root_dir=str(out_dir.parent), base_dir=out_dir.name)
        print(f"[ok] zip: {arc}", flush=True)

    n = len(items)
    if n > 8000:
        print(
            f"[هشدار] تعداد رکوردها زیاد است ({n}). اگر ایمپورت در PHP خطای memory داد،"
            f" با --limit چند بستهٔ جدا بسازید یا memory_limit هاست را بالا ببرید.",
            flush=True,
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
