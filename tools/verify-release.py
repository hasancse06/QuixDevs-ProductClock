#!/usr/bin/env python3
"""Independently verify ZIP CRCs, canonical root, source bytes and dependencies."""
from pathlib import Path
import argparse
import json
import zipfile
from release_lib import SLUG, verify_archive

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag', help='Release tag that must match plugin/readme/changelog versions.')
    args = parser.parse_args()
    source = Path(__file__).resolve().parents[1]
    try:
        report = verify_archive(source, source / 'dist' / (SLUG + '.zip'), args.tag)
        expected_checksum = report['sha256'] + '  ' + SLUG + '.zip\n'
        if (source / 'dist' / (SLUG + '.zip.sha256')).read_text() != expected_checksum:
            raise ValueError('Checksum sidecar does not match the ZIP.')
        if json.loads((source / 'dist/release-manifest.json').read_text()) != report:
            raise ValueError('Release manifest does not match verified ZIP/source.')
    except (ValueError, OSError, zipfile.BadZipFile) as error:
        parser.exit(1, str(error) + '\n')
    print('Verified ZIP integrity, exact runtime contents, dependencies, versions, manifest and SHA-256.')
