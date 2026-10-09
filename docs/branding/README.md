# ProductClock branding

Presentation artwork for QuixDevs ProductClock, using the maintainer-supplied [QuixDevs logo](quixdevs-logo.png) in the social preview and WordPress.org banners. Navy `#091827`, teal `#35e2cc`, white `#f4fbff` and muted blue `#b3c8d7`. The clock ring, detached square marker and clock hands form an original product mark; no WordPress or WooCommerce logos are used.

The supplied transparent PNG is preserved byte-for-byte at its original 164 × 45 pixels. Its colors and proportions are unchanged. SVG compositions embed that exact image, so exports do not depend on a local Desktop path or an external image URL. The original ProductClock clock mark remains the product icon.

The social preview and WordPress.org banners include the creator credit **Created by M A Hasan**.

## Editable sources and exports

| Source | Export |
| --- | --- |
| [quixdevs-logo.png](quixdevs-logo.png) | Original supplied QuixDevs wordmark |
| [github-social-preview.svg](github-social-preview.svg) | [github-social-preview.png](github-social-preview.png), 1280 × 640 |
| [wordpress-banner.svg](wordpress-banner.svg) | [772 × 250 banner](../../wordpress-org-assets/banner-772x250.png), [1544 × 500 banner](../../wordpress-org-assets/banner-1544x500.png) |
| [productclock-mark.svg](productclock-mark.svg) | [128 × 128 icon](../../wordpress-org-assets/icon-128x128.png), [256 × 256 icon](../../wordpress-org-assets/icon-256x256.png) |

SVG typography uses Arial, Helvetica or sans-serif. PNGs are already rendered and visually checked; consumers do not need fonts or rendering dependencies. Do not stretch exports. The project uses the repository's [GPL-2.0-or-later license](../../LICENSE). The QuixDevs logo is the supplied maintainer branding.

To reproduce with Node.js, Sharp and Arial installed, run `node tools/render-branding.mjs` from the repository root. Sharp can be installed in a temporary external development directory; set `QPC_SHARP_MODULE` to its absolute module path if it is not resolvable from this project. This is optional artwork tooling and adds no runtime or Composer dependency. You may also export the SVGs with a vector editor at the exact documented dimensions.

Upload the social preview manually through **GitHub Repository → Settings → Social Preview → Edit → Upload an image** after publication is authorized. See [GitHub's official instructions](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/customizing-your-repositorys-social-media-preview). The opaque PNG is under 1 MB.

Keep all presentation artwork out of the runtime `assets/` folder and ZIP. [WordPress.org handoff](../../wordpress-org-assets/README.md).
