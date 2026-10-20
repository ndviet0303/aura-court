## ADDED Requirements

### Requirement: Owner lookup of payment exceptions
An organization owner SHALL be able to look up, read-only, the payment exceptions recorded on their own organization's collection connections: a transfer below or above the amount due, with a wrong or missing payment code, a duplicate, and one received after its booking expired or was cancelled. Each exception SHALL show its type, the amount received, the payment code as received, the transaction time, the collection connection and its handling status. When the payment code identifies a payment request, the exception SHALL also show that request's booking code and purpose; an exception whose code matches no request SHALL show no booking, and the platform SHALL NOT link it to a booking by amount or time alone. The lookup SHALL be available whenever direct-owner payments are enabled, whether or not marketplace payouts are enabled. Staff, customers and owners of other organizations SHALL be refused with 403. Viewing an exception SHALL NOT change any booking, receipt or exception; any refund SHALL be settled by the owner outside the platform.

#### Scenario: Transfer after cancellation
- **WHEN** a customer cancels a pending booking, then transfers the full amount with its payment code, and the owner opens the payment exceptions
- **THEN** the owner SHALL see a transfer-after-expiry-or-cancellation exception with the amount, the payment code, the transaction time and the booking code, and the booking SHALL stay cancelled

#### Scenario: Unknown payment code
- **WHEN** a transfer reaches the owner's connection with a code that matches no payment request
- **THEN** the exception SHALL show the amount, the code as received and the time, and SHALL show no booking even if its amount equals a pending booking's amount

#### Scenario: Payouts disabled
- **WHEN** direct-owner payments are enabled, marketplace payouts are disabled, and an owner opens the payment exceptions
- **THEN** the lookup SHALL return the organization's exceptions

#### Scenario: Unauthorized reader
- **WHEN** a staff member, a customer or an owner of another organization requests the payment exceptions
- **THEN** the request SHALL be refused with 403 and no exception detail SHALL be returned
