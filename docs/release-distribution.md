# GitHub release distribution

ProductClock's GitHub repository is the complete development project. Customers install the lightweight **`quixdevs-productclock.zip`** attached to a published GitHub Release. The automatic **Source code (zip)** archive is a development checkout, not the supported customer package.

Repository and release-list URL were verified from the existing `origin` remote on October 14, 2026: [QuixDevs-ProductClock Releases](https://github.com/hasancse06/QuixDevs-ProductClock/releases). The listing was empty at verification time, so no nonexistent `/latest/download/` link is used. No tag, release, upload or push was performed during this local task.

## Build and verify

From the repository root, using Python 3.9 or later:

```sh
python3 tools/build-release.py --tag v1.0.0
python3 tools/verify-release.py --tag v1.0.0
unzip -t dist/quixdevs-productclock.zip
```

`--tag` is optional for local builds. When supplied, it must match the plugin header, runtime version constant, readme Stable tag and first changelog version. The workflow additionally verifies that this tag already exists and points at the exact checked-out commit. Version 1.0.0 is unchanged.

The existing deterministic ZIP builder is retained: an explicit runtime allowlist, one canonical root, a fixed ZIP timestamp and regular-file permissions. Its output is now normalized to ignored **`dist/`**, rather than the repository's parent directory. README, CI and maintainer commands have been updated. Existing parent-directory archives are left untouched.

Generated files:

| File | Purpose |
| --- | --- |
| `dist/quixdevs-productclock.zip` | Installable WordPress plugin |
| `dist/quixdevs-productclock.zip.sha256` | SHA-256 checksum, filename relative to the ZIP directory |
| `dist/release-manifest.json` | Version, dependencies, canonical root, file list, size and checksum |

Verify the checksum from `dist/` with `sha256sum --check quixdevs-productclock.zip.sha256` on Linux, or `shasum -a 256 --check quixdevs-productclock.zip.sha256` on macOS. The Python verifier works on both and also checks the sidecar and manifest against the ZIP/source.

## Package contents and dependencies

The single root is exactly **`quixdevs-productclock/`**, irrespective of the source repository's folder name. It contains:

- `quixdevs-productclock.php` and `uninstall.php`.
- All production PHP classes under `includes/`.
- Runtime `assets/css/admin.css` and `assets/js/editor.js`.
- Required `languages/` contents, including the translation template.
- `readme.txt`, `LICENSE` and `CHANGELOG.md`.

No tests, `.github`, docs, screenshots, WordPress.org presentation assets, Composer development packages, caches, local configuration, symlinks or nested ZIP archives are included. Unknown file extensions and hidden/development subdirectories are excluded. The verifier checks canonical entry names, exact source bytes, file sizes and modes, duplicates, traversal, encryption and CRCs.

There are **no production Composer package dependencies** in the current manifest. The plugin has its own PSR-4 loader; Composer is not needed on a customer's server. WordPress 6.8+, PHP 7.4+ and active WooCommerce 10.0+ are prerequisites. Action Scheduler is supplied by WooCommerce. The verifier checks these declarations and referenced own-namespace classes, and fails closed if a future production Composer package is added without an explicit production-only packaging plan. It never bundles the development `vendor/` directory as a workaround.

## Verified package — October 14, 2026

| Property | Value |
| --- | --- |
| Filename | `quixdevs-productclock.zip` |
| Canonical root | `quixdevs-productclock/` |
| Plugin version | 1.0.0 |
| Files | **31** |
| ZIP size | **44,105 bytes** (about 43.1 KiB) |
| SHA-256 | `9d9f6186bae47ff48cff1fc5c3e9f00401bc9cf0b7ca3dac9edf8cf4cc8c5785` |

These values describe this source revision. Rebuild and record new values after any distributable file changes. Repeated builds of the same production files were byte-identical. The manifest and checksum are integrity aids; they are not signed provenance or a guarantee of security.

## Tests actually performed

Local environment: **PHP 8.4.13, WordPress 6.8.3, WooCommerce 10.0.4, MySQL 8.0.46**. Tests used a newly initialized MySQL instance with networking disabled and separate disposable test/admin databases. No existing or production database was accessed.

- Existing WordPress/WooCommerce suite: **41 tests, 78 assertions, passed** on a brand-new disposable database.
- **13 release-tooling tests passed:** deterministic builds; excluded developer/temp files; missing assets/classes; production dependency and version/tag mismatches; symlinks; modified, duplicate, nested and traversal ZIP entries; corrupt CRC; and exact generated screenshot copies.
- All **26 PHP files** (24 production plus both existing test files) passed syntax checks. Production PHPCS passed without errors or warnings; Composer strict validation passed.
- The exact generated ZIP installed and activated through WordPress's installer using WP-CLI 2.12.0, with WooCommerce already active.
- The installed package initialized Action Scheduler, queued and completed real publication and expiration jobs, recorded activity and retained the product price. A real administrator could initialize/render the native ProductClock dashboard and Product Data controls.
- Runtime-enabled **Plugin Check 2.1.0**, including experimental checks, reported no findings on the installed runtime package.
- **Gitleaks 8.30.1** scanned full Git history, the current distributable source tree and the extracted runtime package with redacted output. A separate synthetic-token self-test verified scanner detection. No secrets were found in the project/package scans.
- **actionlint 1.7.12** validated both workflows locally. Runtime files, dependency manifests, translations, license, changelog and plugin version remained byte-for-byte unchanged.

A cold test run exposed stale cached WordPress role objects after WooCommerce installed its capabilities. The test bootstrap now refreshes those objects after role creation. Existing integration tests and assertions are retained; no runtime permissions or scheduling behavior were changed.

The updated GitHub workflow has **not run remotely**. Hosted draft creation, GitHub token policies and the remote PHP 7.4/8.2/8.4 matrix remain unverified in this task. Current-release combinations beyond the stated local baseline, large-store stress, other database configurations and browser/accessibility combinations remain subject to [the testing limits](testing.md). The package smoke check was performed through WP-CLI/native hooks, not a new browser upload session.

## Draft-release workflow

`.github/workflows/release.yml` accepts manual dispatch with an existing tag and `v*` tag pushes. It:

1. Checks out the requested existing tag, matches its commit and validates all version declarations.
2. Installs the pinned official Gitleaks binary after release checksum verification; scans all Git refs and checked-out source.
3. Runs the existing builder and independent verifier, CRC/checksum checks and an extracted-package secret scan; stores the three generated files as a temporary Actions artifact.
4. Reuses the existing CI workflow at that exact commit. Its PHP syntax/PHPCS checks, **PHP 7.4/8.2/8.4 integration matrix**, release-tooling tests and runtime Plugin Check must pass.
5. Downloads and checksum-verifies the tested artifact and creates a **draft** GitHub Release with the ZIP, checksum, manifest and prepared release notes.

Default and validation/CI permissions are `contents: read`. Only the final draft job receives `contents: write`; it does not execute repository scripts. Git checkout credentials are not persisted. No personal access token or separate secret-scanner action license is required. The notes body comes from [github-release-notes.md](github-release-notes.md); the job appends the actual package checksum.

Immediately before draft creation, the final job resolves the remote tag again and refuses to proceed if it no longer points at the tested commit. The command uses `gh release create --draft --verify-tag`. It cannot create a missing tag and never publishes a draft. If a release already exists for the tag, creation fails rather than overwriting or changing it. Re-running a failed preparation may require maintainer review of an existing draft; this workflow never deletes releases or force-replaces assets. See [GitHub CLI's release options](https://cli.github.com/manual/gh_release_create) and [token permission guidance](https://docs.github.com/en/actions/tutorials/authenticate-with-github_token).

## Maintainer release procedure

1. Review/commit the source, documentation and workflow changes. Complete [the release checklist](release-checklist.md), including compatibility checks appropriate for the intended users.
2. Independently approve the release version. This task does not approve a future version bump.
3. Create/push the approved matching tag through your normal reviewed process, or select an already existing tag through **Actions → Prepare draft release → Run workflow**. This local task creates no tag.
4. Wait for package, scanner and reused CI jobs to succeed. Review the resulting draft title, notes, exact ZIP and checksum. Do not upload the automatic source archive as the installable asset.
5. Test the attached ZIP on staging. Publish the draft manually only after release approval. Then check its public download and installation path. This automation never performs that publication step.

## WordPress installation

With WooCommerce already installed and active:

1. Open [GitHub Releases](https://github.com/hasancse06/QuixDevs-ProductClock/releases), select a **published** version and download `quixdevs-productclock.zip` from **Assets**.
2. Open **WordPress Admin → Plugins → Add Plugin → Upload Plugin**.
3. Choose the ZIP, select **Install Now**, then **Activate Plugin**.
4. Open **WooCommerce → ProductClock** and check scheduler health.
5. Edit a supported product in the classic editor and configure **Product Data → ProductClock**.

No Composer, Git or terminal tools are needed on the store. Draft releases and Actions artifacts are maintainer preparation outputs; customers should use the public release asset. Standard cron timing, manual override, editor, network activation and data retention limits are unchanged.

## WordPress.org presentation export

Only the five genuine screenshots under `docs/screenshots/` are tracked. Their former WordPress.org copies were byte-identical and have been removed; no unique screenshot, logo, icon or banner was deleted.

```sh
python3 tools/export-wordpress-assets.py
```

This generates exactly nine PNGs under ignored `dist/wordpress-org-assets/`: the four unique tracked icons/banners plus `screenshot-1.png` through `screenshot-5.png`, copied byte-for-byte from the canonical captures. README images and readme.txt captions retain the same order. Copy those generated PNGs to the top-level SVN `assets/` directory only when authorized. [Asset handoff](../wordpress-org-assets/README.md).
