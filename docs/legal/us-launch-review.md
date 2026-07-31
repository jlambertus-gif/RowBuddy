# United States Launch — Legal Review

Market: United States (ADR-027 Decision 1 — the sole initial launch
market; expansion to any other jurisdiction is out of scope for this
document and for Phase 9).

Status: **Skeleton — no determination has been made for any item below.**
Per ADR-027 Decision 2, this document is the sign-off artifact: legal
sign-off for the US market is this document reaching a recorded
determination for every item, followed by the corresponding
`jurisdiction_rules`/`restricted_categories` rows being set to reflect
it through Administration's existing activation capability (Phase 8).
**No determination on this page is invented by the implementation** — each
must be provided or validated by the appropriate legal authority before
it can be marked resolved. Per ADR-027 Architecture Refinements §7,
Phase 9 must not describe itself as legally launch-ready while any item
below remains "Awaiting determination."

This document walks through `docs/legal/jurisdiction-requirements.md`'s
own checklist, in the same order, for the United States specifically.

## 1. Lawfulness of queue-position transfers

Status: **Awaiting determination.**

Whether transferring a physical queue position — framed per
[ADR-003](../decisions/003-line-standing-service-framing.md) as
compensation for time/effort already spent waiting plus a verified
handoff, never as the sale of the position itself — is lawful under US
federal law and the law of whichever state(s) the platform operates in
at launch.

## 2. Venue or organizer permission

Status: **Awaiting determination.**

Whether venues/organizers for the categories RowBuddy intends to launch
with in the US require, or have granted, explicit permission for
queue-position transfers to occur on their premises or for their events.

## 3. Marketplace licensing requirements

Status: **Awaiting determination.**

Whether operating RowBuddy as a marketplace in the US triggers any
state or federal marketplace-facilitator, money-services-business, or
similar licensing requirement, beyond what Stripe Connect Express's own
onboarding already covers.

## 4. Payment and money-transmission implications

Status: **Awaiting determination.**

Whether the existing authorize-at-bid-win, capture-at-transfer-
confirmation escrow model ([ADR-004](../decisions/004-escrow-authorize-then-capture.md))
and the buyer-side percentage fee model
([ADR-006](../decisions/006-fee-model-buyer-side-percentage.md)),
executed via Stripe Connect Express, are sufficient on their own for US
money-transmission compliance, or whether an additional license or
partner arrangement is required.

## 5. Tax requirements

Status: **Awaiting determination.**

What US federal and state tax obligations (sales tax, marketplace
facilitator tax collection/remittance, 1099-K reporting thresholds for
sellers, etc.) apply to transactions on the platform, and who is
responsible for each.

## 6. Consumer-protection rules

Status: **Awaiting determination.**

Which US federal (e.g., FTC) and state consumer-protection rules apply
to the marketplace, and whether the platform's current disclosures
(the paid line-standing/time-brokering framing, ADR-003) satisfy them.

## 7. Refund obligations

Status: **Awaiting determination.**

What refund rights, if any, US consumer-protection law grants a buyer or
seller beyond what the platform's own Disputes/Transfers domain already
implements (Phase 5/6: transfer-expiry cancellation with no charge,
dispute-resolution refund/split/release outcomes).

## 8. Privacy and evidence-retention requirements

Status: **Awaiting determination.**

What US federal and state privacy law (e.g., state-level data-privacy
statutes) requires for retention and deletion of the private evidence
this platform already captures and stores privately by default
(QueuePresence evidence photos, Transfers evidence, Disputes evidence) —
including whether any statutory retention period or erasure-right
applies, and how it interacts with this platform's current practice of
never deleting evidence automatically.

## 9. Age restrictions

Status: **Awaiting determination.**

What minimum age applies to buying or selling a queue position in the
US (`docs/product/business-rules.md` rule 12 already prohibits minors
transacting; this item determines the specific age threshold and
verification method acceptable for US launch — self-attestation via
Stripe Connect's own KYC age check, or something further). Per ADR-027
§7, building dedicated age-verification infrastructure beyond this
determination is explicitly deferred beyond Phase 9.

## 10. Dispute-resolution requirements

Status: **Awaiting determination.**

What US consumer-arbitration or dispute-resolution requirements apply,
and whether the existing manual, administrator-resolved Disputes model
(Phase 6, `Dispute.Resolved` terminal, ADR-021) — including its current,
defensive-only Stripe chargeback-reconciliation posture (ADR-019 §7,
full chargeback precedence deferred beyond Phase 9 per ADR-027 §7) — is
adequate for US launch, or whether it surfaces a hard blocker requiring
its own separate decision.

## 11. Line-standing framing holds locally

Status: **Awaiting determination.**

Confirmation that [ADR-003](../decisions/003-line-standing-service-framing.md)'s
paid line-standing/time-brokering legal framing — never the sale of a
position itself — holds under US federal law and the law of whichever
state(s) the platform operates in at launch, including any state-level
line-sitting precedent or anti-scalping/anti-touting statute that may
apply to the categories RowBuddy intends to launch with.

## Enforcement once determinations are recorded

No new persistence model, workflow, or Administration feature exists for
this (ADR-027 Decision 2). Once an item above is resolved:

- If the determination requires blocking a category or the US market
  generally, an administrator activates/deactivates the corresponding
  `restricted_categories`/`jurisdiction_rules` row through Administration's
  existing capability (Phase 8, `RestrictionActivationService`), citing
  this document as the mandatory reason.
- Every such change is already recorded in `admin_actions` — acting
  administrator, timestamp, previous/new state, reason — with no
  additional tracking mechanism required.

## Sign-off

This document is **not** signed off. Sign-off is reached only once every
item above is marked resolved with a recorded determination, not
"Awaiting determination." Until then, per ADR-027 Architecture
Refinements §7, any Phase 9 completion reporting must identify legal
sign-off as an external launch blocker.
