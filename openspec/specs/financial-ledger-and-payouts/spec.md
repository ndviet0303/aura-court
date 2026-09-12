# Financial Ledger and Payouts Specification

## Purpose

Provides auditable money movement, provider integration, and settlement controls for
tenant operations without mutable balances or duplicate external payment effects.

## Requirements

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

### Requirement: Settlement and payout lifecycle
The platform SHALL derive tenant settlement from an immutable snapshot and make funds eligible no earlier than D+2. Payouts MUST follow `draft → approved → submitted → paid|failed|reversed`, enforce the configured minimum threshold of 500,000 VND, use an idempotent provider request, and prohibit edits to the ledger during exception handling.

#### Scenario: Eligible weekly payout is approved
- **WHEN** a tenant's settled payable exceeds 500,000 VND and D+2 eligibility is met
- **THEN** the platform SHALL create a payout in `draft` state for approval and record its settlement snapshot

#### Scenario: Submitted payout fails
- **WHEN** the provider reports a submitted payout as failed
- **THEN** the platform SHALL transition it to `failed`, preserve the source ledger, and create an exception for follow-up

### Requirement: After-commit external delivery
The platform SHALL publish notifications, realtime events, and provider side effects only after the transaction that created their source business event commits. Failed delivery MUST be retried without duplicating the committed business or financial state.

#### Scenario: Transaction rolls back
- **WHEN** a reservation or financial transaction is rolled back
- **THEN** no notification, event, or provider request for that transaction SHALL be delivered

#### Scenario: Delivery retry occurs
- **WHEN** an after-commit delivery attempt fails transiently
- **THEN** the platform SHALL retry it and preserve one logical business outcome

### Requirement: Reconciliation and financial exceptions
The platform SHALL reconcile provider activity, journals, settlements, and payouts at least daily. A non-zero mismatch MUST create a traceable exception within 15 minutes and SHALL not be silently corrected by altering ledger entries.

#### Scenario: Reconciliation matches
- **WHEN** daily provider and ledger totals reconcile
- **THEN** the platform SHALL record a successful reconciliation result

#### Scenario: Reconciliation detects a mismatch
- **WHEN** reconciliation finds a non-zero variance
- **THEN** the platform SHALL create a finance exception within 15 minutes with links to the affected records
