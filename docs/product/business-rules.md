# Business Rules

1. The seller must be physically present in the queue.
2. GPS alone is not sufficient proof.
3. One physical position may have only one active auction.
4. The seller must remain near the queue while the auction is active.
5. The seller may not delete an accepted bid.
6. The server calculates all prices and fees.
7. The winning payment is protected until transfer confirmation (see
   `docs/decisions/004-escrow-authorize-then-capture.md`).
8. A position cannot be resold in the MVP.
9. Sensitive and restricted queues are prohibited.
10. Every bid, payment, verification, transfer, and dispute action is
    audited.
11. Evidence is private by default.
12. Minors may not buy or sell positions.
13. RowBuddy does not guarantee organizer acceptance.
14. The seller must disclose that the position is approximate unless
    independently verified.
15. The buyer must arrive within the defined transfer window.
16. The seller must not abandon the queue before transfer or cancellation.
17. Fraudulent evidence may result in suspension and forfeiture according
    to platform policy.
18. The platform fee is a percentage of the winning bid, charged to the
    buyer on top of the bid amount (see
    `docs/decisions/006-fee-model-buyer-side-percentage.md`).
