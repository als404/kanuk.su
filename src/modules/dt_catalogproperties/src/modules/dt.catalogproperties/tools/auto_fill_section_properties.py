#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Plan/apply section-specific PROP* assignments via MySQL CLI, without bootstrapping Bitrix.

Works with both Bitrix property storage modes: b_iblock_element_property (VERSION=1)
and b_iblock_element_property_s16/_m16 (VERSION=2).
"""
import argparse
import collections
import csv
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys

IBLOCK = 16
PROP_CODE = re.compile(r'^PROP[0-9]+$')
# Default common attributes for a category without an inherited configuration.
# Order is intentional and matches the configured common set in admin UI.
BASE_PROPERTY_IDS = (338, 88, 121, 516, 94, 89, 522, 523)


def db_query(mysql, database, sql):
    cmd = mysql + ['--batch', '--raw', '--skip-column-names',
                   '--default-character-set=utf8mb4', database, '-e', sql]
    result = subprocess.run(cmd, text=True, stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE, encoding='utf-8')
    if result.returncode:
        raise RuntimeError('Ошибка MySQL: ' + result.stderr.strip())
    return [json.loads(line) for line in result.stdout.splitlines() if line]


def sql_json(query):
    return 'SELECT JSON_OBJECT(' + query + ')'


def read_data(mysql, database):
    versions = db_query(mysql, database,
        "SELECT JSON_OBJECT('version', VERSION) FROM b_iblock WHERE ID=16")
    if len(versions) != 1 or int(versions[0]['version']) not in (1, 2):
        raise RuntimeError('Не найден инфоблок 16 либо неизвестный режим хранения свойств.')
    version = int(versions[0]['version'])
    sections = db_query(mysql, database,
        "SELECT JSON_OBJECT('id',ID,'parent',COALESCE(IBLOCK_SECTION_ID,0),"
        "'depth',DEPTH_LEVEL,'name',NAME) FROM b_iblock_section "
        "WHERE IBLOCK_ID=16 ORDER BY LEFT_MARGIN,ID")
    properties = db_query(mysql, database,
        "SELECT JSON_OBJECT('id',ID,'code',CODE,'name',NAME,'active',ACTIVE) "
        "FROM b_iblock_property WHERE IBLOCK_ID=16 ORDER BY SORT,ID")
    props = {int(p['id']):p for p in properties
             if PROP_CODE.fullmatch(str(p.get('code') or '')) and p.get('active') == 'Y'}
    configured = db_query(mysql, database,
        "SELECT JSON_OBJECT('id',SECTION_ID,'version',CONFIG_VERSION) "
        "FROM dt_cp_section_config")
    cfg = {int(r['id']):int(r['version']) for r in configured}
    rows = db_query(mysql, database,
        "SELECT JSON_OBJECT('sid',r.SECTION_ID,'pid',r.PROPERTY_ID,'sort',r.SORT_ORDER) "
        "FROM dt_cp_section_property r "
        "JOIN b_iblock_section s ON s.ID=r.SECTION_ID AND s.IBLOCK_ID=16 "
        "ORDER BY r.SECTION_ID,r.SORT_ORDER,r.PROPERTY_ID")
    existing = collections.defaultdict(list)
    for row in rows:
        existing[int(row['sid'])].append(int(row['pid']))
    membership_rows = db_query(mysql, database,
        "SELECT JSON_OBJECT('eid',z.eid,'sid',z.sid) FROM ("
        "SELECT se.IBLOCK_ELEMENT_ID eid,se.IBLOCK_SECTION_ID sid "
        "FROM b_iblock_section_element se "
        "JOIN b_iblock_element e ON e.ID=se.IBLOCK_ELEMENT_ID AND e.IBLOCK_ID=16 "
        "UNION SELECT e.ID eid,e.IBLOCK_SECTION_ID sid "
        "FROM b_iblock_element e WHERE e.IBLOCK_ID=16 AND e.IBLOCK_SECTION_ID IS NOT NULL"
        ") z JOIN b_iblock_section s ON s.ID=z.sid AND s.IBLOCK_ID=16")
    links = collections.defaultdict(set)
    for row in membership_rows:
        links[int(row['eid'])].add(int(row['sid']))
    used = collections.defaultdict(set)
    counts = collections.Counter()
    for eid, sids in links.items():
        for sid in sids:
            counts[sid] += 1
    def record(eid, pid):
        if pid not in props:
            return
        for sid in links.get(eid, ()):
            used[sid].add(pid)
    if not props:
        return sections, props, cfg, existing, used, counts, version
    ids = sorted(props)
    if version == 1:
        for start in range(0, len(ids), 120):
            chunk = ids[start:start + 120]
            query = ("SELECT JSON_OBJECT('eid',v.IBLOCK_ELEMENT_ID,'pid',v.IBLOCK_PROPERTY_ID) "
                     "FROM b_iblock_element_property v "
                     "JOIN b_iblock_element e ON e.ID=v.IBLOCK_ELEMENT_ID AND e.IBLOCK_ID=16 "
                     "WHERE v.IBLOCK_PROPERTY_ID IN (" + ','.join(map(str, chunk)) + ") "
                     "AND v.VALUE IS NOT NULL AND TRIM(CAST(v.VALUE AS CHAR)) <> '' "
                     "GROUP BY v.IBLOCK_ELEMENT_ID,v.IBLOCK_PROPERTY_ID")
            for row in db_query(mysql, database, query):
                record(int(row['eid']), int(row['pid']))
    else:
        # The single-value table has PROPERTY_<id> columns; only fetch populated
        # values as JSON to avoid parsing free-form product values in TSV.
        for start in range(0, len(ids), 50):
            chunk = ids[start:start + 50]
            fields = ','.join("'{}',s.PROPERTY_{}".format(pid,pid) for pid in chunk)
            query = ("SELECT JSON_OBJECT('eid',s.IBLOCK_ELEMENT_ID,'values',JSON_OBJECT(" +
                     fields + ")) FROM b_iblock_element_property_s16 s "
                     "JOIN b_iblock_element e ON e.ID=s.IBLOCK_ELEMENT_ID AND e.IBLOCK_ID=16")
            for row in db_query(mysql, database, query):
                eid = int(row['eid'])
                for raw_pid, value in row['values'].items():
                    if value is not None and str(value).strip() != '':
                        record(eid, int(raw_pid))
        for start in range(0, len(ids), 120):
            chunk = ids[start:start + 120]
            query = ("SELECT JSON_OBJECT('eid',v.IBLOCK_ELEMENT_ID,'pid',v.IBLOCK_PROPERTY_ID) "
                     "FROM b_iblock_element_property_m16 v "
                     "JOIN b_iblock_element e ON e.ID=v.IBLOCK_ELEMENT_ID AND e.IBLOCK_ID=16 "
                     "WHERE v.IBLOCK_PROPERTY_ID IN (" + ','.join(map(str, chunk)) + ") "
                     "AND v.VALUE IS NOT NULL AND TRIM(CAST(v.VALUE AS CHAR)) <> '' "
                     "GROUP BY v.IBLOCK_ELEMENT_ID,v.IBLOCK_PROPERTY_ID")
            for row in db_query(mysql, database, query):
                record(int(row['eid']), int(row['pid']))
    return sections, props, cfg, existing, used, counts, version


def build_plan(sections, props, cfg, existing, used, counts, subtree, base_ids=BASE_PROPERTY_IDS):
    id_to_section = {int(s['id']):s for s in sections}
    if subtree and subtree not in id_to_section:
        raise RuntimeError('Раздел {} не найден в инфоблоке 16'.format(subtree))
    missing_base = [pid for pid in base_ids if pid not in props]
    if missing_base:
        raise RuntimeError('Базовые свойства отсутствуют или неактивны в инфоблоке 16: ' +
                           ', '.join(map(str, missing_base)))
    effective = {}
    plans = []
    for s in sections:
        sid = int(s['id'])
        parent = int(s['parent'])
        if parent and parent not in effective:
            raise RuntimeError('Неверный порядок или дерево разделов: ' + str(sid))
        if sid in cfg:
            effective[sid] = list(existing[sid])  # existing manually-set rules are authoritative
            continue
        from_parent = list(effective.get(parent, []))
        # If there is no inherited list, seed the standard eight properties.
        # This covers top-level and standalone sections, without changing manual sets.
        inherited = from_parent if from_parent else list(base_ids)
        seen = set(inherited)
        extra = [pid for pid in sorted(used.get(sid, ())) if pid not in seen]
        result = inherited + extra
        effective[sid] = result
        in_scope = not subtree or sid == subtree
        if subtree and not in_scope:
            cur = parent
            while cur:
                if cur == subtree:
                    in_scope = True
                    break
                cur = int(id_to_section[cur]['parent'])
        if not in_scope:
            continue
        plans.append({'section_id':sid, 'name':s['name'], 'parent_id':parent,
                      'depth':int(s['depth']), 'products_direct':counts.get(sid, 0),
                      'inherited':len(from_parent),'base':len(inherited) if not from_parent else 0,
                      'discovered':len(extra),
                      'property_ids':result, 'new_ids':extra,
                      'empty':not bool(result)})
    return plans


def save_reports(outdir, metadata, plans, props):
    outdir.mkdir(parents=True, exist_ok=True)
    doc = {'metadata':metadata,'sections':plans}
    data = json.dumps(doc, ensure_ascii=False, indent=2) + '\n'
    (outdir / 'plan.json').write_text(data, encoding='utf-8')
    digest = hashlib.sha256(data.encode('utf-8')).hexdigest()
    (outdir / 'plan.sha256').write_text(digest + '\n', encoding='ascii')
    with (outdir / 'sections.csv').open('w', newline='', encoding='utf-8-sig') as f:
        w = csv.writer(f, delimiter=';')
        w.writerow(['SECTION_ID','NAME','PARENT_ID','DEPTH','DIRECT_PRODUCTS',
                    'INHERITED_COUNT','BASE_COUNT','NEW_COUNT','TOTAL_COUNT','ACTION'])
        for p in plans:
            w.writerow([p['section_id'],p['name'],p['parent_id'],p['depth'],
                        p['products_direct'],p['inherited'],p['base'],p['discovered'],
                        len(p['property_ids']), 'SKIP_EMPTY' if p['empty'] else 'CREATE'])
    with (outdir / 'properties.csv').open('w', newline='', encoding='utf-8-sig') as f:
        w = csv.writer(f, delimiter=';')
        w.writerow(['SECTION_ID','PROPERTY_ID','CODE','NAME','SOURCE','SORT_ORDER'])
        for p in plans:
            inherited_count = p['inherited']
            base_count = p['base']
            for i,pid in enumerate(p['property_ids']):
                info = props.get(pid, {})
                w.writerow([p['section_id'],pid,info.get('code',''),info.get('name',''),
                            ('PARENT' if i < inherited_count else
                             'BASE' if i < inherited_count + base_count else 'PRODUCT'),
                            (i + 1) * 100])
    return digest


def apply(mysql, database, plans, outdir):
    # mysql's auto-rollback on disconnect protects from incomplete transactions;
    # each section insert is guarded by ROW_COUNT of new section config row.
    statements = ['START TRANSACTION;']
    for p in plans:
        if p['empty']:
            continue  # an empty set has different semantics; don't silently configure it
        sid = p['section_id']
        statements.append('INSERT IGNORE INTO dt_cp_section_config (SECTION_ID) VALUES ({});'.format(sid))
        statements.append('SET @dt_cp_created = ROW_COUNT();')
        for rank,pid in enumerate(p['property_ids'],1):
            statements.append(
                'INSERT INTO dt_cp_section_property (SECTION_ID,PROPERTY_ID,SORT_ORDER) '
                'SELECT {sid},{pid},{sort} FROM DUAL WHERE @dt_cp_created = 1;'.format(
                    sid=sid,pid=pid,sort=rank*100))
    statements.append('COMMIT;')
    sql = '\n'.join(statements) + '\n'
    (outdir / 'apply.sql').write_text(sql, encoding='utf-8')
    cmd = mysql + ['--batch','--raw','--default-character-set=utf8mb4',database]
    res = subprocess.run(cmd, input=sql, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                         encoding='utf-8', text=True)
    if res.returncode:
        raise RuntimeError('Запись не завершилась: ' + res.stderr.strip())


def main():
    parser = argparse.ArgumentParser(description='Bitrix iblock 16: plan/assign category properties')
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument('--dry-run', action='store_true')
    mode.add_argument('--apply', action='store_true')
    parser.add_argument('--mysql', default='sudo mysql',
                        help='MySQL CLI command (e.g. "sudo mysql" or "mysql --defaults-extra-file=/secure/my.cnf")')
    parser.add_argument('--database', default='kanuk_utf8')
    parser.add_argument('--section-id', type=int, default=0,
                        help='Only report/apply descendants of this section; ancestors still used for inheritance')
    parser.add_argument('--output-dir', default='./dt_cp_autofill_report')
    parser.add_argument('--confirm', default='', help='For --apply: APPLY_SECTION_CONFIG_16')
    parser.add_argument('--expected-sha256', default='',
                        help='For --apply: exact hash of newly generated plan.json; prevents stale approval')
    args = parser.parse_args()
    if args.apply and (args.confirm != 'APPLY_SECTION_CONFIG_16' or not args.expected_sha256):
        parser.error('--apply requires --confirm APPLY_SECTION_CONFIG_16 and --expected-sha256')
    mysql = shlex.split(args.mysql)
    if not mysql or Path(mysql[0]).name not in ('mysql','sudo'):
        parser.error('Use mysql CLI, optionally prefixed with sudo')
    outdir = Path(args.output_dir)
    sections, props, cfg, existing, used, counts, version = read_data(mysql, args.database)
    plans = build_plan(sections, props, cfg, existing, used, counts, args.section_id)
    metadata = {'iblock_id':IBLOCK,'storage_version':version,'database':args.database,
                'section_id':args.section_id,'configured_count':len(cfg),
                'sections_in_plan':len(plans),'sections_to_create':sum(bool(p['property_ids']) for p in plans),
                'empty_skipped':sum(p['empty'] for p in plans)}
    digest = save_reports(outdir, metadata, plans, props)
    print(json.dumps(metadata, ensure_ascii=False, indent=2))
    print('Отчёт:', outdir.resolve(), 'SHA256:', digest)
    if args.apply:
        if digest != args.expected_sha256:
            raise RuntimeError('План изменился после dry-run. Запись отменена. Изучите новый отчёт и SHA256.')
        apply(mysql, args.database, plans, outdir)
        print('Изменения записаны. Уже настроенные категории пропущены. Проверьте таблицы и публичный кеш.')
    else:
        print('DRY-RUN: никаких SQL-изменений не выполнено.')


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError, ValueError, KeyError) as exc:
        print('ERROR:', exc, file=sys.stderr)
        sys.exit(1)
