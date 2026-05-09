#!/usr/bin/env python3
"""
Split missing-lawyer report into:
1) rows that have a matching local image folder archive
2) rows that are missing in archive

Designed for folder naming pattern like:
  <full_name>__<license_no>__<public_lawyer_id>/profile.webp

Example:
  run-20260429-135734/images/وکیل_پایه_دو/آتنا_خیاط_اسلامی__22427__3552/profile.webp
"""

from __future__ import annotations

import argparse
import csv
import re
import shutil
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional, Tuple


PUBLIC_ID_RE = re.compile(r"hvl-hub-(\d+)", re.IGNORECASE)


@dataclass
class ArchiveHit:
    public_id: str
    folder_rel: str
    profile_rel: str


def extract_public_id(view_url: str) -> str:
    if not view_url:
        return ""
    m = PUBLIC_ID_RE.search(view_url)
    return m.group(1) if m else ""


def scan_archive(images_root: Path) -> Dict[str, ArchiveHit]:
    hits: Dict[str, ArchiveHit] = {}
    for profile_file in images_root.rglob("profile.webp"):
        folder = profile_file.parent
        parts = folder.name.split("__")
        if len(parts) < 3:
            continue
        public_id = parts[-1].strip()
        if not public_id.isdigit():
            continue
        folder_rel = folder.relative_to(images_root).as_posix()
        profile_rel = profile_file.relative_to(images_root).as_posix()
        hits[public_id] = ArchiveHit(public_id=public_id, folder_rel=folder_rel, profile_rel=profile_rel)
    return hits


def read_report_rows(report_csv: Path) -> List[dict]:
    with report_csv.open("r", encoding="utf-8-sig", newline="") as f:
        return list(csv.DictReader(f))


def write_csv(path: Path, rows: List[dict], fieldnames: List[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as f:
        w = csv.DictWriter(f, fieldnames=fieldnames)
        w.writeheader()
        for r in rows:
            w.writerow(r)


def copy_staging_folders(
    images_root: Path,
    matched_rows: List[dict],
    staging_images_root: Path,
) -> Tuple[int, int]:
    copied = 0
    skipped = 0
    for r in matched_rows:
        rel = (r.get("archive_folder_relative") or "").strip()
        if not rel:
            skipped += 1
            continue
        src = images_root / rel
        dst = staging_images_root / rel
        if not src.exists():
            skipped += 1
            continue
        dst.parent.mkdir(parents=True, exist_ok=True)
        if dst.exists():
            shutil.rmtree(dst)
        shutil.copytree(src, dst)
        copied += 1
    return copied, skipped


def main() -> None:
    p = argparse.ArgumentParser(description="Split report by available archive folders.")
    p.add_argument("--report-csv", required=True, help="Path to hvl-home-image-report-*.csv")
    p.add_argument("--images-root", required=True, help="Path to run-*/images directory")
    p.add_argument(
        "--output-dir",
        default="run-output-image-split",
        help="Output directory for generated files",
    )
    p.add_argument(
        "--build-staging",
        action="store_true",
        help="Also copy matched folders to output/images for quick FTP upload",
    )
    args = p.parse_args()

    report_csv = Path(args.report_csv).resolve()
    images_root = Path(args.images_root).resolve()
    output_dir = Path(args.output_dir).resolve()

    if not report_csv.exists():
        raise SystemExit(f"[ERROR] report csv not found: {report_csv}")
    if not images_root.exists():
        raise SystemExit(f"[ERROR] images root not found: {images_root}")

    archive_map = scan_archive(images_root)
    report_rows = read_report_rows(report_csv)

    matched: List[dict] = []
    missing: List[dict] = []

    for row in report_rows:
        public_id = extract_public_id((row.get("view_url") or "").strip())
        out = dict(row)
        out["public_lawyer_id"] = public_id

        if public_id and public_id in archive_map:
            hit = archive_map[public_id]
            out["archive_folder_relative"] = hit.folder_rel
            out["archive_profile_relative"] = hit.profile_rel
            out["target_server_profile_url"] = (
                "https://hovalvakil.org/wp-content/themes/hello-elementor/dist/images/" + hit.profile_rel
            )
            matched.append(out)
        else:
            out["archive_folder_relative"] = ""
            out["archive_profile_relative"] = ""
            out["target_server_profile_url"] = ""
            missing.append(out)

    base_fields = list(report_rows[0].keys()) if report_rows else [
        "id",
        "title",
        "reason",
        "image_url",
        "status",
        "edit_url",
        "view_url",
    ]
    extra_fields = ["public_lawyer_id", "archive_folder_relative", "archive_profile_relative", "target_server_profile_url"]
    fields = base_fields + [f for f in extra_fields if f not in base_fields]

    matched_csv = output_dir / "matched-in-archive.csv"
    missing_csv = output_dir / "missing-in-archive.csv"
    write_csv(matched_csv, matched, fields)
    write_csv(missing_csv, missing, fields)

    upload_list = output_dir / "ftp-upload-folders.txt"
    upload_list.parent.mkdir(parents=True, exist_ok=True)
    unique_folders = sorted({r.get("archive_folder_relative", "").strip() for r in matched if r.get("archive_folder_relative", "").strip()})
    with upload_list.open("w", encoding="utf-8") as f:
        for rel in unique_folders:
            f.write(rel + "\n")

    print(f"[OK] total report rows: {len(report_rows)}")
    print(f"[OK] matched in archive: {len(matched)}")
    print(f"[OK] missing in archive: {len(missing)}")
    print(f"[OUT] {matched_csv}")
    print(f"[OUT] {missing_csv}")
    print(f"[OUT] {upload_list}")

    if args.build_staging:
        staging_root = output_dir / "images"
        copied, skipped = copy_staging_folders(images_root, matched, staging_root)
        print(f"[STAGING] copied folders: {copied}")
        print(f"[STAGING] skipped folders: {skipped}")
        print(f"[OUT] {staging_root}")


if __name__ == "__main__":
    main()

