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
The platform SHALL authorize organization-scoped management of organization profile, venues, courts, operating hours, closures, pricing, employees, POS catalog, memberships, coupons, reports, and payouts according to a documented role-permission matrix. Cashier and staff permissions MUST NOT grant owner-level employee, payout, or organization-administration access.

#### Scenario: Venue manager changes venue operations
- **WHEN** a venue manager accesses an assigned venue's operational settings
- **THEN** the platform SHALL allow only the venue-management actions granted by the role matrix

#### Scenario: Cashier requests payout administration
- **WHEN** a cashier requests a payout-management operation
- **THEN** the platform SHALL deny the operation

### Requirement: Audited elevated access
Platform administration that accesses tenant financial, KYC, or operational data SHALL require an explicitly authorized elevated path and SHALL create an immutable audit record identifying the actor, purpose, affected organization, and outcome. Elevated access MUST NOT bypass the requirement to preserve tenant-scoped auditability.

#### Scenario: Platform finance administrator resolves an exception
- **WHEN** an authorized platform finance administrator opens a tenant payout exception
- **THEN** the platform SHALL record the elevated access and the resolution action in the audit trail
