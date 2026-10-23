#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""Direct MySQL -> XML dealer feed exporter for Bitrix.

No Bitrix CMS bootstrap is loaded. Python dependencies: standard library only.
External commands required: php and mysql/mariadb CLI.
"""

from __future__ import annotations

import argparse
import contextlib
import datetime as dt
import fcntl
import html
import json
import logging
import logging.handlers
import os
import re
import shutil
import subprocess
import sys
import tempfile
from collections import defaultdict
from decimal import Decimal, InvalidOperation
from pathlib import Path
import xml.etree.ElementTree as ET

XML_INVALID_RE = re.compile("[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\uD800-\\uDFFF\\uFFFE\\uFFFF]")
UNIT_RE = re.compile(r",\s*([^,]{1,24})\s*$")

EXCLUDED_CHARACTERISTIC_CODES = {
    "BACKGROUND_IMAGE", "NEWPRODUCT", "SALELEADER", "DISCOUNT", "TIME_BUY",
    "TIME_BUY_FROM", "TIME_BUY_TO", "MORE_PHOTO", "ARTNUMBER", "MANUFACTURER",
    "COUNTRY", "DELIVERY", "GIFT", "GUARANTEE", "ACCESSORIES", "FILES_DOCS",
    "VIDEO", "FREE_TAB", "MINIMUM_PRICE", "DELIVERY_CALC", "TITLE", "KEYWORDS",
    "DESCRIPTION", "CANONICAL", "vote_count_", "vote_sum_", "rating_",
    "vote_count1", "vote_sum1", "rating1", "vote_count", "vote_sum", "rating",
}


def now_iso():
    return dt.datetime.now(dt.timezone.utc).astimezone().isoformat(timespec="seconds")


def clean_xml_text(value):
    return XML_INVALID_RE.sub("", "" if value is None else str(value))


def hex_expr(column, alias):
    return "HEX(CONVERT(COALESCE(%s, '') USING utf8mb4)) AS `%s`" % (
        column, alias.replace("`", "``")
    )


def decode_hex(value):
    if value in (None, "", "NULL", r"\N"):
        return ""
    try:
        return bytes.fromhex(value).decode("utf-8", "replace")
    except (ValueError, TypeError):
        return str(value)


def as_int(value, default=0):
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def as_decimal(value):
    if value in (None, "", "NULL", r"\N"):
        return None
    try:
        return Decimal(str(value))
    except (InvalidOperation, ValueError):
        return None


def decimal_text(value):
    if value is None:
        return ""
    return format(value.quantize(Decimal("0.01")), "f")


def chunks(values, size=400):
    for pos in range(0, len(values), size):
        yield values[pos:pos + size]


def load_config(path):
    cfg = json.loads(path.read_text(encoding="utf-8"))
    required = (
        "site_root", "base_url", "output_dir", "log_file", "lock_file",
        "product_iblock_id", "manufacturer_property_id", "article_property_id",
        "more_photo_property_id", "price_group_id", "brands",
    )
    missing = [k for k in required if cfg.get(k) in (None, "", {})]
    if missing:
        raise ValueError("Missing config keys: " + ", ".join(missing))
    if not isinstance(cfg["brands"], dict) or not cfg["brands"]:
        raise ValueError("brands must be a non-empty object")
    for name, item in cfg["brands"].items():
        if "manufacturer_id" not in item or "file" not in item:
            raise ValueError("Brand %s needs manufacturer_id and file" % name)
        if Path(item["file"]).name != item["file"]:
            raise ValueError("Brand output must be a simple filename: %s" % item["file"])
    return cfg


def setup_logging(path, verbose=False):
    path.parent.mkdir(parents=True, exist_ok=True)
    logger = logging.getLogger("dealer_export")
    logger.setLevel(logging.DEBUG)
    logger.handlers[:] = []
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(message)s")

    fh = logging.handlers.RotatingFileHandler(
        str(path), maxBytes=5 * 1024 * 1024, backupCount=5, encoding="utf-8"
    )
    fh.setLevel(logging.DEBUG)
    fh.setFormatter(fmt)
    logger.addHandler(fh)

    sh = logging.StreamHandler(sys.stderr)
    sh.setLevel(logging.DEBUG if verbose else logging.INFO)
    sh.setFormatter(fmt)
    logger.addHandler(sh)
    return logger


class AlreadyRunning(RuntimeError):
    pass


class RunLock:
    def __init__(self, path):
        self.path = path
        self.fp = None

    def __enter__(self):
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.fp = open(self.path, "a+", encoding="ascii")
        try:
            fcntl.flock(self.fp.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            self.fp.close()
            self.fp = None
            raise AlreadyRunning("Another exporter instance is already running")
        self.fp.seek(0)
        self.fp.truncate()
        self.fp.write(str(os.getpid()) + "\n")
        self.fp.flush()
        os.fsync(self.fp.fileno())
        return self

    def __exit__(self, exc_type, exc, tb):
        if self.fp:
            with contextlib.suppress(Exception):
                fcntl.flock(self.fp.fileno(), fcntl.LOCK_UN)
            self.fp.close()


def read_bitrix_db_config(site_root):
    php = shutil.which("php")
    if not php:
        raise RuntimeError("php CLI not found")

    php_code = r'''
$root = rtrim(getenv("BITRIX_SITE_ROOT"), "/");
$out = array();
$p = $root . "/bitrix/.settings.php";
if (is_file($p)) {
    $s = include $p;
    if (isset($s["connections"]["value"]["default"])) {
        $c = $s["connections"]["value"]["default"];
        $out = array(
            "source" => ".settings.php",
            "host" => isset($c["host"]) ? $c["host"] : "localhost",
            "port" => isset($c["port"]) ? $c["port"] : null,
            "database" => isset($c["database"]) ? $c["database"] : null,
            "user" => isset($c["login"]) ? $c["login"] : null,
            "password" => isset($c["password"]) ? $c["password"] : null
        );
    }
}
if (!$out) {
    $p = $root . "/bitrix/php_interface/dbconn.php";
    if (!is_file($p)) {
        fwrite(STDERR, "Bitrix DB config not found\n");
        exit(2);
    }
    ob_start(); include $p; ob_end_clean();
    $out = array(
        "source" => "dbconn.php",
        "host" => isset($DBHost) ? $DBHost : "localhost",
        "port" => null,
        "database" => isset($DBName) ? $DBName : null,
        "user" => isset($DBLogin) ? $DBLogin : null,
        "password" => isset($DBPassword) ? $DBPassword : null
    );
}
echo json_encode($out);
'''
    env = os.environ.copy()
    env["BITRIX_SITE_ROOT"] = str(site_root)
    p = subprocess.run([php, "-r", php_code], env=env,
                       stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
    if p.returncode != 0:
        raise RuntimeError("Cannot read Bitrix DB config: " + p.stderr.decode("utf-8", "replace"))
    cfg = json.loads(p.stdout.decode("utf-8", "replace"))
    if not cfg.get("database") or not cfg.get("user"):
        raise RuntimeError("Incomplete Bitrix DB config")
    return cfg


def normalize_host_port(cfg):
    out = dict(cfg)
    host = str(out.get("host") or "localhost")
    port = out.get("port")
    if not port:
        m = re.match(r"^([^:]+):(\d+)$", host)
        if m:
            host, port = m.group(1), int(m.group(2))
    out["host"] = host
    out["port"] = int(port) if str(port or "").isdigit() else None
    return out


class MysqlCli:
    def __init__(self, cfg):
        self.cfg = normalize_host_port(cfg)
        self.bin = shutil.which("mysql") or shutil.which("mariadb")
        self.defaults_path = None

    def __enter__(self):
        if not self.bin:
            raise RuntimeError("mysql/mariadb CLI client not found")
        fd, name = tempfile.mkstemp(prefix="dealer-export-db-", suffix=".cnf")
        os.close(fd)
        os.chmod(name, 0o600)
        lines = [
            "[client]",
            "host=%s" % self.cfg["host"],
            "user=%s" % self.cfg["user"],
            "password=%s" % (self.cfg.get("password") or ""),
            "database=%s" % self.cfg["database"],
            "default-character-set=utf8mb4",
        ]
        if self.cfg.get("port"):
            lines.append("port=%s" % self.cfg["port"])
        Path(name).write_text("\n".join(lines) + "\n", encoding="utf-8")
        self.defaults_path = name
        return self

    def __exit__(self, exc_type, exc, tb):
        if self.defaults_path:
            with contextlib.suppress(FileNotFoundError):
                os.unlink(self.defaults_path)

    def query(self, sql):
        cmd = [
            self.bin,
            "--defaults-extra-file=%s" % self.defaults_path,
            "--batch", "--raw", "--quick", "--connect-timeout=15",
        ]
        p = subprocess.run(cmd, input=(sql.rstrip() + ";\n").encode("utf-8"),
                           stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
        if p.returncode != 0:
            raise RuntimeError("SQL failed:\n%s\n\n%s" % (sql, p.stderr.decode("utf-8", "replace")))
        lines = p.stdout.decode("utf-8", "replace").splitlines()
        if not lines:
            return []
        headers = lines[0].split("\t")
        rows = []
        for n, line in enumerate(lines[1:], 2):
            values = line.split("\t")
            if len(values) != len(headers):
                raise RuntimeError("Unexpected mysql TSV shape at line %d" % n)
            rows.append(dict(zip(headers, values)))
        return rows

    def table_exists(self, table):
        safe = table.replace("'", "''")
        return bool(self.query("SHOW TABLES LIKE '%s'" % safe))

    def columns(self, table):
        if not self.table_exists(table):
            return []
        return [r.get("Field", "") for r in self.query("SHOW COLUMNS FROM `%s`" % table.replace("`", "``"))]


def fetch_products(db, cfg, brand_ids, limit=None):
    ib = int(cfg["product_iblock_id"])
    prop = int(cfg["manufacturer_property_id"])
    mids = ",".join(str(int(x)) for x in brand_ids)
    limit_sql = " LIMIT %d" % int(limit) if limit else ""
    rows = db.query("""
SELECT e.ID,
       %s, %s, %s,
       COALESCE(e.IBLOCK_SECTION_ID,0) AS IBLOCK_SECTION_ID,
       COALESCE(e.PREVIEW_PICTURE,0) AS PREVIEW_PICTURE,
       COALESCE(e.DETAIL_PICTURE,0) AS DETAIL_PICTURE,
       %s, %s, %s, %s,
       (SELECT CAST(ep.VALUE AS UNSIGNED)
          FROM b_iblock_element_property ep
         WHERE ep.IBLOCK_ELEMENT_ID=e.ID AND ep.IBLOCK_PROPERTY_ID=%d
         ORDER BY ep.ID LIMIT 1) AS BRAND_ID,
       COALESCE(cp.QUANTITY,0) AS QUANTITY,
       COALESCE(cp.QUANTITY_RESERVED,0) AS QUANTITY_RESERVED,
       COALESCE(cp.AVAILABLE,'N') AS AVAILABLE,
       COALESCE(cp.MEASURE,0) AS MEASURE
  FROM b_iblock_element e
  LEFT JOIN b_catalog_product cp ON cp.ID=e.ID
 WHERE e.IBLOCK_ID=%d
   AND e.ACTIVE='Y'
   AND (e.ACTIVE_FROM IS NULL OR e.ACTIVE_FROM <= NOW())
   AND (e.ACTIVE_TO IS NULL OR e.ACTIVE_TO >= NOW())
   AND EXISTS (
       SELECT 1 FROM b_iblock_element_property bp
        WHERE bp.IBLOCK_ELEMENT_ID=e.ID
          AND bp.IBLOCK_PROPERTY_ID=%d
          AND CAST(bp.VALUE AS UNSIGNED) IN (%s)
   )
 ORDER BY e.ID%s
""" % (
        hex_expr("e.NAME", "NAME_HEX"),
        hex_expr("e.CODE", "CODE_HEX"),
        hex_expr("e.XML_ID", "XML_ID_HEX"),
        hex_expr("e.PREVIEW_TEXT_TYPE", "PREVIEW_TYPE_HEX"),
        hex_expr("e.PREVIEW_TEXT", "PREVIEW_TEXT_HEX"),
        hex_expr("e.DETAIL_TEXT_TYPE", "DETAIL_TYPE_HEX"),
        hex_expr("e.DETAIL_TEXT", "DETAIL_TEXT_HEX"),
        prop, ib, prop, mids, limit_sql,
    ))
    out = []
    for r in rows:
        out.append({
            "id": as_int(r["ID"]),
            "name": clean_xml_text(decode_hex(r["NAME_HEX"])),
            "code": clean_xml_text(decode_hex(r["CODE_HEX"])),
            "xml_id": clean_xml_text(decode_hex(r["XML_ID_HEX"])),
            "section_id": as_int(r["IBLOCK_SECTION_ID"]),
            "preview_picture": as_int(r["PREVIEW_PICTURE"]),
            "detail_picture": as_int(r["DETAIL_PICTURE"]),
            "preview_type": decode_hex(r["PREVIEW_TYPE_HEX"]),
            "preview_text": clean_xml_text(decode_hex(r["PREVIEW_TEXT_HEX"])),
            "detail_type": decode_hex(r["DETAIL_TYPE_HEX"]),
            "detail_text": clean_xml_text(decode_hex(r["DETAIL_TEXT_HEX"])),
            "brand_id": as_int(r["BRAND_ID"]),
            "quantity": as_decimal(r["QUANTITY"]) or Decimal("0"),
            "quantity_reserved": as_decimal(r["QUANTITY_RESERVED"]) or Decimal("0"),
            "available": r["AVAILABLE"] == "Y",
            "measure_id": as_int(r["MEASURE"]),
        })
    return out


def fetch_simple_property(db, product_ids, property_id):
    result = {}
    for part in chunks(product_ids):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT IBLOCK_ELEMENT_ID AS ELEMENT_ID, %s
  FROM b_iblock_element_property
 WHERE IBLOCK_PROPERTY_ID=%d AND IBLOCK_ELEMENT_ID IN (%s)
 ORDER BY ID
""" % (hex_expr("VALUE", "VALUE_HEX"), property_id, ids))
        for r in rows:
            eid = as_int(r["ELEMENT_ID"])
            result.setdefault(eid, clean_xml_text(decode_hex(r["VALUE_HEX"])).strip())
    return result


def fetch_prices(db, product_ids, price_group_id):
    grouped = defaultdict(list)
    for part in chunks(product_ids):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT ID, PRODUCT_ID, PRICE, %s, QUANTITY_FROM, QUANTITY_TO
  FROM b_catalog_price
 WHERE CATALOG_GROUP_ID=%d AND PRODUCT_ID IN (%s)
 ORDER BY PRODUCT_ID,
          CASE WHEN QUANTITY_FROM IS NULL THEN 0 ELSE 1 END,
          QUANTITY_FROM, ID
""" % (hex_expr("CURRENCY", "CURRENCY_HEX"), price_group_id, ids))
        for r in rows:
            grouped[as_int(r["PRODUCT_ID"])].append({
                "price": as_decimal(r["PRICE"]),
                "currency": clean_xml_text(decode_hex(r["CURRENCY_HEX"])),
                "quantity_from": as_decimal(r["QUANTITY_FROM"]),
                "quantity_to": as_decimal(r["QUANTITY_TO"]),
            })
    result = {}
    for pid, rows in grouped.items():
        generic = [x for x in rows if x["quantity_from"] is None and x["quantity_to"] is None]
        result[pid] = (generic or rows)[0]
    return result


def fetch_sections(db, iblock_id):
    rows = db.query("""
SELECT ID, COALESCE(IBLOCK_SECTION_ID,0) AS PARENT_ID,
       %s, %s, ACTIVE, GLOBAL_ACTIVE, DEPTH_LEVEL
  FROM b_iblock_section
 WHERE IBLOCK_ID=%d
 ORDER BY LEFT_MARGIN
""" % (hex_expr("NAME", "NAME_HEX"), hex_expr("CODE", "CODE_HEX"), iblock_id))
    out = {}
    for r in rows:
        sid = as_int(r["ID"])
        out[sid] = {
            "id": sid,
            "parent_id": as_int(r["PARENT_ID"]),
            "name": clean_xml_text(html.unescape(decode_hex(r["NAME_HEX"]))),
            "code": clean_xml_text(decode_hex(r["CODE_HEX"])),
        }
    return out


def section_path(section_id, sections):
    names, seen = [], set()
    cur = section_id
    while cur and cur in sections and cur not in seen:
        seen.add(cur)
        names.append(sections[cur]["name"])
        cur = sections[cur]["parent_id"]
    names.reverse()
    return " / ".join(x for x in names if x)


def fetch_section_links(db, product_ids):
    out = defaultdict(list)
    for part in chunks(product_ids):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT IBLOCK_ELEMENT_ID AS ELEMENT_ID, IBLOCK_SECTION_ID AS SECTION_ID
  FROM b_iblock_section_element
 WHERE IBLOCK_ELEMENT_ID IN (%s)
 ORDER BY IBLOCK_ELEMENT_ID, IBLOCK_SECTION_ID
""" % ids)
        for r in rows:
            eid, sid = as_int(r["ELEMENT_ID"]), as_int(r["SECTION_ID"])
            if sid and sid not in out[eid]:
                out[eid].append(sid)
    return out


def fetch_properties(db, cfg, product_ids):
    out = defaultdict(list)
    min_sort = int(cfg.get("characteristics_min_sort", 100))
    for part in chunks(product_ids, 250):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT ep.IBLOCK_ELEMENT_ID AS ELEMENT_ID,
       p.ID AS PROPERTY_ID,
       %s, %s, p.SORT, p.PROPERTY_TYPE, p.MULTIPLE,
       %s, %s, %s, %s, %s
  FROM b_iblock_element_property ep
  JOIN b_iblock_property p ON p.ID=ep.IBLOCK_PROPERTY_ID
  LEFT JOIN b_iblock_property_enum en ON en.ID=ep.VALUE_ENUM
  LEFT JOIN b_iblock_element le
         ON p.PROPERTY_TYPE='E' AND le.ID=CAST(ep.VALUE AS UNSIGNED)
 WHERE ep.IBLOCK_ELEMENT_ID IN (%s)
   AND p.ACTIVE='Y' AND p.SORT >= %d
 ORDER BY ep.IBLOCK_ELEMENT_ID, p.SORT, p.ID, ep.ID
""" % (
            hex_expr("p.CODE", "CODE_HEX"),
            hex_expr("p.NAME", "NAME_HEX"),
            hex_expr("p.USER_TYPE", "USER_TYPE_HEX"),
            hex_expr("ep.VALUE", "RAW_HEX"),
            hex_expr("ep.DESCRIPTION", "DESC_HEX"),
            hex_expr("en.VALUE", "ENUM_HEX"),
            hex_expr("le.NAME", "LINKED_HEX"),
            ids, min_sort,
        ))
        for r in rows:
            ptype = r["PROPERTY_TYPE"]
            code = decode_hex(r["CODE_HEX"])
            if code in EXCLUDED_CHARACTERISTIC_CODES or ptype == "F":
                continue
            raw = decode_hex(r["RAW_HEX"])
            if ptype == "L":
                value = decode_hex(r["ENUM_HEX"]) or raw
            elif ptype == "E":
                value = decode_hex(r["LINKED_HEX"]) or raw
            else:
                value = raw
            value = clean_xml_text(html.unescape(value)).strip()
            if not value:
                continue
            name = clean_xml_text(html.unescape(decode_hex(r["NAME_HEX"]))).strip()
            unit = ""
            m = UNIT_RE.search(name)
            if m:
                candidate = m.group(1).strip()
                if len(candidate) <= 16:
                    unit = candidate
            out[as_int(r["ELEMENT_ID"])].append({
                "id": as_int(r["PROPERTY_ID"]),
                "code": clean_xml_text(code),
                "name": name,
                "type": ptype,
                "user_type": clean_xml_text(decode_hex(r["USER_TYPE_HEX"])),
                "value": value,
                "unit": clean_xml_text(unit),
                "description": clean_xml_text(decode_hex(r["DESC_HEX"])).strip(),
            })
    return out


def fetch_more_photo_ids(db, product_ids, property_id):
    out = defaultdict(list)
    for part in chunks(product_ids):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT IBLOCK_ELEMENT_ID AS ELEMENT_ID, VALUE AS FILE_ID
  FROM b_iblock_element_property
 WHERE IBLOCK_PROPERTY_ID=%d AND IBLOCK_ELEMENT_ID IN (%s)
 ORDER BY IBLOCK_ELEMENT_ID, ID
""" % (property_id, ids))
        for r in rows:
            eid, fid = as_int(r["ELEMENT_ID"]), as_int(r["FILE_ID"])
            if fid and fid not in out[eid]:
                out[eid].append(fid)
    return out


def fetch_files(db, file_ids):
    out = {}
    for part in chunks(sorted(set(file_ids)), 500):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT ID, %s, %s, %s
  FROM b_file
 WHERE ID IN (%s)
""" % (
            hex_expr("SUBDIR", "SUBDIR_HEX"),
            hex_expr("FILE_NAME", "FILE_NAME_HEX"),
            hex_expr("ORIGINAL_NAME", "ORIGINAL_HEX"),
            ids,
        ))
        for r in rows:
            out[as_int(r["ID"])] = {
                "subdir": clean_xml_text(decode_hex(r["SUBDIR_HEX"])).strip("/"),
                "name": clean_xml_text(decode_hex(r["FILE_NAME_HEX"])).strip("/"),
                "original": clean_xml_text(decode_hex(r["ORIGINAL_HEX"])),
            }
    return out


def resolve_measures(db, fallback, logger):
    if not db.table_exists("b_catalog_measure"):
        logger.warning("b_catalog_measure not found; using fallback unit")
        return {}

    cols = set(db.columns("b_catalog_measure"))
    candidates = [
        c for c in ("SYMBOL_RUS", "SYMBOL", "SYMBOL_INTL", "MEASURE_TITLE")
        if c in cols
    ]
    if not candidates:
        logger.warning(
            "No known symbol column in b_catalog_measure: %s",
            ",".join(sorted(cols)),
        )
        return {}

    select_parts = ["ID"]
    if "CODE" in cols:
        select_parts.append("CODE")
    for col in candidates:
        select_parts.append(hex_expr("`%s`" % col, col + "_HEX"))

    rows = db.query(
        "SELECT %s FROM b_catalog_measure ORDER BY ID" % ", ".join(select_parts)
    )

    # Standard OKEI codes used by this Bitrix installation.  Normalizing them
    # gives dealer-facing Russian units even though SYMBOL_RUS is NULL and the
    # legacy SYMBOL_INTL value for pieces is 'pc. 1'.
    okei_units = {
        6: "м",
        112: "л",
        163: "г",
        166: "кг",
        796: "шт",
    }

    out = {}
    for r in rows:
        code = as_int(r.get("CODE"), 0)
        if code in okei_units:
            out[as_int(r["ID"])] = okei_units[code]
            continue

        value = ""
        for col in candidates:
            value = clean_xml_text(
                html.unescape(decode_hex(r.get(col + "_HEX")))
            ).strip()
            if value:
                break

        if value:
            out[as_int(r["ID"])] = value

    if not out:
        logger.warning("Measure map empty; fallback '%s' will be used", fallback)
    else:
        logger.info(
            "Resolved catalog measures: %s",
            ", ".join("%s=%s" % (mid, out[mid]) for mid in sorted(out)),
        )
    return out


def fetch_offer_counts(db, product_ids, offer_iblock_id=17, link_property_id=72):
    out = defaultdict(int)
    for part in chunks(product_ids):
        ids = ",".join(str(x) for x in part)
        rows = db.query("""
SELECT CAST(ep.VALUE AS UNSIGNED) AS PRODUCT_ID, COUNT(*) AS CNT
  FROM b_iblock_element_property ep
  JOIN b_iblock_element o ON o.ID=ep.IBLOCK_ELEMENT_ID
 WHERE ep.IBLOCK_PROPERTY_ID=%d
   AND CAST(ep.VALUE AS UNSIGNED) IN (%s)
   AND o.IBLOCK_ID=%d AND o.ACTIVE='Y'
 GROUP BY CAST(ep.VALUE AS UNSIGNED)
""" % (link_property_id, ids, offer_iblock_id))
        for r in rows:
            out[as_int(r["PRODUCT_ID"])] = as_int(r["CNT"])
    return out


def file_url(base_url, record):
    if not record or not record.get("name"):
        return ""
    subdir = record.get("subdir", "")
    return "%s/upload/%s%s" % (
        base_url.rstrip("/"),
        (subdir + "/") if subdir else "",
        record["name"],
    )


def product_url(base_url, product, sections):
    code = product.get("code", "").strip("/")
    if not code:
        return ""
    section_code = sections.get(product.get("section_id"), {}).get("code", "").strip("/")
    if section_code:
        return "%s/catalog/%s/%s/" % (base_url.rstrip("/"), section_code, code)
    return "%s/catalog/%s/" % (base_url.rstrip("/"), code)


def add_text(parent, tag, value, attrs=None):
    node = ET.SubElement(parent, tag, attrs or {})
    node.text = clean_xml_text(value)
    return node


def add_properties(parent, values):
    root = ET.SubElement(parent, "properties")
    grouped, order = {}, []
    for p in values:
        if p["id"] not in grouped:
            grouped[p["id"]] = {"meta": p, "values": []}
            order.append(p["id"])
        grouped[p["id"]]["values"].append(p)
    for pid in order:
        group = grouped[pid]
        meta = group["meta"]
        attrs = {"id": str(pid), "code": meta["code"], "name": meta["name"], "type": meta["type"]}
        if meta.get("unit"):
            attrs["unit"] = meta["unit"]
        if meta.get("user_type") and meta["user_type"] not in ("", "NULL"):
            attrs["user_type"] = meta["user_type"]
        node = ET.SubElement(root, "property", attrs)
        for item in group["values"]:
            vattrs = {"description": item["description"]} if item.get("description") else None
            add_text(node, "value", item["value"], vattrs)


def build_xml(cfg, brand_name, brand_id, products, sections, section_links,
              articles, prices, properties, more_photos, files, measures, offer_counts):
    root = ET.Element("dealer_catalog", {
        "schema_version": "1", "generated_at": now_iso(),
        "brand": brand_name, "brand_id": str(brand_id),
    })
    products_node = ET.SubElement(root, "products", {"count": str(len(products))})
    fallback_unit = str(cfg.get("fallback_measure_symbol", "шт"))

    for p in products:
        pid = p["id"]
        node = ET.SubElement(products_node, "product", {"id": str(pid)})
        add_text(node, "article", articles.get(pid, ""))
        add_text(node, "name", p["name"])
        add_text(node, "brand", brand_name, {"id": str(brand_id)})

        primary = p["section_id"]
        add_text(node, "category", section_path(primary, sections), {"id": str(primary)} if primary else None)
        cats = ET.SubElement(node, "categories")
        all_sections = []
        if primary:
            all_sections.append(primary)
        for sid in section_links.get(pid, []):
            if sid not in all_sections:
                all_sections.append(sid)
        for sid in all_sections:
            add_text(cats, "category", section_path(sid, sections), {
                "id": str(sid), "primary": "true" if sid == primary else "false"
            })

        description = p["detail_text"].strip() or p["preview_text"].strip()
        dtype = p["detail_type"] if p["detail_text"].strip() else p["preview_type"]
        add_text(node, "description", description, {"format": "html" if dtype == "html" else "text"})

        price = prices.get(pid)
        pattrs = {"currency": price["currency"]} if price and price.get("currency") else None
        add_text(node, "price", decimal_text(price["price"]) if price else "", pattrs)

        unit = measures.get(p["measure_id"], fallback_unit)
        av = ET.SubElement(node, "availability", {
            "available": "true" if p["available"] else "false", "unit": unit
        })
        add_text(av, "quantity", decimal_text(p["quantity"]))
        add_text(node, "url", product_url(cfg["base_url"], p, sections))

        image_ids = []
        for fid in (p["detail_picture"], p["preview_picture"]):
            if fid and fid not in image_ids:
                image_ids.append(fid)
        for fid in more_photos.get(pid, []):
            if fid and fid not in image_ids:
                image_ids.append(fid)
        imgs = ET.SubElement(node, "images")
        emitted = 0
        for fid in image_ids:
            rec = files.get(fid)
            url = file_url(cfg["base_url"], rec)
            if not url:
                continue
            attrs = {"file_id": str(fid), "main": "true" if emitted == 0 else "false"}
            if rec.get("original"):
                attrs["original_name"] = rec["original"]
            add_text(imgs, "image", url, attrs)
            emitted += 1

        add_properties(node, properties.get(pid, []))
        add_text(node, "active_offer_count", str(offer_counts.get(pid, 0)))

    return ET.ElementTree(root)


def indent_xml(elem, level=0):
    pad = "\n" + "  " * level
    if len(elem):
        if not elem.text or not elem.text.strip():
            elem.text = pad + "  "
        for child in elem:
            indent_xml(child, level + 1)
        if not elem[-1].tail or not elem[-1].tail.strip():
            elem[-1].tail = pad
    if level and (not elem.tail or not elem.tail.strip()):
        elem.tail = pad


def write_staged(tree, output_dir, final_name):
    output_dir.mkdir(parents=True, exist_ok=True)
    indent_xml(tree.getroot())
    fd, name = tempfile.mkstemp(prefix=".%s.tmp." % final_name, dir=str(output_dir))
    path = Path(name)
    try:
        with os.fdopen(fd, "wb") as fp:
            tree.write(fp, encoding="utf-8", xml_declaration=True)
            fp.flush()
            os.fsync(fp.fileno())
        os.chmod(path, 0o644)
        return path
    except Exception:
        with contextlib.suppress(FileNotFoundError):
            path.unlink()
        raise


def validate_xml(path, brand, expected_count):
    tree = ET.parse(str(path))
    root = tree.getroot()
    if root.tag != "dealer_catalog" or root.get("brand") != brand:
        raise ValueError("XML root/brand validation failed")
    products = root.find("products")
    items = [] if products is None else products.findall("product")
    if expected_count <= 0:
        raise ValueError("Refusing to publish empty feed")
    if len(items) != expected_count:
        raise ValueError("XML count mismatch: expected %d got %d" % (expected_count, len(items)))
    for item in items:
        if not item.get("id") or not (item.findtext("name") or "").strip():
            raise ValueError("Required product id/name missing")
        if (item.findtext("brand") or "").strip() != brand:
            raise ValueError("Brand mismatch inside product")


def fsync_dir(path):
    fd = os.open(str(path), os.O_RDONLY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


def run_export(cfg, logger, no_publish=False, limit=None):
    if limit and not no_publish:
        raise ValueError("--limit requires --no-publish")

    site_root = Path(cfg["site_root"]).resolve()
    db_cfg = read_bitrix_db_config(site_root)
    expected = cfg.get("expected_database")
    if expected and db_cfg.get("database") != expected:
        raise RuntimeError("Safety check failed: DB=%r expected=%r" % (db_cfg.get("database"), expected))

    brand_by_id = {int(v["manufacturer_id"]): k for k, v in cfg["brands"].items()}
    output_dir = Path(cfg["output_dir"]).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    staged = []

    try:
        with MysqlCli(db_cfg) as db:
            meta = db.query("SELECT DATABASE() AS DB_NAME, @@version AS DB_VERSION, @@character_set_database AS DB_CHARSET, @@collation_database AS DB_COLLATION")
            if meta:
                logger.info("Database=%s version=%s charset=%s collation=%s",
                            meta[0]["DB_NAME"], meta[0]["DB_VERSION"], meta[0]["DB_CHARSET"], meta[0]["DB_COLLATION"])

            products = fetch_products(db, cfg, sorted(brand_by_id), limit)
            if not products:
                raise RuntimeError("No active target-brand products found")

            source_ids = [p["id"] for p in products]
            logger.info("Selected active target-brand products before article filter: %d", len(source_ids))

            articles = fetch_simple_property(db, source_ids, int(cfg["article_property_id"]))
            missing_article = [x for x in source_ids if not articles.get(x, "").strip()]
            if missing_article:
                logger.warning(
                    "Excluded products without article: %d; ids=%s",
                    len(missing_article),
                    ",".join(map(str, missing_article[:50]))
                )

            products = [p for p in products if p["id"] not in set(missing_article)]
            if not products:
                raise RuntimeError("No exportable products left after article filter")

            ids = [p["id"] for p in products]
            logger.info("Exportable products after article filter: %d", len(ids))
            for mid, name in brand_by_id.items():
                logger.info("Brand %s: %d products", name, sum(1 for p in products if p["brand_id"] == mid))

            # Keep only article values for products that remain in the feed.
            articles = {k: v for k, v in articles.items() if k in set(ids)}

            prices = fetch_prices(db, ids, int(cfg["price_group_id"]))
            sections = fetch_sections(db, int(cfg["product_iblock_id"]))
            section_links = fetch_section_links(db, ids)
            properties = fetch_properties(db, cfg, ids)
            more_photos = fetch_more_photo_ids(db, ids, int(cfg["more_photo_property_id"]))
            measures = resolve_measures(db, str(cfg.get("fallback_measure_symbol", "шт")), logger)
            offer_counts = fetch_offer_counts(db, ids)

            file_ids = set()
            for p in products:
                file_ids.update(x for x in (p["preview_picture"], p["detail_picture"]) if x)
            for x in more_photos.values():
                file_ids.update(x)
            files = fetch_files(db, sorted(file_ids)) if file_ids else {}

            missing_price = [x for x in ids if x not in prices]
            if missing_price:
                logger.warning("Products without price group %s: %d; first ids=%s",
                               cfg["price_group_id"], len(missing_price), ",".join(map(str, missing_price[:20])))
            logger.info("Active linked offers for selected products: %d", sum(offer_counts.values()))

            for brand_name, bcfg in cfg["brands"].items():
                mid = int(bcfg["manufacturer_id"])
                subset = [p for p in products if p["brand_id"] == mid]
                if not subset:
                    raise RuntimeError("Refusing to publish empty feed for %s" % brand_name)
                tree = build_xml(cfg, brand_name, mid, subset, sections, section_links,
                                 articles, prices, properties, more_photos, files, measures, offer_counts)
                final = output_dir / bcfg["file"]
                tmp = write_staged(tree, output_dir, final.name)
                validate_xml(tmp, brand_name, len(subset))
                logger.info("Validated %s: %d products, %d bytes", brand_name, len(subset), tmp.stat().st_size)
                staged.append((tmp, final))

        # Nothing public is touched until both brand files have passed validation.
        published = []
        if no_publish:
            for tmp, final in staged:
                preview = final.with_name(final.stem + ".preview" + final.suffix)
                os.replace(str(tmp), str(preview))
                published.append(preview)
                logger.info("Preview written: %s", preview)
            staged[:] = []
        else:
            backups = []
            replaced = []
            try:
                # Keep rollback copies before changing any public feed.
                for _, final in staged:
                    backup = None
                    if final.exists():
                        fd, backup_name = tempfile.mkstemp(
                            prefix=".%s.previous." % final.name, dir=str(output_dir)
                        )
                        os.close(fd)
                        backup = Path(backup_name)
                        shutil.copy2(str(final), str(backup))
                    backups.append((final, backup))

                for tmp, final in staged:
                    os.replace(str(tmp), str(final))
                    replaced.append(final)
                    published.append(final)
                    logger.info("Published: %s", final)

                staged[:] = []
            except Exception:
                logger.exception("Publish step failed; rolling back already replaced feeds")
                backup_map = dict(backups)
                for final in reversed(replaced):
                    backup = backup_map.get(final)
                    try:
                        if backup and backup.exists():
                            os.replace(str(backup), str(final))
                        else:
                            with contextlib.suppress(FileNotFoundError):
                                final.unlink()
                    except Exception:
                        logger.exception("Rollback failed for %s", final)
                raise
            finally:
                for _, backup in backups:
                    if backup:
                        with contextlib.suppress(FileNotFoundError):
                            backup.unlink()

        try:
            fsync_dir(output_dir)
        except OSError as exc:
            # Some NFS mounts do not support directory fsync. File fsync and
            # same-directory os.replace() have already completed at this point.
            logger.warning("Directory fsync not supported for %s: %s", output_dir, exc)
        return published
    finally:
        for tmp, _ in staged:
            with contextlib.suppress(FileNotFoundError):
                tmp.unlink()


def parse_args():
    p = argparse.ArgumentParser(description="Export BREXIT/ESSON Bitrix products to XML")
    p.add_argument("--config", required=True)
    p.add_argument("--no-publish", action="store_true", help="write *.preview.xml instead of public files")
    p.add_argument("--limit", type=int, default=None, help="preview only; requires --no-publish")
    p.add_argument("-v", "--verbose", action="store_true")
    return p.parse_args()


def main():
    args = parse_args()
    cfg = load_config(Path(args.config).resolve())
    logger = setup_logging(Path(cfg["log_file"]), args.verbose)
    logger.info("Dealer export start; publish=%s", "no" if args.no_publish else "yes")
    try:
        with RunLock(Path(cfg["lock_file"])):
            run_export(cfg, logger, no_publish=args.no_publish, limit=args.limit)
    except AlreadyRunning as exc:
        logger.warning("%s", exc)
        return 0
    except Exception:
        logger.exception("Dealer export FAILED; previous published XML left untouched")
        return 1
    logger.info("Dealer export finished successfully")
    return 0


if __name__ == "__main__":
    sys.exit(main())
