#!/usr/bin/env python3
"""Build the existing deterministic runtime allowlist into ignored dist/."""
from pathlib import Path
import argparse
import json
import os
import tempfile
import zipfile
from release_lib import SLUG, runtime_dependencies, runtime_files, versions, verify_archive


def build(source, tag=None):
    source = Path(source).resolve()
    versions(source, tag)
    runtime_dependencies(source)
    files = runtime_files(source)
    destination = source / 'dist'
    if destination.is_symlink():
        raise ValueError('dist/ must not be a symlink.')
    destination.mkdir(exist_ok=True)
    handle, temporary = tempfile.mkstemp(prefix='.productclock-', suffix='.tmp', dir=destination)
    os.close(handle)
    temporary = Path(temporary)
    try:
        with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for file in files:
                relative = Path(SLUG) / file.relative_to(source)
                entry = zipfile.ZipInfo(relative.as_posix(), (2026, 10, 9, 0, 0, 0))
                entry.compress_type = zipfile.ZIP_DEFLATED
                entry.external_attr = 0o100644 << 16
                archive.writestr(entry, file.read_bytes())
        report = verify_archive(source, temporary, tag)
        target = destination / (SLUG + '.zip')
        temporary.replace(target)
    finally:
        temporary.unlink(missing_ok=True)
    (destination / (SLUG + '.zip.sha256')).write_text(report['sha256'] + '  ' + target.name + '\n')
    (destination / 'release-manifest.json').write_text(json.dumps(report, indent=2) + '\n')
    return report


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag', help='Optional existing release tag, e.g. v1.0.0; must match source versions.')
    args = parser.parse_args()
    try:
        result = build(Path(__file__).resolve().parents[1], args.tag)
    except (ValueError, OSError, zipfile.BadZipFile) as error:
        parser.exit(1, str(error) + '\n')
    print('dist/' + result['archive'] + ': ' + str(result['file_count']) + ' files, ' + str(result['size_bytes']) + ' bytes')
    print('SHA256 ' + result['sha256'])
