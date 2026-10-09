# Future WordPress.org asset handoff

These are presentation assets for a future directory listing. Nothing has been uploaded or submitted. They are excluded from the runtime ZIP by the release builder's allowlist.

| File | Dimensions / purpose |
| --- | --- |
| [icon-128x128.png](icon-128x128.png) | 128 × 128 standard icon |
| [icon-256x256.png](icon-256x256.png) | 256 × 256 retina icon |
| [banner-772x250.png](banner-772x250.png) | 772 × 250 standard banner |
| [banner-1544x500.png](banner-1544x500.png) | 1544 × 500 retina banner |
| [screenshot-1.png](screenshot-1.png) | Product Data → ProductClock controls |
| [screenshot-2.png](screenshot-2.png) | Scheduling dashboard and scheduler health |
| [screenshot-3.png](screenshot-3.png) | Scheduled products and different schedule states |
| [screenshot-4.png](screenshot-4.png) | Settings, timezone and retention options |
| [screenshot-5.png](screenshot-5.png) | Successful publication/expiration and recovery activity |

Screenshots are byte-for-byte copies of [the genuine demo captures](../docs/screenshots/README.md). Their order matches [readme.txt](../readme.txt). They have been cropped for focus and privacy, without adding or changing interface elements.

After WordPress.org acceptance and authorized SVN publication, place these nine PNGs in the **top-level `assets/` directory**, alongside `trunk/` and `tags/`. Do not place them in `trunk/assets`, a version tag or the runtime plugin's CSS/JS `assets` directory. Do not upload this README as a presentation asset. See [the official asset specification](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/).

The standard and retina banners are both required for their intended display. Set `svn:mime-type` to `image/png` if necessary. Directory assets are cached and shared across versions. [Editable branding sources](../docs/branding/README.md) · [Publishing checklist](../docs/github-publishing-checklist.md).
