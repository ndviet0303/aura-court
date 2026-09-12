## ADDED Requirements

### Requirement: No cancellation or rescheduling interface
Customer and operational booking interfaces SHALL omit whole-booking cancellation, partial-slot cancellation and court/date/time rescheduling actions, dialogs and success flows. Booking-related customer copy in Vietnamese and English SHALL clearly state that cancellation and rescheduling are unsupported, including court detail and checkout, and MUST NOT advertise free cancellation, flexible rescheduling, automatic cancellation refunds or membership cancellation exemptions. Back-navigation, editing an unsubmitted selection and automatic expiry SHALL remain distinct from cancellation of a persisted booking.

#### Scenario: Customer views booking history or a held reservation
- **WHEN** a customer opens V1 history, V2 reservation detail or a legacy held-reservation banner
- **THEN** no cancellation/rescheduling control SHALL be available, while supported payment, detail and check-in actions remain available where eligible

#### Scenario: Customer reviews checkout policy
- **WHEN** a customer views court detail or checkout in Vietnamese or English
- **THEN** the policy SHALL state that booking cancellation and rescheduling are unsupported without promising a refund or change window

#### Scenario: Historical cancelled booking is displayed
- **WHEN** a customer views an old cancelled, expired or refunded booking
- **THEN** its recorded status SHALL remain readable and SHALL NOT be relabelled as confirmed or made actionable for cancellation/rescheduling

### Requirement: Retired cancellation-refund administration
The platform SHALL remove approval/rejection controls and actionable queue indicators for booking-cancellation refunds. PATCH `/api/v1/admin/refunds/{refund}` SHALL be retired and return HTTP 404 for otherwise valid authenticated requests without updating refunds, bookings, wallets or journals. Existing cancellation-refund records, including pending records, SHALL remain available through authorized read-only history and SHALL NOT be automatically approved, rejected or paid during rollout. Unrelated financial dispute/compensation interfaces SHALL remain governed by their existing contracts and SHALL NOT be relabelled as replacement booking-cancellation actions.

#### Scenario: Administrator attempts old refund approval
- **WHEN** an administrator sends an approval or rejection to the retired cancellation-refund endpoint
- **THEN** the request SHALL return HTTP 404 without changing the refund status, booking or financial state

#### Scenario: Pending historical refund is viewed
- **WHEN** an authorized operator views a pending cancellation-refund record created before retirement
- **THEN** its original status and references SHALL be visible as read-only history without approval/rejection actions or an instruction to process it in the removed workflow

#### Scenario: Finance opens an unrelated dispute
- **WHEN** an authorized operator opens a financial dispute not originating from the retired cancellation/rescheduling workflow
- **THEN** the existing dispute handling capability SHALL remain available under its existing authorization and financial rules
