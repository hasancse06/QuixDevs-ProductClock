#!/usr/bin/env python3
"""Create a deterministic runtime-only archive; no development directories."""
from pathlib import Path
import zipfile
import hashlib

source = Path(__file__).resolve().parents[1]
destination = source.parent / 'quixdevs-productclock.zip'
files = [source / item for item in ('quixdevs-productclock.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'CHANGELOG.md')]
for directory in ('includes', 'assets', 'languages'):
    files.extend(p for p in (source / directory).rglob('*') if p.is_file() and not p.name.startswith('.'))
with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for file in sorted(files):
        relative = Path('quixdevs-productclock') / file.relative_to(source)
        entry = zipfile.ZipInfo(relative.as_posix(), (2026, 10, 9, 0, 0, 0))
        entry.compress_type = zipfile.ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, file.read_bytes())
with zipfile.ZipFile(destination) as archive:
    assert archive.testzip() is None
    assert 'quixdevs-productclock/quixdevs-productclock.php' in archive.namelist()
    assert all(name.startswith('quixdevs-productclock/') for name in archive.namelist())
    assert not any(part in name.split('/') for name in archive.namelist() for part in ('vendor', 'tests', 'docs', '.git', '.github', 'tools'))
print(f'{destination}: {len(files)} files, {destination.stat().st_size} bytes')
print('SHA256 ' + hashlib.sha256(destination.read_bytes()).hexdigest())
