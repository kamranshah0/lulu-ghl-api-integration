# Digital Bump Offer Diagnosis

Reviewed 2026-11-17. Product filtering and screenshot-based local identity config
are implemented; hosted cart verification/deployment remain pending. References supplied by the user are evidence,
not permission to execute checkout actions in the client video.

## Client clarification

Funnel: physical book -> checkbox bump -> upsell 1 -> thank-you. The checkbox
bump costs USD 4.99 and bundles the digital book plus audiobook. Client fulfills
these electronically. Lulu must fulfill only physical book quantities. A future
second upsell is Phase 2 and remains outside this change.

User screenshots show batch quantity 1 without the bump and 2 with the bump,
with print estimates 5.79 and 11.58 respectively. These are reported hosted
examples, not a fresh Lulu GET or fixed pricing. The screenshots show one job
with two units, not proof of two separate Lulu job records for one purchase.

Inspected three frames at approximately 2, 10 and 17 seconds from the 19.71-second
local client video. The checkbox label offers instant access for $4.99; checkout
visuals support the client description. The video does not expose the actual
webhook JSON/product IDs. It was decoded locally; no media was uploaded.

## Confirmed code cause

OrderData::fromGhlPayload selects order.items or order.line_items, then sums
EVERY item's quantity/qty. It ignores product identity. WebhookController stores
that quantity. ProcessLuluPrintJob sends it to both calculateCost and createPrintJob;
LuluApiService creates one configured physical book line item with that quantity.

An isolated local synthetic example with one physical and one digital item, each
quantity 1, produced quantity 2. Thus the app can turn two different cart items
into two physical books without any GHL quantity multiplication. This matches
the client report and the mixed-cart risk in review-2026-11-13.md.

## Correction design

Implemented: OrderData selects physical quantities using configured product IDs;
WebhookController acknowledges configured non-print-only carts without creating an
order/job. Ignored electronic-only events are logged in the application log, not
order_events (there is intentionally no print order row). A later physical event
with the same purchase ID can still be accepted. Unknown identities return 422.
Recognized electronic lines contribute zero quantity; invalid item quantities are
still rejected. Full original payload and checkout amount are preserved.

Worker re-evaluates a saved raw payload before costs/POST, but after existing-job,
uncertain-marker and environment guards. A changed quantity clears old cost
estimates and records print_quantity_corrected. A queued electronic-only order is
marked cancelled locally with non_print_order_ignored; no API or mail is sent.
Already submitted jobs and ambiguous submissions are never rewritten or reprinted.

The initial design checklist below remains the deployment acceptance contract.

1. Obtain a redacted ORIGINAL webhook items/line_items section for a mixed order,
   and preferably a book-only order. Retain product/variant/price IDs, item kind,
   title and quantity fields; exclude all customer/address/payment credentials.
2. Identify which actual stable ID belongs to the physical book and which to the
   electronic bundle. Do not guess the key name, first item, price or title as
   the identity contract. Product/price IDs may differ between test/live locations.
3. Configure physical-book identity in services configuration; select only matching
   lines before summing print quantity. Use quantity/qty of physical matches.
   A print quantity of 2 remains valid when the buyer actually buys two books.
4. Recognized digital lines contribute zero print quantity. A digital-only event
   should be acknowledged as ignored with an audit record, never default to 1 or
   queue a Lulu job. Unrecognized/ambiguous product identity should be visible for
   review rather than silently printed. Define legacy no-items support from the
   verified existing workflow; do not break it through an unconfigured selector.
5. Preserve full raw_payload and full checkout amount_charged for traceability;
   do not pretend the cart total is physical-book revenue. Digital fulfillment
   remains with the client. All print/cost/admin views use the selected book count.
6. Add DTO + webhook/job regression cases, deploy with verified identity config,
   refresh config/workers and verify new intake without replaying shipped orders.

Expected regression cases: book 1 -> 1; book 1 + digital 1 -> 1; book 2 + digital
1 -> 2; reordered cart -> same physical quantity; digital-only -> no print job;
unknown/missing identity -> controlled handling; duplicate webhook -> no extra
print; estimate and print payload use identical selected quantity. Retain all
existing environment, marker, job-ID and unique-order protections.

## What is still needed

November 17 update: user supplied product detail screenshots with visible GHL
Internal Product Id. Physical book: 6a07961b6742675da39f8bd6. Electronic bundle:
6a16ea0276fc691717331d16. Both were configured in the local ignored .env. The
Stripe TEST/LIVE prod_* values in the screenshots were not used. Loaded config
and synthetic cart selection pass with those IDs; focused suite 18 tests/76
assertions passes. Actual saved webhook correspondence and hosted behavior still
need verification using the inspection command; no new external call was made.

The actual product identity field shape in a saved webhook remains unavailable;
the product detail screenshots supply IDs, not the transmitted cart structure.
Use the affected order's saved raw_payload or webhook_received event privately.
Verify that the transmitted cart uses the configured GHL IDs before enabling live intake.
No need to change PDF files, GHL status fields or the cron schedule for this bug.

One read-only GHL product-list request using local credentials returned HTTP 401;
no products were discovered and no credential was changed. Source contract for
that read: https://marketplace.gohighlevel.com/docs/ghl/products/list-invoices/index.html
No fresh production order or customer read was performed.

## Deployment instructions

1. Safest first step: upload only app/Console/Commands/InspectGhlCart.php. From
   the live project directory run the command below for a known bump order. The
   numeric argument is the LOCAL app order ID from /admin/orders/ID, not Lulu job ID.
   --products-only works independently of the new selector and makes no API call,
   print, database write or email. It outputs only supported product metadata.

   ```bash
   php artisan ghl:inspect-cart LOCAL_ORDER_ID --products-only
   ```

2. Match the physical book and digital bundle's actual product IDs. Put them in
   the deployment .env, not the Lulu POD or GHL custom-field IDs:

   ```dotenv
   GHL_PHYSICAL_PRODUCT_IDS=ACTUAL_PHYSICAL_BOOK_PRODUCT_ID
   GHL_NON_PRINT_PRODUCT_IDS=ACTUAL_DIGITAL_BUNDLE_PRODUCT_ID
   ```

   Multiple verified IDs may be comma-separated. Both lists must be disjoint.
   Supported identity fields: productId, product_id, product.id, product._id or
   product as an ID string. Bare item.id is NOT treated as a product ID because
   it can identify the cart line. No name, price or positional inference.

3. Pause incoming checkout webhook processing and allow in-flight print jobs to
   finish before stopping the worker manager/worker cron for the release. Back up
   changed config. Deploy these runtime files as one release:

   - app/DTOs/OrderData.php
   - app/Exceptions/NonPrintOrderException.php (new)
   - app/Http/Controllers/WebhookController.php
   - app/Jobs/ProcessLuluPrintJob.php
   - config/services.php
   - app/Console/Commands/InspectGhlCart.php (new diagnostic)

   No migration, composer dependency, frontend build or cron-frequency change is
   required for this fix. Previously unsubmitted saved carts are rechecked by the
   worker; failed queue records are not automatically replayed. Old records without
   raw cart data need manual review. Do not run queue:retry all.

4. Refresh config and validate existing saved carts without fulfilling them:

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan ghl:inspect-cart LOCAL_ORDER_ID
   php artisan queue:restart
   ```

   Check book-only and bump examples; expected selection is 1 for a one-book cart
   in both cases, and 2 for an actual two-book cart. Resume intake/workers only
   after identity verification. This command reads saved data; it does not correct
   an already shipped order or create a new print job.

Legacy compatibility: payloads without any items still use the approved explicit
book quantity (default 1). With no selector configured, one anonymous legacy item
is also supported. Identified or multi-item carts require verified mappings; with
mappings configured, every structured item must have a supported known product ID.
No-items events must remain book-only; aggregate cart totals cannot identify products.

Tests cover mixed/physical-only/reordered carts, multiple real copies, nested ID
aliases, electronic-only acknowledgement, unknown/unconfigured products, identity
overlap, invalid quantities, duplicate intake, no-items compatibility, worker
quantity/cost correction, existing/uncertain guards, and read-only inspection.
See handover.md for final suite totals. Live mapping is not verified by fake IDs.

Already printed/shipped jobs cannot be corrected by editing local quantity or
running GHL-only sync. Do not rewrite historical fulfillment to conceal the
overprint. Existing queued, not-yet-submitted affected orders require a separate
review before processing; preserve their raw data and record any correction.
