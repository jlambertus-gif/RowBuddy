# Phase 9 Sprint 5 — Security Checklist

ADR-027 Decision 4 deliverable. For every reviewed item: reviewed;
mitigation present; residual risk (if any); required action (if any);
verification evidence. See `docs/security/security-review.md` for full
narrative findings and exploit-scenario reasoning.

| # | Area | Reviewed | Mitigation present | Residual risk | Required action | Verification evidence |
|---|---|---|---|---|---|---|
| 1 | Evidence-photo private storage | Yes | Evidence photos stored on a disk (`storage/app/private`) never reachable via the public symlink | None found | None | `apps/web/config/filesystems.php`; `app/Infrastructure/LocalPrivateEvidenceStorage.php` |
| 2 | Evidence-photo access authorization | Yes | Session ownership + independent photo↔session binding check in `PresenceSessionService::evidencePhotoUrl()` | None found | None | `packages/QueuePresence/src/Application/PresenceSessionService.php` (`assertOwnedBy`, `evidencePhotoUrl`) |
| 3 | Evidence-photo URL delivery | Yes | Signed, temporary URL, 300s TTL | None found | None | `app/Infrastructure/LocalPrivateEvidenceStorage.php` (`temporaryUrl()`) |
| 4 | Evidence-photo file-type/size validation | Yes | `image`, `mimes:jpeg`, `max:8192` — content-inspected, not extension-trusted | None found | None | `apps/web/app/Http/Requests/UploadEvidencePhotoRequest.php` |
| 5 | Evidence-photo metadata (EXIF/GPS) stripping | Yes | GD decode/re-encode strips source metadata segments unconditionally before storage | None found | None | `packages/QueuePresence/src/Infrastructure/Images/GdImageMetadataStripper.php` |
| 6 | Stripe webhook signature verification | Yes | `Stripe\Webhook::constructEvent()` verified before any payload use; 400 on failure | None found | None | `StripeWebhookController.php`; `StripeWebhookSignatureVerifier.php` |
| 7 | Stripe webhook CSRF exemption scope | Yes | Exact-string route match, not prefix/wildcard | None found | None | `apps/web/bootstrap/app.php` |
| 8 | Stripe webhook secret handling | Yes | Read only from env; blank in `.env.example`; test placeholder is non-`whsec_`-shaped | None found | None | `config/services.php`; `.env.example`; `phpunit.xml` |
| 9 | Stripe webhook replay protection | Yes | DB primary key on `stripe_event_id` | None found | None | `EloquentWebhookEventRepository.php`; `create_webhook_events_table` migration |
| 10 | QR transfer-token generation/storage | Yes | 256-bit random, SHA-256 hash only ever persisted; plaintext never stored by domain package | None found | None | `TransferInitiationService.php`; `Transfer.php` |
| 11 | QR transfer-token comparison | Yes | `hash_equals()` — constant-time | None found | None | `TransferConfirmationService::assertQrTokenMatches()` |
| 12 | QR transfer-token single-use / replay | Yes | Aggregate state guard rejects a second confirmation; identity check precedes token check under row lock | None found | None | `TransferConfirmationService::confirmWithinTransaction()`; `Transfer::confirmBySeller/confirmByBuyer()` |
| 13 | QR transfer-token plaintext exposure surface | Yes | Only the buyer can retrieve it; TTL-bound cache read | Docblock overstated "one-time" guarantee | Fixed: docblock corrected to describe actual re-readable-until-expiry behavior | `ShowTransferQrTokenController.php` (this sprint) |
| 14 | Transfer confirmation rate limiting | Yes | Previously none | Not brute-force-exploitable given #10/#11, but inconsistent with bid-placement's existing convention | Fixed: added `throttle:transfer-confirmation` (20/min/user) | `apps/web/routes/web.php`; `AppServiceProvider.php` (this sprint) |
| 15 | Bid-placement row locking | Yes | `lockForUpdate()` on Auction shared by proximity check and highest-bid check, one transaction | None found | None | `EloquentAuctionRepository::findByIdForUpdate()`; `EloquentAuctionGateway::lockAndCheckForBidding()`; `BidService::place()` |
| 16 | Bid idempotency-key race safety | Yes | DB unique-constraint violation, not check-then-insert | None found | None | `EloquentBidPlacementLedger.php` |
| 17 | Transfer confirmation concurrency | Yes | Row lock acquired before ownership/status/geofence checks, one transaction | None found | None | `TransferConfirmationService::confirmWithinTransaction()` |
| 18 | Payment capture concurrency + external-call safety | Yes | Row lock; validate→call-Stripe→mutate→persist ordering; deterministic idempotency keys | `capture()`/`cancel()` previously had no idempotency key (low risk — Stripe rejects a second state transition itself) | Fixed: added deterministic keys matching `authorize()`/`refund()`'s existing pattern | `StripePaymentAuthorizationGateway.php` (this sprint) |
| 19 | Dispute resolution concurrency | Yes | Aggregate status guard under row lock prevents double-refund | None found | None | `Dispute::resolve()` |
| 20 | Transfers IDOR (view/confirm) | Yes | Acting user id derived only from session; rejected identically for a stranger or the wrong-role party | None found | None | `ShowTransferController.php`; `TransferConfirmationService`'s ownership check |
| 21 | Evidence-photo IDOR | Yes | See #2 | None found | None | Same as #2 |
| 22 | Buyer payment-method IDOR | Yes | Stripe Customer metadata cross-checked against authenticated buyer id, not client-trusted | None found | None | `StripeBuyerPaymentMethodGateway::retrieveConfirmedPaymentMethod()` |
| 23 | Admin capability authorization | Yes | One `Gate::define()` per capability, server-side role lookup, fail-closed | None found | None | `AdministrationServiceProvider.php` |
| 24 | Bid amount/currency input validation | Yes | Server-side integer/size rules; currency-equality check against auction's own currency | None found | None | `PlaceBidRequest.php`; `BidService::attemptPlacement()` |
| 25 | GPS coordinate input validation | Yes | Real `between:` range rules on latitude/longitude/accuracy | None found | None | `RecordGpsPingRequest.php`; `ConfirmTransferAsSellerRequest.php`/`AsBuyerRequest.php` |
| 26 | Queue submission radius validation | Yes | Lower bound enforced; no upper bound | Unbounded radius possible, but user-submitted queues require admin approval before any auction (ADR-005) — compensating control | Recorded for product decision on a sane max; not a security fix | `SubmitQueueRequest.php` |
| 27 | Dispute-correction mandatory reason | Yes | Enforced twice — loosely at FormRequest, strictly (rejects whitespace-only) in the domain service | None found | None | `RecordDisputeCorrectionController.php`; `DisputeCorrectionService::assertReasonNotBlank()` |
| 28 | No raw card data in application layer | Yes | Only a Stripe-generated `setup_intent_id` ever accepted; no card-shaped fields anywhere | None found | None | `BeginBuyerPaymentMethodSetupController.php`; `CompleteBuyerPaymentMethodSetupRequest.php`; whole-app grep |
| 29 | Notification delivery idempotency | Yes | DB unique constraint on `(domain_event_id, recipient_id, notification_type)` | None found | None | `EloquentNotificationDeliveryLedger.php`; `create_notification_deliveries_table` migration |
| 30 | Secrets in tracked source | Yes | Whole-repo pattern scan (Stripe/AWS/PEM/Google key shapes) | None found | None | `git grep` across all tracked non-lock files, zero matches |
| 31 | `.env` exclusion from version control | Yes | Git-ignored | None found | None | `git check-ignore -v apps/web/.env` |
| 32 | Composer dependency vulnerabilities | Yes | `composer audit` in CI for every job; clean at time of review | None found | None | `.github/workflows/ci.yml`; live `composer audit` run, apps/web + 11 packages |
| 33 | npm/frontend dependency vulnerabilities | Yes | `npm audit fix` applied | Was 1 moderate (`postcss` sourceMappingURL, GHSA-fxqj-rqcc-2cmp) | Fixed: patched to 8.5.25; `npm run build` re-verified clean | Live `npm audit` run before/after, this sprint |
| 34 | Automated dependency-update tracking | Yes | Dependabot configured for every package + npm + GitHub Actions | None found | None | `.github/dependabot.yml` |
| 35 | GitHub Secret Scanning | Reviewed | **Status: awaiting owner verification.** Unknown — repository setting, `gh` CLI unavailable in this environment, so it cannot be confirmed or enabled from here | Unknown until confirmed | **Required action**: enable or confirm GitHub Secret Scanning in repository Settings → Code security and analysis | N/A — **launch status: external security-checklist item, not silently assumed complete** |
| 36 | Email verification | Yes | **Implemented 2026-08-04, per your explicit decision (required before launch)**: `Features::emailVerification()` enabled; `User implements MustVerifyEmail`; `Registered -> SendEmailVerificationNotification` explicitly wired (this app has no `EventServiceProvider`); `verified` middleware added to the 5 new-transactional-activity routes (submit queue, place bid, start presence session, both buyer-payment-method routes); transfer confirmation and presence-session continuation deliberately left ungated (existing obligations); sign-in, verification-notice, resend, password recovery/change, and dashboard all confirmed still reachable while unverified | None found post-implementation | None | 9 new tests, 47 assertions, `apps/web/tests/Feature/EmailVerificationTest.php`; full apps/web suite 220/220; PHPStan clean; Pint clean for all touched files |
| 37 | 2FA / passkeys mandate | Reviewed | Available, not required for any account type including admin | Standard MVP posture, not a defect | None (recorded for completeness) | `apps/web/config/fortify.php` |

## Fixes applied this sprint (all small, evidence-backed, zero-regression)

1. `ShowTransferQrTokenController.php` — docblock corrected (#13).
2. `AppServiceProvider.php` + `routes/web.php` — `transfer-confirmation`
   rate limiter added (#14).
3. `StripePaymentAuthorizationGateway.php` — idempotency keys added to
   `capture()`/`cancel()` (#18).
4. `apps/web/package-lock.json` — `postcss` patched via `npm audit fix`
   (#33).
5. Email verification implemented end-to-end per your explicit decision
   (#36): `User` model, `fortify.php`, `FortifyServiceProvider.php`,
   `AppServiceProvider.php`, `routes/web.php`, a new `VerifyEmail.jsx`
   page with real `auth` en/es translations, and an incidental
   simplification of `UpdateUserProfileInformation.php` (a
   now-always-true `instanceof` check PHPStan flagged as dead code once
   `User` unconditionally implements `MustVerifyEmail`).

All five verified via: full `apps/web` Pest suite (220 passed, up from
211 — the 9 new email-verification tests), the full `Transfers`-filtered
subset, the full `Payments` package suite (105 passed, 5 skipped),
`PHPStan` (both `apps/web` and `Payments`, zero errors), `Pint` (zero new
style issues on any file this sprint created or edited — three
pre-existing Fortify-scaffold files this sprint merely touched retain
their pre-existing, unrelated missing-`declare(strict_types=1)` issue,
left alone per the same precedent set in Sprint 4), `npm run build`
(clean), and `npm audit`/`composer audit` (both clean).

## Items requiring your decision or action (not resolved this sprint)

- #35 GitHub Secret Scanning — confirm/enable directly in GitHub.
- #36 Email verification — confirm intentional deferral or schedule the
  fix (adds a `verified` middleware requirement and a migration story
  for existing accounts; out of "small fix" scope for this sprint).
