# End-to-end tests

Covers the theme's critical client-facing flows against a real running WordPress site: submitting `/apply/` for all three workflow types, uploading a document, and the WooCommerce payment/pricing flows. Runs as plain HTTP requests (cURL) plus direct database assertions - no browser, no JavaScript execution. Everything these flows do is a classic WordPress form POST that never needed one.

This is deliberately **not** a substitute for the `bizhub`/`bizupkeep-workflow` plugins' own PHPUnit suites (unit/integration tests against an in-memory database, run with every change to those plugins). It exists because the theme itself - `functions.php`'s form handlers - had no automated coverage at all; every client-facing flow had only ever been verified by hand, re-derived from scratch each session.

## One-time setup

1. A WordPress site with `bizhub`, `bizupkeep-core`, and `bizupkeep-workflow` active, and WooCommerce active. The project's established local setup: portable MariaDB + `php -S localhost:8766` pointed at a WordPress checkout with the theme and plugins deployed - see the `bizupkeep_client_portal_buildout` memory entry ("Test infrastructure") for exactly how that's built if it doesn't already exist.
2. A WordPress user with the `subscriber` role (or any non-elevated role) to act as the test client. The client portal auto-provisions a `bizhub_clients` row the first time this user logs in through the site - log in as them once (e.g. via `wp user update <id> --user_pass=... && curl` a login POST, or just through a browser) before running the suite.
3. `composer install` in this directory.

## Running

```bash
composer install
vendor/bin/phpunit
```

Every environment value (`BIZUPKEEP_E2E_*`) has a default matching the project's established local setup (`http://localhost:8766`, the portable MariaDB on `127.0.0.1:33061`, database `bizupkeep_live_copy`, table prefix `cvqi2xqi_`, test client `testclient3`). Override any of them as environment variables to point the suite at a different site:

```bash
BIZUPKEEP_E2E_BASE_URL=https://staging.example.com \
BIZUPKEEP_E2E_DB_HOST=staging-db.example.com \
BIZUPKEEP_E2E_DB_PREFIX=wp_ \
vendor/bin/phpunit
```

See `Support/Config.php` for the full list of overridable values.

## What's covered (and what isn't)

- `CompanyRegistrationSubmissionTest` - New Company Registration submission, including a rejected-input case.
- `CompanyAmendmentSubmissionTest` - Company Amendment submission (address-change case).
- `AnnualReturnSubmissionTest` - Annual Return submission, asserting it stays at `Created` until staff send a quote.
- `DocumentUploadTest` - the My Documents upload form, including the actual file (multipart/form-data).
- `PaymentFlowTest` - the full real path from Amendment submission through both required document uploads to `AwaitingPayment`, then the "Pay Now" link, asserting it lands on checkout with the exact product matching the amendment's change type(s).

Not covered: the admin-side screens (`bizupkeep-workflow`'s own PHPUnit suite covers those at the unit level; this suite is theme-only), Company Amendment's director-change and combined-type cases, Annual Return's multi-year filing, and the Registration/Annual-Return payment flows (structurally identical patterns to what `PaymentFlowTest` already proves for Amendment - add tests here if a bug is ever found specifically in one of them).

## Test data

Every test creates its own fixture company/workflow with a unique name/registration number (`uniqid()`) rather than relying on or cleaning up shared state - safe to run repeatedly against the same database without interference, but note that **nothing gets cleaned up afterward**. Running this suite regularly against a database that matters will accumulate test companies/workflows/documents/orders indefinitely. Fine for the local disposable test database this was built against; do not point `BIZUPKEEP_E2E_*` at a real production database.
