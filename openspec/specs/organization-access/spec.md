# Organization Access Specification

## Purpose

Provides organization-scoped access and operations so multiple court operators can
use the platform without disclosing or modifying one another's data.

## Requirements

### Requirement: Organization and membership model
The platform SHALL treat an Organization as the tenant boundary. An organization owner MAY operate multiple venues, and a user MAY hold separate memberships in multiple organizations. Membership roles MUST be `organization_owner`, `venue_manager`, `cashier`, `staff`, or `customer`; `platform_admin` MUST be a platform-scoped role rather than an organization membership.

#### Scenario: Owner operates multiple venues
- **WHEN** an organization owner is assigned to an organization with multiple venues
- **THEN** the owner SHALL be authorized to manage each venue permitted by that organization

#### Scenario: User joins a second organization
- **WHEN** a user receives a membership in a second organization
- **THEN** permissions in the second organization SHALL not alter permissions in the first

### Requirement: Tenant isolation
Every tenant-owned API response, export, cache entry, asynchronous job, and realtime channel SHALL be scoped to exactly one organization. A principal MUST NOT read, change, subscribe to, or infer tenant-owned data outside its authorized organization, including through identifiers supplied in a request.

#### Scenario: Cross-tenant identifier is supplied
- **WHEN** an authenticated organization member requests a resource belonging to another organization
- **THEN** the platform SHALL deny the request without returning that resource's data

#### Scenario: Background work handles tenant data
- **WHEN** an asynchronous operation processes a tenant-owned record
- **THEN** its resulting data, notification, cache entry, and event SHALL remain scoped to that record's organization

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

### Requirement: Audited elevated access
Platform administration that accesses tenant financial, KYC, or operational data SHALL require an explicitly authorized elevated path and SHALL create an immutable audit record identifying the actor, purpose, affected organization, and outcome. Elevated access MUST NOT bypass the requirement to preserve tenant-scoped auditability.

#### Scenario: Platform finance administrator resolves an exception
- **WHEN** an authorized platform finance administrator opens a tenant payout exception
- **THEN** the platform SHALL record the elevated access and the resolution action in the audit trail

### Requirement: Reviewed ownership provisioning
Self-service partner ownership SHALL be granted only by approved application review and SHALL be scoped to the resulting organization. The grant MUST preserve the same user's customer rights and existing memberships and MUST NOT imply platform administrator privileges.

#### Scenario: Applicant tries owner API before approval
- **WHEN** a pending applicant calls an organization administration endpoint
- **THEN** the platform SHALL deny access unless an independently valid membership already authorizes that operation

#### Scenario: Approved owner continues as customer
- **WHEN** a newly approved owner opens customer booking or another existing organization context
- **THEN** their customer functions and independently assigned memberships SHALL remain available without cross-tenant privilege expansion

### Requirement: Restricted operations preserve settlement access
Partner suspension or subscription ineligibility SHALL remove new-business permissions without blanket removal of authorized access to existing bookings, payments, refunds and settlement obligations. This exception MUST NOT permit new bookings or unauthorized organization/financial administration.

#### Scenario: Staff handles a prior booking
- **WHEN** a staff member authorized for a venue handles an existing booking after partner suspension
- **THEN** permitted obligation-handling actions SHALL remain available but new bookings and owner-only actions SHALL be denied

### Requirement: Audited legacy onboarding migration
Migration of existing owners and venues SHALL preserve identity, memberships, historical bookings and financial references. It MUST use explicit reviewed partner/publication/entitlement mappings before admitting an organization to new enforcement, without fabricated approvals, blanket free entitlement or silent deactivation.

#### Scenario: Existing owner has no verification evidence
- **WHEN** rollout inventory finds an owner created by legacy onboarding without review evidence
- **THEN** the organization SHALL be reported for reconciliation, not silently marked verified or placed into the enforced cohort with invalid entitlement

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

### Requirement: Organization and POS interface access follows scoped roles
Users with an active authorized organization role SHALL be able to reach the corresponding organization and POS interfaces without requiring platform administrator status. Navigation and route access SHALL follow the existing role-permission matrix and backend authorization, including feature availability. Admitting an organization operator to one page MUST NOT grant access to unrelated platform administration pages or another organization's resources.

#### Scenario: Owner uses their organization and POS
- **WHEN** an active organization owner opens their organization page or POS and the required capability is enabled
- **THEN** the interface SHALL allow entry and the authorized operations SHALL succeed within that organization without assigning platform administrator privileges

#### Scenario: Cashier attempts owner administration
- **WHEN** a cashier with POS access follows a direct link to an owner-only or platform-only page
- **THEN** both the interface and direct privileged API requests SHALL deny the unauthorized operation

### Requirement: Explicit organization POS account provisioning
Provisioning or upgrading an account for organization/POS operation SHALL require an explicitly identified environment, account and organization. The operation SHALL preserve unrelated memberships, existing credentials unless a reset was explicitly requested, and existing feature cohorts. It SHALL grant only the approved role and enable only the approved organization capability. The resulting access and cross-tenant denial SHALL be verified and the operator, target and outcome SHALL be auditable. Missing operational target information MUST NOT be replaced with the first organization or a default account.

#### Scenario: Confirmed account receives organization access
- **WHEN** an authorized operator provisions the confirmed account as owner of the confirmed organization
- **THEN** that account SHALL receive the scoped active membership and confirmed capability configuration while unrelated accounts, organizations and platform roles remain unchanged

#### Scenario: Target information is missing
- **WHEN** provisioning is requested without an account, organization or environment being identified
- **THEN** the provisioning operation SHALL pause for target confirmation without changing users, credentials, memberships or feature configuration

#### Scenario: Role exists but capability is disabled
- **WHEN** the account has the required organization role but POS is not enabled for that organization
- **THEN** verification SHALL report capability-disabled separately from missing-role and SHALL NOT bypass the feature gate or claim that provisioning is fully ready
