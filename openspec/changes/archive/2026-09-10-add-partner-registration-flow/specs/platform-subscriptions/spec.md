## Purpose

Manage the platform service plans purchased by partner organizations, with verified activation, bounded trials and enforceable entitlements independent of customer membership products.

## ADDED Requirements

### Requirement: Versioned platform plan and fee consent
The platform SHALL provide organization-scoped service plans distinct from customer memberships. Before commitment it MUST disclose price, currency, billing cycle, taxes/surcharges, transaction/provider fees, venue/court/staff quotas, features and renewal/change/cancellation/refund terms. Purchase SHALL retain the selected plan and accepted terms versions. Missing commercial configuration MUST prevent activation.

#### Scenario: Plan changes after selection
- **WHEN** the published plan changes after a payment attempt has been created
- **THEN** the attempt SHALL retain its accepted price/terms snapshot and the organization SHALL NOT be silently charged different terms

#### Scenario: Unsupported plan change
- **WHEN** an owner requests an unsupported mid-cycle downgrade or automatic renewal
- **THEN** the platform SHALL disclose the supported operation without applying an unagreed charge or entitlement change

### Requirement: Verified and idempotent paid activation
Paid subscriptions SHALL activate only after server-side verification of a successful provider outcome matching payment reference, subscription, amount and currency. Unknown outcomes SHALL remain pending verification; failed attempts SHALL allow retry without granting entitlement. Duplicate, delayed or out-of-order events MUST NOT duplicate activation, receipts or financial records, or downgrade a completed success.

#### Scenario: Browser success without provider confirmation
- **WHEN** an owner returns from checkout with an unverified success parameter
- **THEN** the UI SHALL show persisted pending status and the subscription SHALL NOT activate

#### Scenario: Mismatched or duplicate confirmation
- **WHEN** a confirmation has an incorrect amount/currency or repeats a processed provider event
- **THEN** mismatches SHALL NOT activate the subscription and valid repeats SHALL produce no additional activation or charge record

#### Scenario: Late confirmed payment
- **WHEN** a timed-out attempt later receives verified success
- **THEN** the platform SHALL reconcile that attempt exactly once, persist its receipt and period, and expose the resulting status

### Requirement: Trial eligibility and entitlement enforcement
The platform SHALL activate trial access only according to explicit configured eligibility and duration, record start/end times and prevent repeated activation from resetting the period. Effective features and quotas SHALL be enforced server-side with existing authorization and platform availability constraints; a subscription MUST NOT grant additional tenant roles.

#### Scenario: Trial replay
- **WHEN** trial activation is retried or the owner switches plans to request another trial
- **THEN** the original trial period SHALL NOT restart or extend beyond policy

#### Scenario: Quota race
- **WHEN** concurrent resource creations would exceed a plan's venue, court or staff limit
- **THEN** only operations within the limit SHALL succeed and rejected operations SHALL not leave partial resources

### Requirement: Expiry and outstanding obligations
The platform SHALL expose trial, pending payment, active, payment failed, past due, suspended, expired and cancelled lifecycle outcomes. It MUST notify owners about activation, payment failures and approaching expiry, stop new bookings once entitlement is invalid, and preserve required access to outstanding obligations. Renewal payment failure MUST NOT invalidate an already-paid unexpired period. Term-end cancellation SHALL stop future renewal while retaining purchased access until its end.

#### Scenario: Expiry without scheduler execution
- **WHEN** a new booking request arrives after the entitlement end time before an expiry job runs
- **THEN** it SHALL be rejected, and existing booking/payment/refund records SHALL remain accessible under authorization

#### Scenario: Renewing an expired subscription
- **WHEN** renewal is verified for an expired subscription
- **THEN** its new period SHALL be recorded once and new bookings SHALL resume only if partner, venue and other eligibility conditions also hold

#### Scenario: Renewal fails before current period ends
- **WHEN** renewal collection fails while current paid access remains valid
- **THEN** the owner SHALL see the failed attempt and retain existing entitlement until its recorded end
