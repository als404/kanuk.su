# Bitrix image WebP sidecar converter

Generates `photo.jpg.webp` next to `photo.jpg` (also JPEG/PNG), retaining original images and names. Doesn't use Bitrix APIs or the DB. Does not process PDF. Requires Python 3 and Pillow with WebP support. Work on VM 247 first; production is out of scope.

## Test on existing 10-image pilot directory (read-only dry run)

```bash
/home/als/webp-venv/bin/python webp_convert.py --root /home/als/webp-test --limit 10
```

**Warning:** this directory already contains WebP files so no JPEG/PNG may be found. For a real pilot use a separate directory of copies of JPEG/PNG with write access, and explicitly run `--apply`. The converter scans in filename order, not random order. `--limit` caps files *examined*, not created. Check that the desired original images are in the selection. No production automatic activation.

```bash
/home/als/webp-venv/bin/python webp_convert.py --root /path/to/pilot/originals --limit 10 --apply
```

The file must be created by a user with directory write permissions. A write-permitted directory is necessary for atomic sidecar creation. Never `chmod -R 777` the site. Do not run as root by default. SELinux file contexts and HTTP-readable modes must be verified before using in `/upload`.

## Options

* `--root DIR`: source folder; may repeat. Only `.jpg`, `.jpeg`, `.png` files are considered. Symbolic links are not traversed.
* `--limit N`: cap files examined per run.
* `--quality 82`: WebP quality (lossy, including PNG, so visually inspect logos, screenshots, diagrams and transparency).
* `--min-saving 5`: write only if WebP is at least 5% smaller.
* `--apply`: explicitly enable sidecar creation. Without it, nothing is written except lock file.
* `--max-pixels`: guard against excessive memory for huge images.

Existing derivatives whose modification time is >= source modification time are skipped. Changing converter settings will *not* regenerate them automatically; use a controlled renewal workflow. Conversion is atomic within the source directory and locked against concurrent runs using `--lock-file`. JSON lines on stdout can be redirected to logs.

## Important

* This generates same-dimension WebP **only**, not responsive sizes. Full-size images can still be very large to decode in browser. Do not equate this with complete image optimization.
* Some PNGs are lossless diagrams or contain alpha; WebP at quality 82 is lossy. Test carefully; a production converter may need per-format quality rules.
* WebP sidecars are not served without an independent reviewed Nginx configuration. Browser `Accept` and cache behavior must be verified.
* This is the first functional build, not an unattended migration: ensure filesystem permissions, SELinux, change detection, dry-run, and HTTP regression checks are completed before scheduling cron.
