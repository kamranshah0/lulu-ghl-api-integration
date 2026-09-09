# Forever Wellthy - Project Requirements

Scope confirmed by client brief: Phase 1. Reviewed 2026-09-09.

## Phase 1 boundaries

Validate the GHL-to-Lulu pipeline using one predefined static interior and cover,
initially with approximately 50-100 print orders. No dynamic PDF generation or
personalization is implemented. Product edition/slug and future personalization
fields are retained in the original payload for traceability.

This file states intended behavior. See handover.md for verification limits and
docs/architecture.md for the exact implemented contract.

## Purpose

This Laravel application receives completed book orders from GoHighLevel (GHL), sends them to Lulu for print fulfillment, tracks the fulfillment lifecycle, and gives the Forever Wellthy team an authenticated admin view of every order.

The product name shown to customers and admins is **Forever Wellthy**. Do not use the word "middleware" in customer-facing or admin-facing copy.

## Main Order Flow

1. GHL posts an order to `POST /api/webhooks/ghl` with the `X-GHL-Secret` header.
2. The app validates the secret, normalizes the payload, and creates exactly one local order for each GHL order ID.
3. A background job calculates Lulu's estimated print/shipping costs, then creates the Lulu print job.
4. The app stores the Lulu job ID, Lulu status, cost estimates, and a complete event timeline.
5. When configured, the related GHL contact is updated with the Lulu job ID and fulfillment status, and receives a timeline note.
6. A transaction email goes to the buyer and an internal notification goes to the configured admin email.
7. An hourly command polls Lulu for active jobs, updates the local order and GHL, and fills missing cost estimates.

## Functional Requirements

### GHL order intake

- The webhook rejects invalid secrets and refuses production requests if `GHL_WEBHOOK_SECRET` is missing.
- GHL must trigger this endpoint only for completed paid book orders. Explicit
  unpaid/refunded/failed payment states are rejected; absent payment status relies
  on the approved workflow contract. This app does not independently collect payment.
- The order ID must be an actual purchase/order ID, not a contact ID. Quantities
  must be positive integers and include only the configured book, not unrelated upsells.
- A duplicate webhook must not create a second Lulu job. `ghl_order_id` is the idempotency key.
- The parser must accept common GHL payload shapes: direct payloads, `payload` wrappers, `order`, `customer`, `shippingAddress`, `shipping_address`, and `shipping` variants.
- Required data must be normalized before Lulu receives it:
  - country must be a two-letter code, e.g. `US`;
  - US state must be a two-letter code, e.g. `MO`;
  - known GHL spelling variants of Missouri, including `Missroii`, must resolve to `MO`.
- Validation failures must return HTTP 422. Unexpected failures must return HTTP 500 without exposing internals to the caller.

### Lulu fulfillment

- The application uses the configured Lulu POD package, cover PDF, interior PDF, page count, shipping level, and contact email.
- Preserve the configured POD package ID, including dots, according to the current Lulu contract.
- Use the actual interior page count for costs; a POD paper component is not a page count.
- Capture each order's sandbox/production environment. Do not replay sandbox orders live.
- A timeout or missing job-ID response requires reconciliation before any new submission.
- Cost estimation is useful but non-blocking: a cost API failure must be recorded without preventing a valid print-job submission.
- A Lulu print-job failure must leave a useful error message and event history for the admin to review/retry.
- Lulu status mapping:

  | Lulu status | Local fulfillment status |
  | --- | --- |
  | `CREATED` | `print_job_created` |
  | `IN_PRODUCTION` | `in_production` |
  | `SHIPPED` | `shipped` |
  | `REJECTED` | `failed` |
  | `ERROR` | `failed` |
  | `CANCELED` | `cancelled` |

Raw Lulu status is retained. UNPAID/payment/pre-production states remain
print_job_created locally; job creation is not proof of payment or printing.
Merchant Lulu payment is separate from buyer payment collected in GHL.

### Costs and order visibility

- Store Lulu `print_cost_estimate` and `shipping_cost_estimate` on the order whenever Lulu returns them.
- Cost extraction must handle Lulu's flat and nested money response formats.
- Existing active orders that have missing estimates are backfilled during hourly status sync.
- Null/missing estimates display as Unavailable, not a fabricated $0.00 cost.
- Print and shipping estimates are not a final invoice or profit calculation;
  separate fulfillment fees and taxes are not currently modeled.
- The admin order detail must show the buyer, normalized shipping address, Lulu job/status, product/POD ID, quantity, both cost estimates, error message, and the event audit timeline.

### GHL synchronization

- GHL synchronization must never undo a successfully created Lulu job if the GHL API call fails.
- Store success/failure events for contact custom-field updates and timeline notes.
- Status and Lulu job ID custom-field IDs are configuration, not hard-coded values.

### Transactional email

- After a Lulu job is created, send one confirmation to the buyer when an email exists and one notification to `ADMIN_EMAIL` when configured.
- A failure sending either email must be logged as an order event and must not fail the Lulu job or prevent the other email.
- Notification retries run independently and skip recipients with recorded successful sends.
- Emails must be branded only as Forever Wellthy, include useful order details, and contain a plain-text alternative.
- Avoid promotional language, tracking links, unnecessary images, buttons, or heavy HTML. This helps message quality, but DNS authentication and sending reputation are the primary inbox-placement controls.

### Admin panel

- Admin authentication is required for all `/admin` routes except login.
- Admins need dashboard counts, searchable/filterable order list, failed-order queue, CSV export, order timeline/detail, and a safe manual retry for failed orders.
- The existing visual theme and layout are intentional. Preserve them; make only targeted usability, responsive, accessibility, data-visibility, and error-state improvements.

## Operations Requirements

- A queue worker must run continuously in production; otherwise webhook orders remain queued and no Lulu job/email will be processed.
- Use the database queue on the same application database connection for transactional
  intake. Shared persistent cache supports worker locks; queue retry_after must
  exceed job timeout. Deployment/recovery steps are in docs/operations.md.
- The Laravel scheduler must run every minute in production so the hourly `lulu:sync-status` command can execute.
- Use a real production database, cache, queue, session store, and SMTP configuration. Do not deploy local/testing defaults.
- All service credentials belong in `.env` only. Never put values from `.env` in source code, tests, documentation, logs, screenshots, or commits.

## Required Environment Groups

Exact values are intentionally not documented here.

| Group | Keys |
| --- | --- |
| App/database | `APP_*`, `DB_*`, `CACHE_*`, `SESSION_*` |
| Queue | `QUEUE_CONNECTION` |
| Lulu | `LULU_CLIENT_KEY`, `LULU_CLIENT_SECRET`, `LULU_USE_SANDBOX`, `LULU_API_BASE`, `LULU_SANDBOX_API_BASE`, `LULU_CONTACT_EMAIL`, `LULU_BOOK_INTERIOR_URL`, `LULU_BOOK_COVER_URL`, `LULU_POD_PACKAGE_ID`, `LULU_BOOK_PAGE_COUNT`, `LULU_SHIPPING_LEVEL` |
| GHL | `GHL_WEBHOOK_SECRET`, `GHL_API_KEY`, `GHL_API_VERSION`, `GHL_LOCATION_ID`, `GHL_CUSTOM_FIELD_ID_STATUS`, `GHL_CUSTOM_FIELD_ID_JOB_ID` |
| Mail | `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_EHLO_DOMAIN` |
| Admin notification | `ADMIN_EMAIL` |
