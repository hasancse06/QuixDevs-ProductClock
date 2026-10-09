# GitHub publishing checklist

This is a manual handoff, not publication authorization. No remote settings, pushes, tags, releases or WordPress.org submission occurred during presentation preparation.

## Local presentation

- [x] Review implemented features and original verification evidence.
- [x] Rewrite the GitHub README for store owners and developers, with real screenshots and ZIP installation instructions.
- [x] Update WordPress.org readme tags, short description and five screenshot captions without changing requirements or version.
- [x] Capture five genuine screenshots from the disposable local store using synthetic data.
- [x] Create social preview, original icons and standard WordPress.org banners.
- [x] Prepare About text, topics, release notes and manual publishing instructions.
- [x] Keep runtime code, runtime dependencies, version numbers and the existing ZIP unchanged.

See [the testing guide](testing.md) for recorded checks and remaining limits, [screenshots](screenshots/README.md) for capture details, and [branding](branding/README.md) for editable artwork.

## Before opening the public repository

- [ ] Confirm the GitHub owner/account and publishing permissions; use repository name `quixdevs-productclock`.
- [ ] Confirm a private security reporting channel. Enable GitHub private vulnerability reporting if appropriate; add confirmed contact details only.
- [ ] Review the source tree for secrets and local configuration, including ignored files before any forced add.
- [ ] Upload the **contents** of the prepared folder to the repository root, including hidden `.github/` and `.gitignore`. Do not upload the enclosing `quixdevs-productclock-github` folder or unrelated workspace files. Keep `vendor`, test databases and local configuration out of source control.
- [ ] Use [github-listing.md](github-listing.md) for About description, thirteen topics and social preview upload. Leave Website blank until a real destination exists.
- [ ] Confirm README relative links, screenshot rendering, collapsed gallery and mobile display in GitHub's preview.
- [ ] Run the configured GitHub CI matrix; expand compatibility checks against then-current WordPress/WooCommerce releases. Do not claim a green CI badge before it runs.
- [ ] Complete [the existing release checklist](release-checklist.md), including staging, browser/accessibility and large-catalog QA.

## Package the reviewed documentation

The repository contains source and presentation assets, not a prebuilt plugin ZIP. Release archives are ignored by Git and should be attached to GitHub Releases separately. Build a fresh archive from the reviewed repository so it includes the current readme.txt.

When preparing the final public package, explicitly run from the repository root:

```sh
python3 tools/build-release.py
unzip -l ../quixdevs-productclock.zip
```

The existing builder includes only bootstrap/uninstall, `includes`, runtime `assets`, `languages`, `readme.txt`, `LICENSE` and `CHANGELOG.md`. Presentation assets are in `docs/branding`, `docs/screenshots` and `wordpress-org-assets`, which the allowlist excludes. Do not copy them into runtime `assets`.

- [ ] Rebuild after the documentation review; record the new hash and file list.
- [ ] Extract into a clean disposable directory and run runtime-enabled Plugin Check; see [testing instructions](testing.md).
- [ ] Upload and activate that exact ZIP on a disposable or staging store with WooCommerce active.
- [ ] Confirm one plugin root, no vendor/tests/CI/local configuration/presentation images, and no functional differences.
- [ ] Inspect the updated readme with the official validator and confirm the publishing account's Contributors entry when known. Do not invent a WordPress.org username.

## Publish only when authorized

- [ ] Confirm the commit intended for release and tag it `v1.0.0` only after review.
- [ ] Create title **QuixDevs ProductClock v1.0.0 – WooCommerce Product Scheduler** using [the prepared release notes](github-release-notes.md).
- [ ] Attach the reviewed `quixdevs-productclock.zip` as an asset; verify its downloaded checksum matches the uploaded package.
- [ ] Replace future download instructions with the actual release URL after it exists. Keep fork-friendly relative documentation and screenshot paths.
- [ ] Check the release preview's documentation links; use confirmed repository URLs if relative release-body links resolve incorrectly.
- [ ] Verify GitHub About and Social Preview, then use the short introduction in [github-listing.md](github-listing.md).

## Future WordPress.org submission

- [ ] Confirm the publishing account, contributor username, product name/trademark availability and directory requirements. Acceptance is the directory team's decision.
- [ ] Recheck compatibility and Plugin Check before submission; retain truthful Tested up to information.
- [ ] Submit the reviewed runtime ZIP through the official directory workflow only when authorized.
- [ ] After acceptance and SVN access, copy the contents of `wordpress-org-assets/` **except its README** into the SVN repository's top-level `assets/`, alongside `trunk/` and `tags/`.
- [ ] Confirm screenshot 1–5 captions match the real images and review small/retina icons and banners. Assets are shared across versions and may be cached.
- [ ] Keep source artwork, publishing guides and presentation images out of the runtime archive. See [the asset handoff](../wordpress-org-assets/README.md).
