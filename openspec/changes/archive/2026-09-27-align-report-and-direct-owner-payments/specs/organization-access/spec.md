## MODIFIED Requirements

### Requirement: Role-based owner operations
Organization owners and authorized employees SHALL access assigned venues and functions including catalog, calendars, pricing, employees, POS, memberships, promotions, reports and owner refund obligations through both UI and API. Permissions SHALL be the intersection of active organization membership, venue assignment, function grant and effective feature entitlement. Cashier is an employee permission profile, not platform administration. Platform roles MUST NOT be granted through employee invitations. Legacy payouts SHALL be available only to separately authorized finance actors for pre-cutover obligations.

#### Scenario: Venue manager changes venue operations
- **WHEN** a manager operates one assigned venue in a multi-venue organization
- **THEN** permitted actions SHALL work there while sibling venue identifiers and exports are denied

#### Scenario: Owner without global admin
- **WHEN** an approved customer-role owner opens their management screens
- **THEN** authorized organization actions SHALL work without granting platform_admin or legacy admin

#### Scenario: Cashier requests payout administration
- **WHEN** a cashier attempts legacy payout or organization administration
- **THEN** the action SHALL be denied without mutations

#### Scenario: Organization switch
- **WHEN** the user switches organization or a grant is revoked
- **THEN** cached UI permissions and direct operations SHALL reflect current grants without hardcoded organization identifiers

## ADDED Requirements

### Requirement: Real staff invitation and permission lifecycle
Invitations SHALL identify organization, venue assignments, role and expiry, create a pending invitation, send a persisted notification and activate membership only after the intended recipient accepts. Duplicate accepts SHALL be idempotent; revocation/expiry SHALL prevent activation. Owners SHALL manage grants without creating global admins, exceeding staff quotas or removing the final active owner. API/UI MUST preserve selected venue assignments and audit actor, reason and outcome.

#### Scenario: Invite accepted
- **WHEN** the addressed user accepts a valid invitation twice
- **THEN** exactly one scoped membership SHALL become active and notification/UI SHALL reflect actual delivery and acceptance

#### Scenario: Scope escalation
- **WHEN** an owner invites an unknown email as an organization owner
- **THEN** the resulting personal account SHALL NOT acquire global admin rights

#### Scenario: Last owner and quota
- **WHEN** a mutation would exceed staff quota or suspend/remove the final active owner
- **THEN** it SHALL fail atomically
