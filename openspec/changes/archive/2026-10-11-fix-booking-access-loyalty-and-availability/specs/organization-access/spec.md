## ADDED Requirements

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
