#!/usr/bin/env python3
"""
Import lawyers from bot scrape (checkpoint.json) into WordPress using:
  POST {WP_BASE}/wp-json/hovalvakil/v1/lawyers/batch

Why a local HTTP server: WordPress sideload uses download_url(URL). Local file paths
are not fetched; serving the scrape folder over http://127.0.0.1:PORT lets sideload work
when WordPress runs on the same machine (or can reach that host).

Setup:
  pip install -r tools/requirements.txt

Example (dry run, first 100):
  python tools/import_lawyers.py --wp-base https://yoursite.test --wp-user admin \\
    --wp-app-password xxxx --dry-run --limit 100

Overnight full import (resume enabled by default):
  python tools/import_lawyers.py --wp-base https://yoursite.test --wp-user admin \\
    --wp-app-password xxxx

State: tools/.import_state.json (completed external_ids)
Failures: tools/import_failed.jsonl (append-only; safe to inspect next morning)

Checkpoint parity:
  All top-level fields from the scrape (names, province/city, contact, license, dates,
  bar_association, profile_url, source, last_update, status, image_* metadata) are copied
  into post content, hvl_records, and/or matching post meta. Featured image URL is sent
  only after verifying ``images/<image_relative_dir>/profile.webp`` exists next to the
  checkpoint (same bytes the bot saved), so WordPress never sideloads a mismatched path.
"""

from __future__ import annotations

import argparse
import base64
import html
import json
import logging
import re
import sys
import time
import threading
import urllib.parse
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any, Dict, List, Tuple

import requests

try:
    import jdatetime
except ImportError:
    jdatetime = None  # type: ignore

STATE_PATH = Path(__file__).resolve().parent / ".import_state.json"
FAIL_LOG = Path(__file__).resolve().parent / "import_failed.jsonl"

BATCH_MAX = 50
MAX_BATCH_RETRIES = 8
MAX_ITEM_RETRIES = 5


def setup_logging(verbose: bool) -> None:
    level = logging.DEBUG if verbose else logging.INFO
    logging.basicConfig(
        level=level,
        format="%(asctime)s %(levelname)s %(message)s",
        datefmt="%H:%M:%S",
    )


def load_state() -> Dict[str, Any]:
    if STATE_PATH.exists():
        try:
            return json.loads(STATE_PATH.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            pass
    return {"completed_ids": []}


def save_state(state: Dict[str, Any]) -> None:
    tmp = STATE_PATH.with_suffix(".tmp")
    tmp.write_text(json.dumps(state, ensure_ascii=False), encoding="utf-8")
    tmp.replace(STATE_PATH)


def jalali_to_iso_date(raw: str) -> str:
    raw = (raw or "").strip()
    if not raw or not jdatetime:
        return ""
    raw = raw.replace("-", "/")
    parts = raw.split("/")
    if len(parts) != 3:
        return ""
    try:
        y, m, d = int(parts[0]), int(parts[1]), int(parts[2])
        jd = jdatetime.date(y, m, d)
        return jd.togregorian().strftime("%Y-%m-%d")
    except (ValueError, OSError, OverflowError):
        return ""


def public_lawyer_id(rec: Dict[str, Any], rid: str) -> str:
    return str(rec.get("public_lawyer_id") or rid).strip()


def external_id_for(rec: Dict[str, Any], rid: str) -> str:
    return f"hub23055-{public_lawyer_id(rec, rid)}"


def extract_mobiles_from_contact(contact: str) -> List[str]:
    if not contact:
        return []
    compact = re.sub(r"\s+", "", contact)
    return re.findall(r"09\d{9}", compact)


def phones_from_record(rec: Dict[str, Any]) -> Tuple[str, str, str]:
    """(hvl_mobile, hvl_office_mobile, hvl_office_phone) from checkpoint + contact line."""
    contact = (rec.get("contact") or "").strip()
    from_field_mobile = (rec.get("mobile") or "").strip()
    office_phone = (rec.get("office_phone") or "").strip()
    nines = extract_mobiles_from_contact(contact)
    mobile = from_field_mobile or (nines[0] if nines else "")
    office_mobile = nines[1] if len(nines) > 1 else ""
    if not office_phone and contact:
        for p in re.split(r"\||/", contact):
            p = p.strip()
            if not p:
                continue
            comp = re.sub(r"\s+", "", p)
            if re.match(r"^09\d{9}$", comp):
                continue
            office_phone = office_phone or p
    return mobile, office_mobile, office_phone


def image_public_url(image_host: str, rel_dir: str) -> str:
    if not rel_dir:
        return ""
    segments = rel_dir.strip("/").replace("\\", "/").split("/")
    enc = "/".join(urllib.parse.quote(seg, safe="") for seg in segments if seg)
    return f"{image_host.rstrip('/')}/images/{enc}/profile.webp"


def lawyer_featured_image_filename(rec: Dict[str, Any], pid: str) -> str:
    """Base name for uploaded media (no extension); extension is taken from the real file on WordPress."""
    name = (rec.get("full_name") or "").strip()
    if not name:
        return f"lawyer-{pid}"
    s = re.sub(r'[\s/\\:*?"<>|]+', "-", name)
    s = re.sub(r"-{2,}", "-", s).strip("-")
    if len(s) > 120:
        s = s[:120].rstrip("-")
    return s or f"lawyer-{pid}"


def verified_featured_image_url(data_dir: Path, rec: Dict[str, Any], image_host: str) -> str:
    """
    Only return URL if the same file the checkpoint names exists under data_dir/images/...
    (matches on-disk scrape; avoids WordPress sideloading a wrong/404 image).
    """
    if (rec.get("image_status") or "").strip().lower() != "ok":
        return ""
    rel = (rec.get("image_relative_dir") or "").strip().replace("\\", "/").strip("/")
    if not rel:
        return ""
    local = (data_dir / "images" / rel / "profile.webp").resolve()
    try:
        if not local.is_file():
            return ""
    except OSError:
        return ""
    return image_public_url(image_host, rel)


def record_to_payload(
    rid: str,
    rec: Dict[str, Any],
    data_dir: Path,
    image_host: str,
    sideload: bool,
) -> Dict[str, Any]:
    pid = public_lawyer_id(rec, rid)
    title = (rec.get("full_name") or "").strip()
    ext_id = external_id_for(rec, rid)
    slug = f"lawyer-{pid}"

    issue_raw = (rec.get("issue_date") or "").strip()
    valid_raw = (rec.get("validity_date") or "").strip()
    lic_issued = jalali_to_iso_date(issue_raw) if issue_raw else ""
    lic_exp = jalali_to_iso_date(valid_raw) if valid_raw else ""

    mobile, office_mobile, office_phone = phones_from_record(rec)
    contact = (rec.get("contact") or "").strip()

    city = (rec.get("city") or "").strip()
    province = (rec.get("province") or "").strip()
    province_city = (rec.get("province_city") or "").strip()
    grade = (rec.get("lawyer_level") or rec.get("grade") or "").strip()
    grade_raw = (rec.get("lawyer_level_raw") or "").strip()
    spec = (rec.get("specialty") or "").strip()
    if spec and grade and spec != grade:
        services_line = f"{spec} — {grade}"
    elif spec:
        services_line = spec
    else:
        services_line = grade

    records_parts: List[str] = []
    if rec.get("bar_association"):
        records_parts.append(str(rec["bar_association"]).strip())
    if province_city:
        records_parts.append("استان / شهر (متن منبع): " + province_city)
    if contact:
        records_parts.append("رشتهٔ تماس (منبع): " + contact)
    if rec.get("profile_url"):
        records_parts.append("لینک پروفایل منبع: " + str(rec["profile_url"]).strip())
    if rec.get("source"):
        records_parts.append("source: " + str(rec["source"]).strip())
    if rec.get("last_update"):
        records_parts.append("last_update (منبع): " + str(rec["last_update"]).strip())
    records_parts.append("شناسهٔ public_lawyer_id (منبع): " + pid)
    if grade_raw and grade_raw != grade:
        records_parts.append("lawyer_level_raw (منبع): " + grade_raw)
    hub_status = (rec.get("status") or "").strip()
    if hub_status:
        records_parts.append("وضعیت در منبع (فیلد status): " + hub_status)
    if issue_raw:
        records_parts.append("تاریخ صدور پروانه (شمسی، منبع): " + issue_raw)
    if valid_raw:
        records_parts.append("تاریخ اعتبار پروانه (شمسی، منبع): " + valid_raw)
    if lic_issued:
        records_parts.append("تاریخ صدور (میلادی ذخیره‌شده در متای سایت): " + lic_issued)
    if lic_exp:
        records_parts.append("تاریخ اعتبار (میلادی ذخیره‌شده در متای سایت): " + lic_exp)
    img_st = (rec.get("image_status") or "").strip()
    records_parts.append("وضعیت تصویر در scrape: " + (img_st or "—"))
    if rec.get("image_error"):
        records_parts.append("image_error (فیلد خام scrape): " + str(rec["image_error"]).strip())
    if img_st == "ok" and rec.get("image_sha256"):
        extra = []
        if rec.get("image_width") and rec.get("image_height"):
            extra.append(f"{rec['image_width']}×{rec['image_height']} px")
        if rec.get("image_bytes"):
            extra.append(f"{rec['image_bytes']} bytes")
        tail = ("؛ " + "، ".join(extra)) if extra else ""
        records_parts.append("SHA256 فایل profile.webp در scrape: " + str(rec["image_sha256"]) + tail)
    if img_st == "ok" and rec.get("image_folder"):
        records_parts.append("مسیر پوشهٔ تصویر (نسبت به images/): " + str(rec["image_folder"]).strip())

    img_url = verified_featured_image_url(data_dir, rec, image_host)

    def he(s: Any) -> str:
        return html.escape((str(s) if s is not None else "").strip())

    content_lines = [
        "<dl class=\"hvl-import-source\">",
        f"<dt>استان</dt><dd>{he(province) or '—'}</dd>",
        f"<dt>شهر</dt><dd>{he(city) or '—'}</dd>",
        f"<dt>استان / شهر (متن منبع)</dt><dd>{he(province_city) or '—'}</dd>",
        f"<dt>آدرس دفتر</dt><dd>{he(rec.get('office_address')) or '—'}</dd>",
        f"<dt>تماس (متن خام منبع)</dt><dd>{he(contact) or '—'}</dd>",
        f"<dt>موبایل (استخراج/فیلد)</dt><dd>{he(mobile) or '—'}</dd>",
        f"<dt>موبایل دفتر دوم (در صورت وجود در خط تماس)</dt><dd>{he(office_mobile) or '—'}</dd>",
        f"<dt>تلفن دفتر</dt><dd>{he(office_phone) or '—'}</dd>",
        f"<dt>پایه / تخصص در منبع</dt><dd>{he(spec)} / {he(grade)}</dd>",
        f"<dt>کانون (منبع)</dt><dd>{he(rec.get('bar_association')) or '—'}</dd>",
        f"<dt>لینک منبع</dt><dd><a href=\"{he(rec.get('profile_url'))}\" rel=\"nofollow noopener\">{he(rec.get('profile_url'))}</a></dd>" if rec.get("profile_url") else "<dt>لینک منبع</dt><dd>—</dd>",
        "</dl>",
    ]

    item: Dict[str, Any] = {
        "external_id": ext_id,
        "title": title,
        "slug": slug,
        "content": "\n".join(content_lines),
        "excerpt": ((rec.get("office_address") or "").strip() + " | " + contact)[:400],
        "status": "publish",
        "city": city,
        "province": province,
        "specialties": [],
        "featured_image_url": img_url,
    }
    if img_url:
        item["featured_image_filename"] = lawyer_featured_image_filename(rec, pid)

    if rec.get("license_no"):
        item["hvl_license_no"] = str(rec["license_no"]).strip()
    if lic_issued:
        item["hvl_license_issued"] = lic_issued
    if lic_exp:
        item["hvl_license_expires"] = lic_exp
    lic_bits = []
    if rec.get("license_no"):
        lic_bits.append("شماره پروانه: " + str(rec["license_no"]).strip())
    if issue_raw:
        lic_bits.append("صدور (شمسی، منبع): " + issue_raw)
    if valid_raw:
        lic_bits.append("اعتبار (شمسی، منبع): " + valid_raw)
    if lic_issued or lic_exp:
        lic_bits.append("ذخیرهٔ میلادی در سایت: " + " / ".join(x for x in [lic_issued or "—", lic_exp or "—"] if x))
    if lic_bits:
        item["hvl_license"] = " | ".join(lic_bits)
    if grade:
        item["hvl_lawyer_grade"] = grade
    if mobile:
        item["hvl_mobile"] = mobile
    if office_mobile:
        item["hvl_office_mobile"] = office_mobile
    if office_phone:
        item["hvl_office_phone"] = office_phone
    addr = (rec.get("office_address") or "").strip()
    if addr:
        item["hvl_office_address"] = addr
    if services_line:
        item["hvl_services"] = services_line
    if records_parts:
        item["hvl_records"] = "\n".join(records_parts)

    return item


def post_batch(
    session: requests.Session,
    wp_base: str,
    items: List[Dict[str, Any]],
    dry_run: bool,
    sideload: bool,
    timeout: int,
) -> requests.Response:
    url = wp_base.rstrip("/") + "/wp-json/hovalvakil/v1/lawyers/batch"
    body: Dict[str, Any] = {
        "dry_run": dry_run,
        "sideload_featured_image": bool(sideload),
        "items": items,
    }
    return session.post(url, json=body, timeout=timeout)


def parse_batch_response(resp: requests.Response) -> Dict[str, Any]:
    try:
        return resp.json()
    except ValueError:
        return {"ok": False, "message": (resp.text or "")[:500]}


def verify_lawyers_batch_route(session: requests.Session, wp_base: str, timeout: int) -> Tuple[bool, str]:
    """
    Ensure production WordPress has the batch import route (theme file
    includes/hovalvakil-lawyer-rest-import.php must be deployed and active).
    """
    index_url = wp_base.rstrip("/") + "/wp-json/"
    try:
        r = session.get(index_url, timeout=timeout)
    except requests.RequestException as exc:
        return False, f"به {index_url} وصل نشد: {exc}"
    if r.status_code != 200:
        return False, f"GET wp-json/ کد {r.status_code} برگرداند."
    try:
        data = r.json()
    except ValueError:
        return False, "پاسخ wp-json/ JSON نیست."
    routes = data.get("routes") or {}
    batch_key = "/hovalvakil/v1/lawyers/batch"
    if batch_key in routes:
        methods = routes[batch_key].get("methods") or []
        if isinstance(methods, list) and "POST" in methods:
            return True, ""
        return False, f"مسیر {batch_key} هست اما POST در methods نیست: {methods!r}"
    for key in routes:
        if isinstance(key, str) and "lawyers/batch" in key:
            return True, ""
    return (
        False,
        "مسیر POST /wp-json/hovalvakil/v1/lawyers/batch روی این سایت ثبت نیست (rest_no_route). "
        "تم فعال روی هاست باید همان نسخه‌ای باشد که فایل includes/hovalvakil-lawyer-rest-import.php را دارد؛ "
        "قالب را آپلود/به‌روز کنید و کش را خالی کنید، بعد دوباره اسکریپت را اجرا کنید.",
    )


def run_http_server(directory: Path, host: str, port: int) -> ThreadingHTTPServer:
    handler = partial(SimpleHTTPRequestHandler, directory=str(directory))
    httpd = ThreadingHTTPServer((host, port), handler)
    thread = threading.Thread(target=httpd.serve_forever, daemon=True)
    thread.start()
    return httpd


def sleep_backoff(attempt: int) -> None:
    time.sleep(min(90.0, 1.8**attempt))


def log_failed(rid: str, rec: Dict[str, Any], reason: str, detail: Any) -> None:
    line = {
        "external_id": external_id_for(rec, rid),
        "reason": reason,
        "detail": detail,
        "title": rec.get("full_name"),
    }
    try:
        with FAIL_LOG.open("a", encoding="utf-8") as fh:
            fh.write(json.dumps(line, ensure_ascii=False) + "\n")
    except OSError:
        pass


def retry_items_one_by_one(
    session: requests.Session,
    wp_base: str,
    pairs: List[Tuple[str, Dict[str, Any]]],
    data_dir: Path,
    image_host: str,
    sideload: bool,
    dry_run: bool,
    timeout: int,
    done_ids: set,
    state: Dict[str, Any],
    log: logging.Logger,
) -> None:
    for rid, rec in pairs:
        ext_id = external_id_for(rec, rid)
        if ext_id in done_ids:
            continue
        payload = record_to_payload(rid, rec, data_dir, image_host, sideload)
        ok_final = False
        for t in range(MAX_ITEM_RETRIES):
            if t:
                sleep_backoff(t)
            try:
                resp = post_batch(session, wp_base, [payload], dry_run, sideload, timeout)
            except requests.RequestException as exc:
                log.warning("Single-item transport %s attempt %s: %s", rid, t + 1, exc)
                continue
            parsed = parse_batch_response(resp)
            if resp.status_code in (401,):
                log.error("401 Unauthorized — stopping.")
                raise SystemExit(1)
            if resp.status_code >= 500 or resp.status_code == 429:
                log.warning("Single-item HTTP %s for %s", resp.status_code, rid)
                continue
            if not resp.ok or not parsed.get("ok"):
                log.warning("Single-item rejected %s: %s", rid, parsed)
                break
            has_row = bool(parsed.get("created")) or bool(parsed.get("updated"))
            if dry_run:
                ok_final = bool(parsed.get("created") or parsed.get("updated"))
            else:
                ok_final = has_row
            if ok_final:
                break
        if ok_final:
            done_ids.add(ext_id)
            if not dry_run:
                state["completed_ids"] = sorted(done_ids)
                save_state(state)
            log.info("Recovered single import id=%s", rid)
        else:
            log_failed(rid, rec, "single_item_exhausted", {})
            log.error("Skipped after retries: %s (%s)", rid, rec.get("full_name"))


def main() -> int:
    ap = argparse.ArgumentParser(description="Import lawyers from checkpoint.json via WordPress REST batch API.")
    ap.add_argument(
        "--checkpoint",
        type=Path,
        default=None,
        help="checkpoint.json path (default: theme/run-20260429-135734/checkpoint.json)",
    )
    ap.add_argument(
        "--data-dir",
        type=Path,
        default=None,
        help="Folder served as web root for /images/... (default: parent of checkpoint)",
    )
    ap.add_argument("--wp-base", required=True, help="Site URL, e.g. https://example.com")
    ap.add_argument("--wp-user", required=True)
    ap.add_argument("--wp-app-password", required=True)
    ap.add_argument("--image-host", default="", help="Public base URL for images (default: http://127.0.0.1:IMAGE_PORT)")
    ap.add_argument("--image-bind", default="127.0.0.1")
    ap.add_argument("--image-port", type=int, default=18779)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument(
        "--no-sideload",
        action="store_true",
        help="Only save image URL into meta (no media library download on WordPress)",
    )
    ap.add_argument("--limit", type=int, default=0, help="Stop after N newly processed lawyers (0=all)")
    ap.add_argument("--no-resume", action="store_true", help="Do not skip ids in tools/.import_state.json")
    ap.add_argument("--batch-sleep", type=float, default=0.2, help="Pause between successful batches (seconds)")
    ap.add_argument("--timeout", type=int, default=180)
    ap.add_argument("--verbose", action="store_true")
    ap.add_argument(
        "--skip-route-check",
        action="store_true",
        help="Do not GET wp-json/ before import (only if index is blocked but batch POST works).",
    )
    args = ap.parse_args()

    setup_logging(args.verbose)
    log = logging.getLogger("import")

    theme_root = Path(__file__).resolve().parents[1]
    checkpoint = args.checkpoint or (theme_root / "run-20260429-135734" / "checkpoint.json")
    data_dir = args.data_dir or checkpoint.parent
    if not checkpoint.is_file():
        log.error("Checkpoint not found: %s", checkpoint)
        return 2

    if jdatetime is None:
        log.warning("Package jdatetime missing — install for Jalali→Gregorian license dates: pip install jdatetime")

    state: Dict[str, Any] = {} if args.no_resume else load_state()
    done_ids: set = set(state.get("completed_ids", []))

    image_host = args.image_host.strip() or f"http://{args.image_bind}:{args.image_port}"
    sideload = not args.no_sideload

    log.info("Local file server root=%s public=%s", data_dir, image_host)
    httpd = run_http_server(data_dir, args.image_bind, args.image_port)
    time.sleep(0.25)

    session = requests.Session()
    auth = base64.b64encode(f"{args.wp_user}:{args.wp_app_password}".encode()).decode("ascii")
    session.headers["Authorization"] = f"Basic {auth}"
    session.headers["Accept"] = "application/json"

    if not args.skip_route_check:
        ok_route, route_msg = verify_lawyers_batch_route(session, args.wp_base, args.timeout)
        if not ok_route:
            log.error("%s", route_msg)
            raise SystemExit(2)
        log.info("REST batch route OK on server.")

    log.info("Loading checkpoint JSON (large file, may take memory/time)...")
    data = json.loads(checkpoint.read_text(encoding="utf-8"))
    done_block: Dict[str, Any] = data.get("done") or {}
    keys = list(done_block.keys())
    log.info("done records: %s | failed bucket keys: %s", len(keys), len(data.get("failed") or {}))

    new_processed = 0

    def flush_batch(buf: List[Tuple[str, Dict[str, Any]]]) -> None:
        nonlocal new_processed
        if not buf:
            return
        payloads = []
        valid_pairs: List[Tuple[str, Dict[str, Any]]] = []
        for rid, rec in buf:
            title = (rec.get("full_name") or "").strip()
            if not title:
                log_failed(rid, rec, "empty_title", {})
                continue
            payloads.append(record_to_payload(rid, rec, data_dir, image_host, sideload))
            valid_pairs.append((rid, rec))
        if not valid_pairs:
            return

        retry_singles: List[Tuple[str, Dict[str, Any]]] = []
        batch_ok = False
        for attempt in range(MAX_BATCH_RETRIES):
            try:
                resp = post_batch(session, args.wp_base, payloads, args.dry_run, sideload, args.timeout)
            except requests.RequestException as exc:
                log.warning("Batch transport error (attempt %s/%s): %s", attempt + 1, MAX_BATCH_RETRIES, exc)
                sleep_backoff(attempt)
                continue

            parsed = parse_batch_response(resp)
            if resp.status_code == 401:
                log.error("401 Unauthorized — check --wp-user and --wp-app-password (Application Passwords).")
                raise SystemExit(1)

            if resp.status_code == 404 and parsed.get("code") == "rest_no_route":
                log.error(
                    "404 rest_no_route برای lawyers/batch — تم فعال روی سرور این endpoint را ندارد. "
                    "تم را با فایل includes/hovalvakil-lawyer-rest-import.php به‌روز و دیپلوی کنید."
                )
                raise SystemExit(2)

            if resp.status_code == 400:
                log.error("400 from server: %s — fix payload or server limits.", parsed)
                for rid, rec in valid_pairs:
                    log_failed(rid, rec, "http_400", parsed)
                batch_ok = True
                break

            if resp.status_code >= 500 or resp.status_code == 429:
                log.warning("Batch HTTP %s (attempt %s)", resp.status_code, attempt + 1)
                sleep_backoff(attempt)
                continue

            if not resp.ok or not parsed.get("ok"):
                log.warning("Batch not ok: status=%s body=%s", resp.status_code, parsed)
                sleep_backoff(attempt)
                continue

            batch_ok = True
            created = {x["index"]: x for x in (parsed.get("created") or []) if isinstance(x, dict) and "index" in x}
            updated = {x["index"]: x for x in (parsed.get("updated") or []) if isinstance(x, dict) and "index" in x}
            errs = [e for e in (parsed.get("errors") or []) if isinstance(e, dict) and "index" in e]
            err_by_i = {int(e["index"]): e for e in errs}

            for i, (rid, rec) in enumerate(valid_pairs):
                ext_id = external_id_for(rec, rid)
                row_ok = i in created or i in updated
                if row_ok:
                    done_ids.add(ext_id)
                    if i in err_by_i:
                        log.warning("Imported %s with notice: %s", rid, err_by_i[i].get("message"))
                else:
                    retry_singles.append((rid, rec))

            if not args.dry_run:
                state["completed_ids"] = sorted(done_ids)
                save_state(state)

            new_processed += len(valid_pairs)
            break

        if not batch_ok:
            log.error("Batch failed after retries — sending %s rows to single-item retry.", len(valid_pairs))
            retry_items_one_by_one(
                session,
                args.wp_base,
                valid_pairs,
                data_dir,
                image_host,
                sideload,
                args.dry_run,
                args.timeout,
                done_ids,
                state,
                log,
            )
            new_processed += len(valid_pairs)
            return

        if retry_singles:
            log.info("Batch left %s rows without create/update — single-item retry.", len(retry_singles))
            retry_items_one_by_one(
                session,
                args.wp_base,
                retry_singles,
                data_dir,
                image_host,
                sideload,
                args.dry_run,
                args.timeout,
                done_ids,
                state,
                log,
            )

    buf: List[Tuple[str, Dict[str, Any]]] = []
    for rid in keys:
        rec = done_block[rid]
        ext_id = external_id_for(rec, rid)
        if ext_id in done_ids:
            continue
        buf.append((rid, rec))
        if len(buf) >= BATCH_MAX:
            flush_batch(buf)
            buf = []
            if args.limit and new_processed >= args.limit:
                break
            time.sleep(args.batch_sleep)

    if buf and (not args.limit or new_processed < args.limit):
        flush_batch(buf)

    httpd.shutdown()
    log.info("Stopped local image server.")
    log.info("This run processed (new batches): %s | state=%s | failures=%s", new_processed, STATE_PATH, FAIL_LOG)
    return 0


if __name__ == "__main__":
    sys.exit(main())
