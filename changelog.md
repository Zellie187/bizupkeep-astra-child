# BizUpKeep Astra Child - Changelog

## 1.12.0
- **Company Registration name-rejection loop**: when a staff reviewer uses bizupkeep-workflow 1.3.0's new "Reject - Name Not Approved" action, the client now sees the application on My Applications with status "Name Not Approved - Please Resubmit", the reviewer's notes (why the name wasn't approved), and a small form to submit up to 4 new proposed names - no need to start a whole new application. Submitting moves the application back to "In Review" and it reappears in staff's Quality Review queue with the new names.
- New helper `bizupkeep_child_latest_rejection_reason()` reads a workflow's transition history to surface the most recent `reject_name` action's notes to the client; `bizupkeep_child_applications_sections()` gained `workflow_uuid`, `needs_resubmission`, and `rejection_reason` keys to support this.
- Verified end-to-end against the production DB copy: admin rejects proposed names with notes -> client sees the notes and resubmits -> application returns to Quality Review with the new names in its metadata -> admin approves normally.

## 1.11.0
- Company Amendment and Annual Return applications can now be filed for a company that isn't already registered with BizUpKeep (e.g. registered elsewhere, or before this platform existed). The "Which Company?" step on `/apply/` is now a radio choice between "an existing company (already registered with us)" and "a company not registered with us"; the latter reveals a small subform (company name, CIPC registration number, registered address) instead of the existing-company picker dropdown. Defaults to whichever mode actually has something to select - "new" if the client has no companies yet, "existing" otherwise.
- New shared helper `bizupkeep_child_render_company_picker()` renders this toggle for both application types; `bizupkeep_child_resolve_company_for_submission()` reads the submitted mode and either looks up the picked company (existing ownership-verified path, unchanged) or creates one via the new `bizupkeep_child_create_external_company()` (`CompanyStatus::ACTIVE`, the client-provided real registration number - not a placeholder - Private Company (Pty) Ltd only, matching the rest of the system's PTY-only scope).
- Verified end-to-end against the production DB copy: both a Company Amendment and an Annual Return filed in "new company" mode create a correctly attributed `Company` row and start the matching workflow with the right metadata; re-selecting an already-registered company in "existing" mode still attaches to that same company without creating a duplicate.

## 1.10.0
- **Fixed a real bug**: My Documents, My Applications, and the WooCommerce payment flow all used to pick "the" workflow for a company by taking whichever instance was created most recently, regardless of type - correct back when Company Registration was the only workflow type a company could have, wrong now that a company can simultaneously have (for example) a completed registration and a fresh amendment. Uploads, status displays, and payment confirmation could silently target the wrong application.
- **My Documents and My Applications are now per-application, not per-company**: each now shows one row/section per workflow instance (with a Type badge), across all three workflow types. Verified live: a company with a completed registration, a completed annual return, and an in-review amendment now correctly shows all three as separate rows, where it previously would have shown only one.
- The document-upload form now carries the specific application's workflow UUID (not just the company UUID), so uploads and the automatic PendingDocuments → DocumentsVerified → AwaitingPayment advance are scoped to the exact application being worked on. Verified end-to-end via real HTTP upload requests: uploading to one company's application left a second, unrelated in-progress application completely untouched.
- The WooCommerce "Pay Now" flow (session key, order meta) is now keyed by workflow UUID instead of company UUID for the same reason; this also means Annual Return and Company Amendment applications sitting in AwaitingPayment now get a working "Pay Now" link through the same mechanism Company Registration already had (previously they may have received the wrong company's payment confirmation, or none).
- New shared helpers (`bizupkeep_child_get_owned_workflow_instance()`, `bizupkeep_child_workflow_type_service()`, `bizupkeep_child_workflow_type_label()`, `bizupkeep_child_client_workflow_instances()`) replace the old "guess the company's workflow" helpers, mirroring the same `WorkflowTypeServiceInterface`-based dispatch pattern bizupkeep-workflow 1.2.0's Quality Review screen already established.
- My Documents deliberately excludes Annual Return applications - that workflow type has no PendingDocuments stage or document requirement.

## 1.9.0
- Homepage fully built out from the approved `bizupkeep-homepage-copy.md` pass: How It Works (4 steps), Why BizUpKeep (4 features), FAQ (5 questions, native `<details>` accordion, first one open), and a footer CTA section with phone/email/WhatsApp and Privacy Policy/Terms & Conditions links, on top of the existing Hero and Packages sections.
- **Fixed a pre-existing bug**: the Packages section previously called `do_shortcode( '[bizupkeep_packages ...]' )`, but that shortcode was never actually registered anywhere in this codebase - the section silently rendered nothing. Replaced with 4 hand-coded pricing cards (New Company Registration, Company Amendments, Annual Returns, Bookkeeping Services) matching the approved copy's exact pricing/bullets, each linking to `/apply/`.
- Added `.bizupkeep-hero-eyebrow`, `.bizupkeep-steps-grid`/`.bizupkeep-step-card`/`.bizupkeep-step-number`, `.bizupkeep-faq*`, and `.bizupkeep-cta-contact`/`.bizupkeep-cta-links` styles to `assets/css/custom.css`; reused the existing `.bizupkeep-package-*` card styles (previously unused, since the shortcode that would have populated them never existed).
- **Requires a Settings → Reading change to actually take effect**: "Your homepage displays" must be set to "A static page" with the "Home" page selected, and the "Home" page's template set to "BizUpKeep Homepage" - neither was configured on the site this was tested against, so the homepage was previously showing the default "Hello world" post regardless of this template's content. Set directly on the test site for verification; needs the same change made on the live site when this deploys.
- Product catalogue imported into WooCommerce (10 real BizUpKeep products from `bizupkeep-products.csv`, replacing 3 generic placeholder products which were set to draft rather than deleted) - see the site's WooCommerce admin, not a theme file change.

## 1.8.0
- `/apply/` is now a single, JS-toggled page covering all three application types instead of just Company Registration:
  - **New Company Registration**: up to 4 proposed company names (in order of preference), registered address, and 1-10 directors via an "+ Add Director" repeater - each with full name, SA ID/passport, phone, email, and residential address. Private Company (Pty) Ltd only; the entity-type dropdown (which used to offer Close Corporation) is gone entirely, since CIPC no longer registers new CCs.
  - **Company Amendment**: pick one of your existing companies, tick any combination of Director / Name / Address change, and the matching fields appear - 4 proposed names for a name change, a "tick to remove" list of the selected company's current directors plus an "add new director(s)" repeater for a director change, a full new-address form for an address change.
  - **Annual Return**: pick an existing company and a financial year.
  - Backed by bizupkeep-workflow 1.1.0's new `CompanyAmendmentService`/`AnnualReturnService` (called directly via the shared container, same pattern as Company Registration) and `CompanyRegistrationService::start()`'s new optional metadata parameter (carries the 4 proposed names). Requires bizhub 0.2.5 (`Director` gained `phone`/`email`/`address` fields, needed by the director repeater) and bizupkeep-workflow 1.1.0.
  - Verified end-to-end against the production DB copy for all three types, including the Annual Return duplicate-filing rejection.
  - **Known gap**: neither BizHub's Quality Review screen nor its Workflows admin list currently show Company Amendment or Annual Return applications (both are hardcoded to Company Registration) - staff have no admin-side way to review/act on the new two types yet beyond direct database access. Flagged as the next priority, not addressed in this release.

## 1.7.0
- Client Portal nav restructured: Dashboard, My Companies, My Documents, My Applications, and My Profile no longer each get their own top-level slot in the portal bar - they're now all children of a single "Client Portal" dropdown item. `bizupkeep_child_sync_client_portal_menu()` migrates sites that already ran the old flat menu (re-parenting the existing five items in place, no duplicates) as well as fresh installs.
- The parent "Client Portal" item is a custom link (to the dashboard page's URL) rather than a page-object item pointing at the dashboard page directly - Dashboard is also one of the five children, and a page-object item can only represent one menu entry per page, so the two would otherwise collide into a single item.
- Added dropdown styling (`.sub-menu`) to `assets/css/custom.css`: shown on hover/focus on desktop, and via a new tap-to-toggle handler in `assets/js/custom.js` for touch devices (gated to `(hover: none)` / narrow viewports, so desktop mouse clicks on the parent link still navigate directly in one click).

## 1.6.0
- New "My Applications" page template: one row per company with its current status, and a "Pay Now" link while AwaitingPayment.
- After documents are verified, the workflow now also auto-advances DocumentsVerified -> AwaitingPayment (`ACTION_REQUEST_PAYMENT` has no guard, so there's no reason to wait for a separate trigger).
- Real WooCommerce payment integration, built from scratch - BizHub's existing `Integrations/WooCommerce/` (ApplicationCreator/OrderListener/ProductMapper) is a separate, unrelated system that creates new Applications from any mapped-product purchase with no link to a company or workflow at all, so this doesn't touch or reuse it:
  - "Pay Now" links to the real "Company Registration Packages" product category archive (the same one the homepage's `[bizupkeep_packages]` shortcode already uses) with the company UUID attached as a query arg.
  - Visiting that link stores the company UUID in the WooCommerce session (after verifying it belongs to the logged-in client and is actually AwaitingPayment).
  - Whichever package the client buys, the session value is copied onto the resulting order as post meta at checkout (re-verified against ownership again, since a session value could otherwise be replayed across accounts).
  - When that order's status changes to processing/completed, payment is confirmed on the company's workflow automatically, using the order ID as `CompanyRegistrationGuard`'s required `context['payment_reference']`. Idempotent - won't double-confirm on a later status change (e.g. processing -> completed).
- Verified against a real WooCommerce checkout (Cash on Delivery, for testing) against the production database copy, not simulated: full chain from application submission through paid confirmation, all four workflow transitions recorded with correct reasons and payment reference.

## 1.5.0
- New "My Documents" page template: one section per company the logged-in client has an application for, each showing its current workflow status, already-uploaded documents, and (only while the workflow is waiting on documents) an upload form.
- Submitting the Apply form now also fires `ACTION_REQUEST_DOCUMENTS`, moving the new workflow from `Created` to `PendingDocuments` immediately - previously it just sat at `Created` with nothing to advance it.
- Uploading both required document categories (ID Document, Proof of Address) for a company automatically fires `ACTION_VERIFY_DOCUMENTS`, moving it to `DocumentsVerified` - no admin action needed for this step. `CompanyRegistrationGuard` only checks a boolean flag with no awareness of real Document rows, so this is new glue code, not something BizHub already did.
- File uploads are validated server-side: extension whitelist (pdf/jpg/jpeg/png), 5MB size limit, `is_uploaded_file()` check, and the *actual* detected MIME type (not the client-supplied header) must match the extension. Also verifies the uploaded-to company actually belongs to the logged-in client's account before accepting anything.
- Uses `BizHub\Documents\Services\DocumentService::uploadDocument()` directly - there is no REST or admin upload route anywhere in BizHub to call instead (confirmed: `Documents/Controllers/DocumentController.php`'s own docblock says upload handling is left to the caller), and BizHub's `Storage` module is an unbuilt stub.
- Requires the `fileinfo` PHP extension for MIME-type detection (standard on virtually all hosting, including Plesk) - without it, uploads are rejected rather than silently accepted with an unverified type.

## 1.4.0
- Submitting the Apply form now also creates the actual `bizhub_companies` record and starts the "Company Registration" BizUpKeep Workflow instance for it, linking company <-> application <-> workflow via matching UUIDs.
- Since a real CIPC registration number doesn't exist until the company is actually registered (the point of the workflow), the company is created with `CompanyStatus::CREATED` and a per-company-unique placeholder registration number (`PENDING-{uuid}`) - both already-supported patterns in BizHub's Companies module (see `CompanyService::updateCompany()` for swapping in the real number later). The registered address isn't collected on this form either, so it gets a clearly marked "To be confirmed" placeholder for the three fields `RegisteredAddress` requires non-empty, pending the workflow's own document-collection phase.
- Requires BizHub 0.2.4+ (`Client::getId()`).
- Note: company/application/workflow creation isn't wrapped in a DB transaction (the framework doesn't expose one, and no other module in this codebase uses one) - a mid-flow failure can leave a Company/Application without a WorkflowInstance. Recoverable manually; not automatic.

## 1.3.0
- Client Portal pages (the "Client Portal" page and its four children) now require login: logged-out visitors are redirected to `wp-login.php` with a `redirect_to` back to the page they wanted.
- On first portal visit, automatically creates the visitor's linked BizHub Client record via `ClientServiceInterface::createClient()` (the same service layer `ClientDashboardController` and the `/profile` REST endpoint already use) if one doesn't exist yet. Name is taken from the WordPress user's first/last name meta, falling back to `display_name` then `user_login` if those are empty. Idempotent - safe on every page load, never creates a duplicate.
- Note: the "Start Application" buttons in the header and homepage template link to `/apply/`, which doesn't exist yet - that's a pre-existing gap, not addressed in this release.

## 1.2.0
- Added a "Client Portal" navigation menu (`bizupkeep-client-portal` location), rendered as a slim utility bar above the main header via `wp_body_open`.
- On theme activation, idempotently creates four Client Portal child pages (My Companies, My Documents, My Applications, My Profile) nested under the existing "Client Portal" page, and a menu linking Dashboard + all four. Re-activating the theme never creates duplicates; the existing "Client Portal" page is reused, never overwritten.
- Placeholder content on the new pages reuses the existing Member Portal CSS (`.bizupkeep-status-pill`, `.bizupkeep-documents-table`, `.bizupkeep-applications-table`) instead of plain text, so it matches the site's actual design language pending the real front-end (BizHub's `ClientPortal` module currently only exposes REST endpoints, no templates).
- Added `.bizupkeep-portal-bar` / `.bizupkeep-portal-menu` styles to `assets/css/custom.css`.

## 1.1.0
- Homepage template's "Our Packages" section now renders `[bizupkeep_packages]` (live WooCommerce pricing) instead of static service cards.
- Added pricing card styles (`.bizupkeep-package-*`) to `assets/css/custom.css`.

## 1.0.0
- Initial release.
- Custom `header.php` with logo/site title, primary nav, CTA button, mobile toggle.
- Custom `footer.php` with footer nav, widget area, dynamic copyright year.
- `page-templates/template-homepage.php` registered homepage template with hero, services grid, content area (Elementor-ready), and CTA section.
- Enqueued parent + child styles plus `assets/css/custom.css` and `assets/js/custom.js`.
- Registered `bizupkeep-primary` and `bizupkeep-footer` nav menus and `bizupkeep-footer-widgets` sidebar.
