## MODIFIED Requirements

### Requirement: Settlement and payout lifecycle
Settlement/payout SHALL remain limited to reconciled pre-cutover platform-held obligations. New direct-owner revenue MUST NOT generate platform payout eligibility. Legacy payouts SHALL retain immutable snapshots, D+2 eligibility, 500000 VND configured minimum, idempotent submission and draft/approved/submitted/paid/failed/reversed evidence until settled. This change SHALL not submit real payouts or erase unresolved liabilities automatically. Finance interfaces whose APIs require the legacy payout capability (payouts and withdrawals, settlements, platform-fee and commission summaries, reconciliation alerts and finance exceptions) SHALL appear only where that capability is enabled; elsewhere they SHALL be absent from navigation, and a direct link SHALL report the capability as unavailable rather than show empty success.

#### Scenario: Direct booking period
- **WHEN** all period revenue was paid directly to owners
- **THEN** no platform settlement payable or payout SHALL be created

#### Scenario: Eligible weekly payout is approved
- **WHEN** authorized finance handles a verified historical payable meeting thresholds
- **THEN** its existing payout lifecycle SHALL remain available and audited

#### Scenario: Submitted payout fails
- **WHEN** a submitted historical payout fails
- **THEN** history SHALL remain and an exception SHALL track resolution

#### Scenario: Direct-only cohort opens the console
- **WHEN** an owner or administrator works in an organization where the legacy payout capability is disabled
- **THEN** no payout, withdrawal, settlement, fee, reconciliation or finance-exception navigation SHALL appear, and a direct link SHALL show the capability as unavailable
