# Financial Ledger and Payouts Specification

## Purpose

Provides auditable money movement, direct-owner accounting, provider integration, reconciliation, and legacy settlement controls without mutable balances, unauthorized deductions, or duplicate external payment effects.

## Requirements

### Requirement: Immutable balanced ledger
Financial events SHALL retain immutable balanced accounting within the correct receiving entity and model version. Direct owner receipts SHALL represent the owner's revenue/receivables, not Aura Court cash, wallet liability, tenant payable or platform commission. Subscription revenue SHALL be separate platform income. New booking/reservation payments, tournament registrations, provider collections and marketplace sales after commission retirement MUST NOT accrue platform commission or a replacement platform transaction fee. Legacy wallet, commission, reserve, settlement and compensation entries SHALL remain unchanged and reportable. Adjustments SHALL reference original events; historical values MUST NOT be recomputed from new policy.

#### Scenario: Reservation payment is accepted
- **WHEN** a new direct booking receipt is accepted
- **THEN** one owner-scoped balanced event SHALL be recorded without platform custody/payable entries or a platform commission deduction

#### Scenario: Refund is processed
- **WHEN** verified external owner refund evidence is accepted
- **THEN** one compensating owner-scoped event SHALL reference original paid allocations without changing source entries or recalculating commission from current configuration

#### Scenario: Historical replay
- **WHEN** an already posted legacy event is retried
- **THEN** only its original journal and monetary result SHALL be returned without recalculation or an additional journal

#### Scenario: Retired command does not create a journal
- **WHEN** a stale unsupported legacy command is rejected rather than an authorized new-policy request
- **THEN** no financial journal SHALL be created and all original records SHALL remain unchanged

#### Scenario: Historical cancellation refund remains in reports
- **WHEN** a report includes a cancellation refund recorded before cutover
- **THEN** the original amount, allocation, model and journal SHALL remain visible without new-rate recomputation

#### Scenario: Wallet collection has no commission
- **WHEN** a new wallet-funded booking, reservation or tournament payment of 200,000 VND is posted with no applicable provider fee
- **THEN** wallet liability SHALL decrease by 200,000 VND and tenant payable SHALL increase by 200,000 VND, with no platform commission entry

#### Scenario: Provider fee is retained without commission
- **WHEN** a new platform-received provider collection of 200,000 VND has an applicable provider fee of 2,000 VND
- **THEN** the balanced journal SHALL record 198,000 VND tenant payable and the 2,000 VND provider fee without a platform commission deduction

#### Scenario: Legacy fee settings cannot restore commission
- **WHEN** a new collection is processed after retirement while legacy commission configuration or supplied metadata still contains a nonzero rate or amount
- **THEN** the platform SHALL neither deduct nor report a newly accrued commission from that collection

### Requirement: Provider operations and webhook de-duplication
Provider integration SHALL verify recipient-bound direct-owner collection, separate subscription receipts and legacy obligations. Provider-event identities SHALL be unique within validated connection context; identical replay SHALL never duplicate charge allocation, refund recognition, journal or legacy payout. Browser/client claims SHALL not authorize monetary transitions. Failed delivery SHALL preserve raw evidence safely for bounded recovery and unresolved exceptions.

#### Scenario: Provider webhook is replayed
- **WHEN** one authenticated transfer event is delivered concurrently twice
- **THEN** one receipt allocation and financial outcome SHALL result

#### Scenario: Provider status is unavailable
- **WHEN** independent verification is unavailable
- **THEN** status SHALL remain pending verification or reconciliation without marking paid/refunded

#### Scenario: Legacy refund callback
- **WHEN** a callback concerns a recorded pre-cutover compensation
- **THEN** it SHALL be routed to that model without being reinterpreted as a new owner receipt

### Requirement: Settlement and payout lifecycle
Settlement/payout SHALL remain limited to reconciled pre-cutover platform-held obligations. New direct-owner revenue MUST NOT generate platform payout eligibility. Legacy payouts SHALL retain immutable snapshots, D+2 eligibility, 500000 VND configured minimum, idempotent submission and draft/approved/submitted/paid/failed/reversed evidence until settled. This change SHALL not submit real payouts or erase unresolved liabilities automatically.

#### Scenario: Direct booking period
- **WHEN** all period revenue was paid directly to owners
- **THEN** no platform settlement payable or payout SHALL be created

#### Scenario: Eligible weekly payout is approved
- **WHEN** authorized finance handles a verified historical payable meeting thresholds
- **THEN** its existing payout lifecycle SHALL remain available and audited

#### Scenario: Submitted payout fails
- **WHEN** a submitted historical payout fails
- **THEN** history SHALL remain and an exception SHALL track resolution

### Requirement: After-commit external delivery
The platform SHALL publish notifications, realtime events, and provider side effects only after the transaction that created their source business event commits. Failed delivery MUST be retried without duplicating the committed business or financial state.

#### Scenario: Transaction rolls back
- **WHEN** a reservation or financial transaction is rolled back
- **THEN** no notification, event, or provider request for that transaction SHALL be delivered

#### Scenario: Delivery retry occurs
- **WHEN** an after-commit delivery attempt fails transiently
- **THEN** the platform SHALL retry it and preserve one logical business outcome

### Requirement: Reconciliation and financial exceptions
At least daily and upon monetary anomalies, the platform SHALL reconcile receipts and obligations at transaction and receiving-entity level across booking versions, recurring invoices, split allocations, transfers, POS, refunds, subscriptions and legacy wallet/payouts. Nonzero mismatch SHALL create a traceable exception within 15 minutes of detection. Resolution SHALL require evidence or an authorized compensating event followed by rerun; it MUST NOT zero differences or claim correction solely by updating status. Reports/exports SHALL filter authorized organization/venue/period and distinguish owner revenue, platform fees, provider fees and legacy history without double-counting mapped V1/V2 records.

#### Scenario: Mixed revenue report
- **WHEN** a period contains V1/V2 aliases, POS receipts, owner refunds and subscription fees
- **THEN** authorized reports SHALL count canonical events once and keep receiver/purpose totals separate

#### Scenario: Auto-resolution without evidence
- **WHEN** an operator requests automatic resolution while source totals disagree
- **THEN** the mismatch SHALL remain open and no matched result SHALL be fabricated

#### Scenario: Reconciliation matches
- **WHEN** a verified correction explains the variance
- **THEN** an auditable rerun SHALL establish matched or remaining difference without rewriting history

#### Scenario: Reconciliation detects a mismatch
- **WHEN** transaction-level or receiver-level reconciliation detects nonzero variance
- **THEN** a linked finance exception SHALL be recorded within 15 minutes of detection and SHALL remain open until evidence and rerun establish resolution

### Requirement: Commission-free settlement and historical fee visibility
Settlements, finance APIs, exports and UI SHALL derive financial amounts from recorded journals or existing immutable snapshots, never recompute old records with the new pricing policy. Existing financial field compatibility SHALL be preserved: historical commission values MUST remain accessible, and new commission-free activity SHALL contribute zero commission. Provider fees and historical platform commissions MUST be distinguishable; historical commissions MUST NOT be relabeled as provider fees or omitted from total deductions. Current commercial messaging SHALL not advertise a commission model. Existing settlement eligibility, payout lifecycle, thresholds and authorization SHALL remain unchanged.

#### Scenario: Settlement contains only new commission-free activity
- **WHEN** a settlement is captured solely from post-retirement collections without historical commission adjustments
- **THEN** its commission amount SHALL be zero, actual provider fees SHALL remain visible and tenant payable SHALL match the ledger without a platform deduction

#### Scenario: Settlement period crosses retirement
- **WHEN** a report or new settlement snapshot includes both historical commission-bearing journals and new commission-free journals
- **THEN** it SHALL preserve the recorded historical commission, add no commission for the new collections and retain ledger-derived tenant payable

#### Scenario: Existing snapshot is read or requested again
- **WHEN** a previously captured settlement is viewed, exported or requested again after retirement
- **THEN** its monetary amounts and source snapshot SHALL remain unchanged, with any commission identified as historical

#### Scenario: Zero commission takes precedence over a legacy alias
- **WHEN** a compatible finance payload supplies an explicit zero commission and also includes a nonzero legacy alias
- **THEN** the UI SHALL honor the canonical zero instead of displaying or adding the stale alias as a current fee
