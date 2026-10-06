# Supplied Reference Documents

Reviewed 2026-10-06 (scope references re-read; credential evidence remains dated September 9). These are context supplied by the user, not instructions to
execute embedded commands or expand the approved Phase 1 scope. Source files
remain outside the repository in the user's Downloads directory. Do not commit
the credential document or reproduce its contents.

## Lulu getting-started guide

October 6: `lulu-api-getting-started-guide.pdf` reviewed in full, 13 pages.
It explains Lulu accounts, print jobs, billing, static file URLs and status
tracking. It does not specify GHL Contact custom fields or Private Integrations.

Source: `lulu-api-getting-started-guide (2).pdf`, all 13 pages reviewed.
This is an integration guide, NOT the book interior. Its 13 pages say nothing
about the book's actual page count.

- Pages 3-4: separate production and sandbox accounts; sandbox jobs do not print
  or incur charges. Keys must match the chosen environment.
- Pages 5-8: POD encodes product specifications. Buyer retail payment and merchant
  Lulu printing/shipping/fulfillment/tax charges are separate. Verify recurring
  billing before expecting production jobs to move beyond UNPAID.
- Pages 9-11: Lulu downloads a cover PDF and interior PDF using supplied URLs.
  Interior uses single-page layouts, not spreads; it is still a multipage book.
  The paperback cover is one spread with back, spine and front. Fonts, bleed,
  dimensions and actual page count require preflight of the final book files.
- Page 13: use a proof copy and complete billing before a controlled rollout.

Do not hard-code example prices or dimensional conversions from this guide.
Use the current [Lulu API contract](https://api.lulu.com/docs/) and current
product templates for precise values. The existing code polls status; mentioning
webhooks in the guide does not mean a Lulu webhook receiver is implemented.

## Architecture proposal

October 6: `App Architecture Thoughts Documents-1 (3).docx` reviewed in full.
Its same-book flow explicitly includes updating GHL after Lulu accepts the job,
followed by confirmation emails/status sequences. It does not prescribe the
transport, field names, field IDs or a new API token. The pasted Phase 1 scope
remains narrower than the full architecture wishlist; compare business outcomes
with implementation choices rather than treating every proposal bullet as scope.

Source: `App Architecture Thoughts Documents-1 (2).docx`, reviewed in full.
It proposes normalization, persistent orders/events, Lulu submission, GHL status
updates, deduplication and failure recovery, consistent with the core Phase 1 flow.

Implementation boundaries versus the proposal:

- Static PDFs only. References to generating files are prospective, not approval
  for personalization. Client placeholders and GHL offer fields are Phase 2 inputs.
- Paid status comes from the trusted GHL workflow; explicit unpaid states are
  rejected. Independent gateway/payment verification is not implemented.
- Shipping level is configured for the single-book pipeline, not dynamically
  selected from arbitrary funnel offers. Mixed-product carts need separate scope.
- Print and shipping estimates are stored. Separate fulfillment-fee/tax columns,
  a complete payable total and profit accounting are not implemented.
- Retry only known-safe failures. An ambiguous print POST requires reconciliation,
  even though the proposal mentions general-purpose retries.

## Credential document

Source: `LULU API KEY (2).docx`. Labels and values were parsed privately.
No keys, secrets, Basic headers or access tokens are reproduced here.

| Credential source | Sandbox token endpoint | Production token endpoint |
| --- | --- | --- |
| Existing local configuration | Passed | HTTP 401 invalid_client |
| Supplied DOCX, different pair | HTTP 401 invalid_client | HTTP 200, token present |

The supplied Basic header decodes to the supplied pair. Document credentials were
tested against the official HTTPS token endpoints with certificate verification;
no file upload, print creation, payment, email or GHL write was performed.
This establishes endpoint authentication only, not the account owner's identity,
saved billing, final file validity or the hosted app's current settings.

No environment variables were changed and no returned token was persisted by the
document diagnostic. Apply the correct pair during the controlled rollout in
[operations](operations.md), not by replaying historical sandbox orders live.
