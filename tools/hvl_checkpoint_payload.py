"""
تبدیل رکورد checkpoint بات به payload ایمپورت وردپرس (بدون requests).
استفاده در import_lawyers.py و build_import_bundle.py.
"""

from __future__ import annotations

import re
import urllib.parse
from pathlib import Path
from typing import Any, Dict, List, Tuple

try:
    import jdatetime
except ImportError:
    jdatetime = None  # type: ignore


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
