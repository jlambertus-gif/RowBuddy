# Phase 4 (Payments) — Completion Report

Tag: `v0.5.0-payments`
Status: **Complete and formally accepted**, 2026-10-22 — domain/backend
scope only, per ADR-015. See §8 for the closure-scope rationale.

## 1. Executive summary

Phase 4 delivers the Payments bounded context end-to-end at the
domain/backend level: a seller can onboard through Stripe Connect
Express; a won auction's buyer-side total (winning bid plus platform fee)
is authorized via a real Stripe manual-capture PaymentIntent, assuming a
payment method already exists for the buyer; Stripe webhook deliveries
are verified and recorded exactly once via a real, local HMAC-SHA256
signature check and an idempotency ledger; and a seller's payout can be
fully *prepared* — readiness validated, expected settlement computed —
without ever executing a transfer. This satisfies the Phase 4 exit
criteria defined in `docs/roadmap.md`.

The phase was delivered as 6 sprints, each scoped, implemented, tested,
and reviewed independently. Six product decisions (launch market, Stripe
account type, fee percentage, KYC gate, currency policy, transaction
value limit) were resolved before Sprint 1 planning began. Three new
ADRs (014–016) resolved every architectural judgment call — the
Payments–Auctions lifecycle boundary, the Phase 4/Phase 5 scope boundary,
and Sprint 4's payment-method/charge-model assumptions — before the
sprint that needed each one. 421 automated tests pass across all seven
affected packages/apps, with clean PHPStan/Larastan and Pint throughout.

**This phase closes without capture, payout execution, or
re-authorization-before-expiry** — all three deliberately deferred to
Phase 5 (Transfers), which does not exist yet. See §8 for the full
rationale, which extends the same reasoning Phase 3's closure already
established for deferring delivery-layer work.

## 2. Product decisions (resolved before Sprint 1)

- **Launch market**: United States only for the MVP — single
  jurisdiction for legal/regulatory review, Stripe Connect platform, and
  currency. International expansion is explicitly out of scope.
- **Stripe Connect account type**: Express — Stripe-hosted onboarding and
  identity verification, minimizing RowBuddy's own KYC-building burden.
  Standard and Custom remain unevaluated, deferred to future phases if
  business requirements change.
- **Platform fee**: 10% of the winning bid, buyer-paid on top (per
  ADR-006), configurable via `PAYMENTS_FEE_PERCENTAGE`, defaulting to 10%.
- **KYC gate for payout eligibility**: Stripe Express's own
  `charges_enabled`/`payouts_enabled` flags — no additional RowBuddy-side
  KYC or manual review in the MVP.
- **Currency policy**: USD only, tied to the US launch market. No
  multi-currency support, no FX policy needed.
- **Transaction value limit**: USD 500 maximum per transaction — a
  temporary fraud/AML control, not a permanent invariant, configurable via
  `PAYMENTS_TRANSACTION_VALUE_LIMIT_USD`.

## 3. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `4db353d` | `packages/Payments` scaffold — the `PaymentIntent` aggregate (`Authorized`/`Failed` only, per what became ADR-015), `FeeCalculator`, swappable `PlatformFeePolicy`/`TransactionValueLimitPolicy` (the latter exposing a `Money` value, not a numeric amount plus currency) with MVP-fixed defaults bound via `apps/web` config. No persistence, no Stripe, no HTTP. |
| 2 | `80ce6bd` | Persistence layer — `payment_intents` migration, `PaymentIntentModel`, the append-only `PaymentIntentRepository` (`record()`/`findById()`/`findByAuctionId()`, no update path), and its Eloquent adapter. |
| 3 | `aecfc02` | ADR-014 and ADR-015 accepted. Stripe Connect Express seller onboarding: `SellerPayoutAccount` (records only the linked Stripe account identity — no cached eligibility flags), `ConnectAccountGateway` with a real Stripe SDK adapter, `SellerOnboardingService`. Eligibility always read live from Stripe. No HTTP caller yet. |
| 4 | `d1cc020` | ADR-016 accepted. `AuctionWinAuthorizationService`, the real `AuctionWon` consumer: computes the buyer's total (bid + fee), validates it against the transaction value limit *before* calling Stripe, authorizes via `PaymentAuthorizationGateway` (a real manual-capture Stripe PaymentIntent, idempotency key derived from `auctionId`) using the separate-charges-and-transfers model — no dependency on seller Connect status. Assumes an already-obtained buyer Stripe PaymentMethod id. Idempotent against a second call for an already-processed auction. |
| 5 | `f839db9` | `ProcessedWebhookEvent` (the idempotency ledger, keyed by Stripe event id via a unique-constraint-driven exception), `WebhookSignatureVerifier` with a real Stripe adapter (pure local HMAC-SHA256, no network call), `StripeWebhookProcessor` (records each verified event once, no-ops on replay, deliberately does not react to specific event types with business logic). Adds `apps/web`'s first Phase 4 HTTP endpoint, `POST /webhooks/stripe`, CSRF-exempt. |
| 6 | `12d345a` | Completes ADR-015 §1's "payout preparation": `PaymentProcessingCostPolicy`/`SellerSettlementCalculator` (an MVP estimate of Stripe's real processing cost — the platform fee never enters this calculation) and `PayoutPreparationService`, combining authorization status, account linkage, and live eligibility into a read-only `PayoutPreparation` snapshot. No payout executed anywhere. |

## 4. ADRs created

- **ADR-014 — Payments–Auctions Boundary: Independent Lifecycle, No `AuctionStatus` Extension**: `AuctionStatus` remains exactly `{Open, Closing, Won, Expired, Cancelled}`; Payments tracks its own lifecycle keyed by `auctionId`/`winningBidId`, reading only `winningBidId`/`winningAmount` from `AuctionWon`. Extends the same "consumer owns the port, upstream stays ignorant" pattern as `AuctionGateway` and `SellerPresenceVerification`/`WinningBidLookup`.
- **ADR-015 — Phase 4 Scope Boundary: Authorization, Webhooks, and Payout Preparation Only; Capture Deferred to Transfers**: Phase 4 stops before the `authorized → captured` transition. No placeholder "transfer confirmed" trigger is introduced. `Captured`/`Held`/`ReleasedToSeller`/`RefundedToBuyer` exist only in prose until Phase 5 defines the Transfers contract. Explicitly distinguishes this from the `LiveProximityChecker` precedent (a known, same-phase caller) since capture depends on a bounded context and contract that don't exist yet.
- **ADR-016 — Sprint 4 Authorization Scope: Assumed Payment Method and the Separate Charges/Transfers Model**: Sprint 4 assumes a valid Stripe PaymentMethod id already exists for the buyer, taken as an explicit external input — buyer payment-method acquisition (Stripe Elements, SetupIntent, saved cards) is a separate, deferred product capability, not designed here. Authorization uses the separate-charges-and-transfers model (ADR-006's reserved alternative to a destination charge), so it never depends on seller Connect onboarding status.

ADRs 001–013 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- One new package, `packages/Payments`, following the exact package shape established in Phases 1–3.
- Payments owns its own `DomainEventPublisher` copy (mirroring Auctions/Bids/QueuePresence) and consumes `AuctionWon` directly rather than through any new cross-module port — the first module in this codebase to react to another module's domain event rather than a synchronous gateway call.
- Three new Stripe-facing ports, each with exactly one real adapter allowed to know the Stripe SDK's shape: `ConnectAccountGateway`, `PaymentAuthorizationGateway`, `WebhookSignatureVerifier` — all in `packages/Payments/src/Infrastructure/Stripe/`.
- The idempotency-ledger pattern (`ProcessedWebhookEvent`, unique-constraint-driven exception, no read-then-write race) is a new, reusable shape for any future inbound-webhook concern this codebase adds.
- `apps/web` gained its first Phase 4 HTTP surface: `POST /webhooks/stripe`, CSRF-exempt, with no translation keys (a machine-to-machine endpoint, never rendered to a user).

## 6. Database changes

`packages/Payments/database/migrations/`:
- `create_payment_intents_table` (Sprint 2)
- `create_seller_payout_accounts_table` (Sprint 3) — one Stripe account per seller, primary-keyed on `seller_id`
- `create_webhook_events_table` (Sprint 5) — the idempotency ledger, primary-keyed on `stripe_event_id`

No changes to any Phase 1–3 package's schema.

## 7. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 | Unaffected by Phase 4. |
| `packages/Queues` | 81 | Unaffected by Phase 4. |
| `packages/QueuePresence` | 81 | Unaffected by Phase 4. |
| `packages/Auctions` | 66 | Unaffected by Phase 4 — `AuctionStatus` gained no new states, per ADR-014. |
| `packages/Bids` | 24 | Unaffected by Phase 4. |
| `packages/Payments` | 55 | Aggregate, application services, persistence, Stripe adapters, webhook signature verification (real HMAC-SHA256, no network), all six sprints. |
| `apps/web` | 83 (was 80 at Phase 3 close) | Includes 3 new tests for the real Stripe webhook HTTP endpoint. |

**Total: 421 automated tests.** PHPStan/Larastan clean across every package at every sprint. Pint clean on `packages/Payments`; `apps/web` carries the same 12 pre-existing style issues from Phase 1, confirmed unrelated and unchanged.

**Pint's cross-package-import hazard recurred twice more this phase** (previously seen in Phases 3: `Auction.php`→QueuePresence, `Bid.php`→Auctions) — both times the injected `use` was same-package (`Payments`→`Payments`, via `{@see}` docblock references to sibling classes), so no actual boundary violation occurred; both were cosmetically cleaned up and confirmed via a full-tree `grep` for `RowBuddy\{Auctions,Bids,Queues,QueuePresence}` returning zero matches in `packages/Payments`.

**No real Stripe test-mode integration test exists** — this environment has no real Stripe test API keys configured (`.env`'s `STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET` are empty). `StripeConnectAccountGateway` and `StripePaymentAuthorizationGateway` are thin, direct SDK wrappers with no branching logic, tested only indirectly through the application services they back (via fakes); `StripeWebhookSignatureVerifier` is fully tested since signature verification is pure local cryptography with no network dependency. This gap was explicitly acknowledged at Sprint 3's review and accepted as appropriate given the environment.

## 8. Closure-scope decision

Phase 4 closes without capture, transfer confirmation, payout execution,
or re-authorization-before-expiry — a narrower bar than the roadmap's
original Phase 4 description ("captured on transfer confirmation, and
paid out") assumed, decided deliberately and documented as it was
reached, not discovered at closure:

- **ADR-015** (Sprint 3 planning) drew the line before capture: Phase 4
  delivers authorization, webhook handling, and payout preparation only.
  No placeholder "transfer confirmed" trigger was introduced to simulate
  the missing Phase 5 contract.
- **Sprint 6** extended the same reasoning to re-authorization-before-
  expiry: since it depends on knowing when a handoff/transfer is
  expected — a Transfers concept that doesn't exist yet — building it now
  would risk the same "trigger for a bounded context that doesn't exist"
  problem ADR-015 already rejected for capture.

This mirrors Phase 3's own closure, which accepted a domain/backend-only
bar in exchange for not building throwaway or speculative delivery-layer
work ahead of design. Phase 4 adds one specific exception: Sprint 5
introduced a real HTTP endpoint (`POST /webhooks/stripe`) because
receiving Stripe webhooks genuinely requires one — the one case Sprint
3's review anticipated as legitimate ("introduce the HTTP surface only
when a later sprint actually requires an externally callable endpoint").

## 9. Known limitations and deferred product decisions

### Accepted limitations (by design, not oversights)

- **No capture, transfer, or payout execution exists anywhere in this
  codebase.** An authorized `PaymentIntent` sits `Authorized` forever
  from this phase's own code's perspective — nothing captures it, and
  Stripe's own authorization hold will eventually expire
  (~5–7 days, ADR-004) with no re-authorization attempted.
- **No reaction to specific Stripe webhook event types.** The idempotency
  ledger records that an event was received; nothing reconciles a
  `PaymentIntent` against `payment_intent.payment_failed` or similar.
- **Buyer payment-method acquisition does not exist.** No SetupIntent,
  Stripe Elements/PaymentElement flow, or saved-card model — `Auction
  WinAuthorizationService` assumes a `stripePaymentMethodId` arrives from
  a capability that has not been built (ADR-016).
- **The processing-cost estimate (3% + $0.30) is an MVP approximation**,
  not Stripe's real, final per-transaction fee — only known once a real
  payout executes.
- **No real Stripe test-mode credentials exist in this environment** —
  the two thin Stripe SDK wrapper adapters are untested against the real
  API (§7).

### Deferred product decisions

- Where and when a buyer attaches a payment method (likely touching
  `packages/Bids`, e.g. requiring a card on file before a bid is placed).
- Whether/when to cap total soft-close-driven re-authorizations, or
  introduce a real re-authorization flow at all, once Transfers exists.
- The real payment-processing cost model, once actual Stripe settlement
  data exists to calibrate against.
- Protection-period duration after transfer confirmation before payout
  release, and whether a post-payout dispute can claw back funds
  (mvp-analysis §10.2 item 4) — unresolved, blocks Phase 5/6 design.
- Per-jurisdiction legal sign-off remains a gate on enabling any market
  beyond the US-only MVP scope.

## 10. Phase 5 readiness assessment

Phase 4 provides what Phase 5 (Transfers) needs to build on:

- An `Authorized` `PaymentIntent` per won auction, keyed by `auctionId`/
  `winningBidId`, ready for Transfers to define the real
  transfer-confirmation contract and become the caller that triggers
  capture — the exact extension point ADR-015 named and deliberately
  left undesigned.
- A `PayoutPreparationService` already answering "is this seller ready,
  and what would they receive" — Phase 5 (or a payout-execution phase
  after it) can call this directly rather than re-deriving readiness
  logic.
- A proven idempotent-webhook pattern (`ProcessedWebhookEvent`) Transfers
  can reuse for its own Stripe events (e.g. `transfer.created`,
  `payout.paid`) without redesigning idempotency from scratch.
- A fourth proven package-per-bounded-context implementation
  (`packages/Payments`), confirming the pattern continues to generalize,
  including a new precedent (a module reacting to another module's
  domain event directly, rather than through a synchronous gateway).

**No blockers identified for Phase 5 at the domain level.** Buyer
payment-method acquisition remains an open, unscheduled product
capability that Phase 5 (or a dedicated delivery-layer phase for
Auctions/Bids/Payments together) will need to address before any of this
can work end-to-end for a real user. Per explicit instruction, Phase 5
implementation will not begin until separately authorized.
