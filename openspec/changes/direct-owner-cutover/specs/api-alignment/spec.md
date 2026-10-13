## MODIFIED Requirements

### Requirement: Policy-aware changes and truthful finance interfaces
Customer/owner/staff booking pages and Vietnamese/English policies SHALL show the captured booking terms and the actual payment and resale status. They SHALL NOT offer booking cancellation, refunds or rescheduling; policies SHALL name slot resale as the only way to pass on a paid booking and SHALL NOT promise cancellation, refunds or rescheduling through the platform. Success SHALL require persisted server confirmation; a dispute judgment or a party's own report SHALL not be labelled as verified payment or money returned. Capability availability SHALL combine permissions, subscriptions, provider readiness and rollout state.

#### Scenario: Cancellation request
- **WHEN** an eligible customer opens a paid booking looking for a way to cancel it
- **THEN** the UI SHALL show no cancel, refund or reschedule action and SHALL offer slot resale under the accepted policy, without a blanket unsupported message or a refund claim

#### Scenario: Change request
- **WHEN** a customer wants to move a paid booking to another court, date or time
- **THEN** the UI SHALL offer no reschedule preview or request, and SHALL explain that the slot can be resold and a new slot booked

#### Scenario: Provider not ready
- **WHEN** an owner has not verified SePay
- **THEN** online payment SHALL be unavailable with setup guidance, without demo success or a QR to Aura Court's account
