# ADR 027: Phase 9 Launch-Readiness Scope and the Minimum-Necessity Delivery Boundary

## Status

Accepted. All eight Phase 9 product decisions (Decision 0 through
Decision 7) are frozen and recorded below.

## Context

`docs/roadmap.md`'s entire documented Phase 9 scope is one sentence:
"Load-testing Reverb/bidding under concurrency, security review,
per-jurisdiction legal sign-off for each launch market
(`docs/legal/jurisdiction-requirements.md`), observability, Horizon
capacity tuning." No ADR assigns concrete work to Phase 9 by name; the
only verbatim "Phase 9" reference anywhere in the existing 26 ADRs
(ADR-026 §1 Consequences) merely notes the roadmap schedules no phase
after it for Fraud & Risk.

A pre-implementation architecture review (this conversation) found that
the roadmap's five named responsibilities cannot be executed in a
vacuum: "load-testing Reverb/bidding under concurrency" presupposes
bidding is reachable over HTTP/WebSocket by something resembling a real
client, and today it isn't — Auctions and Bids remain domain/backend-only
by deliberate deferral since Phase 3. "Security review" of the risks
`docs/product/claude-mvp-analysis.md` §3.2/§3.3 already name (webhook
forgery, QR replay, bid-placement races, IDOR, evidence exposure) can
only review surfaces that exist. "Per-jurisdiction legal sign-off for a
launch market" presupposes a product a buyer or seller could actually
complete a transaction through.

At the same time, treating Phase 9 as license to complete every
previously deferred delivery surface (HTTP/UI for Auctions, Bids,
Payments, Transfers, Disputes, and Ratings; buyer payment-method
acquisition; seller payout execution; Fraud & Risk) would make it far
larger than any prior phase and would reopen scope this project has
deliberately, repeatedly declined to build ahead of need. Decision 0
resolves this tension directly, and every subsequent decision applies
its resulting principle.

## Decision

### 0. "Launch readiness" is bounded by necessity, not completeness

Phase 9 remains fundamentally a hardening and launch-readiness phase. A
delivery surface may be added during Phase 9 **only if one of the five
roadmap responsibilities cannot be performed without it**. This is
narrower than "complete the MVP first, then harden it" and broader than
"harden the backend exactly as it exists" — a strict reading of the
latter would have Phase 9 load-test bidding concurrency against a
surface that doesn't exist and seek legal sign-off for a transaction
flow nobody can complete.

Concretely:

- Implement only the minimum HTTP/WebSocket surface necessary to perform
  realistic bidding-concurrency and Reverb load testing.
- Implement only the minimum payment/transfer delivery surface necessary
  to exercise and legally review the real money-movement path.
- No general UI for Ratings or Disputes.
- No seller payout execution.
- No Fraud & Risk.
- No revisiting of previously accepted bounded-context decisions (the
  synchronous, row-locked bid-placement design from Phase 3 in
  particular stays exactly as built).

Every additional delivery surface introduced during Phase 9 must be
explicitly justified by one of the five roadmap responsibilities.
Anything that cannot be directly traced back to those responsibilities
remains deferred (§7).

### 1. Initial launch market: United States

The United States is formally ratified as Phase 9's — and this
project's — initial launch market. This is **not a new product
decision**; it records the de facto operational assumption already
embodied in the existing architecture and the Phase 4 implementation:
USD-only, Stripe Connect Express, the existing fee model, the existing
payment assumptions, and the existing `jurisdiction_rules`/
`restricted_categories` foundations. `claude-mvp-analysis.md` §10.2 item
1 is resolved by this ratification, not reopened.

Phase 9 is not the place to broaden market scope. All legal review,
launch-readiness work, and capacity planning target the United States
only. Expansion to additional jurisdictions remains a future product
decision and must not be introduced during Phase 9.

### 2. Legal sign-off is documentation-driven, never a new capability

Legal sign-off is a documentation-driven process, not new software.
Phase 9 introduces no separate legal-review workflow, persistence model,
or approval-tracking system. The existing architecture is sufficient:

- `docs/legal/jurisdiction-requirements.md` defines the review checklist
  (ten categories: transfer lawfulness, venue/organizer permission,
  marketplace licensing, payment/money-transmission implications, tax,
  consumer protection, refund obligations, privacy/evidence retention,
  age restrictions, dispute resolution — plus confirming ADR-003's
  line-standing framing holds locally).
- A market-specific legal-review document, starting with the United
  States, records the determination for each checklist item.
- The existing `jurisdiction_rules`/`restricted_categories` data model
  (Phase 1) represents the approved operational state.
- The existing Administration activation/deactivation actions, together
  with `admin_actions` (Phase 8), already provide the complete
  operational audit trail of when and why a legal determination becomes
  active — mandatory reason, acting administrator, timestamp,
  previous/new state.

No additional persistence model, approval workflow, or Administration
feature is required. **The legal conclusions themselves are external
inputs to the software project and must be provided or validated by the
appropriate legal authority — they must never be invented by the
implementation.**

### 3. Provisional MVP engineering targets for load-testing and Horizon tuning

The purpose of this decision is to establish a concrete engineering
target for Phase 9 load testing and Horizon tuning, not to predict
production demand. Sized conservatively for the approved United States
initial launch market (Decision 1):

- Peak concurrent authenticated users: **250**.
- Concurrent active auctions: **100**.
- Peak concurrent bidders on a single auction: **25**.
- Simultaneous bid submissions during a hot-auction closing window: **10
  per second, sustained for 60 seconds**.

Phase 9's responsibility is to demonstrate the platform sustains these
targets while maintaining correct business behavior — not to maximize
throughput. These values are recorded as **provisional engineering
constants**, revisited once real production telemetry exists, following
the exact precedent Phase 3 already established for its own provisional
timing constants (30-minute auction duration, 2-minute soft-close
window, 15-minute staleness threshold — all explicitly "not validated
against real usage," revisable once real data exists).

### 4. Security review: internal, tooled, and independently verifiable

Phase 9 security review combines three things:

1. A structured internal review against the explicitly documented risks
   in `claude-mvp-analysis.md` §3.2 and §3.3.
2. Automated security tooling integrated into the existing development
   workflow.
3. Written documentation of findings and their disposition.

**Scope**: whole-system review, with the highest scrutiny on the new
Phase 9 delivery surfaces (Decision 0) and a confirmatory review of every
existing HTTP surface. The review must verify: authentication and
authorization; IDOR resistance; webhook authenticity; race-condition
handling; evidence privacy; replay protection; input validation; secrets
management; dependency vulnerabilities.

**Approved hardening tasks** (configuration, not new infrastructure):

- Enable `composer audit` in CI.
- Enable Dependabot.
- Enable GitHub Secret Scanning where repository capabilities allow.
- Extend CI so every package executes its own test suite (today only
  `apps/web` and `shared-kernel` run in CI; the other ten packages' 765
  combined tests run only indirectly, through `apps/web`'s wiring
  tests).

**Deliverables**: `docs/security/security-review.md` and
`docs/security/security-checklist.md`. For every identified risk, the
review must record: reviewed; mitigation present; residual risk (if
any); required action (if any); verification evidence (test,
configuration, code reference, or CI check). The objective is not only
to document conclusions but to make every security claim independently
verifiable.

No third-party penetration-test engagement is introduced — not
achievable within this project.

### 5. Observability extends existing platform capabilities only

Phase 9 observability extends existing platform capabilities rather than
introducing new infrastructure. Approved scope:

- Laravel Horizon's own dashboard, protected by a **dedicated
  Administrator-only `AdminCapability`** — extending Phase 8's existing,
  fully generic capability-Gate pattern by exactly one enum case, the
  same way every prior Administration capability has been added.
- Laravel's native `/up` health endpoint, enabled and verified.
- Structured JSON application logging (configuration only; no new
  dependency).
- Verification that `failed_jobs`, Horizon, and application logs
  together provide sufficient operational visibility for the approved
  MVP launch targets (Decision 3).

**No third-party observability platform is introduced during Phase 9.**
Explicitly deferred beyond MVP: APM platforms (Sentry, Bugsnag, Flare,
etc.); dedicated metrics platforms (Prometheus, Grafana, OpenTelemetry,
etc.); distributed tracing; proactive alerting infrastructure — the same
"accepted MVP limitation" posture Phase 7 (ADR-025 §11) already
established for notification-failure alerting, now extended to
observability generally.

**Deliverables**: `docs/operations/observability.md` and
`docs/operations/runbook.md`, describing what is monitored, where it is
monitored, expected operational signals, failure indicators, recovery
procedures, and how Horizon, `failed_jobs`, health checks, and structured
logs are used together during normal operation.

### 6. Horizon capacity tuning is entirely evidence-driven

No number is chosen ahead of evidence. Approved process:

1. Execute the approved Phase 9 load-test scenarios using the provisional
   engineering targets from Decision 3.
2. Observe Horizon, `failed_jobs`, structured logs, and application
   behavior during the tests.
3. Identify actual contention points.
4. Tune Horizon supervisors, process counts, balancing strategy, and
   queue assignments iteratively.
5. Repeat until the approved targets are sustained with stable queue
   latency and without unbounded queue growth.

**Queue separation is not introduced by default.** Dedicated queues and
supervisors may be introduced only when load-test evidence demonstrates
measurable contention between workloads (for example, the new
Reverb-broadcast workload a minimal bidding surface introduces competing
with the existing notification-email and transfer-expiry-sweep
workloads). This same empirical approach also applies to any synchronous
bottleneck discovered during testing (PHP-FPM workers, PostgreSQL
connections, locking, etc.), although those concerns remain outside
Horizon itself.

**Deliverables**: final Horizon configuration; load-test report;
capacity-tuning report; recorded rationale for every configuration
change. All resulting configuration values are documented as provisional
MVP tuning constants, revisited after production telemetry becomes
available — the same discipline as Decision 3.

### 7. Disposition of the accumulated, previously unassigned deferred backlog

Several items were deferred across Phases 4–7 with no phase ever
claiming them. Phase 9 resolves **only** those strictly required to
execute the approved launch-readiness responsibilities above; every
other item is explicitly re-deferred with a documented reason — never
silently dropped.

**Resolved during Phase 9** (necessity-justified):

- Real Stripe test-mode credentials, configured in this environment —
  provided externally (the same "external input, never invented by
  implementation" discipline as Decision 2's legal conclusions);
  necessary because Decision 0's minimum payment/transfer surface can't
  be genuinely exercised or security-reviewed against fakes alone.
- The United States legal-review document (Decision 2), completed with
  an explicit determination for every `jurisdiction-requirements.md`
  checklist item, including evidence retention/erasure, age
  restrictions, and dispute-resolution/chargeback posture — closing the
  loop on a commitment Decision 2 already made, not new engineering.
- The branch/release situation, resolved so launch readiness reflects
  the actual production branch (`main` is currently still at the Phase 0
  commit; all of Phases 1–8 exist only on `feat/phase-1-authentication`).

**Explicitly deferred beyond Phase 9** (documented rationale, not
accidental omission):

- ICU locale-data improvements (Spanish number/date formatting silently
  falls back to English in this Docker image — Phase 7, environment-
  level; fixing it means rebuilding the PHP image, not required by any
  of the five roadmap responsibilities).
- Chargeback precedence and reconciliation (ADR-023 already declined to
  assign this to any phase; the US legal review records whether the
  existing defensive-reconciliation-only posture, ADR-019 §7, is
  acceptable for initial launch — a hard-blocker finding there would
  require its own new decision, outside this ADR).
- Dedicated age-verification infrastructure (self-attestation via Stripe
  Connect's own KYC is the extent of what Decision 0 authorizes; the
  legal review records the policy determination, not a new mechanism).
- Physical handoff safety guidance/content (a product/content matter,
  not architecture).
- Value and AML transaction limits.
- Low-confidence-auction blocking policy.
- In-app chat.
- Seller payout execution.
- Fraud & Risk (any automated/rules-based scoring or account
  correlation).

## Consequences

- A new, minimum HTTP/WebSocket delivery surface for bidding
  (Auctions/Bids) is introduced, justified solely by Decision 3's
  load-testing necessity — sized and scoped during architecture design
  after this ADR is accepted, not before.
- A new, minimum delivery surface for the real money-movement path
  (buyer payment-method acquisition sufficient to authorize a real
  payment; Transfers confirmation) is introduced, justified solely by
  Decision 4's security-review and Decision 2's legal-review necessity —
  likewise scoped during architecture design, not before.
- No new delivery surface for Disputes or Ratings.
- No seller payout execution and no Fraud & Risk capability of any kind
  exist after Phase 9, by the same explicit decision that excluded them
  from every prior phase.
- One new `AdminCapability` case, Administrator-only, gating Horizon's
  dashboard — the only new Administration capability this phase
  introduces, extending Phase 8's existing pattern exactly.
- CI is extended to run every package's own test suite, plus
  `composer audit`, Dependabot, and GitHub Secret Scanning, all as
  configuration rather than new tooling infrastructure.
- Five new documentation artifacts: the US legal-review document,
  `docs/security/security-review.md`, `docs/security/security-checklist.md`,
  `docs/operations/observability.md`, `docs/operations/runbook.md` — plus
  the load-test and capacity-tuning reports Decision 6 requires.
- Horizon's configuration changes from Laravel's untouched defaults to
  an empirically tuned, documented, provisional configuration — no queue
  separation unless load-test evidence demonstrates contention.
- Real Stripe test-mode credentials are configured in this environment,
  supplied externally, never fabricated.
- The `main` branch/release situation is resolved so that "launch
  readiness" refers to the actual deployable branch.
- No new persistence model, workflow, or Administration feature is
  introduced for legal sign-off or approval tracking — Phases 1 and 8's
  existing `jurisdiction_rules`/`restricted_categories`/`admin_actions`
  mechanisms are reused exactly as built.
- Every item in the deferred backlog (§7) remains visible in this ADR
  with its own rationale — none is accidentally omitted from the record.

## Explicitly Out of Scope

Consolidating the individual notes above into one list, for the same
reason ADR-021's and ADR-026's own "Explicitly Out of Scope" sections
exist — so the boundary reads as deliberate scope in any future audit,
never an oversight:

- General HTTP/UI for Ratings and Disputes (Decision 0).
- Seller payout execution (Decision 0/§7).
- Fraud & Risk — any automated, rules-based, or score-driven behavior of
  any kind (Decision 0/§7).
- Expansion to any launch market beyond the United States (Decision 1).
- Any new legal-approval-tracking persistence model, workflow, or
  Administration feature (Decision 2).
- Any third-party penetration-test engagement (Decision 4).
- Any third-party APM, dedicated metrics platform, distributed tracing,
  or proactive alerting infrastructure (Decision 5).
- Pre-emptive Horizon queue separation without demonstrated load-test
  contention (Decision 6).
- ICU locale-data improvements, full chargeback precedence/
  reconciliation, dedicated age-verification infrastructure, physical
  handoff safety guidance/content, value/AML transaction limits,
  low-confidence-auction blocking policy, and in-app chat (Decision 7) —
  each deferred with its own documented rationale above, not accidental
  omission.

## Architecture Refinements (recorded before Sprint 1)

Before any implementation began, the following refinements were applied
to the concrete architecture the accepted decisions above imply — every
one narrows or constrains an already-approved surface, none reopens
Decision 0's necessity boundary:

1. **The auction read surface returns an explicit public DTO, never the
   aggregate or Eloquent model.** `GET /auctions/{auctionId}` exposes
   only: public auction reference, current status, current price,
   closing deadline, bid count, and the minimum next acceptable amount
   if already defined by existing domain rules. It never exposes seller
   identifiers, bidder identifiers, internal database identifiers,
   proximity state, private QueuePresence data, or internal event
   payloads. Only auctions already eligible for public discovery may be
   returned.

2. **Bid placement is hardened at the HTTP boundary without touching
   `BidService`'s own behavior.** `bidderId` is derived exclusively from
   the authenticated user, never accepted from request input (nor is any
   `sellerId`). Amount/currency are validated at the request boundary in
   addition to the existing domain validation. `BidService`'s existing
   transaction and row-lock behavior is unchanged. The endpoint adds
   explicit rate limiting and a stable idempotency mechanism so an HTTP
   retry cannot create a second logical bid. Broadcasting happens only
   after the bid transaction commits, and only for an accepted outcome.

3. **The public auction Reverb channel has its own explicit allowlist,
   separate from the HTTP DTO's.** Broadcast payloads carry only: auction
   public reference, current price, public status, closing deadline, bid
   count. Bidder identity, seller identity, payment information,
   proximity information, private transfer information, and raw domain-
   event payloads are never broadcast. The initial HTTP response is the
   source of current state; Reverb communicates only subsequent changes.

4. **`BuyerPaymentMethod` persists whatever Stripe reference actually
   supports safe reuse, not merely a detached PaymentMethod id** — including
   the buyer's Stripe Customer reference where Stripe's own model requires
   it for reuse. Stripe stays fully isolated behind a Payments-owned port
   — implemented by an adapter living inside `packages/Payments` itself
   (corrected during Sprint 3 implementation: "implemented by an
   `apps/web` infrastructure adapter" was this refinement's own drafting
   inaccuracy, not a new placement pattern — every other Stripe
   integration in this codebase, `ConnectAccountGateway`,
   `WebhookSignatureVerifier`, `PaymentAuthorizationGateway`, already
   lives Payments-internal, bound in `PaymentsServiceProvider`; this ADR
   now mirrors that, rather than the other way around). SetupIntent/
   Stripe.js/Elements ensures raw card data never reaches Laravel. The
   frontend receives no raw Stripe identifier beyond the client secret
   Stripe.js strictly requires. No Stripe key, client secret, webhook
   secret, or test credential is ever committed. Real Stripe test-mode
   integration stays explicitly disabled/skipped, with a stated reason,
   until externally supplied test credentials exist.

5. **Transfers/QR HTTP exposes existing domain behavior through thin
   boundaries — it does not invent a second confirmation protocol.**
   Every endpoint enforces authenticated participant access, correct
   buyer/seller role for the requested action, existing transfer-status
   and confirmation-window rules, CSRF protection, replay protection,
   one-time/expiring QR or confirmation material, no client-controlled
   participant identifiers, and no exposure of private evidence or
   storage references.

6. **No change to the accepted Payments/Transfers state machines, and no
   new framework-listener orchestration replacing the existing direct
   application-service calls** — Phase 4/5's deliberate choice of direct
   invocation over event-listener wiring stands exactly as built,
   consistent with Decision 0's bar against revisiting previously
   accepted bounded-context decisions. Any real Stripe operation
   preserves deterministic idempotency, after-commit event publication,
   the existing authorization/capture ordering, and the existing
   external-success/local-failure recovery behavior.

7. **Phase 9 must never claim legal launch-readiness while a required
   determination is still marked "awaiting review."** The US legal-review
   document may start as a structured skeleton; the implementation never
   invents a legal conclusion. If a determination remains unavailable by
   Sprint 6, the completion report records legal sign-off as an external
   launch blocker, not a closed item.

8. **The branch/release resolution (§7) requires its own separate,
   explicit approval immediately before execution** — merging
   `feat/phase-1-authentication` into `main`, rewriting branch history, or
   changing the production release branch does not happen as a side
   effect of Sprint 6 planning alone.

## Sprint 2 approved implementation decisions

Sprint 2 implemented the minimum Auction/Bids HTTP+Reverb surface
(Refinements §1–§3 above). During implementation review, four further
decisions were required to make that surface concrete and were
explicitly approved:

1. **Public auction visibility: HTTP GET governs discoverability; Reverb
   completes the lifecycle.** A fresh `GET /auctions/{auctionId}` returns
   the public snapshot only for auctions in `Open` or `Closing` status.
   `Won`, `Expired`, and `Cancelled` auctions are not publicly
   discoverable through a fresh lookup. This is a deliberate split of
   responsibility, not an inconsistency: the HTTP endpoint governs what a
   *new* client may discover; the public Reverb channel governs what an
   *already-connected* client continues to receive. A client already
   viewing an auction when it resolves still receives the terminal status
   broadcast, so its page does not go stale — even though that same
   auction would now 404 for a fresh visitor.

2. **Broadcast event set.** The allowlisted public snapshot is
   rebroadcast, after-commit, whenever any of exactly six domain events
   fires: `BidPlaced`, `AuctionClosingStarted`,
   `AuctionClosingDeadlineExtended`, `AuctionWon`, `AuctionExpired`,
   `AuctionCancelled`. Every one of these six passes through the same
   `AuctionPublicSnapshotAssembler` allowlist Refinement §1/§3 already
   requires — none ever broadcasts bidder identity, seller identity,
   payment data, proximity data, private presence data, or a raw
   domain-event payload.

3. **HTTP idempotency semantics.** The logical idempotency identity for a
   bid-placement request is the pair (authenticated bidder id,
   Idempotency-Key) — never the key alone, since idempotency keys are
   client-generated and scoped per bidder. Required behavior, all
   implemented in `IdempotentBidPlacementService` plus the
   `bid_placement_claims` ledger:
   - Same bidder + same key + identical request (same auction, amount,
     currency): returns the original logical result (the same accepted
     bid, or the same rejection) without invoking `BidService` again and
     without publishing a second `BidPlaced` event or a second
     accepted-bid broadcast.
   - Same bidder + same key + a different auction, amount, or currency:
     rejected as idempotency-key misuse (`422`), never silently
     processed as a new request.
   - A concurrent duplicate still being resolved returns `409` — the
     response body explicitly states, via both `message` and a
     structured `retryable: true` field, that retrying this exact
     request with the *same* Idempotency-Key is safe. `409` is never a
     signal to generate a new key or abandon the request.
   - The `bid_placement_claims` table's composite primary key
     (`bidder_id`, `idempotency_key`) is deliberately kept as
     defense-in-depth beneath the application-level ledger logic, the
     same layered posture Payments' own webhook-idempotency ledger
     already uses.

4. **Rate limit.** `30` bid-placement requests per authenticated user per
   minute, keyed by the authenticated user id (never IP alone — the
   `throttle:bid-placement` middleware runs only inside the `auth`
   middleware group, so the user is always resolved first). Documented,
   like Decision 3's own targets, as a **provisional MVP constant**,
   subject to revision once Sprint 4's load testing produces real
   evidence. A request rejected by the rate limiter never reaches the
   controller, so it is never treated as a bid attempt and never writes
   an idempotency claim — true by construction of the middleware
   ordering, and covered by an explicit test.

**Process note.** The research pass that preceded this sprint's
implementation was explicitly scoped read-only ("research only — do not
write or edit any code") but produced a full implementation anyway. The
resulting code was independently re-read and re-validated in full before
any of it was accepted or committed — it was not accepted on the
strength of the subagent's own self-report. This is recorded as a
process failure in delegation scope, not as a reason to discard
otherwise-valid, independently-verified work. Future research-only
delegations must remain strictly read-only; any unexpected file
modification from one must stop further implementation immediately
pending review, rather than being treated as a completed deliverable.

**Test coverage required by these refinements**, beyond each sprint's own
functional tests:

- Bid placement: unauthenticated rejection; seller self-bidding rejected;
  suspended accounts rejected; duplicate HTTP retries idempotent (return
  the original result, create no second bid, publish no second
  broadcast); reusing a key for a genuinely different request rejected
  as client error; a concurrent still-in-progress duplicate returns a
  `409` explicitly marked retryable with the same key; concurrent
  submissions preserve existing deterministic ordering; rejected bids
  never broadcast an accepted-bid update; rate-limited requests are
  never treated as bid attempts and never write an idempotency claim.
- Transfers/QR: IDOR attempts; QR replay; expired QR/transfer windows;
  wrong participant; already-confirmed actions; duplicate request
  delivery; suspended users completing an existing obligation where
  ADR-026 already permits it.

**Approved sprint sequence** (unchanged from the architecture-design
turn): (1) CI/security-tooling foundations, structured logging, `/up`,
the Horizon Administrator capability, the legal-review skeleton; (2) the
minimum Auction/Bids HTTP+Reverb surface; (3) the minimum
`BuyerPaymentMethod`+Transfers surface; (4) load testing and empirical
Horizon/capacity tuning; (5) the structured security review; (6)
operations/legal documentation finalization, the separately approved
branch/release resolution, full validation, completion report, release
tag. Each sprint requires explicit review and approval before the next
begins.

## Sprint 3 approved implementation decisions

Sprint 3 implemented the minimum `BuyerPaymentMethod`+Transfers surface
(Refinements §4–§5 above). Four implementation decisions were required
and explicitly approved:

1. **Stripe adapter placement corrected to match actual precedent** — see
   Refinement §4's own updated text above. `StripeBuyerPaymentMethodGateway`
   lives in `packages/Payments/src/Infrastructure/Stripe/`, bound in
   `PaymentsServiceProvider`, exactly like every other Stripe adapter in
   this codebase.

2. **Real `AuctionWon` → `AuctionWinAuthorizationService` and
   `PaymentAuthorized` → `TransferInitiationService` wiring**, both
   previously unwired since Phase 4/5. `TriggerAuctionWinAuthorization`
   resolves the winning buyer's saved `BuyerPaymentMethod` itself; a
   missing payment method fails loudly via `MissingBuyerPaymentMethod`
   (an uncaught exception on this queued listener, landing in Laravel's
   own `failed_jobs` — the explicit, documented failure path ADR-025 §11
   already established for bounded, unproactive failure handling).
   `TriggerTransferInitiation` additionally delivers the one-time
   plaintext QR/confirmation token `TransferInitiationService` returns
   (never persisted by Transfers itself, ADR-017 §2) to the buyer via a
   short-lived cache entry, TTL-bound by the transfer's own expiry —
   the delivery-layer mechanism `TransferIssuance`'s own docblock
   explicitly flagged as not yet existing. Both listeners are additive
   composition-root wiring for a previously entirely-unwired capability,
   not a replacement of Phase 4/5's own accepted direct-invocation
   orchestration (Refinement §6) — no existing wiring is touched, since
   none existed for either reaction.

3. **The `Transfer` participant-authorization gap is fixed at the
   application-service layer, not the aggregate.** `TransferConfirmationService::confirmBySeller()`/
   `confirmByBuyer()` now require and verify `requestingUserId` against
   the transfer's own `sellerId`/`buyerId` before anything else runs —
   including before the expiry evaluator — throwing the new
   `TransferAccessDenied` otherwise. `Transfer` the aggregate itself is
   unchanged; this mirrors `PresenceSessionService`'s own
   `assertOwnedBy()` posture (ownership enforced in the application
   layer, never the aggregate or the HTTP layer). `Issued -> Confirmed/
   Expired/Cancelled` behavior is unaffected beyond this guard.

4. **`@stripe/stripe-js` added as the one new frontend dependency**,
   smallest compatible caret range, `npm audit` clean. No
   `@stripe/react-stripe-js` or any other Stripe frontend package —
   `SetupPaymentMethod.jsx` uses `loadStripe()`/`elements.create('card')`/
   `confirmCardSetup()` directly. Raw card data is entered into a
   Stripe-controlled DOM node and never reaches Laravel; only the
   resulting `setup_intent_id` is ever posted to the backend.

**Design decisions made during implementation — approved:**

- **QR/confirmation-code delivery is cache-based, not a rendered QR
  barcode — a presentation limitation only, not a protocol decision.**
  The buyer retrieves the plaintext code as text via an authenticated,
  buyer-only endpoint (`GET /transfers/{id}/qr-token`), backed by a
  short-lived cache entry keyed by transfer id, TTL bound to the
  transfer's own `expiresAt`. No QR-image-rendering library was
  introduced (only `@stripe/stripe-js` was approved as a new
  dependency); the code may later be rendered as a visual QR barcode
  purely as a presentation change, without touching the domain
  protocol — the underlying value is, and remains, the same plaintext
  string `TransferConfirmationService::confirmBySeller()` already
  verifies via `hash_equals()` against the stored hash. This does not
  invent a second confirmation protocol; it only delivers the secret the
  *existing* hash-based protocol already expects the seller to submit.
  Confirmed satisfied:
  - Buyer-only authenticated retrieval (`ShowTransferQrTokenController`
    checks `requestingUserId === $transfer->buyerId`, 403 otherwise).
  - TTL bounded by the transfer's own expiry — the cache entry is
    written with exactly `$transfer->expiresAt` as its expiration.
  - No token exposure in logs (no `Log::` call anywhere in this
    delivery path touches it), audit payloads (`TransferIssued::payload()`/
    `auditPayload()` carry only `transfer_id`/`auction_id`/
    `winning_bid_id`/`seller_id`/`buyer_id` — never the QR token, at rest
    or hashed), page source (the token is fetched via a separate
    authenticated XHR after an explicit buyer click, never embedded in
    an initial Inertia page prop that would appear in the server-rendered
    HTML), or any unrelated response (`ShowTransferController`'s own
    `toResponse()` never includes it; only the dedicated endpoint does).
  - Confirmation remains one-time through the existing `Transfer`
    invariants unchanged by this sprint: a second seller confirmation
    attempt is still rejected as `IllegalStateTransition` regardless of
    how many times the code was retrieved or resubmitted.
  - The cache used in production (`CACHE_STORE=redis`, `.env.example`)
    is already shared and multi-process-safe by the existing platform
    default — no code change was needed to satisfy this; the array
    driver is used only in the test environment (`phpunit.xml`,
    per-process, correctly isolated per test).
- **Evidence-photo HTTP endpoints were not built.** Sprint 3's explicit
  scope named "minimal transfer HTTP/UI" and "QR confirmation flow," not
  evidence upload; `TransferEvidenceSubmissionService` (Phase 6) remains
  domain/backend-only, consistent with "necessity, not completeness"
  (Decision 0) and explicitly reaffirmed on Sprint 3 approval.
- **`GET /transfers/{id}` is participant-only, not public** — unlike the
  public Auction read endpoint (Refinement §1), a transfer inherently
  concerns exactly two named parties and their confirmation obligation,
  not general discovery.

**Real Stripe test-mode status — explicit, not to be conflated with
"validated."** `packages/Payments/tests/Infrastructure/StripeBuyerPaymentMethodGatewayTest.php`
exists and is written, but **has not executed against real Stripe test
credentials in this environment** — all five of its cases
`markTestSkipped()` because `STRIPE_SECRET` is not configured. That skip
condition is exact: it triggers only when the configured secret does not
start with `sk_test_`, never unconditionally. **Phase 9 must not be
described as having validated the real Stripe money-movement path until
externally supplied real Stripe test-mode credentials are configured and
these five tests actually run and pass** — this mirrors Decision 2's own
"external input, never fabricated" discipline for legal conclusions,
applied here to Stripe credentials specifically, and is a launch
blocker to be resolved no later than Sprint 6 (§7's "resolved during
Phase 9" list already commits to this).

## Sprint 4 note (recorded during Sprint 3 implementation)

Any test that fires a real `AuctionWon` event now exercises
`TriggerAuctionWinAuthorization`'s full dependency graph, including a
real `StripeClient` construction — `apps/web/phpunit.xml` now configures
a non-empty, deliberately non-`sk_test_`-shaped placeholder `STRIPE_KEY`/
`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET` so tests unrelated to Payments do
not fail merely resolving the listener. **These placeholders are not, and
must never be mistaken for, real Stripe credentials**: none of the three
values (`STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET`, all
literally `phpunit_placeholder_..._not_real`) is a valid Stripe key of
any kind, none starts with `sk_test_`/`pk_test_`/`whsec_` — the real
prefixes Stripe itself issues — all three are confined to `phpunit.xml`
(never `.env` or any file Laravel would load outside the PHPUnit test
runner), and none is ever read as satisfying the real-Stripe-credentials
skip check in `StripeBuyerPaymentMethodGatewayTest.php`, which explicitly
requires the `sk_test_` prefix Stripe issues to real test-mode secret
keys. Real Stripe
test-mode integration tests detect the placeholder's shape and skip
themselves explicitly rather than attempting a network call against it.

## References

- `docs/roadmap.md` — Phase 9's own one-sentence scope description, the
  only authoritative source for what this phase covers.
- `docs/product/claude-mvp-analysis.md` §3.2/§3.3 (named security/legal
  risk categories Decision 4 reviews against), §10.2 items 1, 3, and 14
  (launch market, legal sign-off ownership, scale targets — resolved by
  Decisions 1, 2, and 3 respectively).
- `docs/legal/jurisdiction-requirements.md` — the ten-item pre-launch
  legal checklist Decision 2 reuses without modification.
- ADR-003 (line-standing legal framing, reconfirmed per-market by
  Decision 2's legal review).
- ADR-019 §7 (the existing defensive Stripe-webhook-cancellation
  reconciliation, referenced in Decision 7's chargeback disposition).
- ADR-023 (the precedent for explicitly declining to assign a deferred
  item to a numbered phase, extended by Decision 7).
- ADR-025 §11 (the "accepted MVP limitation" posture for alerting,
  extended by Decision 5 to observability generally).
- ADR-026 (the capability-based Gate pattern Decision 5 extends by one
  case; the only other ADR to explicitly reference Phase 9).
- `docs/releases/phase-3-completion-report.md` (the provisional-
  timing-constant precedent Decision 3 and Decision 6 both follow).
- `docs/releases/phase-7-completion-report.md` (the "accepted MVP
  limitation" precedent for declining proactive alerting, and the ICU
  locale-data gap Decision 7 re-defers).
- Phase 9 architecture review (this conversation).
