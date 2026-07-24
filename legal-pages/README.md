# Legal pages

Source copies of the three standalone legal documents (Privacy Policy, Refund Policy,
Terms & Conditions), restyled to match the BizUpKeep brand palette (`#1c3d5a` navy /
`#f2994a` orange, matching `assets/css/custom.css`) and carrying the full company
identity: A2Z Business Administrators t/a BizUpKeep, Reg No. 2017/182869/07.

These are tracked here for versioning only - the theme does not load or reference
them automatically. Each is a complete, self-contained `<html>` document (its own
`<style>` block, its own banner/footer) meant to be applied to the matching live
WordPress page:

- `A2Z_Privacy_Policy.html` -> "Privacy Policy" page
- `A2Z_Refund_Policy.html` -> "Refund policy" page
- `A2Z_Terms_and_Conditions.html` -> "Terms and Conditions" page

As of this commit, all three of those pages are **empty** on the live site (no
`post_content` at all) - nothing is currently overwritten by applying these.

**Before pasting one into the WordPress page editor**, note these files are full
`<html><head><body>` documents. Dropping one as-is into a normal page's content
would nest a second `<html>/<head>/<body>` inside the theme's own page markup
(the theme's `header.php`/`footer.php` already open/close those tags) - invalid
HTML, and the page would render with two overlapping headers. Either:

1. Strip the outer `<!DOCTYPE>`/`<html>`/`<head>`/`<body>` wrapper (keep the
   `<style>` block plus everything from `<div class="top-banner">` down to the
   closing `</footer>`) and paste that fragment into a Custom HTML block, or
2. Give the page a "blank canvas" template with no site header/footer, and use
   the file exactly as-is.

Not done automatically here since it changes how the pages render sitewide -
flag if you'd like this wired up as a proper page template instead.
