# src/features/disputes (reserved)

Not implemented yet.

Reserved for buyer-side dispute filing and status viewing, against the
new `/api/v1/transfers/{id}/disputes` surface ADR-028 Decision 3
introduces (wrapping the already-existing `DisputeFilingService`).
Dispute _resolution_ remains admin-only/web-only and is never built here.
Scoped for Sprint 4.
