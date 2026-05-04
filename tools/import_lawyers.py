#!/usr/bin/env python3
"""
Import lawyers from bot scrape (checkpoint.json) into WordPress using:
  POST {WP_BASE}/wp-json/hovalvakil/v1/lawyers/batch

Images (default ``--image-mode rest_media``):
  Each ``profile.webp`` is uploaded from your PC with ``POST /wp-json/wp/v2/media``, then the
  batch item sends ``featured_attachment_id`` so the **remote** server never has to fetch
  ``http://127.0.0.1/...`` (that always failed with «نشانی معتبر نیست»).

Optional ``--image-mode sideload_url``: starts a local HTTP server so WordPress can
  ``download_url`` only if the server can reach that URL (same machine / tunnel).

Setup:
  pip install -r tools/requirements.txt

یک‌بار فایل ``tools/import.config.json`` را از روی نمونه بسازید (در گیت نیست)؛ بعد فقط:

  python tools/import_lawyers.py

یا در ویندوز: ``tools\\run_import.cmd``

اولویت تنظیمات: آرگومان‌های خط فرمان > متغیرهای محیطی ``HVL_WP_*`` > ``import.config.json``.

Example (بدون فایل تنظیم):
  python tools/import_lawyers.py --wp-base https://example.com --wp-user USER \\
    --wp-app-password xxxx --log-file tools/import_run.log --limit 5

State: tools/.import_state.json | Failures: tools/import_failed.jsonl
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import logging
import re
import sys
import time
import threading
import urllib.parse
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any, Callable, Dict, List, Tuple

import requests

try:
    import jdatetime
except ImportError:
    jdatetime = None  # type: ignore

STATE_PATH = Path(__file__).resolve().parent / ".import_state.json"
FAIL_LOG = Path(__file__).resolve().parent / "import_failed.jsonl"
THEME_ROOT = Path(__file__).resolve().parents[1]
_CONFIG_FILENAMES = ("import.config.json", ".import.config.json")


def _argv_has_flag(flag: str) -> bool:
    """True if user passed e.g. --batch-sleep or --batch-sleep=2 on the command line."""
    prefix = flag + "="
    for a in sys.argv:
        if a == flag or a.startswith(prefix):
            return True
    return False


BATCH_MAX = 50
MAX_BATCH_RETRIES = 8
MAX_ITEM_RETRIES = 5


def load_import_config_json() -> Dict[str, Any]:
    """Local credentials/settings (not committed). First existing file wins."""
    for name in _CONFIG_FILENAMES:
        p = THEME_ROOT / "tools" / name
        if p.is_file():
            try:
                raw = json.loads(p.read_text(encoding="utf-8"))
                return raw if isinstance(raw, dict) else {}
            except json.JSONDecodeError:
                return {}
    return {}


def resolve_theme_relative(path_val: str | Path | None) -> Path | None:
    if path_val is None or path_val == "":
        return None
    p = Path(path_val)
    if p.is_absolute():
        return p.resolve()
    return (THEME_ROOT / p).resolve()


def apply_config_env_defaults(args: argparse.Namespace, file_cfg: Dict[str, Any]) -> None:
    """
    Fill wp_base / wp_user / wp_app_password and optional paths from env + file if CLI left them empty.
    Priority: explicit CLI > HVL_WP_* env > import.config.json.
    """
    # wp_base
    if not getattr(args, "wp_base", None) or not str(args.wp_base).strip():
        v = (os.environ.get("HVL_WP_BASE") or "").strip() or str(file_cfg.get("wp_base") or "").strip()
        if v:
            args.wp_base = v
    # wp_user
    if not getattr(args, "wp_user", None) or not str(args.wp_user).strip():
        v = (os.environ.get("HVL_WP_USER") or "").strip() or str(file_cfg.get("wp_user") or "").strip()
        if v:
            args.wp_user = v
    # app password (spaces OK in JSON)
    if not getattr(args, "wp_app_password", None) or not str(args.wp_app_password).strip():
        v = (os.environ.get("HVL_WP_APP_PASSWORD") or "").strip() or str(file_cfg.get("wp_app_password") or "").strip()
        if v:
            args.wp_app_password = v
    # checkpoint / data_dir only if not passed on CLI
    if getattr(args, "checkpoint", None) is None and file_cfg.get("checkpoint"):
        args.checkpoint = resolve_theme_relative(str(file_cfg["checkpoint"]))
    if getattr(args, "data_dir", None) is None and file_cfg.get("data_dir"):
        args.data_dir = resolve_theme_relative(str(file_cfg["data_dir"]))
    # optional numeric / string overrides from file if not in argv (simple: only if key present)
    if file_cfg.get("batch_sleep") is not None and not _argv_has_flag("--batch-sleep"):
        try:
            args.batch_sleep = float(file_cfg["batch_sleep"])
        except (TypeError, ValueError):
            pass
    if file_cfg.get("limit") is not None and not _argv_has_flag("--limit"):
        try:
            args.limit = int(file_cfg["limit"])
        except (TypeError, ValueError):
            pass
    if file_cfg.get("timeout") is not None and not _argv_has_flag("--timeout"):
        try:
            args.timeout = int(file_cfg["timeout"])
        except (TypeError, ValueError):
            pass
    if file_cfg.get("image_mode") and not _argv_has_flag("--image-mode"):
        im = str(file_cfg["image_mode"]).strip()
        if im in ("rest_media", "sideload_url", "none"):
            args.image_mode = im  # type: ignore[assignment]
    if file_cfg.get("image_host") and not _argv_has_flag("--image-host"):
        args.image_host = str(file_cfg["image_host"]).strip()
    if file_cfg.get("log_file") and not _argv_has_flag("--log-file"):
        lp = resolve_theme_relative(str(file_cfg["log_file"]))
        args.log_file = lp if lp is not None else Path(str(file_cfg["log_file"]))


def setup_logging(verbose: bool, log_file: Path | None) -> None:
    level = logging.DEBUG if verbose else logging.INFO
    fmt = "%(asctime)s %(levelname)s [import] %(message)s"
    handlers: List[logging.Handler] = [logging.StreamHandler(sys.stdout)]
    if log_file is not None:
        log_file.parent.mkdir(parents=True, exist_ok=True)
        handlers.append(logging.FileHandler(log_file, encoding="utf-8"))
    logging.basicConfig(level=level, format=fmt, datefmt="%H:%M:%S", handlers=handlers, force=True)


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


def local_profile_webp_path(data_dir: Path, rec: Dict[str, Any]) -> Path | None:
    """Return path to profile.webp on disk if scrape says image ok and file exists."""
    if (rec.get("image_status") or "").strip().lower() != "ok":
        return None
    rel = (rec.get("image_relative_dir") or "").strip().replace("\\", "/").strip("/")
    if not rel:
        return None
    local = (data_dir / "images" / rel / "profile.webp").resolve()
    try:
        return local if local.is_file() else None
    except OSError:
        return None


def verified_featured_image_url(data_dir: Path, rec: Dict[str, Any], image_host: str) -> str:
    """HTTP URL for local static server (sideload_url mode only when server can reach this host)."""
    if local_profile_webp_path(data_dir, rec) is None:
        return ""
    rel = (rec.get("image_relative_dir") or "").strip().replace("\\", "/").strip("/")
    return image_public_url(image_host, rel) if rel else ""


def upload_media_rest_with_retries(
    session: requests.Session,
    wp_base: str,
    file_path: Path,
    upload_filename: str,
    timeout: int,
    log: logging.Logger,
) -> int | None:
    """POST file to wp/v2/media; return attachment id or None."""
    media_url = wp_base.rstrip("/") + "/wp-json/wp/v2/media"
    for attempt in range(5):
        try:
            with open(file_path, "rb") as handle:
                files = {"file": (upload_filename, handle, "image/webp")}
                resp = session.post(media_url, files=files, timeout=timeout)
        except OSError as exc:
            log.error("[STEP] خواندن فایل تصویر ناموفق: %s — %s", file_path, exc)
            return None
        except requests.RequestException as exc:
            log.warning("[STEP] خطای شبکه در wp/v2/media (تلاش %s/5): %s", attempt + 1, exc)
            sleep_backoff(attempt)
            continue
        if resp.status_code in (429, 500, 502, 503, 504):
            log.warning("[STEP] wp/v2/media کد HTTP %s (تلاش %s/5)", resp.status_code, attempt + 1)
            sleep_backoff(attempt)
            continue
        if not resp.ok:
            log.warning("[STEP] wp/v2/media رد شد: %s — %s", resp.status_code, (resp.text or "")[:500])
            return None
        try:
            data = resp.json()
        except ValueError:
            log.warning("[STEP] wp/v2/media پاسخ JSON نبود")
            return None
        mid = int(data.get("id") or 0)
        if mid > 0:
            return mid
        log.warning("[STEP] wp/v2/media بدون id در پاسخ: %s", data)
        return None
    return None


def record_to_payload(
    rid: str,
    rec: Dict[str, Any],
    data_dir: Path,
    image_host: str,
    use_sideload_url: bool,
    featured_attachment_id: int | None = None,
    include_remote_url_for_meta: bool = False,
) -> Dict[str, Any]:
    pid = public_lawyer_id(rec, rid)
    title = (rec.get("full_name") or "").strip()
    ext_id = external_id_for(rec, rid)

    issue_raw = (rec.get("issue_date") or "").strip()
    valid_raw = (rec.get("validity_date") or "").strip()
    lic_issued = jalali_to_iso_date(issue_raw) if issue_raw else ""
    lic_exp = jalali_to_iso_date(valid_raw) if valid_raw else ""

    mobile, office_mobile, office_phone = phones_from_record(rec)
    if office_mobile and re.sub(r"\s+", "", office_mobile) == re.sub(r"\s+", "", mobile or ""):
        office_mobile = ""

    city = (rec.get("city") or "").strip()
    province = (rec.get("province") or "").strip()
    grade = (rec.get("lawyer_level") or rec.get("grade") or "").strip()

    verified_url = ""
    if use_sideload_url or include_remote_url_for_meta:
        verified_url = verified_featured_image_url(data_dir, rec, image_host)

    item: Dict[str, Any] = {
        "external_id": ext_id,
        "title": title,
        "content": "",
        "excerpt": "",
        "status": "publish",
        "city": city,
        "province": province,
        "specialties": [],
    }
    if featured_attachment_id and featured_attachment_id > 0:
        item["featured_attachment_id"] = int(featured_attachment_id)
    elif use_sideload_url and verified_url:
        item["featured_image_url"] = verified_url
        item["featured_image_filename"] = lawyer_featured_image_filename(rec, pid)
    elif include_remote_url_for_meta and verified_url:
        item["featured_image_url"] = verified_url

    if rec.get("license_no"):
        item["hvl_license_no"] = str(rec["license_no"]).strip()
    if lic_issued:
        item["hvl_license_issued"] = lic_issued
    if lic_exp:
        item["hvl_license_expires"] = lic_exp
    if issue_raw:
        item["hvl_license_issued_jalali"] = issue_raw
    if valid_raw:
        item["hvl_license_expires_jalali"] = valid_raw
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

    return item


def _build_item_payload(
    rid: str,
    rec: Dict[str, Any],
    data_dir: Path,
    image_host: str,
    *,
    image_mode: str,
    dry_run: bool,
    no_sideload: bool,
    session: requests.Session,
    wp_base: str,
    timeout: int,
    log: logging.Logger,
) -> Dict[str, Any]:
    pid = public_lawyer_id(rec, rid)
    att: int | None = None
    if image_mode == "rest_media" and not dry_run:
        lp = local_profile_webp_path(data_dir, rec)
        if lp is not None:
            fname = lawyer_featured_image_filename(rec, pid) + ".webp"
            log.info("[STEP] آپلود تصویر (wp/v2/media) برای id=%s — %s", pid, rec.get("full_name"))
            att = upload_media_rest_with_retries(session, wp_base, lp, fname, timeout, log)
            if att:
                log.info("[STEP] آپلود موفق attachment_id=%s", att)
            else:
                log.warning("[STEP] آپلود تصویر ناموفق؛ پست بدون تصویر شاخص ذخیره می‌شود.")
    use_http_sideload = image_mode == "sideload_url" and not no_sideload
    include_url_meta = image_mode == "sideload_url" and no_sideload
    return record_to_payload(
        rid,
        rec,
        data_dir,
        image_host,
        use_sideload_url=use_http_sideload,
        featured_attachment_id=att,
        include_remote_url_for_meta=include_url_meta,
    )


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
    build_payload: Callable[[str, Dict[str, Any]], Dict[str, Any]],
    batch_sideload: bool,
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
        payload = build_payload(rid, rec)
        ok_final = False
        for t in range(MAX_ITEM_RETRIES):
            if t:
                sleep_backoff(t)
            try:
                resp = post_batch(session, wp_base, [payload], dry_run, batch_sideload, timeout)
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
    file_cfg = load_import_config_json()
    ap = argparse.ArgumentParser(description="Import lawyers from checkpoint.json via WordPress REST batch API.")
    ap.add_argument(
        "--checkpoint",
        type=Path,
        default=None,
        help="checkpoint.json path (default: theme/run-20260429-135734/checkpoint.json or import.config.json)",
    )
    ap.add_argument(
        "--data-dir",
        type=Path,
        default=None,
        help="Folder served as web root for /images/... (default: parent of checkpoint)",
    )
    ap.add_argument(
        "--wp-base",
        default=None,
        help="Site URL (or set in tools/import.config.json / env HVL_WP_BASE)",
    )
    ap.add_argument("--wp-user", default=None, help="WordPress username (or import.config.json / HVL_WP_USER)")
    ap.add_argument(
        "--wp-app-password",
        default=None,
        help="Application password without spaces (or import.config.json / HVL_WP_APP_PASSWORD)",
    )
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
    ap.add_argument(
        "--image-mode",
        choices=("rest_media", "sideload_url", "none"),
        default="rest_media",
        help="rest_media=آپلود profile.webp با wp/v2/media از همین PC (پیش‌فرض، برای هاست راه‌دور). "
        "sideload_url=HTTP محلی تا وردپرس download_url بزند. none=بدون تصویر",
    )
    ap.add_argument(
        "--log-file",
        type=Path,
        default=None,
        help="رونوشت لاگ در این فایل UTF-8 (مثلاً tools/import_run.log)",
    )
    args = ap.parse_args()
    apply_config_env_defaults(args, file_cfg)

    if not args.wp_base or not str(args.wp_base).strip():
        sys.stderr.write(
            "خطا: آدرس سایت خالی است.\n"
            "  یکی را انجام دهید:\n"
            "  • فایل tools/import.config.json از روی tools/import.config.example.json بسازید و wp_base / wp_user / wp_app_password را پر کنید\n"
            "  • یا متغیرهای HVL_WP_BASE و HVL_WP_USER و HVL_WP_APP_PASSWORD را بگذارید\n"
            "  • یا همان‌ها را به‌صورت --wp-base ... --wp-user ... --wp-app-password ... بدهید\n"
        )
        return 2
    if not args.wp_user or not str(args.wp_user).strip():
        sys.stderr.write("خطا: wp_user خالی است (فایل تنظیم یا HVL_WP_USER یا --wp-user).\n")
        return 2
    if not args.wp_app_password or not str(args.wp_app_password).strip():
        sys.stderr.write("خطا: wp_app_password خالی است (فایل تنظیم یا HVL_WP_APP_PASSWORD یا --wp-app-password).\n")
        return 2

    def _anchor_path(p: Path | None) -> Path | None:
        if p is None:
            return None
        if p.is_absolute():
            return p.resolve()
        return (THEME_ROOT / p).resolve()

    args.checkpoint = _anchor_path(args.checkpoint)
    args.data_dir = _anchor_path(args.data_dir)
    if args.log_file is not None:
        args.log_file = _anchor_path(args.log_file)

    log_file_resolved = args.log_file.resolve() if args.log_file else None
    setup_logging(args.verbose, log_file_resolved)
    log = logging.getLogger("import")
    log.info(
        "[STEP] شروع — site=%s image-mode=%s dry-run=%s resume=%s",
        args.wp_base,
        args.image_mode,
        args.dry_run,
        not args.no_resume,
    )

    theme_root = THEME_ROOT
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
    batch_sideload = args.image_mode == "sideload_url" and not args.no_sideload

    httpd: ThreadingHTTPServer | None = None
    if args.image_mode == "sideload_url":
        log.info("[STEP] راه‌اندازی سرور محلی تصاویر root=%s public=%s", data_dir, image_host)
        httpd = run_http_server(data_dir, args.image_bind, args.image_port)
        time.sleep(0.25)
    elif args.image_mode == "rest_media":
        log.info("[STEP] تصاویر: آپلود مستقیم wp/v2/media از این ماشین (بدون 127.0.0.1 روی سرور)")
    else:
        log.info("[STEP] تصاویر غیرفعال (image-mode=none)")

    session = requests.Session()
    app_pw = re.sub(r"\s+", "", str(args.wp_app_password))
    auth = base64.b64encode(f"{args.wp_user}:{app_pw}".encode()).decode("ascii")
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

    def build_payload(rid: str, rec: Dict[str, Any]) -> Dict[str, Any]:
        return _build_item_payload(
            rid,
            rec,
            data_dir,
            image_host,
            image_mode=args.image_mode,
            dry_run=args.dry_run,
            no_sideload=args.no_sideload,
            session=session,
            wp_base=args.wp_base,
            timeout=args.timeout,
            log=log,
        )

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
            payloads.append(build_payload(rid, rec))
            valid_pairs.append((rid, rec))
        if not valid_pairs:
            return

        log.info("[STEP] ارسال بچ به lawyers/batch — تعداد=%s sideload_url=%s", len(payloads), batch_sideload)
        retry_singles: List[Tuple[str, Dict[str, Any]]] = []
        batch_ok = False
        for attempt in range(MAX_BATCH_RETRIES):
            try:
                resp = post_batch(session, args.wp_base, payloads, args.dry_run, batch_sideload, args.timeout)
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
                        log.warning("[STEP] وکیل id=%s ذخیره شد با هشدار: %s", rid, err_by_i[i].get("message"))
                else:
                    retry_singles.append((rid, rec))

            if not args.dry_run:
                state["completed_ids"] = sorted(done_ids)
                save_state(state)

            new_processed += len(valid_pairs)
            log.info("[STEP] بچ موفق — ایجاد/به‌روزرسانی=%s خطا=%s", len(created) + len(updated), len(errs))
            break

        if not batch_ok:
            log.error("[STEP] بچ بعد از retry ناموفق — تلاش تک‌تک برای %s ردیف.", len(valid_pairs))
            retry_items_one_by_one(
                session,
                args.wp_base,
                valid_pairs,
                build_payload,
                batch_sideload,
                args.dry_run,
                args.timeout,
                done_ids,
                state,
                log,
            )
            new_processed += len(valid_pairs)
            return

        if retry_singles:
            log.info("[STEP] %s ردیف بدون create/update — retry تک‌تک.", len(retry_singles))
            retry_items_one_by_one(
                session,
                args.wp_base,
                retry_singles,
                build_payload,
                batch_sideload,
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

    if httpd is not None:
        httpd.shutdown()
        log.info("[STEP] سرور محلی تصاویر متوقف شد.")
    log.info("[STEP] پایان — پردازش‌شده (شمارش بچ): %s | state=%s | failures=%s", new_processed, STATE_PATH, FAIL_LOG)
    return 0


if __name__ == "__main__":
    sys.exit(main())
