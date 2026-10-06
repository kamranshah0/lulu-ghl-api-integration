# Forever Wellthy - Handover

Last reviewed: 2026-10-06 (rotated GHL token verified and local mappings configured; older evidence retains its original dates). Workspace changes are not a production deployment.

## Current GHL checkpoint: local authentication and mappings pass

After the user installed a newly rotated token, a read-only GET to the versioned
custom-field endpoint with Version v3 returned HTTP 200. The 21 returned Contact
fields included exactly the intended contact.lulu_status and contact.lulu_job_id,
both TEXT, model contact, and matching configured location ownership.
Local ignored .env now sets GHL_API_VERSION=v3 and both verified field-ID mappings.
The agent did not replace the token/location or touch unrelated configuration.
Loaded configuration matches the file, with no configuration cache. A fake-HTTP
smoke check using actual local mappings confirmed two versioned Contact PUTs would
be formed; no real Contact PUT was sent. Full suite: 44 tests / 165 assertions.

This supersedes earlier 401/missing-field observations below for LOCAL config.
Remote contact-write authorization, hosted deployment, real status sync, notification
workflows and inbox delivery are still unverified. Next deploy the new token,
matching API version and both mappings to the intended host; refresh configuration
and workers under controlled rollout. Verify an existing paid order's Lulu status
sync without a new print submission. Do not run the broad sync as a read-only test:
it can update GHL and dispatch missing emails. No source/schema changes, print
jobs, contact updates, emails, worker restarts or hosted deployment in this task.

## October 6: proceed with existing Phase 1 confirmation flow

Latest follow-up after user says setup is ready: fresh read-only field discovery
with the currently loaded local token returned HTTP 401 (invalid/token indicators)
with Version v3, and HTTP 401 with Version 2021-07-28. Local .env has exactly one
GHL_API_KEY assignment, valid PIT format and no surrounding whitespace. Loaded
configuration matches the file and is not cached. Both field-ID mappings remain
blank; API mode still defaults to legacy. These checks do not prove the newly
rotated token was the value installed, nor establish the underlying rejection
cause. Ask user to verify the latest token was placed in this workspace's local
GHL_API_KEY, not only the hosted environment. Do not request its contents, repeat
rotation blindly, switch modes as a claimed fix, or claim successful setup.
This follow-up made two external GETs only; no config, contacts, prints or emails
changed. No tests rerun because no application code changed.

Latest checkpoint: user screenshot shows the new Forever Wellthy Lulu Sync
Private Integration has been created. Its token was pasted into chat; do not
reproduce, persist or use that value. Recommend rotation of this newly created
token before deployment (if already used elsewhere, coordinate replacement).
User should place the replacement only in the local ignored .env GHL_API_KEY,
not in chat/screenshots; do not change hosted config or start workers. After user
confirms local update, read-only field discovery with the matching versioned API
is next. Integration scopes and new-token validity are not yet API-verified.
No credential/config changes or external requests performed at this checkpoint.

Latest user asks to act on the supplied requirement rather than ask them to choose
implementation alternatives. Continue the existing static-PDF pipeline and Contact
status/job-ID confirmation mechanism. Do not introduce native payment status or
order fulfillment mutations, personalization, or a replacement UI.

Fresh verification using local configuration (not hosted configuration):
- Both GHL_CUSTOM_FIELD_ID_STATUS and GHL_CUSTOM_FIELD_ID_JOB_ID remain blank.
- The credential has Private Integration token format, but API mode is legacy.
- GET https://rest.gohighlevel.com/v1/custom-fields/ returned HTTP 401.
- GET /locations/{configured-location}/customFields?model=contact on LeadConnector
  returned HTTP 401 with Version 2021-07-28 and again with documented Version v3.
  The v3 error contains invalid/token indicators; no response body was disclosed.
- Thus changing the API mode alone is insufficient. The existing credential did
  not authenticate for field discovery; field IDs/ownership could not be fetched.
- Full local php artisan test passed: 44 tests, 165 assertions. Mocked integration
  tests are not evidence that hosted GHL updates or notification delivery work.

Next prerequisite: obtain a valid token for the same client sub-account via its
administrator or a scoped Private Integration (contacts.readonly, contacts.write,
locations/customFields.readonly). Store credentials securely, never in chat.
Verify read-only access and field ownership, then configure the version and both
IDs together. Deploy deliberately and verify one existing Lulu job's confirmation
without resubmitting a print. Live workflow notifications still need verification.
Only three external GETs and documentation edits occurred; no .env, source/schema,
contact, order, email, worker or scheduler change. Do not claim the live fix is done.

## October 6: pause integration setup and clarify original scope

Latest user instruction supersedes the provisioning guidance below: pause new
integration/configuration work and compare the pasted Phase 1 scope with supplied
references. Read the complete architecture DOCX (3) and all 13 pages of the newly
supplied Lulu getting-started PDF, plus current intake and GHL/job code.
The pasted scope requires static-file order intake/storage/submission, status
visibility and testing; it does not prescribe a GHL custom-field design or a new
Private Integration. The architecture proposal explicitly includes updating GHL
after Lulu acceptance and customer confirmation/status emails. It is reference
context, not authorization to expand Phase 1 or implement every proposal item.
Current code already attempts Contact status/job-ID writes and a separate note,
then queues app emails. It does not change GHL payment status. HTTP 202 queued
acknowledges intake only; asynchronous Lulu acceptance needs a later notification
or update. Attempting GHL updates is not evidence that GHL accepted them.
Two Contact fields are the existing implementation choice, not a Lulu requirement.
Private Integration migration is not inherently required by the business scope;
verify existing token/API authorization before prescribing replacement credentials.
Do not remove existing sync, stop tracking, create tokens or change config based on
this discussion. Clarify whether confirmation must trigger GHL workflows or whether
app emails/admin visibility suffice before approving a different delivery mechanism.
Documentation-only review; no code/config/database/external changes or tests run.

## October 6: payment status confirmed by screenshots

User supplied details of two GHL funnel orders. The pending example explicitly
shows Payment Pending, zero paid and an outstanding balance; the completed example
shows Paid and a successful Stripe transaction. These labels concern payment, not
Lulu print progress. Do not mark the pending order paid or replay it for printing.
A nearby completed entry for the same customer appears in the list, but a checkout
retry/abandonment explanation is unverified without order/transaction correlation.
The completed example contains one physical book and one digital-copy bump:
two line items do not imply two printed copies. Actual app/Lulu quantity unverified.
Return to the existing Contact-field sync configuration; native payment status is
not the target of that sync. Verify hosted mappings and a saved paid order's sync
without resubmitting it; notification workflow execution remains a separate check.
Screenshot interpretation and documentation only; no code/config/live writes or tests.

October 6 creation-dialog checkpoint: screenshot shows Single line selected, with
Add to object, Field name and Folder name required. Guide Contact object, names
Lulu Status and Lulu Job ID, an available Contact folder, automatic key unchanged,
and blank defaults/placeholders. Creation is not yet confirmed. After saving ask
for the filtered Lulu field list; actual API IDs and hosted mappings remain pending.

October 6 subsequent screenshot confirms both Lulu Status and Lulu Job ID exist
as Single line Contact fields in Additional Info. Visible merge keys are
contact.lulu_status and contact.lulu_job_id, not API field IDs. Next inspect the
visible 6/7 columns menu for an ID option; do not assume it exists. If the UI does
not expose IDs, use authorized read-only API field discovery. Actual IDs, token
authorization, hosted mappings and successful sync remain unverified. No code,
environment or external service was changed by the agent at this checkpoint.

October 6 columns screenshot: the only unchecked column is Object; no API ID
column is offered. Stop searching this menu. Next open Settings -> Private
Integrations in the same sub-account and inspect the existing integration list
without exposing tokens or rotating/deleting anything. Authorized field discovery
needs locations/customFields.readonly; verify token/version compatibility before
using it. API IDs and hosted configuration remain unverified. Documentation only.

October 6 Private Integrations screenshot shows the empty-state create screen
in the selected sub-account, not proof that the existing legacy/OAuth token is
invalid. Guide a new Forever Wellthy Lulu Sync integration with contacts.readonly,
contacts.write and locations/customFields.readonly only; inspect permissions before
generation. Do not request tokens in chat or replace hosted credentials yet.
Token/API-version pairing and field discovery must be verified before a controlled
switch. No integration created or production configuration changed by the agent.

## October 2: clarify which GHL status is intended

User hypothesizes that a checkout order may show Pending and should become
successful after Lulu job creation; no screenshot confirms an actual pending order.
Re-read GhlApiService and its callers: current sync writes Contact custom fields
and contact notes, not native Payments order/payment/fulfillment or Opportunity
status. Fixing Contact field mappings does not itself change those native statuses.
Official GHL order docs distinguish payment status from fulfillment status. Never
mark payment successful or shipment fulfilled merely because a Lulu job exists.
Next request: Payments -> Orders -> affected order details with customer data
redacted; inspect the exact status label and the client's notification trigger
before deciding whether native order fulfillment integration is needed.
No scope change, code/config edits, tests or live writes in this clarification.
References: https://help.gohighlevel.com/support/solutions/articles/155000007158
and https://marketplace.gohighlevel.com/docs/ghl/payments/list-orders/index.html

## October 1: access granted, diagnosis before configuration

User reports receiving GHL access. No authenticated GHL browser/server connection
is available to the agent; user login access does not configure the application's
API token or field mappings. Local read-only checks reproduced missing
GHL_CUSTOM_FIELD_ID_STATUS and GHL_CUSTOM_FIELD_ID_JOB_ID before any outbound write.
API key/location are present, API mode is legacy, and config is not cached.
MySQL still refuses connections; local logs end September 15 and cannot establish
the current hosted error. No live GHL authentication or field ownership verified.

Official docs reviewed: contact field listing returns id separately from fieldKey;
the current versioned contact API uses customFields id/fieldValue and a Version
header. GHL reports v1 end-of-support (not universal shutdown); existing legacy
connections may continue working. Do not declare the token invalid or blindly
change its version. For a deliberate Private Integration migration, verify the
correct sub-account and minimum scopes contacts.readonly, contacts.write and
locations/customFields.readonly before switching the app's token/version together.

Next checkpoint for this novice user: correct GHL sub-account -> Settings ->
Custom Fields -> Contact -> inspect existing Lulu/status/job fields and share a
redacted screenshot. Then verify actual API IDs and matching app token/location,
configure hosted mappings, refresh config/workers and verify an existing Lulu job's
status sync without resubmitting a print. GHL workflows/email delivery are separate.
Focused local verification: 16 tests / 81 assertions passed (GHL service, status,
notifications). No code/config/schema changes or external integration writes today.

References: https://marketplace.gohighlevel.com/docs/ghl/locations/get-custom-fields/
and https://help.gohighlevel.com/support/solutions/articles/155000008488-highlevel-api-migration-guide-how-to-move-to-private-integrations

October 1 workflow screenshot: Custom Data explicitly includes contactId mapped
to the Contact.ID dynamic value, separately from type and the order JSON text.
The pasted order JSON alone is not the whole webhook. OrderData accepts top-level
contactId; do not instruct duplicating it or claim it is absent based on that snippet.
Actual resolved delivery/persistence remains unverified. The order row may be
serialized text rather than an object; inspect existing webhook_received data
privately before changing parsing or assuming the displayed UI proves its wire shape.
The pictured US branch has Onboarding Email and Internal Notification after the
outbound webhook, not a visible Lulu-status trigger. Their execution results must
be checked separately; a GHL status-field failure does not establish why those
actions did not run. Next: inspect a saved order's GHL contact ID and the two
outgoing Contact custom-field mappings. Do not use Test workflow on a paid order.

October 1 access screenshots: Business Profile, Custom Fields and Private Integrations
are now visible in the client-labelled sub-account. Screenshots show portions of
the first 20 of 54 fields, with no visible Lulu mappings; this does not prove those
fields are absent. Next ask the user to search existing Contact fields for Lulu,
fulfillment/status and job before creating duplicates. Custom Values is a separate
screen and is not the outgoing Contact field mapping. Account ownership of the
checkout and correspondence with the app location/token still need verification.
Documentation-only guidance; no live fields, configuration or contacts changed.

October 1 follow-up search: user reports only one result across the requested
searches; supplied status-search screenshot shows the locked standard
opportunity.status field. Do not repurpose it for Contact fulfillment updates.
Guide creation of Lulu Status and Lulu Job ID as Contact Single line fields,
checking the creation dialog first if needed. Field creation and API IDs remain
unverified; no app or external configuration changed by the agent.

September 29 meeting checklist: confirm the GHL sub-account owning the book checkout,
grant the existing user access to Contact Custom Fields, funnel and relevant workflows,
obtain internal editor links, and inspect existing status/job-ID mappings before creating
anything. Clarify whether missing notifications are initial buyer/admin confirmations
or GHL fulfillment-stage workflow messages; record one affected order/time/recipient
privately. Identify who manages hosted deployment and whether September fixes were
installed. Client need not know API IDs: obtain scoped access or involve their GHL
administrator. Do not request passwords in chat, rotate working keys, bulk retry,
or replay the manually placed order. No technical checks or deployment today.

September 16 guidance: configure verified GHL contact field IDs on the hosted app,
not only the local checkout. Preserve the existing token/version until verified;
do not rotate working Lulu/SMTP credentials for a field-mapping error. Verify
ADMIN_EMAIL and SMTP configuration separately for app emails. Refresh config and
restart workers after controlled deployment. September 15 changes need no new
migration; the earlier safety migration must already be applied. Normal hourly
sync can repair existing jobs without reprinting. Documentation-only follow-up;
no environment, hosted config, database or external service changed today.

September 16 user guidance preference: user is unfamiliar with GHL. Guide one
screen at a time: client login -> correct sub-account -> Settings -> Custom Fields
-> Contact fields. Inspect existing fields before creating duplicates; request a
redacted screenshot at this checkpoint. Do not assume an API field ID is visible
in the UI or confuse it with the displayed merge key. No configuration changed.

September 16 screenshots: selected sub-account label is AQai; Settings shows My
Profile, Calendars, Integrations and Brand Boards, but no Custom Fields. Limited
permissions are a possibility, not a verified diagnosis. Do not assume this is the
Forever Wellthy location from the account name or branding. Next checkpoint: open
the top-left account selector and confirm which location owns the book funnel.
Ask its administrator for Contact Custom Fields access or the two verified field
IDs if access is restricted. Do not request broad agency admin access by default.

## September 15: GHL sync and notification report

User supplied hosted screenshots with repeated September 13 hourly GHL sync
failures for IN_PRODUCTION, and reported another order completed the Lulu flow.
An earlier failed order was placed manually by the client. Do not resubmit that
order: verify and link the existing job where reconciliation identifiers match.
Lulu production progress is user/screenshot evidence, not a fresh hosted audit.

Local checks on September 15:

- Both GHL_CUSTOM_FIELD_ID_STATUS and GHL_CUSTOM_FIELD_ID_JOB_ID are still blank.
  GHL token/location and SMTP/admin configuration are present; presence is not
  authentication, scope, field ownership or delivery verification. Mode is legacy.
- MySQL still refuses connections. Available local logs end September 9, not the
  dates shown in the incident. Hosted settings, events and queued email jobs have
  not been inspected. Missing field mappings are confirmed LOCALLY; the old
  generic screenshot message cannot distinguish them from remote rejection.
- Hourly failure events indicate the hosted sync command was running at those
  timestamps. They do not prove the queue worker or email transport is healthy.
- App SMTP confirmations and GHL workflow notifications are separate mechanisms.
  A workflow triggered by a field change may not run while field sync fails.
  SMTP confirmations are independent and still require email-event investigation.

Local fixes (not deployed):

- GHL errors now name missing config keys or expose only the HTTP status with a
  safe remediation hint. Raw GHL response/customer data is not copied into errors.
- Both status/job field mappings are required and must differ. Previously one
  configured field alone could be incorrectly recorded as a complete sync.
- Initial status update and contact note are attempted/logged independently;
  a note failure no longer masquerades as a status failure or loses a synced marker.
- Polling failure copy no longer falsely implies a new Lulu change every hour.
- Regression tests verify GHL failure does not suppress either order email,
  polling retries do not resend acknowledged emails, and no print is recreated.

Verification: 44 tests / 165 assertions passed; formatting checked. No new schema
change, .env change, hosted deployment, live contact update, print job or email.
Next: configure verified hosted GHL fields/token/version, verify matching workflow
triggers, refresh config/workers, and inspect confirmation_email/admin_notification_email
sent/failed events plus the hosted queue/SMTP logs. Keep the prior spam incident
resolved unless new delivery evidence specifically implicates spam again.

September 15 follow-up guidance: start in the correct GHL sub-account's Settings
> Custom Fields (Contact object), reuse existing status/job-ID fields or create
single-line text fields if absent. Obtain their actual API IDs, not merge keys or
display names. Set the two mappings in the HOSTED application's .env, preserving
existing API token/version. Deploy the three changed application PHP files through
the controlled rollout, refresh config/workers, then inspect sync and separate email
events. Field IDs/hosted state still await verification; no live settings changed.

## Scheduler clarification (2026-09-10)

- No hosted cPanel cron entry or worker-manager configuration was changed by the
  agent. The user's screenshot was reviewed, but execution on the host is unverified.
- Screenshot: both entries run every minute. The scheduler PHP path appears to
  lack its leading slash; the queue command uses stop-when-empty, tries=3 and
  timeout=90. Both discard command output to /dev/null.
- Recommended corrections and diagnostic log destinations are in operations.md.
  The queue recommendation adds max-time=50 to bound worker lifetime under load;
  it is checked between jobs, not a hard 50-second job timeout or singleton lock.
- Application schedule still runs lulu:sync-status hourly; the audit added
  withoutOverlapping(55). The normal schedule:run cron remains every minute.
- Print-job timeout is 240 seconds; queue retry_after defaults to 360 seconds.
  Explicit host environment overrides still need verification.
- Laravel job-level timeout overrides the worker flag. Do not claim the screenshot's
  timeout=90 alone proves this print job was terminated after 90 seconds.
- Buyer/admin notifications use the existing queue through SendOrderEmails;
  there is no separate email cron to add.
- This clarification involved screenshot/code/config review and documentation edits.
  No scheduler, worker, external integration diagnostic or test suite was run today.

## Scope

Phase 1: GHL paid book order -> database -> Lulu static interior/cover ->
Lulu ID/status -> GHL contact updates and buyer/admin emails.
Initial operational validation target: 50-100 orders.
Personalization is outside this implementation. Existing admin theme is preserved.

## September 9 readiness snapshot: live switch held

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
