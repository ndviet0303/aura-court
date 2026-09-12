# Capability: Wallet Payment

## Purpose

Enables users to pay for bookings and tournament fees by deducting from their wallet balance without generating wallet credits or debits from retired booking cancellation or rescheduling.

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

### Requirement: No wallet movement from retired booking changes
The platform SHALL NOT create wallet credits, price-difference debits, refund requests or replacement payment records from attempted booking cancellation, partial cancellation or rescheduling. Retiring these operations SHALL NOT delete, reverse or recompute historical wallet transactions or balances. Existing deposit, booking payment, tournament payment and unrelated authorized compensation behavior SHALL remain unchanged.

#### Scenario: Cancel or reschedule attempt leaves wallet untouched
- **WHEN** a client attempts a retired cancellation or rescheduling command, including repeated attempts
- **THEN** the wallet balance and transaction history SHALL remain unchanged and no refund request SHALL be created

#### Scenario: Historical refund remains visible
- **WHEN** a user reloads wallet history containing a cancellation or rescheduling refund issued before retirement
- **THEN** the original amount, transaction reference and recorded balance SHALL remain available without issuing or reversing a credit

#### Scenario: Unpaid hold expires
- **WHEN** an unpaid booking expires automatically
- **THEN** expiry SHALL release eligible inventory without crediting a wallet

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
