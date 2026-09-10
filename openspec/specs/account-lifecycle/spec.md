## Purpose

Provides complete persisted account and profile journeys with role-aware access to authorized organization capabilities.

## Requirements

### Requirement: Account lifecycle
The platform SHALL support registration, login/logout, email verification, password change and single-use password reset; expired or invalid credentials SHALL fail without changing the account.

#### Scenario: Verified account journey
- **WHEN** a user registers, verifies email, logs in and logs out
- **THEN** verification persists and the logged-out credential cannot authorize protected requests

#### Scenario: Reset replay
- **WHEN** a consumed or expired reset link is submitted
- **THEN** the password remains unchanged and an actionable error is returned

### Requirement: Profile and role-aware access
The platform SHALL persist profile edits and expose only operations authorized by the existing organization role matrix; changing active organization SHALL refresh permissions without exposing another tenant's data.

#### Scenario: Organization switch
- **WHEN** a cashier switches to an organization where they are a customer
- **THEN** cashier actions are unavailable and direct privileged requests are rejected

#### Scenario: Profile reload
- **WHEN** a user saves valid profile fields and reloads
- **THEN** the saved values are returned from the account record

### Requirement: Partner entry independent of personal onboarding
The platform SHALL offer partner registration to eligible existing customers as well as newly onboarded users. Selecting owner intent during personal onboarding MUST NOT grant ownership, replace customer identity or create active business inventory. Completing personal onboarding SHALL NOT prevent later partner registration.

#### Scenario: Existing customer becomes an applicant
- **WHEN** a customer who previously completed onboarding chooses to become a partner
- **THEN** the platform SHALL open their application journey without requiring a new account or repeating personal onboarding

#### Scenario: Legacy owner intent request
- **WHEN** a client submits `court_owner` to the personal onboarding endpoint
- **THEN** the platform SHALL NOT create owner privileges, an organization or active courts and SHALL provide the partner-registration next step
