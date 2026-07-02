# Brand Asset Structure

Brand assets live under `public/assets/brands/{brand-slug}/`.

Current standardized brand folders:

* `ultimate-back-office`
* `lead-hub`
* `247sp`
* `supersimplepayments`
* `tellushowwedid`
* `emd`

Each brand folder should include:

* `logo.svg` for the default brand logo
* `logo-dark.svg` for dark backgrounds
* `logo-light.svg` for light neutral backgrounds
* `favicon.svg` for browser tabs
* `app-icon.svg` for compact product navigation and app surfaces
* `social-placeholder.md` until an approved social image is added

Shared placeholder assets live under `public/assets/placeholders/`. Shared UI artwork that is not brand-specific belongs under `public/assets/ui/`, grouped into `icons`, `illustrations`, and `badges`.

## Future Modules

To add a future module, create `public/assets/brands/{module-slug}/` with the required files above, then add the module entry to `private/config/brands.php`. The application shell should use the module `app_icon` or `favicon` path for sidebar navigation rather than introducing one-off HTML.

## Existing UI Paths

The older `public/app/assets/img/` and `public/accounts/assets/img/` paths remain in place for compatibility. Do not delete or replace those files until each consuming page has been migrated safely.
