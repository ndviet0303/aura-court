## MODIFIED Requirements

### Requirement: Verified owner collection connection
An authorized owner SHALL register the receiving bank account and manually configure a SePay connection using platform-provided instructions and a connection-specific webhook credential. The webhook credential SHALL be presented in an authentication form that the provider supports for its bank-transaction callbacks; for SePay this is its API-key authorization header, and the setup instructions SHALL name that form. The platform MUST verify control/authorization and a successful authenticated test before enabling direct online collection. A current verified connection SHALL be a condition of the venue's new-booking eligibility, not of opening the venue or its courts: an owner MAY open a venue and its courts before any connection is verified, and while the venue has no current verified connection every new-booking entry point SHALL refuse new bookings for its courts. Secrets SHALL remain server-side, encrypted, scoped, rotatable and absent from responses/logs. Bank login passwords MUST NOT be requested by Aura Court.

#### Scenario: Incomplete connection
- **WHEN** an owner has entered only a bank account number
- **THEN** the account can be shown as draft, online collection SHALL fail with setup guidance, and the venue SHALL NOT be eligible for new bookings

#### Scenario: Venue opens before its connection is verified
- **WHEN** an owner opens a venue and one of its courts that meet their opening conditions while the venue has no verified connection
- **THEN** both SHALL open without a connection-related missing item
- **AND** a new booking for that court SHALL be refused with 409, without creating a booking, hold or payment attempt, until a connection for the venue is verified

#### Scenario: Verified connection and rotation
- **WHEN** an authorized owner completes connection verification or rotates the webhook secret
- **THEN** the connection SHALL retain verification/audit history and reject messages authenticated for another or revoked connection

#### Scenario: Receiving account changes
- **WHEN** a verified receiver is changed while payment attempts remain open
- **THEN** existing attempts SHALL retain their receiver snapshot; new attempts SHALL require verified replacement configuration, without silently redirecting old QR payments

#### Scenario: Provider callback uses its supported credential form
- **WHEN** SePay delivers a bank-transaction callback carrying the connection's credential in its API-key authorization header
- **THEN** the callback SHALL be authenticated for that connection, while a missing, wrong, rotated or another connection's credential SHALL be rejected without creating a receipt
