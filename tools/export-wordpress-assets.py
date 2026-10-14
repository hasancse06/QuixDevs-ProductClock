#!/usr/bin/env python3
"""Generate a complete, ignored WordPress.org SVN asset handoff."""
from pathlib import Path
import argparse
import shutil

SCREENSHOTS = ('01-product-scheduling.png', '02-dashboard.png', '03-scheduled-products.png', '04-settings.png', '05-activity.png')
ARTWORK = ('icon-128x128.png', 'icon-256x256.png', 'banner-772x250.png', 'banner-1544x500.png')


def export(source):
    source = Path(source).resolve()
    mappings = [(source / 'wordpress-org-assets' / name, name) for name in ARTWORK]
    mappings += [(source / 'docs/screenshots' / name, 'screenshot-' + str(i) + '.png') for i, name in enumerate(SCREENSHOTS, 1)]
    if any(not file.is_file() or file.is_symlink() for file, _ in mappings):
        raise ValueError('Missing or symlinked source screenshot/artwork; nothing exported.')
    destination = source / 'dist/wordpress-org-assets'
    if (source / 'dist').is_symlink() or destination.is_symlink():
        raise ValueError('Asset export destination must not be a symlink.')
    destination.mkdir(parents=True, exist_ok=True)
    expected = {name for _, name in mappings}
    if any(file.name not in expected for file in destination.iterdir()):
        raise ValueError('Unexpected files in asset export directory; review them before exporting.')
    for file, name in mappings:
        shutil.copyfile(file, destination / name)
    return destination


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.parse_args()
    try:
        result = export(Path(__file__).resolve().parents[1])
    except (ValueError, OSError) as error:
        parser.exit(1, str(error) + '\n')
    print('Exported nine PNGs to ' + str(result))
