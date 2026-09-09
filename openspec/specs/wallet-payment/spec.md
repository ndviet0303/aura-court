# Capability: Wallet Payment

## Purpose

Enables users to pay for bookings and tournament fees by deducting from their wallet balance, and automatically credits refunds back to the wallet upon eligible cancellation.

## Requirements

### Requirement: Pay booking from wallet balance
The platform SHALL allow a user to pay for a booking by deducting the booking's total amount from their wallet balance in a single atomic operation. Upon successful deduction, the booking SHALL transition to `confirmed` status and all held slots SHALL transition to `confirmed`. The wallet transaction SHALL be immutable, referencing the booking as its source.

#### Scenario: Sufficient balance pays for booking
- **WHEN** a user confirms payment for a booking with total amount 200,000 VND and their wallet balance is 500,000 VND
- **THEN** the platform SHALL deduct 200,000 VND from the wallet, confirm the booking and its slots, and record an immutable wallet transaction with `balance_after` of 300,000 VND

#### Scenario: Insufficient balance rejects payment
- **WHEN** a user attempts to pay for a booking with total amount 200,000 VND and their wallet balance is 100,000 VND
- **THEN** the platform SHALL reject the payment without modifying the wallet balance or booking status, and SHALL return the current balance and the shortfall amount

#### Scenario: Concurrent wallet payments
- **WHEN** two payments attempt to deduct from the same wallet simultaneously and only one can be satisfied by the available balance
- **THEN** exactly one SHALL succeed and the other SHALL receive an insufficient-balance rejection, with no negative balance possible

### Requirement: Pay tournament fee from wallet balance
The platform SHALL allow a user to pay tournament entry fees by deducting from their wallet balance. The deduction SHALL follow the same atomic, immutable transaction rules as booking payments.

#### Scenario: Tournament fee paid from wallet
- **WHEN** a user confirms tournament registration with an entry fee of 300,000 VND and wallet balance of 400,000 VND
- **THEN** the platform SHALL deduct 300,000 VND, confirm the tournament registration, and record an immutable wallet transaction referencing the tournament as its source

### Requirement: Automatic refund to wallet on cancellation
When a confirmed booking is cancelled within the refund policy (at least 2 hours before the first slot), the platform SHALL credit the full booking amount back to the user's wallet balance immediately. The refund SHALL be recorded as an immutable wallet credit transaction referencing the original booking. No admin review or manual approval SHALL be required.

#### Scenario: Eligible cancellation refunds to wallet instantly
- **WHEN** a user cancels a confirmed booking 3 hours before the first slot start time
- **THEN** the platform SHALL credit the full booking amount to the user's wallet, set the booking status to `refunded`, and record an immutable wallet credit transaction

#### Scenario: Late cancellation does not refund
- **WHEN** a user cancels a confirmed booking 1 hour before the first slot start time
- **THEN** the platform SHALL reject the cancellation per the existing 2-hour policy without modifying the wallet balance

#### Scenario: Pending booking cancellation does not refund
- **WHEN** a user cancels a booking that has not yet been paid (no wallet deduction occurred)
- **THEN** the platform SHALL cancel the booking without creating any wallet transaction

### Requirement: Wallet is the sole booking payment method
The platform SHALL NOT accept QR bank transfer or card payments as direct booking payment methods. The only path to pay for a booking or tournament fee SHALL be through wallet balance deduction. Wallet deposit (adding funds to the wallet) remains available via the existing deposit flow.

#### Scenario: No QR or card payment option at checkout
- **WHEN** a user views the booking payment screen
- **THEN** the platform SHALL display only the wallet balance and a pay-from-wallet action, with no QR code or card form

#### Scenario: Insufficient balance shows top-up guidance
- **WHEN** a user's wallet balance is less than the booking total
- **THEN** the platform SHALL display the shortfall amount and provide a link to the wallet deposit page

### Requirement: Persisted tournament registration checkout
Tournament registration SHALL verify eligibility and capacity, calculate the entry fee on the server and atomically persist the participant and wallet debit for paid entry. Free entry SHALL persist registration without a debit. Retry SHALL return the same registration and receipt; local counters or redirects SHALL NOT constitute registration.

#### Scenario: Paid registration
- **WHEN** an eligible player pays a published fee with sufficient funds
- **THEN** one persisted participant, wallet transaction and balanced financial posting exist

#### Scenario: Last place race
- **WHEN** two players attempt to buy the final place
- **THEN** one registers and only that player's wallet is debited

#### Scenario: Insufficient funds
- **WHEN** a player cannot cover the entry fee
- **THEN** no confirmed participant or debit is created

### Requirement: Verified wallet deposit journey
The platform SHALL expose deposit initiation, pending/status and completed history for the selected tenant wallet. Only a verified provider outcome SHALL credit the wallet; redirects, QR display and duplicate events SHALL NOT create credit.

#### Scenario: Duplicate confirmation
- **WHEN** a verified deposit event is delivered twice
- **THEN** the wallet receives exactly one credit and the UI reports the persisted result

#### Scenario: Unverified return
- **WHEN** a customer returns from a provider without verified collection
- **THEN** the deposit remains pending or failed without credit
