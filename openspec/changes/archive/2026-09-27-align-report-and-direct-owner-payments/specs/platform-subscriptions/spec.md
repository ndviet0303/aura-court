## MODIFIED Requirements

### Requirement: Versioned platform plan and fee consent
The platform SHALL manage service plans distinct from customer memberships. Authorized platform administrators SHALL create, revise, publish and retire versioned plans with configured 1-, 6- and 12-calendar-month terms, prices, currency, disclosed taxes/provider fees, venue/court/staff quotas, features and renewal/change/refund terms. Subscriptions SHALL remain the sole platform service-charge model without transaction commission. Selection/payment SHALL snapshot accepted price and terms. Unsupported or incomplete commercial configurations SHALL not activate; upgrades/downgrades SHALL disclose effective date, price and quota consequences before commitment.

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

## ADDED Requirements

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
