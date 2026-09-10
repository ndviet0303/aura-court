## ADDED Requirements

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
