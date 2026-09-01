## MODIFIED Requirements

### Requirement: Immutable balanced ledger
Every financial business event SHALL produce an immutable journal whose debit and credit totals balance. The ledger MUST represent gross collection, provider fee, platform commission, tenant payable, refund, reserve, and wallet liability where applicable; balances and settlement amounts MUST be derived from journal entries rather than updated directly. Wallet payment and wallet refund events SHALL each produce their own balanced journal entries against the `wallet_liability` account.

#### Scenario: Reservation payment is accepted
- **WHEN** a reservation payment is confirmed via wallet balance deduction
- **THEN** the platform SHALL create a balanced immutable journal debiting `wallet_liability` and crediting the associated collection and liability accounts

#### Scenario: Refund is processed
- **WHEN** a confirmed booking is cancelled and the wallet is credited with the refund amount
- **THEN** the platform SHALL create compensating balanced entries crediting `wallet_liability` and debiting the appropriate reversal accounts, without modifying the original payment journal

### Requirement: Provider operations and webhook de-duplication
The platform SHALL use a provider-neutral payment interface for wallet deposit collection, verified webhooks, beneficiary onboarding, payout submission, and payout-status retrieval. Provider event identifiers MUST be unique, and replaying a verified event MUST NOT create another charge, refund, journal, or payout operation. Booking payments and refunds no longer flow through external payment providers; they are handled internally via wallet balance operations.

#### Scenario: Provider webhook is replayed
- **WHEN** the platform receives a verified wallet deposit event with an already-recorded provider event identifier
- **THEN** it SHALL acknowledge the duplicate without repeating financial effects

#### Scenario: Provider status is unavailable
- **WHEN** a payout status cannot be obtained from the provider within the retry policy
- **THEN** the payout SHALL remain unresolved and be visible in the finance exception process

## REMOVED Requirements

### Requirement: External provider refund processing
**Reason**: Refunds are now handled internally by crediting the user's wallet balance. External provider refund operations are no longer needed for booking cancellations.
**Migration**: All booking refunds are credited to the user's wallet automatically. The `refund_pending` booking status and admin manual review flow are replaced by instant wallet credit with `refunded` status. Existing pending refunds should be processed manually before migration.
