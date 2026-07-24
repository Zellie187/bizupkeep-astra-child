# Legal pages

Restyled copies of the three legal documents (Privacy Policy, Refund Policy,
Terms & Conditions), matching the BizUpKeep brand palette (`#1c3d5a` navy /
`#f2994a` orange, matching `assets/css/custom.css`) and carrying the full company
identity: A2Z Business Administrators t/a BizUpKeep, Reg No. 2017/182869/07.

Tracked here for versioning only - the theme does not load or reference these
automatically.

## Two versions of each

- `A2Z_*.html` - the full, standalone `<html>` document (its own `<head>`,
  `<style>`, `<body>`). Useful as a reference/preview (open directly in a
  browser) but **do not** paste this version into the WordPress page editor -
  it would nest a second `<html>/<head>/<body>` inside the theme's own page
  markup (`header.php`/`footer.php` already open/close those tags), producing
  invalid HTML and a duplicated header on the page.
- `A2Z_*_fragment.html` - the same content with the `<!DOCTYPE>`/`<html>`/
  `<head>`/`<body>` wrapper stripped out (everything from `<style>` down to
  the closing `</footer>` only). **This is the version to paste** into a
  Custom HTML block on the matching WordPress page:

  - `A2Z_Privacy_Policy_fragment.html` -> "Privacy Policy" page
  - `A2Z_Refund_Policy_fragment.html` -> "Refund policy" page
  - `A2Z_Terms_and_Conditions_fragment.html` -> "Terms and Conditions" page

As of the last check, all three of those pages are **empty** on the live site
(no `post_content` at all) - nothing is currently overwritten by applying these.
