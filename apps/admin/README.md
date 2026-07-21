# apps/admin (reserved)

Not implemented yet.

Back-office functionality (queue moderation per
`docs/decisions/005-hybrid-queue-authorship.md`, KYC/dispute overrides,
restricted-category management) ships inside `apps/web` for now, as
protected routes and Inertia pages gated by role-based authorization
policies.

This directory is reserved for the day back-office needs genuinely diverge
from the public marketplace app — a different release cadence, a different
team, or a different scaling profile — and it becomes worth separating.
Until then, creating a second Laravel installation here would be pure
operational overhead with no corresponding benefit.
