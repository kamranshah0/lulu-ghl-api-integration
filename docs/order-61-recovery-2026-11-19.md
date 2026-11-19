# Failed Order 61: Address Rejection and Safe Recovery

Reviewed 2026-11-19. Local analysis of user-supplied copied logs and screenshots;
no connection to the live database, Lulu, GHL or SMTP. No order retried.
This document intentionally excludes customer information and private file URLs.

## Confirmed Evidence

- Local app order 61 appears failed in the supplied hosted screenshot, with no
  Lulu job ID, production environment, saved quantity 2 and unavailable estimates.
- Copied live logs show starts at 2026-11-13 10:21:02, 10:23:02 and 10:29:02
  (timestamps as recorded, no timezone conversion inferred). All three cost
  attempts and all three print attempts return HTTP 400.
- Print responses specify MAX_LENGTH_EXCEEDED at shipping_address.street1,
  with a 30-character limit. Submitted street1 contains 40 characters; street2
  is empty. This is an address validation rejection, not an authentication error.
  The claim that editing code caused the failure is not established by these logs.
- Screenshot of the saved webhook shows top-level line_items with bare funnel
  item.id values and stable global product IDs inside item.meta.product_id.
  Physical and digital global IDs match the earlier configured GHL product IDs.
  Actual bump title is Instant Digital Copy + Audiobook, not the earlier UI label.
  The original quantity 2 incorrectly counted both lines as printed books.
- Full purchase total is supplied as total_price/total_cart_price; older amount
  parsing did not recognize these keys. Missing local amount is not proof of
  nonpayment. The paid-order workflow contract remains required.
- User terminal output also confirms book-only order 60 selects physical quantity
  1, excluded lines 0 after the November 17 compatibility release. This alone
  does not establish mixed-cart filtering or successful print recovery.

Copied logs end at the incident and cannot rule out a later manual Lulu order.
No stored job ID alone is not conclusive evidence of no remote job.

## Local Corrections

1. LuluApiService::normalizeShippingAddress normalizes whitespace and splits a
   street1 over 30 characters at a word boundary. Overflow is prepended to the
   existing street2, preserving its information. Both lines must fit 30 characters.
   Nothing is silently truncated, abbreviated or written back to the original
   order address. Unsplittable addresses fail locally with a review instruction.
2. Cost and print APIs apply the same normalization. The worker preflights it
   before cost/auth/print calls and before setting the durable submission marker.
   Admin Lulu payload preview reflects the prepared lines or safe validation error;
   original customer address remains visible. No theme/layout change.
3. OrderData recognizes line_items[].meta.product_id (also meta.productId) as
   stable identity, after existing explicit aliases. Bare item.id is still not
   global identity. Unknown supplied IDs cannot fall back to title matching.
   Inspector exposes only whitelisted meta product/price identifiers.
4. total_price/total_cart_price are additional numeric amount aliases after the
   existing totalAmount/total_amount/amount precedence. This fixes new intake;
   existing stored null amounts are NOT automatically backfilled during retry.

The worker's existing cart re-evaluation corrects an unsubmitted saved quantity
from 2 to 1, clears stale cost estimates and records print_quantity_corrected.
Job-ID, environment, submission marker, locks and purchase uniqueness guards remain.

## Safe Hosted Recovery

1. Confirm with the client that this purchase was not already fulfilled manually.
   Check the production Lulu portal for an existing job using the purchase reference.
   If one exists, link/reconcile it; do not create another print.
2. Follow the existing release procedure: pause intake/worker cron as appropriate,
   allow in-flight submissions to finish, then deploy these changed runtime files:

   - app/Services/LuluApiService.php
   - app/Jobs/ProcessLuluPrintJob.php
   - app/DTOs/OrderData.php
   - app/Console/Commands/InspectGhlCart.php
   - resources/views/admin/orders/show.blade.php

   Existing physical/non-print GLOBAL product-ID mappings must remain configured.
   No new environment keys, migration, dependency install or frontend build needed.
   Do not map funnel item.id or Stripe prod_* as the global product IDs.
3. From the live project directory, refresh and inspect without printing:

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan view:clear
   php artisan ghl:inspect-cart 61
   ```

   Required: Selected physical quantity: 1; excluded lines: 1. Inspecting does
   NOT change the saved quantity. The worker corrects it when safely processing.
   In admin order 61, the Lulu Shipping Payload preview should show two street
   lines within 30 characters. If selection/address fails, stop and review.
4. Reload workers using php artisan queue:restart and resume the existing worker
   setup. This is a reload signal, not a command to start/drain the whole queue.
5. Only after checks pass, open admin Orders -> order 61 -> Retry Fulfillment
   once. This uses the existing transactional retry endpoint, queues this order
   and may create a real production print, update GHL and send app emails.
   The existing cron worker will pick it up. Do not replay the webhook, run
   queue:retry all, clear submission markers manually, or create a second row.
6. Verify new events: admin_manual_retry, print_quantity_corrected if needed,
   lulu_cost_calculated, lulu_job_created with an actual job ID. Check GHL/email
   outcomes separately. If the response is ambiguous, stop and reconcile.

The supplied incident has explicit HTTP 400 rejections; current code clears its
submission marker for these definitive failures. Actual hosted retry eligibility
still requires failed state, no job ID, no marker and matching environment. If
the button is blocked, investigate current state rather than bypassing guards.

## Verification

- Copied actual request formatted locally: original street1 length 40 -> street1
  30, street2 9; all address tokens and all other fields preserved. No API called.
- Full mocked suite: 89 tests / 388 assertions pass; changed PHP passes Pint.
- Regressions: stable meta identity, actual funnel shape with synthetic customer
  data, unknown ID rejection, full amount intake, one-book failed-order recovery,
  matching cost/print address and quantity, original address retention, long-line
  rejection before any integration call, Unicode/30-character boundaries, admin
  preview, one-time manual retry and uncertainty guard.
- Hosted deployment, current eligibility and final print/email success remain
  unverified by the agent. No production side effects performed.

## Reference

Lulu's [official address troubleshooting guidance](https://help.luludirect.lulu.com/en/support/solutions/articles/64000270793-why-is-my-lulu-direct-order-marked-action-required-)
recommends splitting overlong addresses across the second address line. The
specific 30-character API validation for this incident is evidenced by the copied
live HTTP 400 responses, not inferred from a different provider's limits.
