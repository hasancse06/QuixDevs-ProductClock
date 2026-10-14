# WordPress.org presentation assets

The four unique icon/banner exports are tracked here. Screenshots have one tracked source under [docs/screenshots](../docs/screenshots/README.md), used directly by the GitHub README. Redundant WordPress.org copies are generated, not committed.

| Tracked artwork | Dimensions |
| --- | --- |
| [icon-128x128.png](icon-128x128.png) | 128 × 128 |
| [icon-256x256.png](icon-256x256.png) | 256 × 256 |
| [banner-772x250.png](banner-772x250.png) | 772 × 250 |
| [banner-1544x500.png](banner-1544x500.png) | 1544 × 500 |

## Generate the complete handoff

From the repository root:

```sh
python3 tools/export-wordpress-assets.py
```

The ignored **`dist/wordpress-org-assets/`** directory contains exactly nine PNGs: the four artworks above and these byte-identical copies of the real admin captures:

| Generated filename | Tracked source / readme caption |
| --- | --- |
| `screenshot-1.png` | [Product scheduling controls](../docs/screenshots/01-product-scheduling.png) |
| `screenshot-2.png` | [Dashboard](../docs/screenshots/02-dashboard.png) |
| `screenshot-3.png` | [Scheduled products](../docs/screenshots/03-scheduled-products.png) |
| `screenshot-4.png` | [Settings](../docs/screenshots/04-settings.png) |
| `screenshot-5.png` | [Activity](../docs/screenshots/05-activity.png) |

The order matches [readme.txt](../readme.txt). The exporter fails if any source is missing and does not alter artwork or interface pixels. It does not require Node.js or Composer. To edit/re-render unique artwork first, follow [the branding guide](../docs/branding/README.md), then rerun the export.

After WordPress.org acceptance and authorized SVN publication, copy the nine generated PNGs into the **top-level SVN `assets/` directory**, alongside `trunk/` and `tags/`. Do not put them in `trunk/assets`, a version tag or the runtime CSS/JS `assets` directory. Do not upload this README. See [the official asset specification](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/).

Both standard and retina banners are prepared. Set `svn:mime-type` to `image/png` if necessary. Directory assets are cached and shared across versions. None of these presentation assets belong in the installable plugin ZIP. Nothing has been uploaded or submitted by this export task. [Publishing checklist](../docs/github-publishing-checklist.md).
