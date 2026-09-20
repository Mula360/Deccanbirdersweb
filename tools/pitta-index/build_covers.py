#!/usr/bin/env python3
"""Render a cover thumbnail for every PITTA edition into assets/pitta-covers/.

The Archives page shows each issue as its front cover, so this takes page 1
of every catalogued PDF and writes a small JPEG named after the edition's
catalog key — the same key the search index and the db_pitta posts use.

PDFs come from tools/pitta-index/.cache (shared with build_index.py) and are
downloaded if missing. Roughly 24 KB per cover, ~3.3 MB for the full run.

    python3 tools/pitta-index/build_covers.py            # only what's missing
    python3 tools/pitta-index/build_covers.py --refresh  # re-render everything
    python3 tools/pitta-index/build_covers.py --only 2026-09-regular
"""

import argparse
import csv
import shutil
import subprocess
import sys
from pathlib import Path

from build_index import CACHE, CATALOG, ROOT, catalog_key, download_drive

COVERS = ROOT / 'assets' / 'pitta-covers'
WIDTH = 300      # displayed at ~150px wide, so this stays sharp on retina
QUALITY = 72


def render_cover(pdf, dest):
    """Page 1 of $pdf as a JPEG at $dest. Returns None, or an error string."""
    tmp = dest.with_suffix('')  # pdftoppm appends .jpg itself
    res = subprocess.run(
        ['pdftoppm', '-jpeg', '-jpegopt', f'quality={QUALITY}', '-f', '1', '-l', '1',
         '-scale-to-x', str(WIDTH), '-scale-to-y', '-1', '-singlefile', str(pdf), str(tmp)],
        capture_output=True, text=True)
    if res.returncode != 0:
        return (res.stderr or 'pdftoppm failed').strip()[:200]
    if not dest.exists():
        return 'pdftoppm wrote nothing'
    return None


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--refresh', action='store_true', help='re-render covers that already exist')
    ap.add_argument('--only', nargs='*', help='limit to these catalog keys')
    args = ap.parse_args()

    if not shutil.which('pdftoppm'):
        sys.exit('pdftoppm not found — brew install poppler')

    COVERS.mkdir(parents=True, exist_ok=True)
    rows = list(csv.DictReader(CATALOG.open(newline='', encoding='utf-8')))

    written, skipped, missing, failed = 0, 0, [], []
    for row in rows:
        key = catalog_key(row)
        if args.only and key not in args.only:
            continue
        if row['status'].strip().upper() != 'IN DRIVE' or not row['drive_id'].strip():
            missing.append(key)
            continue

        dest = COVERS / f'{key}.jpg'
        if dest.exists() and not args.refresh:
            skipped += 1
            continue

        pdf = CACHE / f'{key}.pdf'
        if not pdf.exists():
            print(f'  downloading {key} …', flush=True)
            err = download_drive(row['drive_id'].strip(), pdf)
            if err:
                failed.append((key, err))
                continue

        err = render_cover(pdf, dest)
        if err:
            failed.append((key, err))
            continue
        written += 1
        print(f'  {key}: {dest.stat().st_size // 1024} KB')

    total = sum(f.stat().st_size for f in COVERS.glob('*.jpg'))
    print(f'\n{written} written, {skipped} already there — '
          f'{len(list(COVERS.glob("*.jpg")))} covers, {total / 1e6:.1f} MB in {COVERS.relative_to(ROOT)}')
    if missing:
        print(f'Skipped as MISSING ({len(missing)}):', ', '.join(missing))
    if failed:
        print(f'FAILED ({len(failed)}):')
        for key, why in failed:
            print(f'  {key}: {why}')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
