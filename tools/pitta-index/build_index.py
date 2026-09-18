#!/usr/bin/env python3
"""Build data/pitta-index.json — the per-page text of every PITTA edition.

Reads data/pitta-catalog.csv, fetches each "IN DRIVE" edition's PDF from
Google Drive (cached in tools/pitta-index/.cache/), extracts text page by
page with poppler's pdftotext (reading order, so multi-column pages
keep sentences whole), OCRs near-empty (scanned) pages with
tesseract when it's installed, and writes the index the theme's
/wp-json/db/v1/pitta-search endpoint reads.

Usage:
  python3 tools/pitta-index/build_index.py              # download + index
  python3 tools/pitta-index/build_index.py --pdf-dir D  # use local PDFs named {key}.pdf
  python3 tools/pitta-index/build_index.py --only 2019-05-regular

Keys are {year}-{mm}-{slug(edition)} and must stay in step with
db_pitta_catalog_key() in functions.php.
"""

import argparse
import csv
import datetime
import json
import re
import shutil
import subprocess
import sys
import tempfile
import unicodedata
import urllib.parse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CATALOG = ROOT / 'data' / 'pitta-catalog.csv'
INDEX = ROOT / 'data' / 'pitta-index.json'
CACHE = Path(__file__).resolve().parent / '.cache'

OCR_MIN_CHARS = 40  # pages with less extractable text than this are treated as scans


def slug(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def catalog_key(row):
    month = int(row['month']) if row['month'].strip() else 0
    return f"{int(row['year'])}-{month:02d}-{slug(row['edition'])}"


def download_drive(file_id, dest):
    """Fetch a publicly shared Drive file with curl (sidesteps Python's
    certificate store). confirm=t skips Drive's virus-scan interstitial.
    Returns None on success or an error string."""
    url = ('https://drive.usercontent.google.com/download?export=download&confirm=t&id='
           + urllib.parse.quote(file_id))
    tmp = dest.with_suffix('.part')
    res = subprocess.run(['curl', '-sSL', '--fail', '--max-time', '180', '-o', str(tmp), url],
                         capture_output=True, text=True)
    if res.returncode != 0:
        tmp.unlink(missing_ok=True)
        return 'download failed: ' + res.stderr.strip()[:200]
    head = tmp.read_bytes()[:4096]
    if head.startswith(b'%PDF'):
        tmp.replace(dest)
        return None
    tmp.unlink()
    if b'accounts.google.com' in head or b'Sign-in' in head or b'ServiceLogin' in head:
        return 'not shared publicly (Drive asked for sign-in)'
    return 'Drive returned a page that is not a PDF'


def normalise(text):
    text = text.replace('\u00ad', '')  # soft hyphens
    # A hyphen at a line end is kept but the break dropped ("Black-\nrumped" ->
    # "Black-rumped"); search folds hyphens to spaces, so true syllable splits
    # are the only casualty and compound bird names stay intact.
    text = re.sub(r'(\w)-\n\s*(\w)', r'\1-\2', text)
    text = re.sub(r'[\x00-\x08\x0b-\x1f\x7f]', ' ', text)  # control chars (incl. stray \f)
    return re.sub(r'\s+', ' ', text).strip()


def ocr_page(pdf, page_no):
    with tempfile.TemporaryDirectory() as tmp:
        base = Path(tmp) / 'p'
        subprocess.run(['pdftoppm', '-r', '300', '-gray', '-png', '-f', str(page_no), '-l', str(page_no),
                        '-singlefile', str(pdf), str(base)], check=True, capture_output=True)
        out = subprocess.run(['tesseract', str(base) + '.png', '-', '-l', 'eng'],
                             check=True, capture_output=True, text=True)
        return out.stdout


def extract(pdf, can_ocr, stats):
    out = subprocess.run(['pdftotext', '-enc', 'UTF-8', str(pdf), '-'],
                         check=True, capture_output=True, text=True)
    raw_pages = out.stdout.split('\f')
    if raw_pages and not raw_pages[-1].strip():
        raw_pages.pop()  # pdftotext ends with a trailing form feed
    pages = []
    for i, raw in enumerate(raw_pages, start=1):
        text = normalise(raw)
        if len(text) < OCR_MIN_CHARS:
            if can_ocr:
                text = normalise(ocr_page(pdf, i))
                stats['ocr_pages'] += 1
            else:
                stats['scan_pages_skipped'] += 1
        pages.append(text)
    return pages


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--pdf-dir', type=Path, help='read {key}.pdf from this folder instead of Drive')
    ap.add_argument('--only', nargs='*', help='limit to these catalog keys (merged into the existing index)')
    ap.add_argument('--refresh', action='store_true', help='re-download even if cached')
    args = ap.parse_args()

    for tool in ('pdftotext', 'pdftoppm'):
        if not shutil.which(tool):
            sys.exit(f'{tool} not found — brew install poppler')
    can_ocr = bool(shutil.which('tesseract'))
    if not can_ocr:
        print('! tesseract not found — scanned pages will be left empty (brew install tesseract)')

    CACHE.mkdir(exist_ok=True)
    rows = list(csv.DictReader(CATALOG.open(newline='', encoding='utf-8')))

    issues = {}
    if args.only and INDEX.exists():
        issues = json.loads(INDEX.read_text(encoding='utf-8')).get('issues', {})

    stats = {'ocr_pages': 0, 'scan_pages_skipped': 0}
    report = {'indexed': [], 'missing': [], 'failed': [], 'empty': []}

    for row in rows:
        key = catalog_key(row)
        if args.only and key not in args.only:
            continue
        if row['status'].strip().upper() != 'IN DRIVE' or not row['drive_id'].strip():
            report['missing'].append(key)
            continue

        if args.pdf_dir:
            pdf = args.pdf_dir / f'{key}.pdf'
            if not pdf.exists():
                report['failed'].append((key, f'no {pdf.name} in {args.pdf_dir}'))
                continue
        else:
            pdf = CACHE / f'{key}.pdf'
            if args.refresh or not pdf.exists():
                print(f'  downloading {key} …', flush=True)
                try:
                    err = download_drive(row['drive_id'].strip(), pdf)
                except Exception as e:  # network errors, timeouts
                    err = str(e)
                if err:
                    report['failed'].append((key, err))
                    continue

        try:
            pages = extract(pdf, can_ocr, stats)
        except subprocess.CalledProcessError as e:
            report['failed'].append((key, 'extraction failed: ' + (e.stderr or '').strip()[:200]))
            continue
        issues[key] = {'pages': pages}
        chars = sum(map(len, pages))
        (report['empty'] if chars < OCR_MIN_CHARS * len(pages or [1]) else report['indexed']).append(key)
        print(f'  {key}: {len(pages)} pages, {chars:,} chars')

    INDEX.write_text(json.dumps({
        'generated': datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds'),
        'issues': dict(sorted(issues.items())),
    }, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')

    print(f'\nWrote {INDEX.relative_to(ROOT)} ({INDEX.stat().st_size / 1e6:.1f} MB, {len(issues)} editions)')
    print(f"Indexed: {len(report['indexed'])}   OCR'd pages: {stats['ocr_pages']}"
          f"   scanned pages left empty: {stats['scan_pages_skipped']}")
    if report['empty']:
        print('Mostly empty text (scans? install tesseract and re-run):', ', '.join(report['empty']))
    if report['missing']:
        print(f"Skipped as MISSING ({len(report['missing'])}):", ', '.join(report['missing']))
    if report['failed']:
        print(f"FAILED ({len(report['failed'])}):")
        for key, why in report['failed']:
            print(f'  {key}: {why}')
    return 1 if report['failed'] else 0


if __name__ == '__main__':
    sys.exit(main())
