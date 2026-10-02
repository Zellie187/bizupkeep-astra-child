# Legal pages

Restyled copies of the three legal documents (Privacy Policy, Refund Policy,
Terms & Conditions), matching the BizUpKeep Startup Stack brand palette (Ink
`#1B1A2E` / Coral `#FF5A5F`, matching `assets/css/custom.css`'s
`--bizupkeep-ink`/`--bizupkeep-coral` tokens) and carrying the full company
identity: A2Z Business Administrators t/a BizUpKeep, Reg No. 2017/182869/07.

These documents are still missing one real piece of information: a physical/
registered business address in the "Contact Us" section of each (marked with
a `[physical/registered business address to be added]` placeholder) - email
and phone are already correct and real.

## Auto-published

`bizupkeep_child_setup_legal_pages()` in `functions.php` publishes each
`*_fragment.html` file's content verbatim as a real WordPress page
(`/privacy-policy/`, `/terms-and-conditions/`, `/refund-policy/`), the same
idempotent create-once-on-activation pattern used for this theme's other
auto-created pages (Homepage, Apply, Contact, Startup Stack).

**Create-once, never overwritten**: once a page exists at one of those slugs,
this function will never touch its content again - so an edit made directly
in wp-admin (e.g. filling in the real address once it's known) survives every
future theme reactivation/upgrade. This also means **editing a `*_fragment.html`
file here does nothing to an already-published page** - to push an update
live, either edit the page's Custom HTML block directly in wp-admin, or
delete the page and let it regenerate from the (now-updated) fragment file on
the next page load.

## Two versions of each

- `A2Z_*.html` - the full, standalone `<html>` document (its own `<head>`,
  `<style>`, `<body>`, including a Google Fonts `<link>` for Space Grotesk/IBM
  Plex Sans). Useful as a reference/preview (open directly in a browser) -
  **not** used by `bizupkeep_child_setup_legal_pages()` and **must not** be
  pasted into the WordPress page editor - it would nest a second
  `<html>/<head>/<body>` inside the theme's own page markup
  (`header.php`/`footer.php` already open/close those tags), producing
  invalid HTML and a duplicated header on the page.
- `A2Z_*_fragment.html` - the same content with the `<!DOCTYPE>`/`<html>`/
  `<head>`/`<body>` wrapper stripped out (everything from `<style>` down to
  the closing `</footer>` only, relying on the theme's own already-enqueued
  Space Grotesk/IBM Plex Sans fonts). **This is the version
  `bizupkeep_child_setup_legal_pages()` publishes**, one per page:

  - `A2Z_Privacy_Policy_fragment.html` -> `/privacy-policy/`
  - `A2Z_Refund_Policy_fragment.html` -> `/refund-policy/`
  - `A2Z_Terms_and_Conditions_fragment.html` -> `/terms-and-conditions/`
