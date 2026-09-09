## ADDED Requirements

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

