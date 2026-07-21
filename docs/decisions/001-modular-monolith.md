# ADR 001: Modular Monolith

## Decision

The MVP will use a modular monolith.

## Rationale

- Faster delivery
- Lower infrastructure cost
- Easier transactions
- Easier debugging
- Clear module boundaries without premature microservices

## Consequence

Modules must remain logically separated so selected services may be
extracted later if scale requires it. This is enforced with an automated
architecture-boundary test starting in Phase 0, not left as convention.
