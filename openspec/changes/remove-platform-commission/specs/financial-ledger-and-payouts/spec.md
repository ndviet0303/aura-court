## MODIFIED Requirements

### Requirement: Immutable balanced ledger
Every financial business event SHALL produce an immutable journal whose debit and credit totals balance. The ledger MUST represent gross collection, provider fee, tenant payable, refund, reserve, and wallet liability where applicable; balances and settlement amounts MUST be derived from journal entries rather than updated directly. New booking/reservation payments, tournament registrations, provider collections and marketplace sales after commission retirement MUST NOT accrue platform commission or a replacement platform transaction fee. Historical commission entries SHALL remain available for reporting and separately authorized compensation of their original financial event. Wallet payment and supported wallet compensation events SHALL each produce their own balanced journal entries against the `wallet_liability` account. Retired booking cancellation and rescheduling operations SHALL NOT produce new reversal or price-difference journals. Previously posted payment/refund journals and settlement inputs MUST remain unchanged and reportable.

#### Scenario: Reservation payment is accepted
- **WHEN** a reservation payment is confirmed via wallet balance deduction
- **THEN** the platform SHALL create a balanced immutable journal debiting `wallet_liability` and crediting the associated collection and liability accounts without a platform commission deduction

#### Scenario: Refund is processed
- **WHEN** a separately authorized compensation outside retired booking cancellation/rescheduling credits a wallet
- **THEN** the platform SHALL create compensating balanced entries crediting `wallet_liability` and debiting the appropriate reversal accounts, using original recorded allocations without modifying the original payment journal or recalculating commission from current configuration

#### Scenario: Retired command does not create a journal
- **WHEN** cancellation, partial cancellation, rescheduling or legacy cancellation-refund approval is attempted
- **THEN** no new financial journal SHALL be posted and existing journals SHALL remain unchanged

#### Scenario: Historical cancellation refund remains in reports
- **WHEN** settlement or financial reporting includes a refund posted before retirement
- **THEN** it SHALL retain the original journal and amount rather than dropping or reversing the historical refund

#### Scenario: Wallet collection has no commission
- **WHEN** a new booking, reservation or tournament payment of 200,000 VND is posted with no applicable provider fee
- **THEN** wallet liability SHALL decrease by 200,000 VND and tenant payable SHALL increase by 200,000 VND, with no platform commission entry

#### Scenario: Provider fee is retained without commission
- **WHEN** a new provider collection of 200,000 VND has an applicable provider fee of 2,000 VND
- **THEN** the balanced journal SHALL record 198,000 VND tenant payable and the 2,000 VND provider fee without a platform commission deduction

#### Scenario: Legacy fee settings cannot restore commission
- **WHEN** a new collection is processed after retirement while legacy commission configuration or supplied metadata still contains a nonzero rate or amount
- **THEN** the platform SHALL neither deduct nor report a newly accrued commission from that collection

#### Scenario: Historical event is replayed
- **WHEN** an already-posted payment or marketplace sale with historical commission is retried after retirement
- **THEN** its original journal and monetary outcome SHALL be retained without recalculation or an additional journal

## ADDED Requirements

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
