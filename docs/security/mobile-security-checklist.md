# Mobile Sprint 6 — IDOR/Rate-Limit Review Pass

Mirrors the structure and methodology of `docs/security/security-checklist.md`
(Phase 9 Sprint 5), scoped to every `/api/v1` mobile endpoint added across
ADR-028 Sprints 1–6. For every reviewed item: reviewed; mitigation
present; residual risk (if any); required action (if any); verification
evidence.

| # | Endpoint | Reviewed | IDOR mitigation | Rate limiting | Residual risk | Required action | Verification evidence |
|---|---|---|---|---|---|---|---|
| 1 | `POST auth/register` | Yes | N/A — no id-accepting parameter | None (mirrors Fortify's own `register` route, which also has none) | Registration abuse (account farming) possible, pre-existing, app-wide | None — out of mobile scope | `RegisterApiUserTest.php`; `vendor/laravel/fortify/routes/routes.php` |
| 2 | `POST auth/login` | Yes | N/A | `throttle:login` (Fortify's own named limiter, reused) | None found | None | `LoginApiUserTest.php` |
| 3 | `POST auth/forgot-password` | Yes | N/A | None (mirrors Fortify's own `password.email` route, which also has none) | Email-enumeration/reset-spam possible, pre-existing, app-wide | None — out of mobile scope | `ForgotPasswordApiTest.php`; `vendor/laravel/fortify/routes/routes.php` |
| 4 | `POST auth/reset-password` | Yes | N/A | None (mirrors Fortify's own `password.update` route) | Same as #3 | None — out of mobile scope | `ResetPasswordApiTest.php` |
| 5 | `POST auth/logout` | Yes | Revokes only the token that authenticated the request itself, never a client-supplied token id; the optional `expo_push_token` removal is scoped to the authenticated user's own id (`DeviceTokenRepository::deleteByToken($userId, $token)`) | None (self-scoped, destructive-but-idempotent, low abuse value) | None found | None | `LogoutApiUserTest.php` (includes the Sprint 4 IDOR fix for device-token removal) |
| 6 | `POST auth/email/verification-notification` | Yes | Acting user derived from token only | Fortify's own `verification` named limiter (6/min) | None found | None | `EmailVerificationNotificationApiTest.php` |
| 7 | `GET me` | Yes | Self-only by construction | None (read-only) | None found | None | `ShowApiCurrentUserTest.php` |
| 8 | `POST auctions/{id}/bids` | Yes | Bidder id derived from token; domain rejects seller bidding on own auction | `throttle:bid-placement` (30/min/user) | None found | None | `PlaceBidApiTest.php` |
| 9 | `POST buyer-payment-methods/setup-intent` | Yes | Buyer id derived from token | None (mirrors web's own route, which also has none) | A malicious authenticated user could create unbounded Stripe SetupIntents against their own account — cost/quota risk, not a cross-user risk | None — out of mobile scope, mirrors web | `BuyerPaymentMethodSetupApiTest.php` |
| 10 | `POST buyer-payment-methods` | Yes | SetupIntent's own buyer cross-checked against authenticated user server-side, never client-trusted (`SetupIntentBuyerMismatch`) | None (mirrors web) | None found | None | `BuyerPaymentMethodSetupApiTest.php` |
| 11 | `GET transfers/{id}` | Yes | 403 for a requester who is neither the transfer's buyer nor seller | None (read-only) | None found | None | `TransferApiTest.php` |
| 12 | `GET transfers/{id}/qr-token` | Yes | Buyer-only; 403 for the seller or a stranger | None (read-only) | None found | None | `TransferApiTest.php` |
| 13 | `POST transfers/{id}/confirm-as-seller` | Yes | Role + identity check before the QR-token/geofence checks; wrong-role or stranger rejected identically | `throttle:transfer-confirmation` (20/min/user) | None found | None | `TransferApiTest.php` |
| 14 | `POST transfers/{id}/confirm-as-buyer` | Yes | Same as #13 | `throttle:transfer-confirmation` | None found | None | `TransferApiTest.php` |
| 15 | `POST transfers/{id}/ratings` | Yes | Rater id derived from token; domain rejects a non-participant or a second rating from the same rater | **Was none. Fixed this sprint**: `throttle:rating-submission` (20/min/user) — for consistency with #8/#13/#14, not a demonstrated exploit (a second attempt is already domain-rejected) | None found post-fix | None | `RatingApiTest.php`; `AppServiceProvider.php` (this sprint) |
| 16 | `GET transfers/{id}/ratings` | Yes | Participant-only; 403 for a stranger. Double-blind reveal (own rating always shown, counterpart only once revealed) | None (read-only) | None found | None | `RatingApiTest.php` |
| 17 | `POST transfers/{id}/disputes` | Yes | Filing-buyer id derived from token; domain rejects the seller or a stranger, and a second filing | **Was none. Fixed this sprint**: `throttle:dispute-filing` (20/min/user) — same reasoning as #15 | None found post-fix | None | `DisputeApiTest.php`; `AppServiceProvider.php` (this sprint) |
| 18 | `GET disputes/{id}` | Yes | Filing-buyer-only; 403 for the seller or a stranger | None (read-only) | None found | None | `DisputeApiTest.php` |
| 19 | `GET profile` | Yes | Self-only by construction | None (read-only) | None found | None | `UpdateProfileApiTest.php` |
| 20 | `PUT profile` | Yes | Token owner only updated, regardless of a client-supplied `id` field | **Was none. Fixed this sprint**: `throttle:profile-update` (10/min/user) — see the dedicated finding below | None found post-fix | None | `UpdateProfileApiTest.php`; `AppServiceProvider.php` (this sprint) |
| 21 | `POST devices` | Yes | Token owner only; re-registering an already-known token reassigns it to whoever currently holds that token's authenticated session — intentional (Expo's one-token-per-install model), not an IDOR, and covered by a dedicated test documenting it as deliberate | **Was none. Fixed this sprint**: `throttle:device-registration` (20/min/user) — consistency, same reasoning as #15/#17 | None found post-fix | None | `RegisterDeviceTokenApiTest.php`; `AppServiceProvider.php` (this sprint) |
| 22 | `POST queues` | Yes | Submitter id derived from token | None (mirrors web's own `/queues` route, which also has none) | Repeated submissions create multiple pending queues (moderation-queue spam) — pre-existing, app-wide, not mobile-specific | None — out of mobile scope, mirrors web | `SubmitQueueApiTest.php` |
| 23 | `GET account-standing` | Yes | Self-only; no id-accepting parameter at all | None (read-only, self-only) | None found | None | `ShowAccountStandingApiTest.php` (this sprint) |

## Fixes applied this sprint (all small, evidence-backed, consistency-driven — mirroring Phase 9 Sprint 5's own posture)

1. **`throttle:profile-update` (#20)** — the one finding with a real
   cross-user impact: `PUT profile` had no rate limit at all, on web or
   mobile, and an email change unconditionally re-sends the verification
   notification to whatever address is supplied — including an address
   that belongs to a real third party, not another RowBuddy account. An
   authenticated attacker could repeatedly change their own profile's
   email to a victim's real address, causing RowBuddy to send that
   victim repeated verification emails. Fixed on the mobile route this
   sprint owns; the identical, pre-existing gap on web's own `PUT
   user/profile-information` (Fortify's own default route) is
   deliberately left untouched, matching Sprint 5's own posture toward
   the `SubmitterAccountSuspended` finding — out of this sprint's mobile
   scope, not silently patched.
2. **`throttle:rating-submission`, `throttle:dispute-filing`,
   `throttle:device-registration` (#15/#17/#21)** — consistency fixes,
   not responses to a demonstrated exploit: bid-placement and transfer-
   confirmation already had rate limiters from the sprints that
   introduced them; these three newer mobile-only endpoints didn't,
   for no principled reason. Brought in line.

## Items reviewed, found pre-existing and app-wide, deliberately not fixed this sprint

- **#1/#3/#4**: `register`, `forgot-password`, `reset-password` have no
  rate limiting anywhere in this application — Fortify's own default
  routes carry the identical gap. A real finding, but fixing Fortify's
  own web-facing auth routes is outside "mobile Sprint 6."
- **#9/#10**: buyer-payment-method routes have no rate limiting, on web
  or mobile — same posture.
- **#22**: queue submission has no rate limiting, on web or mobile —
  same posture; also compensated somewhat by ADR-005's own
  admin-approval gate (a spammed queue can never itself become
  auctionable without a human approving it first).

All four items above are recorded here for visibility, not silently
assumed complete, matching Phase 9 Sprint 5's own explicit precedent for
items it reviewed but chose not to fix in-sprint.

## Validation

Full `apps/web` Pest suite re-run after every fix in this document:
**353 passed** (348 before this sprint's endpoint + throttle additions).
PHPStan and Pint both clean on every file this sprint touched.
