"""Shared release checks. Customer installations do not load this tooling."""
from pathlib import Path, PurePosixPath
import hashlib
import json
import re
import zipfile

SLUG = 'quixdevs-productclock'
ROOT_FILES = ('quixdevs-productclock.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'CHANGELOG.md')
EXTENSIONS = {
    'includes': {'.php'},
    'assets': {'.css', '.js', '.svg', '.png', '.jpg', '.jpeg', '.webp', '.gif', '.woff', '.woff2', '.ttf'},
    'languages': {'.pot', '.po', '.mo', '.json', '.txt'},
}
REQUIRED_FILES = set(ROOT_FILES) | {'assets/css/admin.css', 'assets/js/editor.js', 'languages/quixdevs-productclock.pot', 'includes/Plugin.php', 'includes/Dependencies.php', 'includes/Activator.php', 'includes/Deactivator.php'}
EXCLUDED_PARTS = {'tests', 'test', 'docs', 'wordpress-org-assets', 'vendor', 'node_modules', 'tools', 'dist', 'build', '__pycache__'}


def require(condition, message):
    if not condition:
        raise ValueError(message)


def versions(source, tag=None):
    source = Path(source)
    bootstrap = (source / (SLUG + '.php')).read_text()
    header = re.search(r'^\s*\* Version:\s*(\S+)', bootstrap, re.M)
    constant = re.search(r"define\(\s*'QUIXDEVS_PRODUCTCLOCK_VERSION',\s*'([^']+)'", bootstrap)
    stable = re.search(r'^Stable tag:\s*(\S+)', (source / 'readme.txt').read_text(), re.M)
    change = re.search(r'^##\s+(\S+)', (source / 'CHANGELOG.md').read_text(), re.M)
    require(all((header, constant, stable, change)), 'Missing version header, constant, stable tag or changelog version.')
    version = header.group(1)
    require(re.fullmatch(r'\d+\.\d+\.\d+(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?', version), 'Invalid plugin version.')
    require(version == constant.group(1) == stable.group(1) == change.group(1), 'Plugin version, constant, stable tag and changelog must agree.')
    require(tag is None or tag == 'v' + version, 'Release tag must exactly match v' + version)
    require(re.search(r'^\s*\* Requires Plugins:\s*woocommerce\s*$', bootstrap, re.M), 'WooCommerce dependency header missing.')
    return version


def runtime_dependencies(source):
    composer = json.loads((Path(source) / 'composer.json').read_text())
    requirements = composer.get('require', {})
    external = [name for name in requirements if name != 'php' and not name.startswith('ext-')]
    require(not external, 'Unpackaged production Composer dependencies: ' + ', '.join(external) + '. Add an explicit production-only vendor packaging plan before releasing.')
    require(requirements.get('php') == '>=7.4', 'Review the PHP runtime baseline before releasing.')
    require(composer.get('autoload', {}).get('psr-4', {}).get('QuixDevs\\ProductClock\\') == 'includes/', 'Review the production autoloader before releasing.')
    # Every own-namespace class referenced by production imports must be packaged.
    for file in runtime_files(source):
        if file.suffix != '.php':
            continue
        code = file.read_text()
        references = re.findall(r'\buse\s+QuixDevs\\ProductClock\\([A-Za-z_\\]+)\s*;', code)
        references += re.findall(r'\\QuixDevs\\ProductClock\\([A-Za-z_\\]+)(?=::|\s*\()', code)
        for relative in references:
            dependency = Path(source) / 'includes' / (relative.replace('\\', '/') + '.php')
            require(dependency.is_file() and not dependency.is_symlink(), 'Missing autoloaded production class: ' + relative)
    return {'composer_packages': [], 'php': requirements['php'], 'woocommerce': '>=10.0 (store prerequisite)', 'action_scheduler': 'Provided by active WooCommerce; not bundled'}


def runtime_files(source):
    source = Path(source)
    files = []
    for name in ROOT_FILES:
        file = source / name
        require(file.is_file() and not file.is_symlink(), 'Missing or symlinked production file: ' + name)
        files.append(file)
    for directory, extensions in EXTENSIONS.items():
        base = source / directory
        require(base.is_dir() and not base.is_symlink(), 'Missing or symlinked runtime directory: ' + directory)
        for file in base.rglob('*'):
            relative = file.relative_to(source)
            if any(part.startswith('.') or part in EXCLUDED_PARTS for part in relative.parts):
                continue
            require(not file.is_symlink(), 'Symlinks are not distributable: ' + str(relative))
            if not file.is_file() or file.suffix.lower() not in extensions:
                continue
            if file.name.endswith(('~', '.bak', '.tmp')):
                continue
            files.append(file)
    names = {file.relative_to(source).as_posix() for file in files}
    require(REQUIRED_FILES <= names, 'Missing required production assets or translations.')
    return sorted(files)


def verify_archive(source, archive_path, tag=None):
    source, archive_path = Path(source), Path(archive_path)
    version = versions(source, tag)
    dependencies = runtime_dependencies(source)
    files = runtime_files(source)
    expected = {SLUG + '/' + file.relative_to(source).as_posix(): file for file in files}
    with zipfile.ZipFile(archive_path) as archive:
        names = archive.namelist()
        require(len(names) == len(set(names)), 'Duplicate ZIP entries.')
        for name in names:
            path = PurePosixPath(name)
            require(not path.is_absolute() and '..' not in path.parts and '\\' not in name, 'Unsafe ZIP entry: ' + name)
            require(name.startswith(SLUG + '/') and not name.endswith('/'), 'Noncanonical ZIP entry: ' + name)
        require(set(names) == set(expected), 'ZIP contents do not match the production allowlist.')
        for entry in archive.infolist():
            require(entry.file_size == expected[entry.filename].stat().st_size, 'Unexpected runtime file size: ' + entry.filename)
            require(entry.external_attr >> 16 == 0o100644, 'Nonregular or unexpected file mode: ' + entry.filename)
            require(not entry.flag_bits & 1, 'Encrypted runtime ZIP entry.')
        require(archive.testzip() is None, 'ZIP CRC verification failed.')
        for name, file in expected.items():
            require(archive.read(name) == file.read_bytes(), 'ZIP/source mismatch: ' + name)
    return {'plugin': SLUG, 'version': version, 'archive': SLUG + '.zip', 'root': SLUG + '/',
            'size_bytes': archive_path.stat().st_size, 'file_count': len(expected),
            'sha256': hashlib.sha256(archive_path.read_bytes()).hexdigest(),
            'runtime_dependencies': dependencies, 'files': sorted(expected)}
