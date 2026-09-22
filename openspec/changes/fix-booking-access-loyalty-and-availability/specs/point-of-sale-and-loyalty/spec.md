## MODIFIED Requirements

### Requirement: Versioned tenant memberships and loyalty
The platform SHALL provide tenant-scoped memberships whose benefits are versioned and preserved for already-earned entitlement periods. It SHALL award and redeem loyalty points only under the tenant's active rules and SHALL record the source and use of every point change. Redemption SHALL bind a point debit to a validated source transaction and a persisted permitted benefit atomically; a standalone debit without that benefit MUST NOT succeed. The accepted redemption SHALL preserve the conversion policy version, points spent and monetary benefit so later rule changes cannot alter the recorded transaction.

#### Scenario: Membership plan changes
- **WHEN** a tenant publishes a new version of the membership benefit
- **THEN** existing active membership entitlements SHALL retain the benefit version assigned to them

#### Scenario: Customer redeems loyalty points
- **WHEN** a customer meets a tenant rule for loyalty redemption
- **THEN** the platform SHALL apply the permitted benefit and record the point debit with its source transaction in one atomic outcome

#### Scenario: Benefit persistence fails
- **WHEN** storing the source transaction's loyalty benefit fails after redemption processing begins
- **THEN** the operation SHALL roll back the point debit and all associated transaction changes

#### Scenario: Legacy debit-only request
- **WHEN** a client submits `/api/v2/loyalty/redeem` with arbitrary `source_type/source_id` and no supported validated checkout context
- **THEN** the API SHALL reject the request with an actionable error without changing points or order totals

## ADDED Requirements

### Requirement: Authoritative loyalty conversion and preview
For ordinary customer booking checkout, the platform SHALL return the same-organization available points, applicable redemption policy, permitted point amount, monetary reduction and remaining amount payable. Preview SHALL have no effect on point balances, coupon usage, wallets or booked inventory. Conversion SHALL use the active base redemption policy's exact points-to-VND ratio, round down once to whole VND and preserve that ratio in the accepted result; earn multipliers MUST NOT replace a redemption policy. An accepted redemption MUST use positive integer points, produce a positive benefit, remain within the available balance and not exceed the amount remaining after applicable coupon/membership discounts. The server SHALL apply existing promotion restrictions before loyalty reduction and SHALL reject an explicitly prohibited combination. Invalid or ambiguous policy data SHALL cause an actionable failure rather than an invented conversion rate.

#### Scenario: Preview does not spend points
- **WHEN** an eligible customer previews 100 points under a policy of 100 points for 5,000 VND on a 200,000 VND payable amount
- **THEN** the preview SHALL show a 5,000 VND loyalty reduction and 195,000 VND remaining without changing any persisted balance or inventory

#### Scenario: Excessive or ineffective redemption
- **WHEN** requested points exceed the balance, produce zero VND after rounding, or imply a discount greater than the remaining payable amount
- **THEN** the operation SHALL reject the selection and return localized guidance without consuming points

#### Scenario: Policy changes after preview
- **WHEN** the displayed redemption policy becomes inactive or its version changes before confirmation
- **THEN** checkout SHALL reject the stale redemption and request a refreshed preview without silently changing the promised benefit or spending points

#### Scenario: Earn multiplier is newer than redemption policy
- **WHEN** an organization publishes an earn multiplier after its base redemption policy
- **THEN** preview and checkout SHALL still use the active base redemption policy's conversion ratio

### Requirement: Source-bound idempotent redemption
A confirmed point redemption SHALL belong to the authenticated beneficiary and the same organization as its source checkout. Retries of the same logical checkout SHALL return its existing outcome without a second debit or discount; conflicting reuse SHALL fail. Concurrent checkouts sharing a balance MUST NOT overdraw points. Invalid, paid, expired or foreign source transactions SHALL NOT be retroactively discounted through a redemption request.

#### Scenario: Response is lost after commit
- **WHEN** the customer retries the same logical point-funded checkout after losing the response
- **THEN** the same booking and redemption result SHALL be returned without another point or wallet debit

#### Scenario: Two checkouts compete for points
- **WHEN** two concurrent checkouts request points from a balance sufficient for only one
- **THEN** at most one SHALL consume those points, the other SHALL fail without financial or inventory effects, and the balance SHALL remain nonnegative

#### Scenario: Foreign or already-paid source
- **WHEN** a new redemption request, rather than a valid replay of an accepted checkout, references another customer or organization's transaction, a nonexistent source, or a completed/expired transaction
- **THEN** it SHALL fail without changing that source or any point balance
