## MODIFIED Requirements

### Requirement: PSP and operating-model go-no-go gate
Before enabling direct-owner collection, split payments, paid tournament registrations or POS QR collection for a cohort, the platform SHALL record verified provider evidence for connection isolation, receiving-account control, callbacks authenticated in the provider's supported form, receipt matching, deduplication and late/unknown outcomes. Paid slot resale is settled directly between buyer and seller and SHALL NOT depend on provider evidence. Subscription verification SHALL use its separate receiver. New direct flows MUST NOT require unsupported automatic bank refunds/payouts or pretend those capabilities exist. Legacy payouts SHALL retain their own provider/operating gate. Accountable operational owners and exception procedures SHALL be recorded.

#### Scenario: All required PSP capabilities are verified
- **WHEN** sandbox and isolated acceptance prove the required direct-money controls and a real provider callback for the cohort's receiving connection reaches the receipt boundary and confirms one obligation
- **THEN** only the approved cohort/capabilities SHALL be enabled

#### Scenario: A required PSP capability is unavailable
- **WHEN** a required callback or receiving-account verification cannot be demonstrated
- **THEN** that financial capability SHALL remain blocked with the missing evidence recorded

#### Scenario: Unsupported automatic debit
- **WHEN** a provider lacks automatic owner-bank refunds
- **THEN** the system SHALL offer no refund workflow and SHALL record unmatched or late money as finance exceptions, never fake successful automated refunds

### Requirement: Truthful payment acceptance boundaries
Browser evidence for direct-owner cohorts SHALL use receiver-bound pending obligations and server-verified receipt processing, separate platform subscription collection, finance exceptions for unmatched or late money and immutable legacy-wallet history. Local synthetic provider events MUST be labeled as isolated application-contract evidence, never operational provider certification, and SHALL use the same callback authentication form the provider sends. Browser return flags, screenshots, optimistic UI or mocked success responses MUST NOT qualify as paid/returned money. Any external provider certification requiring real access SHALL remain an independently gated activity.

#### Scenario: Verified test receipt
- **WHEN** a correctly authenticated synthetic incoming event is delivered to the real isolated receipt-processing boundary
- **THEN** acceptance SHALL verify one matching obligation transitions correctly and duplicate delivery creates no second effect, with the evidence marked synthetic-provider

#### Scenario: Wrong late or client-claimed receipt
- **WHEN** payment evidence has the wrong receiver/amount/reference, arrives after expiry or consists only of a client success claim
- **THEN** acceptance SHALL verify no false booking confirmation or subscription activation and the applicable reconciliation or finance-exception outcome

#### Scenario: Direct booking refund
- **WHEN** a client tries to cancel, refund or reschedule a paid direct booking
- **THEN** acceptance SHALL verify the request is rejected and no refund obligation, payment obligation, wallet credit or fabricated bank transfer appears

### Requirement: Persisted and authorized browser journeys
Critical acceptance SHALL exercise user actions through the interface and real application API, verify durable results after reload and prove relevant role, organization and sibling-venue restrictions. Coverage MUST include authentication, per-court availability, booking/payment/expiry/conflict, promotions and points, the absence of cancellation, refund and reschedule actions, POS/shift accounting and attendance/service tabs. Extended acceptance SHALL cover partner/subscription/publication/staff workflows, recurrence/split/resale, inventory, community/tournaments, disputes and reporting. Negative commands SHALL retain valid input where applicable and MUST NOT produce false success, duplicate business effects or unauthorized data disclosure.

#### Scenario: Critical mutation succeeds
- **WHEN** a customer completes a booking or a cashier completes an order through the interface
- **THEN** a subsequent reload and authoritative read SHALL agree on identity, status, amounts and relevant inventory, with one persisted business outcome

#### Scenario: Competing users or repeated command
- **WHEN** two customers attempt the same court-time or an uncertain successful operation is retried
- **THEN** acceptance SHALL demonstrate exclusive inventory and a single business effect while the losing or pending interface reports the actual outcome

#### Scenario: Unauthorized actor
- **WHEN** a cashier attempts platform administration, an owner targets another organization or a manager targets an unassigned sibling venue
- **THEN** both interface access and relevant direct requests SHALL enforce the current authorization contract without revealing foreign data
