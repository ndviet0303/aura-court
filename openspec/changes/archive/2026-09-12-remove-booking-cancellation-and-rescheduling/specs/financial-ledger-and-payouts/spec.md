## MODIFIED Requirements

### Requirement: Immutable balanced ledger
Every financial business event SHALL produce an immutable journal whose debit and credit totals balance. The ledger MUST represent gross collection, provider fee, platform commission, tenant payable, refund, reserve, and wallet liability where applicable; balances and settlement amounts MUST be derived from journal entries rather than updated directly. Wallet payment and supported wallet compensation events SHALL each produce their own balanced journal entries against the `wallet_liability` account. Retired booking cancellation and rescheduling operations SHALL NOT produce new reversal or price-difference journals. Previously posted payment/refund journals and settlement inputs MUST remain unchanged and reportable.

#### Scenario: Reservation payment is accepted
- **WHEN** a reservation payment is confirmed via wallet balance deduction
- **THEN** the platform SHALL create a balanced immutable journal debiting `wallet_liability` and crediting the associated collection and liability accounts

#### Scenario: Refund is processed
- **WHEN** a separately authorized compensation outside retired booking cancellation/rescheduling credits a wallet
- **THEN** the platform SHALL create compensating balanced entries crediting `wallet_liability` and debiting the appropriate reversal accounts, without modifying the original payment journal

#### Scenario: Retired command does not create a journal
- **WHEN** cancellation, partial cancellation, rescheduling or legacy cancellation-refund approval is attempted
- **THEN** no new financial journal SHALL be posted and existing journals SHALL remain unchanged

#### Scenario: Historical cancellation refund remains in reports
- **WHEN** settlement or financial reporting includes a refund posted before retirement
- **THEN** it SHALL retain the original journal and amount rather than dropping or reversing the historical refund

### Requirement: Provider operations and webhook de-duplication
The platform SHALL use a provider-neutral payment interface for wallet deposit collection, verified webhooks, beneficiary onboarding, payout submission, and payout-status retrieval. Provider event identifiers MUST be unique, and replaying a verified event MUST NOT create another charge, refund, journal, or payout operation. Ordinary booking payments SHALL remain internal wallet balance operations. Cancellation/rescheduling SHALL NOT initiate wallet or external-provider refunds; separately supported refund/compensation contracts SHALL remain unchanged.

#### Scenario: Provider webhook is replayed
- **WHEN** the platform receives a verified wallet deposit event with an already-recorded provider event identifier
- **THEN** it SHALL acknowledge the duplicate without repeating financial effects

#### Scenario: Provider status is unavailable
- **WHEN** a payout status cannot be obtained from the provider within the retry policy
- **THEN** the payout SHALL remain unresolved and be visible in the finance exception process
