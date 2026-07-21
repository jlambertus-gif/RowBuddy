# Verification Model

## Signals

- GPS location
- GPS accuracy
- Continuous presence time
- Photo or short-video evidence
- Community confirmations
- Seller reputation
- Device and fraud signals

## Confidence levels

- Unverified
- Location verified
- Evidence verified
- Community verified
- Transfer completed

## Principle

No single signal is sufficient. Confidence is based on multiple independent
signals and must never be presented as an absolute guarantee. Confidence is
modeled as a score with derived tiers, computed from an accumulating,
independently-arriving signal set — not a mutable status field set once
(see `docs/product/claude-mvp-analysis.md` §7.2).
