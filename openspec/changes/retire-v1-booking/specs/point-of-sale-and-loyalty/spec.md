## MODIFIED Requirements

### Requirement: Versioned tenant memberships and loyalty
Memberships SHALL be tenant-scoped and benefits/version/validity preserved for earned periods even when plans stop selling. Booking and POS SHALL use the same authoritative entitlements and combination restrictions. Qualifying completed identified-customer POS transactions SHALL earn points under active base earn rules plus applicable disclosed membership multipliers. Redemption SHALL bind to a permitted benefit and source transaction, reserve available unexpired points while payment is pending, and consume them atomically at confirmation. Redemption SHALL bind a point debit to a validated source transaction and a persisted permitted benefit atomically; a standalone debit without that benefit MUST NOT succeed. The accepted redemption SHALL preserve the conversion policy version, points spent and monetary benefit so later rule changes cannot alter the recorded transaction. Expiry/reversal SHALL be explicit auditable events, never mutable ledger edits; retries and concurrent spending MUST NOT overdraw points.

#### Scenario: Membership plan changes
- **WHEN** a membership plan is revised or retired
- **THEN** current members SHALL retain earned snapshot benefits through the agreed validity period

#### Scenario: Configured multiplier
- **WHEN** a qualifying POS transaction uses a published member earn multiplier
- **THEN** earned points SHALL follow that multiplier once, not an unrelated newest rule

#### Scenario: Expired points
- **WHEN** a customer previews or spends points after expiry
- **THEN** available balance SHALL exclude expired lots and retries SHALL not duplicate expiry or redemption

#### Scenario: Payment rollback
- **WHEN** payment/benefit persistence fails after points were reserved
- **THEN** no irrevocable point debit or orphan discount SHALL remain

#### Scenario: Customer redeems loyalty points
- **WHEN** an eligible customer redeems unexpired tenant points in a valid paid or zero-payable transaction
- **THEN** the permitted benefit and single point consumption SHALL commit together with source/version; unpaid hold reservations SHALL not be presented as irreversible consumption

#### Scenario: Benefit persistence fails
- **WHEN** storing the source transaction's loyalty benefit fails after redemption processing begins
- **THEN** the operation SHALL roll back the point debit and all associated transaction changes

#### Scenario: Legacy debit-only request
- **WHEN** a client submits `/api/loyalty/redeem` with arbitrary `source_type/source_id` and no supported validated checkout context
- **THEN** the API SHALL reject the request with an actionable error without changing points or order totals
