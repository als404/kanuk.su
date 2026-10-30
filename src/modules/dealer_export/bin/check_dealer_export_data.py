#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import argparse
import json
from pathlib import Path

import dealer_export as de


def show(title, rows):
    print("\n=== %s ===" % title)
    if not rows:
        print("(empty)")
        return
    for row in rows:
        print(json.dumps(row, ensure_ascii=False, sort_keys=True))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--config", required=True)
    args = ap.parse_args()

    cfg = de.load_config(Path(args.config).resolve())
    db_cfg = de.read_bitrix_db_config(Path(cfg["site_root"]).resolve())

    brand_ids = sorted(int(v["manufacturer_id"]) for v in cfg["brands"].values())
    mids = ",".join(str(x) for x in brand_ids)
    iblock = int(cfg["product_iblock_id"])
    brand_prop = int(cfg["manufacturer_property_id"])
    article_prop = int(cfg["article_property_id"])
    price_group = int(cfg["price_group_id"])

    with de.MysqlCli(db_cfg) as db:
        show("b_catalog_measure columns",
             db.query("SHOW COLUMNS FROM b_catalog_measure"))

        measure_cols = set(db.columns("b_catalog_measure"))
        display_cols = [c for c in (
            "ID", "CODE", "SYMBOL_RUS", "SYMBOL", "SYMBOL_INTL",
            "MEASURE_TITLE", "IS_DEFAULT"
        ) if c in measure_cols]
        if display_cols:
            exprs = []
            for col in display_cols:
                if col == "ID":
                    exprs.append("ID")
                elif col in ("CODE", "IS_DEFAULT"):
                    exprs.append("`%s`" % col)
                else:
                    exprs.append(de.hex_expr("`%s`" % col, col + "_HEX"))
            show("b_catalog_measure data",
                 db.query("SELECT %s FROM b_catalog_measure ORDER BY ID" %
                          ", ".join(exprs)))

        base = """
            FROM b_iblock_element e
            JOIN b_iblock_element_property bp
              ON bp.IBLOCK_ELEMENT_ID=e.ID
             AND bp.IBLOCK_PROPERTY_ID=%d
             AND CAST(bp.VALUE AS UNSIGNED) IN (%s)
            LEFT JOIN b_catalog_price p
              ON p.PRODUCT_ID=e.ID
             AND p.CATALOG_GROUP_ID=%d
            WHERE e.IBLOCK_ID=%d
              AND e.ACTIVE='Y'
              AND (e.ACTIVE_FROM IS NULL OR e.ACTIVE_FROM <= NOW())
              AND (e.ACTIVE_TO IS NULL OR e.ACTIVE_TO >= NOW())
        """ % (brand_prop, mids, price_group, iblock)

        show("target price summary", db.query("""
            SELECT
              COUNT(DISTINCT e.ID) AS products,
              COUNT(DISTINCT CASE WHEN p.ID IS NULL THEN e.ID END) AS without_price_row,
              COUNT(DISTINCT CASE WHEN p.ID IS NOT NULL AND p.PRICE=0 THEN e.ID END) AS zero_price,
              COUNT(DISTINCT CASE WHEN p.PRICE>0 THEN e.ID END) AS positive_price,
              MIN(CASE WHEN p.PRICE>0 THEN p.PRICE END) AS min_positive,
              MAX(p.PRICE) AS max_price
            %s
        """ % base))

        common_select = """
            SELECT
              e.ID,
              %s,
              %s,
              CAST(bp.VALUE AS UNSIGNED) AS BRAND_ID,
              COALESCE(cp.QUANTITY,0) AS QUANTITY,
              COALESCE(cp.AVAILABLE,'N') AS AVAILABLE,
              p.ID AS PRICE_ID,
              p.PRICE,
              %s,
              (
                SELECT %s
                FROM b_iblock_element_property ap
                WHERE ap.IBLOCK_ELEMENT_ID=e.ID
                  AND ap.IBLOCK_PROPERTY_ID=%d
                ORDER BY ap.ID
                LIMIT 1
              ) AS ARTICLE_HEX,
              (
                SELECT mp.VALUE
                FROM b_iblock_element_property mp
                WHERE mp.IBLOCK_ELEMENT_ID=e.ID
                  AND mp.IBLOCK_PROPERTY_ID=71
                ORDER BY mp.ID
                LIMIT 1
              ) AS MINIMUM_PRICE_PROPERTY
            FROM b_iblock_element e
            JOIN b_iblock_element_property bp
              ON bp.IBLOCK_ELEMENT_ID=e.ID
             AND bp.IBLOCK_PROPERTY_ID=%d
             AND CAST(bp.VALUE AS UNSIGNED) IN (%s)
            LEFT JOIN b_catalog_product cp ON cp.ID=e.ID
            LEFT JOIN b_catalog_price p
              ON p.PRODUCT_ID=e.ID
             AND p.CATALOG_GROUP_ID=%d
            WHERE e.IBLOCK_ID=%d
              AND e.ACTIVE='Y'
              AND (e.ACTIVE_FROM IS NULL OR e.ACTIVE_FROM <= NOW())
              AND (e.ACTIVE_TO IS NULL OR e.ACTIVE_TO >= NOW())
        """ % (
            de.hex_expr("e.NAME", "NAME_HEX"),
            de.hex_expr("e.CODE", "CODE_HEX"),
            de.hex_expr("p.CURRENCY", "CURRENCY_HEX"),
            de.hex_expr("ap.VALUE", "ARTICLE_VALUE_HEX"),
            article_prop,
            brand_prop, mids, price_group, iblock
        )

        show("sample zero-price products", db.query(
            common_select + """
              AND p.ID IS NOT NULL
              AND p.PRICE=0
            ORDER BY e.ID
            LIMIT 20
            """
        ))

        show("sample positive-price products", db.query(
            common_select + """
              AND p.PRICE>0
            ORDER BY e.ID
            LIMIT 20
            """
        ))

        show("products without BASE price row", db.query(
            common_select + """
              AND p.ID IS NULL
            ORDER BY e.ID
            LIMIT 50
            """
        ))

        show("products without article", db.query(
            common_select + """
              AND NOT EXISTS (
                SELECT 1
                FROM b_iblock_element_property ap2
                WHERE ap2.IBLOCK_ELEMENT_ID=e.ID
                  AND ap2.IBLOCK_PROPERTY_ID=%d
                  AND COALESCE(ap2.VALUE,'') <> ''
              )
            ORDER BY e.ID
            LIMIT 20
            """ % article_prop
        ))

        show("price groups", db.query("""
            SELECT ID, NAME, BASE, SORT
            FROM b_catalog_group
            ORDER BY SORT, ID
        """))

        show("offer iblock activity", db.query("""
            SELECT
              COUNT(*) AS total_offers,
              SUM(CASE WHEN ACTIVE='Y' THEN 1 ELSE 0 END) AS active_offers
            FROM b_iblock_element
            WHERE IBLOCK_ID=17
        """))

        show("CML2_LINK rows to target products", db.query("""
            SELECT
              COUNT(*) AS link_rows,
              COUNT(DISTINCT ep.IBLOCK_ELEMENT_ID) AS linked_offers,
              COUNT(DISTINCT CAST(ep.VALUE AS UNSIGNED)) AS linked_products
            FROM b_iblock_element_property ep
            JOIN b_iblock_element o ON o.ID=ep.IBLOCK_ELEMENT_ID
            WHERE ep.IBLOCK_PROPERTY_ID=72
              AND o.IBLOCK_ID=17
              AND CAST(ep.VALUE AS UNSIGNED) IN (
                SELECT e.ID
                FROM b_iblock_element e
                JOIN b_iblock_element_property bp2
                  ON bp2.IBLOCK_ELEMENT_ID=e.ID
                 AND bp2.IBLOCK_PROPERTY_ID=%d
                 AND CAST(bp2.VALUE AS UNSIGNED) IN (%s)
                WHERE e.IBLOCK_ID=%d
              )
        """ % (brand_prop, mids, iblock)))


if __name__ == "__main__":
    main()
