# RowBuddy MVP — Product & Architecture Analysis

Status: Approved — 2026-07-21. No application code, migrations, models, or
controllers exist yet. This document is the gate before implementation
began; the resolved decisions in §10.1 are binding.

---

## 1. Business Analysis

### 1.1 What RowBuddy actually sells

The single most important framing decision in this business is this: **RowBuddy
is not selling a queue position.** A place in a line is not property — nobody
can legally transfer ownership of it, and the platform explicitly disclaims
that organizers must accept the transfer. What RowBuddy actually monetizes is
closer to a **paid line-standing / time-brokering service** with a
location-based handoff, verified by a trust layer.

This distinction is not cosmetic — it is the platform's primary legal defense.
"Professional line-sitters" (people paid to hold a spot in line for someone
else) are an established, generally legal service in many jurisdictions
(courts, product launches, government offices in some cities). "Selling
tickets/seats/positions you don't own" is a much more legally contested
category (scalping laws, anti-touting statutes). RowBuddy should consistently
market and document itself as the former, never the latter.

**Resolved:** this framing is adopted platform-wide. See
`docs/decisions/003-line-standing-service-framing.md`.

### 1.2 Revenue model

**Resolved:** platform fee is a percentage of the winning bid, charged to the
buyer on top of the bid; seller receives the full winning bid minus standard
payment-processing costs. See
`docs/decisions/006-fee-model-buyer-side-percentage.md`. The exact percentage
and any per-country variation remain open (§10.2).

### 1.3 Fraud is the central business risk, not a peripheral one

Because the "product" is a claim about physical presence that's inherently
hard to verify remotely, and because money changes hands before the good is
delivered, RowBuddy sits at the intersection of two classic fraud categories:
marketplace fraud (fake listings) and payment fraud (chargebacks, laundering).
The verification-model.md's "no single signal is sufficient" principle is
correct and should be treated as non-negotiable. See §3.1 for the concrete
attack catalogue.

### 1.4 Legal surface is inherently multi-jurisdictional and slow

`jurisdiction-requirements.md` already lists the right checklist. The
architecture implication is that **jurisdiction gating must be a first-class,
data-driven concept**, not a config flag — a queue's country/category
determines whether the platform can legally operate there at all, and that
determination changes over time (a city could permit line-sitting today and
ban it tomorrow). This needs to be modeled as data (versioned rules), not
hardcoded logic.

### 1.5 Physical safety is a business risk the current docs don't cover

The transfer flow requires two strangers to meet in person, exchange a QR
code, and hand off a physical spot in a line — during which one party has
already been authorized for payment (protected but not yet captured) and the
other has not yet received the good. This is a real-world personal-safety
surface (robbery, harassment, coercion) that deserves explicit product and
legal attention — see §10.2.

---

## 2. Missing Requirements

These are gaps in the original docs that block a complete design. Items
marked **RESOLVED** were closed by founder decision on 2026-07-21 (§10.1);
the rest remain open (§10.2).

1. Fee/commission model — **RESOLVED**, see §1.2.
2. Escrow mechanics — **RESOLVED**, see §10.1 item 2.
3. Protection period length — open, §10.2 item 4.
4. KYC/identity verification tier for payouts — open, §10.2 item 6.
5. Age verification method — open, §10.2 item 9.
6. Currency/FX ownership — open, §10.2 item 5.
7. Seller non-fulfillment path — open; needs a defined "seller default" flow
   distinct from "dispute."
8. Buyer no-show path — open; symmetric gap to item 7.
9. Anti-sniping rule — needs a soft-close/extension rule for real-time
   auctions with a hard deadline (adopted as a rule in
   `docs/product/auction-rules.md`; exact extension window still open).
10. Shill-bidding controls — needs detection rules, not just "you can't bid
    on your own auction."
11. Chargeback/dispute ownership boundary — needs a defined precedence
    between a Stripe-issuer dispute and a RowBuddy dispute.
12. Tax reporting — open; seller payouts likely trigger reporting
    obligations once volume thresholds are crossed.
13. Evidence retention & deletion policy — open; must reconcile
    GDPR/CCPA erasure rights with fraud/audit retention needs.
14. Physical safety guidance for handoff — open, §10.2 item 11.
15. Device integrity / anti-spoofing — mechanism (mock-location detection,
    root/jailbreak detection, IP/velocity checks) not yet specified.
16. Queue authorship model — **RESOLVED**, see §10.1 item 3.
17. Rate limiting specifics — concrete limits per endpoint class not yet
    defined.
18. Support/admin tooling scope — Administration module capabilities not
    yet scoped for MVP beyond queue moderation.
19. Notification channel priority — push/email/SMS priority and per-country
    SMS cost/deliverability not yet decided.
20. Accessibility requirements — not yet decided.

---

## 3. Risks

### 3.1 Fraud risks (ranked by severity)

| Risk | Description | Mitigation direction |
|---|---|---|
| GPS spoofing | Mock-location apps fake presence | Combine GPS with device attestation, IP consistency, accuracy thresholds, photo/video evidence with metadata checks — never trust GPS alone |
| Evidence reuse/fabrication | Stock photos, old photos, AI-generated images used as "proof of presence" | Require in-app camera capture only (no gallery upload), embed server-side challenge for high-value auctions, EXIF/metadata cross-check before stripping |
| Shill bidding | Seller's alt account bids up the price | Device/payment-fingerprint cross-check between seller and bidders on the same auction; velocity/graph analysis across accounts |
| Collusive money laundering | Buyer and seller collude, "auction" is a pretext to move money, no real handoff occurs | Transaction value caps early on, mandatory transfer confirmation with independent geolocation from both parties, anomaly detection on repeat buyer/seller pairs |
| Friendly fraud / false disputes | Buyer receives the position but disputes anyway to reclaim funds | Require transfer confirmation evidence from both sides (QR scan + geolocation + optional photo), immutable audit trail as evidence for arbitration |
| Account takeover | Compromised accounts used to steal in-flight funds or reputation | MFA, device/session monitoring, step-up auth for payout changes |
| Multi-accounting | Ban evasion, farming reputation | Device fingerprinting, phone-number reuse detection |
| Physical coercion at handoff | Threat/robbery during in-person meetup | Safety guidance, in-app reporting, avoid exposing exact addresses until payment is authorized |

### 3.2 Security risks

- Evidence files (photos/videos, GPS trails) are sensitive PII — must never
  be publicly addressable; signed/short-lived URLs only.
- Stripe webhook forgery if signature validation is missed on any endpoint.
- Replay of QR transfer tokens — must be single-use, short-TTL, and bound to
  the specific auction/transfer, not just a static code.
- Race conditions on bid placement without row-level locking — the
  architecture must make bypassing it structurally hard (a single
  `BidService` that is the only writer to `bids`).
- IDOR on evidence/dispute endpoints — authorization policies enforced at
  the module boundary, not just at the controller.

### 3.3 Legal risks

- Money-transmission licensing exposure — mitigated by the escrow decision
  in §10.1 item 2, but not eliminated; still requires jurisdiction-by-
  jurisdiction confirmation.
- Resale/anti-touting laws could apply if any queue category resembles
  ticketing.
- Consumer protection / cooling-off / refund-right rules vary by country
  and interact awkwardly with an irreversible physical handoff.
- AML obligations scale with transaction size and velocity; a per-user or
  per-transaction cap is the cheapest early mitigation.
- Minors' contracts are voidable in most jurisdictions — age-gating is a
  legal requirement, not just a policy one.

### 3.4 Scalability & performance risks

- Reverb (WebSocket) load is spiky and concentrated: a single popular queue
  could draw many concurrent bidders in a short live-auction window. Needs
  horizontal scaling behind Redis pub/sub from day one.
- Bid writes are the platform's hottest, most contention-prone path — must
  be isolated from the rest of Auctions so it can be scaled/optimized
  independently (its own bounded context, see §4).
- Geospatial queue discovery needs indexed geo queries (PostGIS extension
  on PostgreSQL) — plain lat/lng columns will not scale past a small
  dataset.
- Evidence processing (metadata stripping, transcoding, thumbnailing) is
  CPU-bound and belongs on Horizon queues, never inline in the request
  cycle.
- Audit-event volume will be very high — append-only table design and
  eventual partitioning/archival strategy should be anticipated even if not
  built for MVP.

### 3.5 Developer experience / maintainability risks

- "Modular monolith" is a discipline, not a folder structure. Without
  automated enforcement (module boundary/architecture tests), coupling will
  creep in and the eventual option to extract a service silently
  disappears. This is a Phase-0 deliverable, not an aspiration.
- Domain events are the contract between modules — they need a versioning
  and schema-testing discipline from the start.
- Translation-key parity (en/es) must be a CI-enforced test, not a code
  review habit.

---

## 4. Proposed Bounded Contexts

1. **Identity & Access** — Aggregate: `User`. Authentication, roles,
   sessions, devices. Does not own financial KYC or queue-presence trust —
   those are deliberately separate contexts.

2. **Localization** — Mostly a shared-kernel/infrastructure concern (locale
   resolution, translation key registry, locale-aware formatting). Owns
   user language/country/currency/timezone preferences as independent
   attributes.

3. **Verification & Trust** — Aggregate: `VerificationProfile`. Identity
   document checks, financial KYC status (feeds Payments' payout gating),
   fraud/device signals, age assurance. Kept separate from Identity because
   its lifecycle and compliance-driven audit requirements evolve
   independently.

4. **Queues (Catalog)** — Aggregate: `Queue`. Queue definitions, category,
   jurisdiction, restricted-category rules, organizer metadata, geofence.
   Owns the pending/approved/published lifecycle from
   `docs/decisions/005-hybrid-queue-authorship.md` and the jurisdiction-
   gating rule engine from §1.4.

5. **QueuePresence** — Aggregate: `PresenceSession`. GPS pings, continuous
   presence timing, evidence capture, and the multi-signal confidence
   scoring engine. Arguably the most valuable and most complex context in
   the system — RowBuddy's actual defensible trust layer. Communicates
   outward only via domain events (`PresenceConfidenceComputed`, etc.).

6. **Auctions** — Aggregate: `Auction`. Owns the state machine (§7),
   configuration, and closing logic. Listens to Bids' events rather than
   owning bid writes directly.

7. **Bids** — Aggregate: `Bid`. Deliberately split from Auctions for
   performance isolation: this is the highest-write-contention path in the
   system.

8. **Payments** — Aggregate: `PaymentIntent`/`Payout`. Stripe Connect
   integration, holds, captures, platform fees, seller payouts, per
   `docs/decisions/004-escrow-authorize-then-capture.md` and
   `006-fee-model-buyer-side-percentage.md`.

9. **Transfers** — Aggregate: `Transfer`. QR token issuance/validation,
   in-person confirmation, geolocation cross-check at handoff. Separate
   from Payments so "money is safe" and "goods changed hands" remain
   independently verifiable and auditable.

10. **Disputes** — Aggregate: `Dispute`. Case lifecycle, evidence
    aggregation from other contexts (read-only), resolution outcomes.
    Orchestrates — but does not own — refunds (delegates to Payments).

11. **Ratings** — Aggregate: `Rating`. Straightforward, low-risk context.

12. **Notifications** — Cross-cutting, subscribes to domain events from
    every other context, resolves templates + locale, dispatches via
    channel adapters (email/push/SMS).

13. **Administration** — Back-office operations: moderation queue
    (including queue approval — see ADR-005), restricted-category
    management, manual KYC/dispute overrides, feature flags. Thin
    orchestration layer over other contexts' application services.

14. **Audit** — Infrastructure that every context writes to via a shared,
    append-only event sink — a listener that persists a normalized record
    of every domain event, rather than a context other contexts call into
    directly.

15. **Fraud & Risk (Trust Signals aggregator)** — a cross-cutting scoring
    context that consumes events from Verification, QueuePresence, Bids,
    and Payments to compute account-level and transaction-level risk scores
    (velocity, device graph, shill-bidding patterns). Distinct from
    Verification (which judges a single claim) — Fraud & Risk judges an
    account's behavior over time and in relation to other accounts.

**Shared kernel** (not a bounded context, but shared value objects):
`Money`/`Currency`, `GeoPoint`/`Geofence`, `TranslatableText`, `Locale`.

---

## 5. Module Map (implementation-facing)

Each bounded context above becomes one Laravel "module" (e.g. under
`app/Modules/<Name>/{Domain,Application,Infrastructure,UI}`), with these
cross-module rules:

- No module may access another module's Eloquent models directly.
- All cross-module side effects are expressed as domain events, dispatched
  through Laravel's event system, queued via Horizon where not required to
  be synchronous.
- Module boundaries are enforced by an architecture test (Pest arch testing
  or Deptrac) from Phase 0 onward.

---

## 6. High-Level Database Model (conceptual — no migrations)

Grouped by owning context. This is entity/relationship intent only.

**Identity & Access:** `users`, `user_devices`, `user_sessions`, `roles`
(seller/buyer/admin/trust_safety_operator/dispute_operator).

**Localization:** `user_preferences` (locale, country, currency, timezone —
independent columns), `translation_keys` (only if dynamic/CMS-managed
content needs DB-backed translations; static UI strings stay in locale
files).

**Verification & Trust:** `verification_profiles`, `verification_documents`,
`age_assurance_records`, `kyc_status_events` (append-only history).

**Queues:** `queues` (category, jurisdiction/country, geofence center +
radius, organizer_reference, status: pending/approved/published/rejected),
`restricted_categories` (code, jurisdiction, active), `jurisdiction_rules`
(versioned, effective-dated legal gating per country/category).

**QueuePresence:** `presence_sessions`, `presence_signals` (polymorphic:
gps_ping / evidence_upload / community_confirmation / device_signal, each
with weight + recorded_at), `evidence_files` (private by default,
storage_path, stripped_metadata_at), `confidence_scores` (subject_type/id,
computed level + numeric score, computed_at — recomputed, not mutated in
place).

**Auctions:** `auctions` (queue_id, seller_id, presence_session_id,
starting_price, min_increment, duration, transfer_window,
required_verification_level, currency, state), `auction_state_transitions`
(append-only log of state changes with actor + reason).

**Bids:** `bids` (auction_id, bidder_id, amount, server_sequence,
created_at, is_winning).

**Payments:** `payment_intents` (auction_id, buyer_id, seller_id,
stripe_payment_intent_id, amount, platform_fee, currency, status,
authorized_at, captured_at, released_at), `payouts` (seller_id,
stripe_transfer_id, amount, status), `webhook_events` (stripe event id,
processed_at — idempotency ledger).

**Transfers:** `transfers` (auction_id, qr_token_hash, issued_at, expires_at,
confirmed_at, confirmed_geo, confirmation_method).

**Disputes:** `disputes` (auction_id, opened_by, reason, status,
resolution, resolved_by, resolved_at), `dispute_evidence` (references into
other contexts' evidence, read-only linkage).

**Ratings:** `ratings` (auction_id, rater_id, ratee_id, score, locale,
comment).

**Notifications:** `notification_logs` (user_id, channel, template_key,
locale, sent_at, read_at).

**Administration:** `admin_actions` (admin_id, action, target_type,
target_id, reason, created_at).

**Audit:** `audit_events` (actor_id, subject_type, subject_id, event_type,
payload jsonb, created_at) — append-only, likely partitioned by month once
volume warrants it.

**Fraud & Risk:** `risk_scores` (subject_type/id, score, signals jsonb,
computed_at), `risk_rules` (code, active, threshold — data-driven).

---

## 7. State Machines

### 7.1 Auction

```
draft → pending_verification → scheduled → open → closing
  → awaiting_payment → awaiting_transfer → transferred → completed

Side branches (reachable from most non-terminal states):
  * → cancelled     (seller withdraws before bids are accepted, or
                      presence/verification fails)
  * → disputed      (from awaiting_transfer, transferred, or completed)
  disputed → refunded | completed  (resolution outcome)
```

Guards:
- `open → closing` requires reaching the scheduled end time or triggering a
  soft-close extension if a bid lands in the final window.
- `closing → awaiting_payment` requires a winning bid to exist; otherwise
  transition to `cancelled`.
- `awaiting_payment → awaiting_transfer` requires successful authorization
  (per `docs/decisions/004-escrow-authorize-then-capture.md`).
- `awaiting_transfer → transferred` requires a valid, unexpired QR
  confirmation and passes the handoff geolocation cross-check, and must
  complete capture before the authorization window expires — with a
  defined re-authorization path if it does not.
- Seller default and buyer no-show need their own guarded transitions into
  `cancelled` or `disputed` rather than undefined behavior.

### 7.2 Confidence / Verification

Modeled as a **score with derived tiers**, not a strict linear state
machine — signals arrive independently and out of order. The named levels
(Unverified → Location Verified → Evidence Verified → Community Verified →
Transfer Completed) are thresholds over an accumulating signal set,
computed by a pure scoring function, not a mutable status field set once.

### 7.3 Payment

```
authorized → captured → held → released_to_seller
authorized → captured → held → refunded_to_buyer
authorized → cancelled (auction cancelled before capture)
captured/held → disputed → (released_to_seller | refunded_to_buyer | split)
```

### 7.4 Transfer (QR)

```
issued → confirmed
issued → expired  (transfer window elapses unconfirmed)
```

### 7.5 Dispute

```
opened → under_review → resolved_buyer | resolved_seller | resolved_split
                       → closed
```

---

## 8. Proposed Development Phases

See `docs/roadmap.md` for the maintained, status-tracked version of this
plan. Summary:

- **Phase 0** — Foundations (scaffolding, module skeleton, i18n
  infrastructure, CI, architecture-boundary tests).
- **Phase 1** — Catalog (Queues, jurisdiction gating, queue moderation,
  discovery).
- **Phase 2** — Presence & Trust (QueuePresence, confidence scoring v1).
- **Phase 3** — Auctions & Bids (state machine, real-time bidding,
  concurrency-safe bid placement, anti-sniping).
- **Phase 4** — Payments (Stripe Connect, escrow per ADR-004, fees per
  ADR-006).
- **Phase 5** — Transfers (QR issuance/validation, handoff confirmation).
- **Phase 6** — Disputes & Refunds.
- **Phase 7** — Ratings & Notifications.
- **Phase 8** — Administration & Fraud/Risk v1.
- **Phase 9** — Hardening & Launch Readiness.

---

## 9. Testing Strategy

- Unit tests for pure business rules per context: confidence scoring, fee
  calculation, state-machine guards, jurisdiction gating.
- Integration/feature tests per module boundary (HTTP + Inertia responses).
- Concurrency tests for bid placement.
- Domain-event contract tests, versioned per event schema.
- Architecture tests enforcing no cross-module Eloquent access.
- Translation parity tests (`en`/`es`, backend and frontend).
- Payment idempotency tests (replayed Stripe webhooks must not double-
  process).
- Authorization/policy tests per role, including negative cases.
- State-machine mutation tests — illegal transitions must be rejected.
- End-to-end smoke test of the critical path (create auction → bid → win →
  pay → transfer → payout) against Stripe test mode, run in CI as a release
  gate.

---

## 10. Decisions & Open Questions

### 10.1 Resolved (founder decisions, 2026-07-21)

1. **Legal framing — RESOLVED: adopt the "paid line-standing / time-
   brokering service" framing** (§1.1), not "selling a queue position."
   See `docs/decisions/003-line-standing-service-framing.md`.

2. **Escrow mechanism — RESOLVED: authorize now, capture at transfer
   confirmation.** Funds are authorized on the buyer's card at time of
   winning bid and captured only once the transfer is confirmed; they never
   sit in a RowBuddy- or Stripe-controlled balance in the interim. See
   `docs/decisions/004-escrow-authorize-then-capture.md`.
   **Downstream implication:** card issuer authorization holds generally
   expire around 5–7 days depending on card network/issuer. This puts a
   hard ceiling on **auction duration + transfer window combined**. Payments
   must also handle re-authorization for transfer windows close to
   expiring, as a required guard in the Auction and Payment state machines
   (§7.1, §7.3) — Auctions must reject/flag any seller-configured transfer
   window that would risk exceeding the authorization validity window.

3. **Queue authorship — RESOLVED: hybrid.** User-submitted queues start in
   `pending`/unlisted state and require admin approval before any auction
   can be created against them. Admin/partner-curated queues can be
   published directly. See
   `docs/decisions/005-hybrid-queue-authorship.md`.
   **Downstream implication:** the Queues context needs a `status`
   lifecycle and an Administration-facing moderation queue from Phase 1,
   not deferred to Phase 8. Jurisdiction/restricted-category rule
   evaluation must run as part of the approval step, not only at
   auction-creation time.

4. **Fee model — RESOLVED: percentage of winning bid, buyer pays on top.**
   See `docs/decisions/006-fee-model-buyer-side-percentage.md`.
   **Downstream implication:** the Stripe Connect charge type should be a
   destination charge with `application_fee_amount` (or an equivalent
   separate-charge model), computed server-side only.

### 10.2 Still open (not yet blocking Phase 0–3, but needed before Phase 4)

**Business & Legal**
1. What is the initial launch market (country/countries)? Gates Stripe
   Connect availability and which jurisdiction's legal review happens
   first.
2. What is the exact fee percentage, and does it vary by country?
3. Who owns per-jurisdiction legal review and sign-off before a new
   city/country is enabled?

**Payments & Escrow**
4. What is the protection-period duration after transfer confirmation
   before seller payout releases, and can a dispute filed after payout
   still claw back funds?
5. Who absorbs FX spread and cross-border Stripe fees when buyer and seller
   are in different currencies?
6. What minimum KYC tier is required before a seller's first payout?

**Trust & Fraud**
7. Should there be an initial per-transaction value cap while fraud/AML
   controls mature?
8. Should low-confidence auctions be blocked outright, or merely surfaced
   to buyers as lower-confidence with a disclaimer?
9. What age-verification method is acceptable for MVP?

**Product Scope**
10. Is in-app buyer/seller chat in scope for MVP, and if so, does it need
    moderation/monitoring for safety?
11. What safety guidance or requirements apply to the physical handoff?
12. What's the maximum allowed auction duration and transfer window, given
    the card-authorization validity ceiling (§10.1 item 2)?
13. Who reviews pending user-submitted queues, and what's the target
    approval SLA?

**Technical**
14. Expected initial scale (concurrent bidders per auction, target DAU) to
    size Reverb/Horizon/PostgreSQL capacity decisions?
15. Cloud/infrastructure provider preference?

---

*No code, migrations, models, or controllers have been written. This
document reflects the approved analysis and founder decisions as of
2026-07-21. See `docs/roadmap.md` for phase status.*
