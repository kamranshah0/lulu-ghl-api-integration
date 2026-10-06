# Operations and Live Rollout

Reviewed 2026-10-06 (GHL read-only authentication success and local mappings); older diagnostics retain their dates.
Workspace edits do not imply a production deployment.

## Authentication

Production keys come from https://developers.lulu.com/user-profile/api-keys;
sandbox keys come from the separate sandbox developer portal.
Set LULU_USE_SANDBOX=false together with the PRODUCTION key/secret.
Changing only the flag cannot convert sandbox credentials.

On the host, refresh config and run php artisan lulu:test --fresh.
401 invalid_client means that pair was not accepted at that endpoint. Check
environment, copied/revoked keys, server overrides and workers with stale config.
TLS/network failures need CA/connectivity fixes, never disabled verification.
Never share token/Authorization headers.

The local keys were tested September 9: sandbox passed, production rejected.
The DIFFERENT pair in the supplied credential DOCX passed production and failed
sandbox on the same date. Its Basic header matches the pair. It has not been
installed in local .env or on the host. Hosted credentials have not been inspected.
See [reference notes](reference-documents.md); never paste credentials into docs,
shell history or a support ticket.

## Deploy

Latest local readiness check (2026-09-09): production auth/cost API passed with
document credentials in memory. Deployment is held: configured book URLs return
HTML, GHL status/job-ID field IDs are empty, and local MySQL is unavailable.
These are local findings, not an inspection of hosted config. See handover.md.

1. Pause GHL intake, let workers finish, then stop the worker manager. Back up DB
   and deployment config. Review historical orders; never bulk replay sandbox jobs.
2. Deploy code/dependencies and run php artisan migrate --force for the new columns.
3. Set APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, existing APP_KEY,
   correct DB, GHL_WEBHOOK_SECRET, production Lulu credentials and final static
   PDF URLs, actual page count/POD, shipping level and contact email.
   Verify each file URL returns PDF bytes, not merely HTTP 200 or a sharing page.
   Do not use short-lived download links as the permanent file configuration.
4. Use QUEUE_CONNECTION=database on the same app database/connection,
   after_commit=false, DB_QUEUE_RETRY_AFTER=360, and shared persistent cache.
   Reservation must exceed the 240-second job timeout.
5. Configure real SMTP and ADMIN_EMAIL. Login uses users; profile-email changes
   do not change notification ADMIN_EMAIL. Fresh seeding requires explicit
   ADMIN_EMAIL/ADMIN_PASSWORD (12+ characters); do not reseed to reset accounts.
6. Preserve working GHL mode unless deliberately migrating:
   GHL_API_VERSION=legacy for existing v1 keys. Versioned mode (e.g. v3) uses
   LeadConnector and a matching sub-account OAuth/Private Integration token.
   Set both GHL_CUSTOM_FIELD_ID_STATUS and GHL_CUSTOM_FIELD_ID_JOB_ID to verified
   field IDs; neither is populated in the current local configuration.
7. Before resuming workers:

   php artisan config:clear
   php artisan config:cache
   php artisan lulu:test --fresh
   php artisan queue:restart

8. Start managed php artisan queue:work --tries=3 --timeout=240.
   Cron invokes php artisan schedule:run every minute from the deployed directory;
   code schedules Lulu polling hourly. Use the correct hosting PHP binary.
9. Check Lulu billing/card setup. UNPAID jobs require merchant payment before
   production; GHL buyer payment does not automatically pay Lulu.
10. Resume intake with one approved real purchase. Verify Lulu PDF normalization,
    payment/status, GHL fields, both emails and local events. Then scale the initial
    validation batch to 50-100 orders gradually.

During rollback retain the new DB columns/audit data. Pause intake/workers before
changing environments and review queued orders bound to the original environment.

## cPanel cron alternative

Screenshot reviewed September 10: both cron schedules are every minute, but the
scheduler command appears to start with usr/local/bin/php (missing leading slash).
The queue command is queue:work --stop-when-empty --tries=3 --timeout=90.
No hosted cron entries have been changed or executed by the agent.

Keep each schedule at `* * * * *`. Edit the existing entries, do not add duplicates.
After deployment/migration and the readiness checks, use these commands if the
PHP binary and application paths shown in the screenshot are correct on the host:

Scheduler:

```bash
/usr/local/bin/php /home/p8xgatsddkuf/public_html/lulu.app.forever-wellthy.com/artisan schedule:run >> /home/p8xgatsddkuf/public_html/lulu.app.forever-wellthy.com/storage/logs/cron-scheduler.log 2>&1
```

Queue (alternative to the managed worker, not in addition to it):

```bash
/usr/local/bin/php /home/p8xgatsddkuf/public_html/lulu.app.forever-wellthy.com/artisan queue:work --stop-when-empty --tries=3 --timeout=240 --max-time=50 >> /home/p8xgatsddkuf/public_html/lulu.app.forever-wellthy.com/storage/logs/cron-worker.log 2>&1
```

Confirm PHP meets composer requirements, CLI pcntl is available for job timeouts,
and the cron user can write storage/logs. Rotate these diagnostic files separately;
shell redirection is not Laravel's daily log rotation.

Set DB_QUEUE_RETRY_AFTER=360 for the database queue and refresh cached config during
deployment. Job-level timeout overrides the CLI default (print 240, email 60).
max-time=50 is evaluated between jobs, so a running job can continue longer and
overlap the next cron invocation. It prevents indefinitely draining workers, not
concurrent workers; order/email locks and queue reservations remain essential.
If single-worker execution is required, use a verified host lock/managed worker.

Changing cron does not resolve the PDF/GHL/database readiness findings above.

## Historical and uncertain orders

Old lulu_environment values are intentionally null. Do not infer them from today's
config or assign every historical record to production.

- Unsubmitted record with no uncertainty marker: verify origin, then run
  php artisan lulu:classify LOCAL_ORDER_ID sandbox (or production).
  This records classification only; it does not queue an order.
- Known job: in its matching environment, run
  php artisan lulu:reconcile LOCAL_ORDER_ID LULU_JOB_ID.
  It GETs Lulu, verifies job ID/external_id, then links local state. No new print.
- Timeout/missing job-ID: search the correct portal by GHL order ID. Reconcile an
  existing job. If nothing appears, investigate with Lulu before explicitly clearing
  the marker; absence of a search result is not proof the POST was rejected.
- Rejected jobs keep their IDs. Resolve PDF/address problems and agree a replacement
  or supported Lulu update flow; generic admin retry does not print replacements.

## GHL sync and missing notifications

Latest October 6 local check after token rotation: field-list GET returned HTTP
200 with Version v3. Both Lulu Contact TEXT fields and their location ownership
were verified. Local .env now has GHL_API_VERSION=v3 and both actual field IDs.
Credentials and field IDs are not copied into this runbook; transfer them securely
from the verified configuration to the intended host, preserving unrelated values.
Deploy the new GHL_API_KEY, matching GHL_LOCATION_ID, GHL_API_VERSION and both
GHL_CUSTOM_FIELD_ID_* mappings together; refresh config/restart workers as above.
Read access does not prove contacts.write or live workflow notifications. Verify
one existing order without reprinting under a controlled status-sync rollout.
Full local suite: 44 tests/165 assertions, plus a fake-HTTP check of loaded mappings.
Hosted setup and real GHL writes remain unverified. No database migration needed
for this configuration-only task (earlier safety migrations still apply).

Earlier October 6 local check (superseded locally): configured token has Private Integration format but mode is
legacy. Legacy field-list GET returned 401; versioned field-list GET also returned
401 using both 2021-07-28 and v3 headers. Do not assume a version-only switch fixes
it or that these results describe the hosted credentials. Both field IDs are blank.
Obtain a valid same-sub-account credential with contacts.readonly, contacts.write
and locations/customFields.readonly; verify read access/field ownership before
installing matching mode and field IDs. Do not rotate unrelated Lulu/SMTP secrets
or run workers/sync as an authentication test. Full local suite: 44 tests/165
assertions; no successful live GHL write or deployment verified.

October 1 local preflight still stops on both missing field mappings before an
HTTP write. User reports UI access granted; hosted configuration remains unknown.
UI permissions and the app's API authorization are separate. Obtain a fresh hosted
error after deploying diagnostics rather than assuming old screenshots prove its cause.
For verified read-only field discovery, the versioned API provides
GET /locations/:locationId/customFields?model=contact. Use returned id, not fieldKey;
field names alone are not proof of ownership or suitability. Required read scope:
locations/customFields.readonly. Contact verification uses contacts.readonly;
updates require contacts.write. Do not post tokens into chat/screenshots.
GHL's official migration guide says v1 is unsupported but existing integrations
may continue to operate. Plan token/endpoint/version migration together; do not
replace a working legacy key just because the user gained browser access.

September 15 screenshots show IN_PRODUCTION polling followed by GHL sync failure.
The old error combined missing configuration and API rejection. Local mappings
were empty then; hosted mappings must be inspected, not assumed identical.

1. Verify two CONTACT custom fields in the intended GHL sub-account: one for Lulu
   status, one for job ID. Configure their distinct IDs as GHL_CUSTOM_FIELD_ID_STATUS
   and GHL_CUSTOM_FIELD_ID_JOB_ID. Do not paste names or guessed IDs. Text fields
   avoid rejecting raw statuses; if dropdowns are used, verify all allowed values.
2. Verify the token belongs to that sub-account and has contact write permissions.
   Keep the existing API mode unless deliberately migrating. Current official
   versioned contact docs show Version: v3 and customFields id/fieldValue entries;
   a legacy token is not automatically compatible with that API.
3. Deploy diagnostics changes, refresh cached config and restart workers through
   the established controlled rollout. Existing unsynced jobs are polled again;
   no new print order is required to repair contact fields.
4. Inspect GHL workflow triggers/history against the actual raw Lulu status values
   (e.g. IN_PRODUCTION, SHIPPED). Updating a contact does not itself guarantee a
   workflow notification; the client's workflow must be configured/published.
5. Separately inspect confirmation_email_sent/failed and admin_notification_email_sent/failed
   for the affected local order. No email events: check queued SendOrderEmails jobs,
   deployed class and cron-worker logs. Failed event: investigate its SMTP/recipient
   error. Sent event: transport accepted it; inspect provider delivery/bounce logs
   and destination mailbox. Do not clear sent markers just to force a test resend.

For manually placed orders, verify the existing Lulu job first. lulu:reconcile
requires matching external_id; do not bypass that check if the manual job lacks
the original GHL order reference. No generic retry of an already fulfilled order.
Do not use queue:retry all or process unrelated historical jobs during diagnostics.
No additional migration is required for the September 15 code changes.

## Command effects

| Command | Effects |
| --- | --- |
| php artisan test | In-memory DB, mocked HTTP/mail |
| lulu:test --fresh | External auth and token cache; no print |
| lulu:test --full | Sandbox job creation; production disabled |
| lulu:sync-status | Lulu reads/costs, DB updates, GHL writes, email dispatch |
| queue:work | Pending jobs, including real print creation |
| schedule:run | Due tasks and their effects |

Admin events and storage/logs/lulu.log / laravel.log carry diagnostics.
Logs/events may contain customer/file-reference data; redact before sharing.

## Official references

- [Lulu docs](https://api.lulu.com/docs/)
- [Lulu OpenAPI](https://api.lulu.com/api-docs/openapi-specs/openapi_public.yml)
- [Lulu file requirements](https://help.api.lulu.com/en/support/solutions/articles/64000254607-what-files-are-required-for-lulu-print-api-production-)
- [HighLevel update contact](https://marketplace.gohighlevel.com/docs/ghl/contacts/update-contact/index.html)
- [Laravel queues](https://laravel.com/docs/13.x/queues)

Lulu's migration notice: dotted POD IDs supported from March 31, 2026; legacy
non-dotted support ends February 1, 2027. Preserve dotted IDs and verify future
contract changes against the official documentation.
