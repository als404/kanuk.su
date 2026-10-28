#!/usr/bin/env python3
"""Generate sidecar WebP files for Bitrix images without altering originals.

Requires Pillow. Defaults to dry-run. Only JPEG/PNG under explicit safe roots.
"""
import argparse
import fcntl
import json
import os
import sys
import tempfile
import time
from pathlib import Path

from PIL import Image, ImageOps, UnidentifiedImageError

EXTS = {'.jpg', '.jpeg', '.png'}


def parse_args():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--root', action='append', required=True, help='Image directory; repeatable')
    p.add_argument('--limit', type=int, default=100, help='Max source images to examine (default 100)')
    p.add_argument('--quality', type=int, default=82)
    p.add_argument('--min-saving', type=float, default=5, help='Minimum size saving in percent')
    p.add_argument('--apply', action='store_true', help='Create WebP sidecars; otherwise dry-run')
    p.add_argument('--lock-file', default='/tmp/discount-tools-image-webp.lock')
    p.add_argument('--max-pixels', type=int, default=50_000_000)
    return p.parse_args()


def process(source, args):
    target = source.with_name(source.name + '.webp')
    source_stat = source.stat()
    if target.is_file() and target.stat().st_mtime_ns >= source_stat.st_mtime_ns:
        return 'up_to_date', 0, 0
    if not args.apply:
        return 'would_convert', source_stat.st_size, 0
    temp_name = None
    try:
        with Image.open(source) as im:
            if im.format not in {'JPEG', 'PNG'}:
                return 'wrong_format', 0, 0
            if im.width * im.height > args.max_pixels:
                return 'too_many_pixels', 0, 0
            im = ImageOps.exif_transpose(im)
            if im.mode not in ('RGB', 'RGBA'):
                im = im.convert('RGBA' if 'A' in im.getbands() or 'transparency' in im.info else 'RGB')
            # If an image has transparency, WebP must preserve it.
            with tempfile.NamedTemporaryFile(dir=str(source.parent), prefix='.webp-tmp-', suffix='.webp', delete=False) as f:
                temp_name = f.name
            im.save(temp_name, 'WEBP', quality=args.quality, method=4, exif=b'')
        converted_size = os.stat(temp_name).st_size
        saving_pct = 100.0 * (1 - converted_size / source_stat.st_size)
        if saving_pct < args.min_saving:
            return 'not_smaller_enough', source_stat.st_size, converted_size
        # Do not publish a stale derivative if the source changed during conversion.
        if source.stat().st_mtime_ns != source_stat.st_mtime_ns or source.stat().st_size != source_stat.st_size:
            return 'source_changed', 0, 0
        os.chmod(temp_name, 0o644)
        os.utime(temp_name, ns=(source_stat.st_mtime_ns, source_stat.st_mtime_ns))
        os.replace(temp_name, target)
        temp_name = None
        return 'created', source_stat.st_size, converted_size
    except (OSError, ValueError, UnidentifiedImageError) as exc:
        return 'error:' + type(exc).__name__, 0, 0
    finally:
        if temp_name:
            try:
                os.unlink(temp_name)
            except OSError:
                pass


def main():
    args = parse_args()
    if args.limit < 1 or not (1 <= args.quality <= 100) or not (0 <= args.min_saving < 100):
        sys.exit('Invalid --limit, --quality or --min-saving')
    # Lock is held for the full run. No cron overlap.
    lock_fd = os.open(args.lock_file, os.O_CREAT | os.O_RDWR, 0o600)
    try:
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        sys.exit('Another conversion process is running')
    count = 0
    stats = {}
    saved_bytes = 0
    for raw in args.root:
        root = Path(raw).resolve(strict=True)
        if not root.is_dir():
            sys.exit('Not a directory: ' + str(root))
        for base, dirs, files in os.walk(root, followlinks=False):
            dirs[:] = sorted(d for d in dirs if not Path(base, d).is_symlink())
            for name in sorted(files):
                if Path(name).suffix.lower() not in EXTS:
                    continue
                source = Path(base) / name
                if source.is_symlink() or not source.is_file():
                    continue
                status, old, new = process(source, args)
                stats[status] = stats.get(status, 0) + 1
                if status == 'created':
                    saved_bytes += old - new
                print(json.dumps({'file': str(source), 'status': status, 'original_bytes': old, 'webp_bytes': new}, ensure_ascii=False), flush=True)
                count += 1
                if count >= args.limit:
                    break
            if count >= args.limit:
                break
        if count >= args.limit:
            break
    print(json.dumps({'examined': count, 'applied': args.apply, 'counts': stats, 'saved_bytes': saved_bytes}, ensure_ascii=False))


if __name__ == '__main__':
    main()
