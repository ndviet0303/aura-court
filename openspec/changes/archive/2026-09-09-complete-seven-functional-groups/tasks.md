## 1. Baseline and contracts

- [x] 1.1 Create a seven-area acceptance matrix linking each delta and existing required spec to routes, UI, persistence and test evidence; classify each flow as working, partial or missing.
- [x] 1.2 Reproduce POS false success, receipt mismatch, wrong reservation identity, unpaid V2 confirmation and local tournament registration in regression tests on an isolated database.
- [x] 1.3 Inventory V1/V2 request/response contracts, pricing combination policy, role matrix and effective feature flags; document ordinary versus specialized booking paths.
- [x] 1.4 Record baseline backend/frontend test commands and results; verify the test database is isolated before any reset or migration.

## 2. Account, tenant and customer foundation

- [x] 2.1 Verify and repair registration, email verification, login/logout and password change/reset APIs with expired-token and replay tests.
- [x] 2.2 Connect profile and account screens to persisted outcomes; add a real-API reload journey and actionable validation errors.
- [x] 2.3 Enforce existing role matrix across routes and organization switching; test customer, staff, cashier, manager, owner and elevated platform access.
- [x] 2.4 Expose effective tenant capabilities and connect navigation/forms to them; test unavailable features without simulated success.
- [x] 2.5 Add tenant customer relationships and guest contact persistence with uniqueness and cross-tenant tests; keep employee memberships separate.
- [x] 2.6 Implement authorized customer lookup using real wallet, membership and loyalty values and wire POS customer selection.
- [x] 2.7 Audit/backfill explicit legacy booking-to-reservation mappings with dry-run counts, orphan/collision reporting and no ID coincidence fallback.

## 3. Venue, court and pricing

- [x] 3.1 Verify and repair venue/sport-zone/court CRUD and public detail/list integration, including amenities, inactive records and preserved booking history.
- [x] 3.2 Unify opening hours, dated exceptions and maintenance closures between availability and checkout; test conflicting existing bookings and venue-local date boundaries.
- [x] 3.3 Connect admin calendar/closure screens to persisted APIs and prove availability updates after reload.
- [x] 3.4 Verify date-exception, peak, weekly and base rule precedence, versioned quote snapshots and concurrent rule-change behavior.
- [x] 3.5 Apply coupon and membership eligibility/combination on the server; remove client-only financial discounts and test invalid/expired coupons.
- [x] 3.6 Verify membership benefit versioning and loyalty award/redemption records with existing spec scenarios.
- [x] 3.7 Pass identity/catalog/pricing package acceptance before enabling dependent checkout work for pilot.

## 4. Booking and wallet integrity

- [x] 4.1 Implement shared ordinary V1/V2 wallet-funded checkout orchestration with atomic inventory, debit and financial posting; preserve V1 response contracts.
- [x] 4.2 Replace ordinary V2 held/confirm UI flow with canonical checkout and reject unpaid legacy confirmations; retain tests for specialized split and invoice paths.
- [x] 4.3 Make rescheduling atomic across old/new inventory and wallet price differences; test conflicts and insufficient additional balance.
- [x] 4.4 Verify whole and partial cancellation/refund eligibility, payer amounts, repeated requests and immutable financial reversals.
- [x] 4.5 Connect history, payment result and refund UI to authoritative records; eliminate local confirmed status and test reload after failure/success.
- [x] 4.6 Verify recurring series/monthly invoices, overdue release and reminder behavior against existing advanced-booking requirements.
- [x] 4.7 Complete waitlist create/remove and released-slot notifications without automatic booking promises.
- [x] 4.8 Verify existing split payment, resale and entitlement-transfer regression cases without enabling their gated features.
- [x] 4.9 Pass concurrent slot and wallet debit tests and V1 historical compatibility tests before activating ordinary checkout.

## 5. Deposit and finance journeys

- [x] 5.1 Connect tenant wallet deposit initiation/status/history to verified provider events; test duplicate, invalid and out-of-order events.
- [x] 5.2 Connect wallet top-up and balance UI to actual tenant records and pending/failure states; prohibit fallback balances.
- [x] 5.3 Verify booking, tournament-ready and POS financial posting interfaces produce balanced journals and retry-safe source references.
- [x] 5.4 Verify and repair financial report/CSV, settlement, payout and exception screens against real API payloads and role checks.
- [x] 5.5 Test payout lifecycle, threshold, eligibility, daily reconciliation and compensating records against existing finance specs.
- [x] 5.6 Collect provider sandbox evidence for deposit and payout paths or record the precise external blocker; keep rollout disabled until existing readiness gates pass.

## 6. POS and inventory

- [x] 6.1 Add server quote/receipt fields for rental duration, configured VAT, rounding, discounts and cash tender/change; add exact-total tests.
- [x] 6.2 Enforce open-shift sale completion and same-tenant canonical reservation attachment in API validation; test unmapped legacy IDs and unauthorized cashier access.
- [x] 6.3 Replace POS catch-and-success receipt construction with server receipt rendering, retained carts on rejection and stable-key retry on uncertain responses.
- [x] 6.4 Complete product/SKU/barcode CRUD and receipt/adjustment stock movements with atomic available-stock checks and audit history.
- [x] 6.5 Implement verified pending QR collection and expiry/compensation behavior; keep QR disabled if provider verification is unavailable.
- [x] 6.6 Verify identified-customer wallet and cash sale paths, membership applied once and stock/payment/journal agreement.
- [x] 6.7 Complete shift open/close/review UI from real records; test expected cash, variance and preservation of original financial entries.
- [x] 6.8 Pass POS error, offline draft, repeated payment, concurrent stock and receipt-reload acceptance tests.

## 7. On-site attendance and tabs

- [x] 7.1 Add canonical booking attendance records and idempotent authenticated check-in/out APIs with actor/time and current venue-local day validation.
- [x] 7.2 Connect QR/code scanning and operational court status to real attendance; test wrong tenant, cancelled booking and duplicate scan.
- [x] 7.3 Implement staff-assisted registered-customer walk-in checkout using ordinary booking/payment rules and audit actor identity.
- [x] 7.4 Add persisted service tabs and stock reservations; implement item add/remove with no paid cash or sale movement before settlement.
- [x] 7.5 Implement tab settlement through POS financial commands and stock-reservation conversion exactly once; remove court_tab-to-cash coercion.
- [x] 7.6 Block checkout with unpaid tabs and connect outstanding/settled tab UI; test tab reload, cancellation and concurrent stock use.
- [x] 7.7 Pass a complete check-in → service purchase → settlement → checkout → shift reconciliation real-API journey.

## 8. Community and tournaments

- [x] 8.1 Add friendship request/accept/decline/remove persistence, authorization and duplicate-request tests.
- [x] 8.2 Replace hard-coded friends with persisted connection search and wire match invitations/responses with individual failure reporting.
- [x] 8.3 Verify profile/discovery filters, join capacity and invite-only access; connect social UI and preserve existing reputation/dispute rules.
- [x] 8.4 Add audience-scoped feed post/comment/reaction APIs with ownership checks, bounded input and escaped rendering tests.
- [x] 8.5 Add reporting and audited moderation hide/restore APIs including controlled friends-only report review; test unauthorized access.
- [x] 8.6 Build feed, connections and moderation interfaces against actual APIs with reload and private-audience tests.
- [x] 8.7 Wire friendship/match/tournament notifications through the existing recipient-scoped after-commit delivery path; test rollback and duplicates.
- [x] 8.8 Add server-published tournament entry fee/capacity and atomic wallet-funded or free registration with stable retry and participant persistence.
- [x] 8.9 Replace local registration counters and payment success with participant/receipt APIs; test last-place race, insufficient funds and refresh.
- [x] 8.10 Verify create/regenerate bracket, participant check-in, score conflict, standings and realtime resynchronization journeys for the two supported formats.
- [x] 8.11 Pass community/tournament package acceptance including privacy, invitations, paid registration and persisted result.

## 9. Integrated acceptance and rollout

- [x] 9.1 Run the seven-area browser acceptance matrix against actual test APIs without business response mocks; record persisted reload and negative-path evidence.
- [x] 9.2 Run full backend/frontend regressions, tenant/role tests and money/inventory reconciliation checks; fix failures without weakening assertions.
- [x] 9.3 Rehearse additive migrations and explicit identity backfill with counts/checksums, historical API compatibility and documented rollback.
- [x] 9.4 Re-run existing required load/concurrency and backup/restore readiness checks; record unmet external or operational gates as blocked.
- [x] 9.5 Document capability flags, package entry/exit evidence and operator runbooks for payment uncertainty, stock variance and moderation.
- [x] 9.6 Record independent pilot activation/disable procedures preserving committed history; do not activate production without a separate user request.
- [x] 9.7 Update the seven-group capability report and README from demonstrated evidence, distinguishing implemented, verified and externally blocked features.
