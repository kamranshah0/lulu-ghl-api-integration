# Phase 1 Architecture

Reviewed 2026-09-15 (GHL diagnostics and independent notification behavior).

## Intake contract

routes/api.php -> VerifyGhlWebhook -> WebhookController -> OrderData.
The approved workflow supplies completed paid book orders. Supported envelopes:
direct data, payload, nested order; shipping aliases are normalized.
A bare id must be an ORDER identifier, never a contact identifier.

OrderData retains the original payload, including edition/product/personalization
fields, in orders.raw_payload. Phase 1 prints the one configured static book;
all line-item quantities are interpreted as copies of it. Filter mixed carts in GHL.

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
| SyncLuluStatus | Hourly status/cost/GHL recovery and missing email dispatch |
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
- Reconcile checks both Lulu job ID and external_id before linking existing state.

Three attempts; print timeout 240 seconds; order lock 300 seconds; database queue
reservation 360 seconds. Shared persistent cache and restarted workers are required.

## Status and cost

| Lulu | Local |
| --- | --- |
| CREATED, UNPAID, PAYMENT_IN_PROGRESS, PRODUCTION_READY, PRODUCTION_DELAYED | print_job_created |
| IN_PRODUCTION | in_production |
| SHIPPED | shipped |
| REJECTED, ERROR | failed |
| CANCELED / CANCELLED | cancelled |

Raw lulu_status is retained. Unknown statuses remain visible/polled; they do not
imply printing or shipping. Job creation is not payment or completed normalization.
GHL buyer payment and the merchant's Lulu printing payment are separate.

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

## Tests

- LuluAuthenticationTest: auth, cache/expiry, 401, payload contract, no 5xx POST replay.
- OrderDataTest: normalization and malformed data.
- OrderPipelineTest: intake, dispatch failure, retry barriers, environments, CSV.
- StatusAndNotificationTest: sync, reconciliation, email retry, Blade routes.
- GhlApiServiceTest: failure results and versioned payload shape.

PHPUnit uses in-memory SQLite and rejects stray HTTP. It cannot verify MySQL
concurrency under load, hosted credentials, actual PDFs, inbox placement or cron.
