## MODIFIED Requirements

### Requirement: Versioned platform plan and fee consent
The platform SHALL provide organization-scoped service plans distinct from customer memberships. Monthly and yearly billing SHALL be cycles of the same subscription model, the sole model for platform service charges. The platform MUST NOT offer commission-based access or charge a platform percentage or replacement transaction surcharge on booking, tournament registration or marketplace transfer activity. Before commitment it MUST disclose price, currency, billing cycle, applicable taxes and actual provider fees, venue/court/staff quotas, features and renewal/change/cancellation/refund terms. Provider fees MUST be identified separately from the platform subscription price and MUST NOT be presented as platform commission. Purchase SHALL retain the selected plan and accepted terms versions. Missing commercial configuration MUST prevent activation. Existing trial, verified activation, renewal, expiry and entitlement rules SHALL remain applicable.

#### Scenario: Plan changes after selection
- **WHEN** the published plan changes after a payment attempt has been created
- **THEN** the attempt SHALL retain its accepted price/terms snapshot and the organization SHALL NOT be silently charged different terms

#### Scenario: Unsupported plan change
- **WHEN** an owner requests an unsupported mid-cycle downgrade or automatic renewal
- **THEN** the platform SHALL disclose the supported operation without applying an unagreed charge or entitlement change

#### Scenario: One commercial model with two billing cycles
- **WHEN** a partner reviews platform pricing or selects a plan
- **THEN** the platform SHALL present configured monthly/yearly subscription options without a commission-based alternative or transaction-volume percentage charge

#### Scenario: Subscription fee is not deducted from booking proceeds
- **WHEN** a partner purchases or renews a plan
- **THEN** payment SHALL use the existing verified subscription collection flow without deducting the subscription price or an additional platform fee from booking settlements

#### Scenario: Provider fees remain separate
- **WHEN** a supported payment incurs an actual provider fee
- **THEN** applicable disclosures SHALL distinguish that fee from subscription charges without introducing a new platform transaction charge
