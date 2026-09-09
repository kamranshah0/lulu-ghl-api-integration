# Working on Forever Wellthy

Read handover.md first, then rules.md. Use project-requirements.md for scope;
open docs/architecture.md or docs/operations.md only as needed.

- Implement Phase 1 with static PDFs. No personalization without a new instruction.
- Preserve the Blade theme/layout. Brand: Forever Wellthy.
- After each task update relevant docs and their verification date.
- Distinguish local code tests, external diagnostics, user reports and deployment.
- Read changed files and git status; preserve user edits.
- Never output .env, passwords, tokens, private file links or customer payloads.
- php artisan test uses in-memory SQLite and blocks unmocked HTTP requests.
- Sandbox and production Lulu credentials differ. Dotted POD IDs stay dotted.
- Never disable TLS or blindly repeat a possibly accepted print POST.
- Preserve environment, submission marker, job-ID and unique GHL order guards.
- Run migrations before exercising new schema on a local database.
- Workers, status sync and schedule:run can print, update GHL or send emails.
- lulu:test --fresh contacts Lulu and changes token cache but does not print.
- lulu:test --full creates a sandbox job and is disabled in production.

September 9 evidence: local keys pass sandbox and fail production with
HTTP 401 invalid_client. The NEW supplied credential DOCX passes production and
fails sandbox; it has NOT been installed. See handover before changing config.
Latest readiness: production cost API passes in-memory, but local PDF URLs return
HTML, GHL field IDs are blank and MySQL is down. Live switch is held, not deployed.
