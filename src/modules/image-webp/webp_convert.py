#!/usr/bin/env python3
"""Generate sidecar WebP images for Bitrix uploads, without loading Bitrix.

SQLite remembers successfully processed and intentionally skipped source versions.
--limit counts conversion attempts; scanning already completed sources is allowed.
Dry-run never changes images or database records (but creates SQLite DB if missing).
"""
import argparse
import fcntl
import json
import os
import sqlite3
import sys
import tempfile
from pathlib import Path

from PIL import Image, ImageOps, UnidentifiedImageError

EXTS = {'.jpg', '.jpeg', '.png'}
TERMINAL = {'created', 'up_to_date', 'not_smaller_enough', 'wrong_format', 'too_many_pixels'}


def parse_args():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--root', action='append', required=True, help='Repeatable image directory')
    p.add_argument('--limit', type=int, default=50, help='Maximum conversions attempted per run')
    p.add_argument('--quality', type=int, default=82)
    p.add_argument('--min-saving', type=float, default=5)
    p.add_argument('--apply', action='store_true')
    p.add_argument('--lock-file', default='/var/lib/discount-tools/image-webp/converter.lock')
    p.add_argument('--state-db', default='/var/lib/discount-tools/image-webp/state.sqlite3')
    p.add_argument('--max-pixels', type=int, default=50_000_000)
    return p.parse_args()


def convert(source, source_stat, args):
    target = source.with_name(source.name + '.webp')
    temp_name = None
    try:
        with Image.open(source) as im:
            if im.format not in {'JPEG', 'PNG'}:
                return 'wrong_format', 0
            if im.width * im.height > args.max_pixels:
                return 'too_many_pixels', 0
            im = ImageOps.exif_transpose(im)
            if im.mode not in ('RGB', 'RGBA'):
                im = im.convert('RGBA' if 'A' in im.getbands() or 'transparency' in im.info else 'RGB')
            with tempfile.NamedTemporaryFile(dir=str(source.parent), prefix='.webp-tmp-', suffix='.webp', delete=False) as f:
                temp_name = f.name
            im.save(temp_name, 'WEBP', quality=args.quality, method=4, exif=b'')
        converted_size = os.stat(temp_name).st_size
        if 100.0 * (1 - converted_size / source_stat.st_size) < args.min_saving:
            return 'not_smaller_enough', 0
        # Reject stale output if source changed mid-conversion.
        current = source.stat()
        if (current.st_mtime_ns, current.st_size) != (source_stat.st_mtime_ns, source_stat.st_size):
            return 'source_changed', 0
        os.chmod(temp_name, 0o644)
        os.utime(temp_name, ns=(source_stat.st_mtime_ns, source_stat.st_mtime_ns))
        os.replace(temp_name, target)
        temp_name = None
        return 'created', source_stat.st_size - converted_size
    except (OSError, ValueError, UnidentifiedImageError, ZeroDivisionError) as exc:
        return 'error:' + type(exc).__name__, 0
    finally:
        if temp_name is not None:
            try:
                os.unlink(temp_name)
            except OSError:
                pass


def main():
    args = parse_args()
    if args.limit < 1 or not (1 <= args.quality <= 100) or not (0 <= args.min_saving < 100) or args.max_pixels < 1:
        sys.exit('Invalid arguments')
    roots = [Path(r).resolve(strict=True) for r in args.root]
    if any(not r.is_dir() for r in roots):
        sys.exit('All --root paths must be directories')
    # Shared lock covers DB updates as well as image conversion.
    lock_fd = os.open(args.lock_file, os.O_CREAT | os.O_RDWR, 0o600)
    try:
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        sys.exit('Another conversion process is running')
    db = sqlite3.connect(args.state_db, timeout=10)
    try:
        db.execute('''CREATE TABLE IF NOT EXISTS processed (
            path TEXT PRIMARY KEY, size INTEGER NOT NULL, mtime_ns INTEGER NOT NULL,
            quality INTEGER NOT NULL, min_saving REAL NOT NULL, max_pixels INTEGER NOT NULL,
            status TEXT NOT NULL
        )''')
        db.commit()
        stats = {}
        scanned = attempts = saved_bytes = 0
        for root in roots:
            for base, dirs, files in os.walk(root, followlinks=False):
                dirs[:] = sorted(d for d in dirs if not Path(base, d).is_symlink())
                for name in sorted(files):
                    if Path(name).suffix.lower() not in EXTS:
                        continue
                    source = Path(base) / name
                    if source.is_symlink() or not source.is_file():
                        continue
                    scanned += 1
                    try:
                        st = source.stat()
                        if st.st_size == 0:
                            stats['empty'] = stats.get('empty', 0) + 1
                            continue
                        path = str(source)
                        row = db.execute('SELECT size,mtime_ns,quality,min_saving,max_pixels,status FROM processed WHERE path=?', (path,)).fetchone()
                        signature = (st.st_size, st.st_mtime_ns, args.quality, args.min_saving, args.max_pixels)
                        target = source.with_name(source.name + '.webp')
                        if row and tuple(row[:5]) == signature and row[5] in TERMINAL:
                            # A previously created file may be deleted out of band.
                            if row[5] != 'created' or target.is_file():
                                stats['up_to_date'] = stats.get('up_to_date', 0) + 1
                                continue
                        if target.is_file() and target.stat().st_mtime_ns >= st.st_mtime_ns:
                            status, saving = 'up_to_date', 0
                        elif not args.apply:
                            status, saving = 'would_convert', 0
                            attempts += 1
                        else:
                            status, saving = convert(source, st, args)
                            attempts += 1
                        stats[status] = stats.get(status, 0) + 1
                        saved_bytes += saving
                        if args.apply and status in TERMINAL:
                            db.execute('''INSERT INTO processed(path,size,mtime_ns,quality,min_saving,max_pixels,status)
                                VALUES(?,?,?,?,?,?,?) ON CONFLICT(path) DO UPDATE SET
                                size=excluded.size,mtime_ns=excluded.mtime_ns,quality=excluded.quality,
                                min_saving=excluded.min_saving,max_pixels=excluded.max_pixels,status=excluded.status''',
                                (path, *signature, status))
                            db.commit()
                    except OSError as exc:
                        key = 'error:' + type(exc).__name__
                        stats[key] = stats.get(key, 0) + 1
                    if attempts >= args.limit:
                        break
                if attempts >= args.limit:
                    break
            if attempts >= args.limit:
                break
        print(json.dumps({'scanned': scanned, 'attempted': attempts, 'applied': args.apply,
                          'counts': stats, 'saved_bytes': saved_bytes}, ensure_ascii=False))
    finally:
        db.close()
        os.close(lock_fd)


if __name__ == '__main__':
    main()
