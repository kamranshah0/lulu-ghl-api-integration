# Phase 1 Architecture

Reviewed 2026-11-19 (metadata identities and lossless address formatting).

## Intake contract

routes/api.php -> VerifyGhlWebhook -> WebhookController -> OrderData.
The approved workflow supplies completed paid book orders. Supported envelopes:
direct data, payload, nested order; shipping aliases are normalized.
A bare id must be an ORDER identifier, never a contact identifier.

OrderData retains the original payload, including edition/product/personalization
fields, in orders.raw_payload. Phase 1 prints the one configured static book;
only configured physical identities contribute to print quantity. Product IDs
take priority; explicit exact title aliases can match ID-less legacy items only.
Configured non-print identities contribute zero; unknown identities return 422.
No price, substring or positional inference. An unknown supplied ID never falls
back to a title. Normalized title configuration must be disjoint.
Electronic-only events return 200 ignored_non_print_order with an application-log
entry but no local print order or queue message. No-items legacy input is book-only.
See bump-offer-diagnosis.md for identity aliases and configuration prerequisites.
Actual funnel evidence now identifies stable IDs at line_items[].meta.product_id;
bare item.id is the funnel product ID, not the configured global product ID.
New intake also accepts total_price/total_cart_price amount aliases; retry does
not backfill old stored null amounts or treat a total as payment verification.

LuluApiService::normalizeShippingAddress prepares two <=30-character street lines
at word boundaries without dropping tokens or overwriting the saved address.
Overflow precedes existing street2; inability to fit fails locally. Worker
preflights this before integration calls/submission marker; cost/print use the same
prepared address. Admin payload preview shows prepared lines or validation error.
The service also normalizes both endpoints for callers outside the worker.

orders.ghl_order_id is unique. Intake stores order, event and database queue
message in one transaction. Production must use the database queue on the same
database connection, with after_commit=false. Redis/SQS or a separate queue DB
requires revisiting transaction/outbox guarantees.

HTTP: 202 queued; 200 duplicate ignored; 401 wrong secret; 422 invalid payload,
quantity or declared unpaid state; 503 missing production secret; 500 unexpected
failure. Shipping validation in the worker keeps incomplete orders visible.

## Class map

| Class | Responsibility |
| --- | --- |
| OrderData | Aliases, state/country, quantity/amount, original payload |
| WebhookController | Deduplication, storage, dispatch, HTTP result |
| ProcessLuluPrintJob | Lock, validation, environment, cost, print, GHL, mail dispatch |
| LuluApiService | Auth, bounded TLS HTTP, print/cost/status contracts |
| GhlApiService | Legacy or versioned contact fields/notes; honest result |
| SendOrderEmails | Recipient-specific retries and successful-send guards |
| SyncLuluStatus | Hourly full recovery; --ghl-only retries saved pending GHL statuses without Lulu/mail |
| InspectGhlCart | Read saved product metadata/validate selection; no integration calls or mutations |
| Order / OrderEvent | Persistence, mapping, retry eligibility, timeline |
| Admin controllers | Auth, dashboard, filters, CSV, detail/retry, profile |

bootstrap/app.php schedules hourly polling with an overlap lock.
The host still needs minute-by-minute schedule:run and a managed queue worker.

## Submission safety

- New orders capture lulu_environment. Null historical values require classification.
- Shared-cache order lock prevents concurrent workers. Wrong environment is blocked.
- Authenticate/validate configuration before recording submission_started_at.
- Persist that marker BEFORE POST. Job IDs/uncertain markers block another POST.
- Definite 4xx rejection except 408 clears the marker; connection failures, 5xx
  and success without job ID keep it. No assumption of remote idempotency.
- Admin retry never recreates an existing Lulu job, even when rejected.
- Worker reselects quantities from saved raw cart data before cost/print only after
  existing-job, uncertainty-marker and environment guards. Quantity changes clear
  stale costs and log print_quantity_corrected. Queued electronic-only rows become
  cancelled without API calls/email; unknown identities fail before submission.
- Reconcile checks both Lulu job ID and external_id before linking existing state.

Three attempts; print timeout 240 seconds; order lock 300 seconds; database queue
reservation 360 seconds. Shared persistent cache and restarted workers are required.

## Status and cost

| Lulu | Local |
| --- | --- |
| CREATED, UNPAID, PAYMENT_IN_PROGRESS, PRODUCTION_READY, PRODUCTION_DELAYED | print_job_created |
| IN_PRODUCTION | in_production |
| SHIPPED, DELIVERED | shipped |
| REJECTED, ERROR | failed |
| CANCELED / CANCELLED | cancelled |

Raw lulu_status is retained. Unknown statuses remain visible/polled; they do not
imply printing or shipping. Job creation is not payment or completed normalization.
GHL buyer payment and the merchant's Lulu printing payment are separate.
DELIVERED previously used the default print_job_created mapping; November 13
fix preserves its raw status while mapping it to the existing shipped enum.

Costs are nullable and non-blocking. Backfill preserves any existing estimate.
Page count comes from LULU_BOOK_PAGE_COUNT, not POD paper numbers.
ghl_synced_status changes only on accepted field updates. Unsynced terminal orders
remain eligible. Email events record transport acceptance, not delivery/read receipts.

GhlApiService requires both configured status/job-ID fields with distinct IDs for
fulfillment sync. Missing config, rejected HTTP, unsuccessful response flags and
connection failures raise safe RuntimeExceptions. Callers preserve the Lulu job and
record the error; the generic false-result fallback remains for alternate implementations.
Initial contact-note attempts are independent of field sync. Their separate
ghl_note_added/ghl_note_failed events do not change ghl_synced_status. Notes still
have no automatic replay; status fields retry through polling. GHL workflows are
external configuration; this app only sends initial SMTP order confirmations,
not a new email on every fulfillment-status change.

Manual lulu:sync-status --ghl-only reuses the existing syncGhl path and pending
marker selection, including delivered/terminal jobs. It uses saved lulu_status,
not a fresh Lulu request. It preserves job/payment/fulfillment state, skips already
synced orders and other/unknown environments, and never dispatches app mail or
contact notes. Missing/UNKNOWN statuses are rejected rather than sent as truth.
External GHL automations may run on field changes. No cron changes are necessary.

## Tests

- LuluAuthenticationTest: auth, cache/expiry, 401, payload contract, no 5xx POST replay.
- OrderDataTest: normalization and malformed data.
- OrderPipelineTest: intake, dispatch failure, retry barriers, environments, CSV.
- StatusAndNotificationTest: sync, reconciliation, email retry, Blade routes.
- GhlApiServiceTest: failure results and versioned payload shape.
- GhlOnlySyncTest: bulk pending recovery, DELIVERED, repeat skip, environment/reference
  guards, missing configuration/status, partial failures, no Lulu calls or app emails.
- PhysicalBookSelectionTest: physical/digital quantities, ignored electronic-only
  events, identity safety, duplicates, queued correction/cost contract, inspection.

PHPUnit uses in-memory SQLite and rejects stray HTTP. It cannot verify MySQL
concurrency under load, hosted credentials, actual PDFs, inbox placement or cron.

Developer onboarding: developer-guide.md. Current findings and deployment limits:
review-2026-11-13.md. Historical incidents are optional under history/.
