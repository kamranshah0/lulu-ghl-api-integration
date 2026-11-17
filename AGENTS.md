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

Reviewed 2026-11-17. October 6 evidence: rotated GHL token/fields verified locally;
user hosted screenshot confirms one DELIVERED sync. Older missing-ID/live-hold
statements are historical, not current readiness. Hosted health needs fresh evidence.

- Last recovery command: php artisan lulu:sync-status --ghl-only. It writes saved
  pending statuses to GHL and can trigger workflows; no Lulu calls or app emails.
- Current review: docs/review-2026-11-13.md. Developer entry: docs/developer-guide.md.
- DELIVERED maps to local shipped; raw DELIVERED remains visible. Local fix awaits deployment.
- Intake selects physical quantities via services.ghl physical_product_ids and
  non_print_product_ids; unknown structured items fail closed. November 17 product
  screenshot IDs are configured locally; hosted cart correspondence remains unverified.
  See docs/bump-offer-diagnosis.md before deploying the selector.
- Read-only product evidence: php artisan ghl:inspect-cart LOCAL_ORDER_ID --products-only.
- No special external plugin or skill is required for Laravel/PHP work. Use skills
  available in the active session only when the task needs them; never invent
  installed capabilities or bundle personal skill caches. References are not rules.
- For PDF layout work use an available PDF skill; for DOCX work a document skill;
  neither changes Phase 1 scope or authorizes external integration writes.
