# Deployment checklist (staging → production)

1. Inventory actual paths, OS, Python, Pillow/WebP support and free disk space on target hosting. Do not reuse VM 247 paths without verification.
2. Put script outside web root, e.g. `/opt/discount-tools/image-webp/webp_convert.py`; use a dedicated Python virtual environment and least-privilege account.
3. Check ownership, ACL and SELinux of `/upload/iblock` and `/upload/resize_cache`. Ensure safe atomic file creation by that account; no recursive permissions changes.
4. On copied small pilot JPEG/PNG inputs, run dry-run then `--apply`; inspect image quality and compare byte size, transparency and dimensions.
5. Check full Nginx server block (all `location` rules, cache/ETag headers and upstream/CDN). Implement optional WebP negotiation only for image URLs; never bypass PHP execution denials or static access protections.
6. Verify `curl` with and without `Accept: image/webp` on JPG and PNG, absence of WebP sidecar fallback, content type, `Vary: Accept`, Basic Auth behavior (staging), PDF delivery, cache consistency and response after cache clear.
7. Enable limited batch with monitoring before any cron. Measure CPU/disk and error logs; test updates to existing files.
8. Rollback Nginx rules and stop cron if needed. Keep originals and DB untouched; sidecar WebP files may be removed later under controlled policy.

Note: production's PHP / character encoding are not involved in the converter because it runs outside Bitrix, but target hosting paths and access rules must be checked independently.
