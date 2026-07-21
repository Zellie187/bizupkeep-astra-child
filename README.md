# BizUpKeep Astra Child

A child theme of [Astra](https://wordpress.org/themes/astra/) for `bizupkeep.co.za` ("A2Z Business Administrators"). Provides the site's front end — homepage, header/footer, Client Portal pages, and the client-facing application intake flow (`/apply/`) — on top of the [BizHub](https://github.com/Zellie187/Bizplugins) plugin stack (`bizhub` + `bizupkeep-core` + `bizupkeep-workflow`).

## Requires

- WordPress with the Astra parent theme active
- `bizhub` 0.2.5+, `bizupkeep-workflow` 1.1.0+ (for `Director` contact/address fields and the Company Amendment/Annual Return workflow types the apply form starts)
- WooCommerce (payment flow, homepage packages)

## Layout

- `functions.php` — theme setup, Client Portal page/menu provisioning, the apply-form handlers (New Registration / Company Amendment / Annual Return), document upload, WooCommerce payment integration.
- `page-templates/` — `template-homepage.php`, `template-apply.php`, `template-documents.php`, `template-applications.php`.
- `assets/css/custom.css`, `assets/js/custom.js` — all front-end styling/behaviour; no build step.
- `changelog.md` — version history; each release bumps `style.css`'s `Version:` header and `BIZUPKEEP_CHILD_VERSION` in `functions.php` together.

## Releasing

No build step - zip the theme directory directly with `astra-child/` as the top-level folder inside the archive, so it can be uploaded straight into `wp-content/themes/` via WordPress admin (Appearance → Themes → Add New → Upload Theme).

## Deploying a new version to the live site

After activating (or re-uploading over) the theme:

1. **Settings → Reading**: "Your homepage displays" must be set to "A static page", with the "Home" page selected. Not set automatically by the theme - a fresh install (or a site that's never had this configured) will show the default post loop instead of `template-homepage.php`.
2. Confirm the "Home" page's template (Page Attributes) is "BizUpKeep Homepage" - `after_switch_theme` only assigns templates to pages *it* creates (Client Portal, Apply); it does not touch a pre-existing "Home" page.
3. The Client Portal, Apply, My Documents, and My Applications pages are created/updated idempotently on theme (re-)activation - safe to reactivate.
