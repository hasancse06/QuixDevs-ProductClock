"""Packaging regression tests use disposable source copies, never a store/database."""
from pathlib import Path
import importlib.util
import json
import shutil
import sys
import tempfile
import unittest
import warnings
import zipfile

PROJECT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(PROJECT / 'tools'))
from release_lib import SLUG, runtime_files, verify_archive, versions


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, PROJECT / 'tools' / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


builder = load('release_builder', 'build-release.py')
exporter = load('asset_exporter', 'export-wordpress-assets.py')


class DistributionTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.source = Path(self.temporary.name) / 'arbitrary-repository-folder'
        for file in runtime_files(PROJECT) + [PROJECT / 'composer.json']:
            dest = self.source / file.relative_to(PROJECT)
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(file, dest)

    def test_deterministic_canonical_package_and_checksum(self):
        first = builder.build(self.source, 'v1.0.0')
        path = self.source / 'dist' / (SLUG + '.zip')
        data = path.read_bytes()
        self.assertEqual(first, builder.build(self.source, 'v1.0.0'))
        self.assertEqual(data, path.read_bytes())
        self.assertFalse((self.source.parent / (SLUG + '.zip')).exists())
        self.assertEqual(first, verify_archive(self.source, path, 'v1.0.0'))
        self.assertEqual(first, json.loads((path.parent / 'release-manifest.json').read_text()))
        self.assertEqual(first['sha256'] + '  ' + path.name + '\n', path.with_suffix('.zip.sha256').read_text())

    def test_development_and_temporary_files_are_excluded(self):
        extras = ['tests/foo.php', '.github/workflows/other.yml', 'docs/image.png', 'wordpress-org-assets/image.png', 'vendor/package/foo.php', 'assets/nested.zip', 'assets/debug.log', 'assets/.private/secret.js', 'includes/tests/not-runtime.php', 'includes/foo.php.tmp', 'languages/backup.zip', 'includes/.DS_Store']
        for name in extras:
            file = self.source / name
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text('not production')
        result = builder.build(self.source)
        for name in extras:
            self.assertNotIn(SLUG + '/' + name, result['files'])
        self.assertFalse(any('/vendor/' in name for name in result['files']))

    def test_missing_required_asset_fails(self):
        (self.source / 'assets/js/editor.js').unlink()
        with self.assertRaisesRegex(ValueError, 'Missing required'):
            builder.build(self.source)

    def test_missing_autoloaded_class_fails(self):
        (self.source / 'includes/Support/Lock.php').unlink()
        with self.assertRaisesRegex(ValueError, 'Missing autoloaded'):
            builder.build(self.source)

    def test_runtime_symlink_fails(self):
        (self.source / 'includes/linked.php').symlink_to(self.source / 'uninstall.php')
        with self.assertRaisesRegex(ValueError, 'Symlinks'):
            builder.build(self.source)

    def test_new_production_dependency_cannot_be_silently_omitted(self):
        path = self.source / 'composer.json'
        data = json.loads(path.read_text())
        data['require']['example/new-runtime-library'] = '^1.0'
        path.write_text(json.dumps(data))
        with self.assertRaisesRegex(ValueError, 'Unpackaged production Composer'):
            builder.build(self.source)

    def test_version_and_tag_mismatch_fail(self):
        for tag in ['v9.0.0', 'master', 'v1.0.0\ninjected=1']:
            with self.assertRaisesRegex(ValueError, 'Release tag'):
                versions(self.source, tag)
        path = self.source / 'readme.txt'
        path.write_text(path.read_text().replace('Stable tag: 1.0.0', 'Stable tag: 9.0.0'))
        with self.assertRaisesRegex(ValueError, 'must agree'):
            builder.build(self.source)

    def test_nested_zip_entry_fails(self):
        builder.build(self.source)
        path = self.source / 'dist' / (SLUG + '.zip')
        with zipfile.ZipFile(path, 'a') as archive:
            archive.writestr(SLUG + '/nested.zip', b'archive')
        with self.assertRaisesRegex(ValueError, 'allowlist'):
            verify_archive(self.source, path)

    def test_modified_archive_bytes_fail(self):
        builder.build(self.source)
        path = self.source / 'dist' / (SLUG + '.zip')
        with zipfile.ZipFile(path) as archive:
            contents = [(entry, archive.read(entry.filename)) for entry in archive.infolist()]
        with zipfile.ZipFile(path, 'w') as archive:
            for entry, data in contents:
                if entry.filename == SLUG + '/uninstall.php':
                    data = b'x' * len(data)
                archive.writestr(entry, data)
        with self.assertRaisesRegex(ValueError, 'ZIP/source mismatch'):
            verify_archive(self.source, path)

    def test_duplicate_zip_entry_fails(self):
        builder.build(self.source)
        path = self.source / 'dist' / (SLUG + '.zip')
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', UserWarning)
            with zipfile.ZipFile(path, 'a') as archive:
                archive.writestr(SLUG + '/uninstall.php', b'duplicate')
        with self.assertRaisesRegex(ValueError, 'Duplicate ZIP'):
            verify_archive(self.source, path)

    def test_corrupt_crc_fails(self):
        builder.build(self.source)
        path = self.source / 'dist' / (SLUG + '.zip')
        data = bytearray(path.read_bytes())
        central = data.index(b'PK\x01\x02')
        data[central + 16] ^= 0xff  # Alter only the central-directory CRC.
        path.write_bytes(data)
        with self.assertRaisesRegex(ValueError, 'CRC verification failed'):
            verify_archive(self.source, path)

    def test_traversal_entry_fails(self):
        builder.build(self.source)
        path = self.source / 'dist' / (SLUG + '.zip')
        with zipfile.ZipFile(path, 'a') as archive:
            archive.writestr('../escape.php', b'bad')
        with self.assertRaisesRegex(ValueError, 'Unsafe ZIP'):
            verify_archive(self.source, path)

    def test_asset_export_uses_one_canonical_screenshot_source(self):
        for directory in ['docs/screenshots', 'wordpress-org-assets']:
            shutil.copytree(PROJECT / directory, self.source / directory)
        destination = exporter.export(self.source)
        self.assertEqual(9, len(list(destination.iterdir())))
        for index, name in enumerate(exporter.SCREENSHOTS, 1):
            self.assertEqual((self.source / 'docs/screenshots' / name).read_bytes(), (destination / ('screenshot-' + str(index) + '.png')).read_bytes())
        self.assertEqual((self.source / 'dist/wordpress-org-assets').resolve(), destination)
        package = builder.build(self.source)
        self.assertFalse(any('screenshot' in name for name in package['files']))


if __name__ == '__main__':
    unittest.main()
