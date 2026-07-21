# rowbuddy/shared-kernel

Cross-cutting concerns shared by every RowBuddy bounded-context package.
This package must never contain domain/business rules specific to a single
module (no queue, auction, bid, or payment logic lives here) — only types
and contracts general enough that any module, including ones that don't
exist yet, can depend on them without coupling to each other.

## What belongs here

- **Contracts/** — interfaces that define a shared protocol between
  modules (e.g. how a domain event exposes its occurrence time, how an
  entity exposes its original locale) without any module depending on
  another module's concrete classes.
- **Support/** — small, framework-agnostic infrastructure (e.g. a
  testable clock) that many modules need but that isn't itself a business
  rule.
- **ValueObjects/** — immutable, general-purpose types (`Money`,
  `Currency`, `GeoPoint`, `Geofence`, `Locale`) with their own internal
  validation, but no knowledge of RowBuddy's business rules.
- **Localization/** — the locale/country/currency/timezone independence
  rule from ADR-002, expressed as a shared value type, plus the
  platform's supported-locale policy (`en` default, `es` supported).
- **Events/** — the base contract/class every module's domain events
  build on, so event versioning and shape stay consistent platform-wide.
- **Exceptions/** — base exception types other packages extend, so
  cross-cutting concerns (validation failure, not-found) can be caught
  generically where useful.

## What does not belong here

- Identity/authentication — intentionally not created yet. It becomes its
  own package (`packages/identity`) only when authentication work begins.
- Any of the other bounded contexts (Queues, Auctions, Bids, Payments,
  Transfers, Disputes, Ratings, Notifications, Administration, Audit,
  Verification, QueuePresence, Fraud & Risk) — each gets its own package,
  created in the roadmap phase that implements it (see `docs/roadmap.md`).

## Consuming this package

`apps/web` (and any future app) requires this package via a Composer path
repository pointing at this directory — never by copying code.
