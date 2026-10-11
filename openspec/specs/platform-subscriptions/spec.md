## Purpose

Manage the platform service plans purchased by partner organizations, with verified activation, bounded trials and enforceable entitlements independent of customer membership products.

## Requirements

### Requirement: Versioned platform plan and fee consent
The platform SHALL manage service plans distinct from customer memberships. Authorized platform administrators SHALL create, revise, publish and retire versioned plans with configured 1-, 6- and 12-calendar-month terms, prices, currency, disclosed applicable taxes and actual provider fees, venue/court/staff quotas, features and renewal/change/refund terms. Subscriptions SHALL remain the sole platform service-charge model without transaction commission. The configured 1-, 6- and 12-calendar-month terms SHALL be billing cycles of that same subscription model. The platform MUST NOT offer commission-based access or charge a platform percentage or replacement transaction surcharge on booking, tournament registration or marketplace transfer activity. Provider fees MUST be identified separately from the platform subscription price and MUST NOT be presented as platform commission. Selection/payment SHALL snapshot accepted price and terms. Unsupported or incomplete commercial configurations SHALL not activate; upgrades/downgrades SHALL disclose effective date, price and quota consequences before commitment. Existing trial, verified activation, renewal, expiry and entitlement rules SHALL remain applicable.

#### Scenario: Plan changes after selection
- **WHEN** the catalog changes after payment initiation
- **THEN** the original attempt SHALL retain accepted amounts/terms and period computation

#### Scenario: Six month term
- **WHEN** a six-calendar-month payment is verified
- **THEN** the period SHALL extend by six calendar months with documented end-of-month handling, not 30 days

#### Scenario: Unsupported plan change
- **WHEN** a downgrade exceeds target quotas or lacks a disclosed effective-date policy
- **THEN** the platform SHALL reject or schedule an explicitly agreed transition without silent entitlement loss

#### Scenario: Published catalog
- **WHEN** an administrator retires a plan
- **THEN** new purchase SHALL stop while purchased periods and evidence remain accessible

#### Scenario: One commercial model with configured billing terms
- **WHEN** a partner reviews platform pricing or selects a plan
- **THEN** the platform SHALL present configured 1-, 6- or 12-calendar-month subscription options without a commission-based alternative or transaction-volume percentage charge

#### Scenario: Subscription fee is not deducted from booking proceeds
- **WHEN** a partner purchases or renews a plan
- **THEN** payment SHALL use the existing verified subscription collection flow without deducting the subscription price or an additional platform fee from booking settlements

#### Scenario: Provider fees remain separate
- **WHEN** a supported payment incurs an actual provider fee
- **THEN** applicable disclosures SHALL distinguish that fee from subscription charges without introducing a new platform transaction charge

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

### Requirement: Subscription contract and enforcement completeness
Plan selection, trial, payment, status, renewal and plan changes SHALL complete end-to-end using matching API paths, payloads and response semantics. Platform-receiver receipts SHALL be verified server-side before activation, not accepted from arbitrary authenticated client fields. Venue, court and employee creation and updates SHALL enforce quotas atomically; business feature use SHALL require role, venue grant, plan feature and rollout availability independently. Expired plans SHALL block new business while preserving prior obligations.

#### Scenario: Browser claim
- **WHEN** a client submits matching reference/amount without verified provider evidence
- **THEN** subscription SHALL remain pending and no active period SHALL be granted

#### Scenario: Staff and court quota race
- **WHEN** concurrent additions would exceed a plan limit
- **THEN** only allowed creations SHALL commit

#### Scenario: Plan unavailable feature
- **WHEN** an owner with correct role but no plan entitlement calls pricing activation or gated business feature
- **THEN** the server SHALL deny it and the UI SHALL explain the actual missing entitlement
