# Security Model

## Priorities

- Identity verification
- Evidence privacy
- Payment integrity
- Bid concurrency
- Transfer integrity
- Auditability
- Fraud prevention

## Required controls

- Authorization policies
- Signed URLs for private evidence
- File validation
- Metadata removal where appropriate
- Database transactions
- Row-level locking
- Payment idempotency
- Webhook signature validation
- Rate limiting
- Device and session monitoring
- Immutable audit events

## Notes

- Evidence (photos/videos, GPS trails) is sensitive PII and must never be
  publicly addressable.
- QR transfer tokens must be single-use, short-TTL, and bound to the
  specific auction/transfer — never a static, reusable code.
- Bid writes must go through a single, lock-safe write path to prevent
  race conditions under concurrent bidding.

See `docs/product/claude-mvp-analysis.md` §3 for the full risk catalogue
(fraud, security, legal, scalability, maintainability).
