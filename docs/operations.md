# Operations and Live Rollout

Reviewed 2026-09-09. Workspace edits do not imply a production deployment.

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
