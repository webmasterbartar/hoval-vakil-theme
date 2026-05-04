#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تبدیل checkpoint.json بات به CSV برای ایمپورت در وردپرس:
  ابزارها → ایمپورت CSV وکلا

همان منطق رکوردها که در build_import_bundle.py (و hvl_checkpoint_payload) استفاده می‌شود.
خروجی UTF-8 با BOM (باز شدن درست در Excel).

مثال:
  python tools/checkpoint_to_csv.py --checkpoint run-20260429-135734/checkpoint.json --out dist/lawyers.csv
"""

from __future__ import annotations

import argparse
import csv
import json
import sys
from pathlib import Path
from typing import Any, Dict, List

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

# ترتیب با includes/hovalvakil-lawyer-csv-import.php (hovalvakil_lawyer_csv_allowed_headers) هماهنگ باشد.
CSV_FIELDNAMES: List[str] = [
    "external_id",
    "title",
    "slug",
    "content",
    "excerpt",
    "status",
    "city",
    "province",
    "specialties",
    "bundle_featured_relative",
    "featured_image_url",
    "featured_image_filename",
    "featured_attachment_id",
    "hvl_photo_url",
    "hvl_import_external_id",
    "hvl_mobile",
    "hvl_office_mobile",
    "hvl_license_no",
    "hvl_license_issued",
    "hvl_license_expires",
    "hvl_license_issued_jalali",
    "hvl_license_expires_jalali",
    "hvl_lawyer_grade",
    "hvl_license_file_url",
    "hvl_experience",
    "hvl_price_from",
    "hvl_rating",
    "hvl_reviews_count",
    "hvl_license",
    "hvl_education",
    "hvl_office_phone",
    "hvl_office_working_hours",
    "hvl_office_map_image",
    "hvl_records",
    "hvl_services",
    "hvl_office_address",
]


def _configure_stdio_utf8() -> None:
    for stream in (sys.stdout, sys.stderr):
        if hasattr(stream, "reconfigure"):
            try:
                stream.reconfigure(encoding="utf-8")
            except Exception:
                pass


def anchor_path(p: Path | None) -> Path | None:
    if p is None:
        return None
    if p.is_absolute():
        return p.resolve()
    return (THEME_ROOT / p).resolve()


def item_to_csv_row(item: Dict[str, Any]) -> Dict[str, str]:
    row: Dict[str, str] = {}
    for key in CSV_FIELDNAMES:
        if key not in item:
            row[key] = ""
            continue
        val = item[key]
        if key == "specialties" and isinstance(val, list):
            row[key] = "|".join(str(x).strip() for x in val if str(x).strip())
        elif val is None:
            row[key] = ""
        else:
            row[key] = str(val)
    return row


def record_to_csv_item(rid: str, rec: Dict[str, Any], data_dir: Path) -> Dict[str, Any]:
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
    if local_profile_webp_path(data_dir, rec) is not None:
        rel = (rec.get("image_relative_dir") or "").strip().replace("\\", "/").strip("/")
        if rel:
            item["bundle_featured_relative"] = f"images/{rel}/profile.webp"
    return item


def main() -> int:
    _configure_stdio_utf8()
    ap = argparse.ArgumentParser(description="checkpoint.json → lawyers.csv برای ایمپورت وردپرس")
    ap.add_argument(
        "--checkpoint",
        type=Path,
        default=THEME_ROOT / "run-20260429-135734" / "checkpoint.json",
    )
    ap.add_argument("--data-dir", type=Path, default=None, help="پوشهٔ حاوی images/ (پیش‌فرض: کنار checkpoint)")
    ap.add_argument("--out", type=Path, required=True, help="مسیر فایل CSV خروجی")
    ap.add_argument("--limit", type=int, default=0, help="حداکثر تعداد رکورد (۰=همه)")
    args = ap.parse_args()

    checkpoint = anchor_path(args.checkpoint)
    data_dir = anchor_path(args.data_dir) if args.data_dir else (checkpoint.parent if checkpoint else None)
    out_path = anchor_path(args.out)

    if not checkpoint or not checkpoint.is_file():
        sys.stderr.write(f"checkpoint یافت نشد: {checkpoint}\n")
        return 2
    if not data_dir or not data_dir.is_dir():
        sys.stderr.write(f"data-dir نامعتبر: {data_dir}\n")
        return 2
    if not out_path:
        sys.stderr.write("مسیر out نامعتبر است.\n")
        return 2

    if jdatetime is None:
        print("هشدار: jdatetime نصب نیست؛ تاریخ میلادی پروانه ممکن است خالی شود.", flush=True)

    print("[info] خواندن checkpoint...", flush=True)
    data = json.loads(checkpoint.read_text(encoding="utf-8-sig"))
    done_block = data.get("done") or {}
    if not isinstance(done_block, dict):
        sys.stderr.write("فرمت checkpoint نامعتبر است.\n")
        return 2

    keys = sorted(done_block.keys(), key=lambda k: int(k) if str(k).isdigit() else str(k))
    if args.limit and args.limit > 0:
        keys = keys[: args.limit]

    out_path.parent.mkdir(parents=True, exist_ok=True)
    n_out = 0
    skipped = 0
    with out_path.open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=CSV_FIELDNAMES, extrasaction="ignore", quoting=csv.QUOTE_MINIMAL)
        w.writeheader()
        for rid in keys:
            rec = done_block.get(rid)
            if not isinstance(rec, dict):
                continue
            if not (rec.get("full_name") or "").strip():
                skipped += 1
                continue
            item = record_to_csv_item(str(rid), rec, data_dir)
            w.writerow(item_to_csv_row(item))
            n_out += 1

    print(f"[ok] نوشته شد: {out_path} — {n_out} سطر (بدون عنوان حذف‌شده: {skipped})", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
