# Forever Wellthy - Handover

Last reviewed: 2026-09-09. Workspace changes are not a production deployment.

## Scope

Phase 1: GHL paid book order -> database -> Lulu static interior/cover ->
Lulu ID/status -> GHL contact updates and buyer/admin emails.
Initial operational validation target: 50-100 orders.
Personalization is outside this implementation. Existing admin theme is preserved.

## Latest readiness check: live switch held

2026-09-09, following the user's request to go live only if everything is ready:

- Production auth and cost calculation PASSED using the supplied production pair
  in process-memory configuration and the application's LuluApiService.
  For a synthetic US address, quantity 1 and configured page count 60, the actual
  parser returned USD print 3.49 and shipping 5.69. These are sample estimates,
  not a quote for every buyer or a complete invoice.
- A separate Python HTTP client received 403 for the cost endpoint. Rechecking
  with the application's Laravel HTTP transport succeeded; do not diagnose an
  account permission failure from that first client-specific result.
- Both configured book URLs returned HTTP 200 with text/html, NOT PDF bytes,
  using both Laravel HTTP and Python. No direct download button was found in
  the returned HTML. Final file contents, page count and print readiness remain
  unverified. Replace/verify stable downloadable PDF URLs before live intake.
- Local GHL_CUSTOM_FIELD_ID_STATUS and GHL_CUSTOM_FIELD_ID_JOB_ID are empty.
  Contact-note submission alone is not structured job-ID/status synchronization.
  Verify and configure the intended GHL field IDs on the deployment host.
- Local MySQL still refuses connections. Schema and pending orders cannot be
  inspected on that database. No local queue worker or scheduler process was found.
- APP_ENV remains local, APP_DEBUG=true and Lulu remains sandbox. No hosted
  deployment connection was identified in the project; hosted state is unknown.
- Tests rerun: 38 tests / 127 assertions passed. No print jobs, payments, emails
  or GHL writes were made. No .env changes, migrations or worker starts performed.

Next: obtain the hosted deployment connection, verify host config/database/queue,
resolve final PDF access and GHL field mappings, then follow the rollout checklist.
Do not describe this workspace as live or universally ready based on auth alone.

## Live authentication incident

The screenshot shows authentication failing before print-job submission.
The old exception discarded Lulu's response body, causing the misleading
`No response body returned` message.

Verified 2026-09-09:

- Local config is sandbox; key/secret present; config not cached.
- Configured credentials PASS sandbox authentication.
- The SAME local credentials FAIL production authentication: HTTP 401,
  `invalid_client`, `Invalid client or Invalid client credentials`.
- The newly supplied `LULU API KEY (2).docx` contains a DIFFERENT pair.
  That pair PASSES production authentication (HTTP 200/access token present)
  and FAILS sandbox authentication (HTTP 401/invalid_client).
- The document's Basic header matches its key/secret. Values and returned tokens
  were kept in memory, not copied to this repository or printed.
- Production endpoint was tested only in process memory. `.env` was not changed.
- Local logs did not contain the client's hosted September 8 authentication
  failure. Hosted credentials, worker configuration and logs remain unverified.

The supplied document provides a verified production pair for the rollout.
Configure it securely on the intended host, refresh config and restart workers
only after the deployment checklist. Basic Auth changes alone cannot make sandbox
credentials valid in production. Authentication success does not verify account
billing, PDF acceptance, or hosted order creation.

## Changes in this audit

- Lulu auth: Basic Auth, verified TLS, bounded HTTP requests, token expiry,
  credential-specific cache, one refresh on explicit 401, safe OAuth diagnostics.
- Print payload: nested `printable_normalization` with `source_url`.
  Cost endpoint uses `shipping_option`; print endpoint uses `shipping_level`.
- POD IDs stay dotted. Old instructions to strip dots are superseded.
  `060UC444` describes paper, NOT the book's page count.
- Per-order locks, durable pre-submission marker, environment binding and existing
  job-ID guards prevent blind duplicate submissions after uncertain outcomes.
- Transactional database-queue intake; concurrent duplicate handling; explicit
  unpaid statuses rejected; quantity/shape validation; blank-field fallback;
  original payload retained, including future/product fields.
- Hourly environment-isolated, chunked polling; local status repair, ERROR
  handling, partial cost preservation and GHL retry even for terminal orders.
- GHL failures no longer reported as success. Legacy mode retained by default;
  optional versioned mode uses LeadConnector, Version and customFields.
- Independent SendOrderEmails job retries buyer/admin separately and skips
  recipient success events. Hourly polling recovers missing confirmations.
- Admin: theme preserved, unsafe retry hidden, null money shown as Unavailable,
  environment and unpaid warning, fewer dashboard queries, matching CSV/list
  filters and streamed CSV with spreadsheet formula protection.
- Auth: fail-closed production webhook, constant-time secret comparison, login
  throttle, logout invalidation, no flashed password, no public default seed login.
- New operator commands: lulu:classify and lulu:reconcile, neither creates a print job.

## Verification

- PHPUnit: 38 tests / 127 assertions passed after formatting; Blade compilation
  and whitespace checks passed.
- Sandbox authentication passed with TLS verification enabled.
- Supplied document credentials passed production authentication with verified
  TLS; token requests only, no print creation or account changes.
- Sandbox cost calculation accepted the dotted POD/documented payload: print 3.49,
  shipping 5.69 for a synthetic US address, quantity 1. Not fixed pricing.
- No print job, paid order, real GHL contact update or external email created.
- No hosted deployment or production database migration performed.
- Local MySQL is unavailable (connection refused), so the development DB migration
  has not been run either. New migrations passed on PHPUnit's in-memory SQLite.

## Prior work retained

Missouri and known misspellings resolve to MO; explicit MI remains Michigan.
Cost parsing/backfill and the event timeline remain. Simple HTML/plain-text emails
remain. Email spam was resolved per the user's report; do not reopen without new
evidence. SMTP acceptance does not prove inbox placement.

## Before rollout

Follow [operations](docs/operations.md): migration, production keys, worker settings,
classification of historical orders, actual PDF/page count and Lulu billing.
The local page-count setting is 60. File URLs were checked during the latest
readiness review but returned HTML, so actual PDFs could not be preflighted.
Verify hosted GHL token/version and custom fields. Modern mode has mocked contract
tests, not a live contact-write test. Then run one approved real order and gradually
validate the initial batch.

## Supplied reference documents

All three September 9 attachments have been reviewed: the 13-page Lulu guide,
architecture proposal and credential DOCX. See [reference notes](docs/reference-documents.md)
for their scope, findings and precedence. They are reference material, not an
instruction to switch live, generate personalized PDFs or expand Phase 1.

## Boundaries

- GHL must send paid book-only orders with genuine order IDs. Missing payment
  status assumes that workflow contract; this app is not a payment gateway.
- Mixed-product carts, tax-ID-required destinations and every international
  address format are not implemented. Unknown country names are not truncated.
- GHL status retries are persisted; failed contact-note POSTs are recorded but
  have no dedicated replay mechanism.
- Print/shipping estimates are not the full Lulu invoice. Separate fulfillment
  fees and taxes are not modeled as order columns; do not present their sum as
  net profit or total payable. The attached guide's example rates are not fixed prices.
- SMTP acceptance and the local success event cannot be atomic. A crash between
  them can duplicate an email; it must never trigger a duplicate print.
- Uncertain Lulu submissions need portal reconciliation. external_id is a
  reference; the app does not assume Lulu enforces its uniqueness.
- Historical records with unknown environments are intentionally held for review.

Class map and test entry points: [architecture](docs/architecture.md).
