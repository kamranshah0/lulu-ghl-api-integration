# Forever Wellthy - Current Handover

Reviewed: 2026-11-17 (hosted title-only inspection follow-up). Start here, then rules.md. Do not read the archive by default.

## Outcome and scope

Phase 1: paid GHL book order -> local order/events -> queued static-PDF Lulu print
job -> saved job ID/status/cost estimates -> GHL Contact fields + independent
buyer/admin confirmation emails. Hourly polling maintains status and recovery.
Keep the existing Blade theme. Phase 2 personalization is discussed, NOT implemented.
See project-requirements.md for scope and docs/developer-guide.md for implementation.

## Latest operational evidence

November 17 user hosted terminal screenshot: migrate reports nothing pending;
config clear/cache succeed. ghl:inspect-cart 60 prints physical book title + qty 1,
but no supported product ID, so the ID-only selector rejects it. Inspector's filtered
output does not prove there are no other identity fields in the original payload.

Follow-up implemented locally: explicit exact title aliases ONLY for items with
no supported product ID. Config GHL_PHYSICAL_PRODUCT_TITLES and
GHL_NON_PRINT_PRODUCT_TITLES uses | separators. ID mappings always take priority;
unknown IDs/titles fail closed. No fuzzy, price or positional classification.
Inspector now gives the safe validation reason and rejects external/non-numeric
order IDs before lookup. Full suite: 77 tests / 332 assertions; focused: 27 / 124.
No live API, database, print, email or environment change in this follow-up.

Physical title observed on host: Free Book + Domestic Shipping (US). Digital UI
title: Instant Digital Copy; actual saved bump title still needs inspection.
GHL product screenshot IDs remain configured locally: physical
6a07961b6742675da39f8bd6; digital 6a16ea0276fc691717331d16 (not Stripe IDs).
Next: deploy follow-up runtime files, configure exact aliases, refresh config,
inspect 60 and a real bump order, then restart/resume workers. See
docs/bump-offer-diagnosis.md. Do not call this follow-up live-verified.

| Date | Evidence | What it establishes |
| --- | --- | --- |
| 2026-10-06 | Read-only GHL API HTTP 200 | Rotated token could list the two Contact TEXT fields in the configured location |
| 2026-10-06 | Local configuration | v3 + both verified field-ID mappings were installed locally |
| 2026-10-06 | User hosted screenshot, 10:09:34 | One `ghl_status_synced` event with raw status DELIVERED after recovery |
| 2026-11-13 | Local review/tests | See docs/review-2026-11-13.md; no fresh production API/DB/email audit |

Do not reopen resolved GHL field setup merely because September history says IDs
were empty. Do not claim all orders or inbox delivery are verified from one event.
Earlier email spam was resolved per user report; investigate new evidence separately.

## Last recovery command

```bash
php artisan lulu:sync-status --ghl-only
```

Uses saved pending Lulu statuses; no new prints, Lulu calls, cost calls, note
replay or app emails. Writes GHL fields + local events/markers and can trigger GHL
workflows. Successful markers skip repeat updates. Other/unknown Lulu environments
are excluded. Do not intentionally overlap with hourly/manual sync instances.
After deployment .env changes: config:clear, config:cache, queue:restart.
See docs/operations.md for full commands and cron. Existing hourly schedule stays.

## November 13 review

- Confirmed bump flow IMPLEMENTED locally: exact configured product IDs select
  physical quantities; known digital/audio products are excluded; unknown cart
  identities fail closed. Config: GHL_PHYSICAL_PRODUCT_IDS and
  GHL_NON_PRINT_PRODUCT_IDS (comma-separated, disjoint). Screenshot IDs are now
  installed locally (November 17); ID-less legacy titles now have explicit aliases
  as described above. Hosted mixed-cart acceptance remains unverified.
- New read-only command: php artisan ghl:inspect-cart LOCAL_ORDER_ID --products-only.
  It reads saved product fields without customer data/API calls/writes. Default
  invocation also validates selection. This diagnostic can be deployed first to
  obtain IDs. See docs/bump-offer-diagnosis.md for exact deployment/file list.
- Product IDs were obtained from GHL product screenshots. The later hosted
  inspection reveals a legacy title-only supported shape. Follow the alias
  deployment instructions; do not request credentials or guess Stripe IDs.
- Worker rechecks saved raw carts only before an unsubmitted print: corrects
  quantity, clears stale cost estimates and audits the correction; electronic-only
  queued rows become cancelled. Existing job/uncertainty/environment guards remain.
- Initial bump suite: 68 tests / 284 assertions passed; superseded by the
  November 17 follow-up totals above. Changed PHP formatted with Pint.
  A read-only remote product-list attempt returned 401; no product metadata obtained.

- Client clarification: $4.99 bump is digital PDF + audiobook, electronically
  fulfilled by client; Lulu must ignore it. Original pre-fix DTO summed physical
  1 + digital 1 into print quantity 2. Inspected three client-video
  frames; product IDs/wire shape are not visible. See docs/bump-offer-diagnosis.md
  for cause, implemented selection contract and live identity evidence needed.

- Fixed DELIVERED mapping to local `shipped`, preserving raw DELIVERED in Lulu/GHL.
  Avoids regressing completed jobs to Print Job Created; no enum migration required.
  This fix is local and still needs deployment.
- Review baseline before bump correction: 50 tests / 208 assertions passed.
- Local bootstrap: local/debug-on/sandbox, database queue, retry_after=360, GHL v3
  and field mappings present. ADMIN_EMAIL missing. No secret values recorded.
- Local SELECT 1 failed; database/schema on the host were not checked.
- Vite build unavailable because dependencies are not installed (vite not found).
- Current limitations: actual hosted cart ID correspondence still needs verification; serialized order JSON is
  unsupported; Contact fields are shared by repeat buyers; manual sync concurrency
  and real notification delivery need operational attention. See review report.

## Reading map

| Need | Read |
| --- | --- |
| Minimal AI context and constraints | AGENTS.md, this file, rules.md |
| Business scope | project-requirements.md |
| Developer onboarding, schema, events, commands | docs/developer-guide.md |
| Class/flow detail | docs/architecture.md |
| Deployment, cron, diagnosis and recovery | docs/operations.md |
| Current risks, verification and follow-ups | docs/review-2026-11-13.md |
| Old incident reasoning only | docs/history/handover-through-2026-10-06.md |
| Supplied references | docs/reference-documents.md |

## Maintenance

After each meaningful task update this file's current outcome, evidence date,
tests, deployment status and remaining work. Update only the relevant deeper docs.
Keep this entry short; move lengthy incident history to docs/history. Preserve
user edits and all print idempotency/environment/submission guards. Never copy
credentials, private file URLs or customer payloads into docs or tool output.
