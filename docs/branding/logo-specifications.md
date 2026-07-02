# Logo Specifications

All logo files should be SVG unless a vendor or channel requires another format.

## Required Files

* `logo.svg`: default logo for standard page, email, and product use.
* `logo-dark.svg`: logo variant designed for dark backgrounds.
* `logo-light.svg`: logo variant designed for light neutral backgrounds.
* `app-icon.svg`: square icon for application navigation, cards, launchers, and compact module identity.
* `favicon.svg`: square browser favicon.

## Sizing

Default logo artboards should use a horizontal layout. Current placeholders use `320x80`.

App icons and favicons should be square. Current placeholders use `128x128` for app icons and `64x64` for favicons.

## Naming

Use lowercase filenames exactly as listed in the required file set. Brand folder names should match the manifest slug in `private/config/brands.php`.

Do not encode version names in filenames. Replace a file in place only after the new asset has been approved.
