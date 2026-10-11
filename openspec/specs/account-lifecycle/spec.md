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

### Requirement: Consistent account eligibility at every entry point
Restricted accounts SHALL be denied login via password and linked Google/Facebook identity and SHALL be denied new booking, recurrence, transfer and community mutations through existing sessions. Email verification SHALL be required for customer-originated V1/V2 bookings. A nonempty phone field MUST NOT count as verified; partner initiation/submission SHALL require verified email or actual phone-verification evidence. Staff-assisted walk-ins SHALL record the authorized actor and identified customer under the same documented eligibility policy.

#### Scenario: Restricted existing session
- **WHEN** an account is locked after login and attempts a new booking through either API version
- **THEN** the request SHALL fail without payment, inventory or point effects

#### Scenario: Unverified V2 customer
- **WHEN** an unverified customer submits checkout or a recurring series directly
- **THEN** creation SHALL fail with verification guidance

#### Scenario: Phone merely entered
- **WHEN** an applicant with unverified email enters a phone number without verification evidence
- **THEN** partner submission SHALL remain unavailable

### Requirement: Authenticated visitors leave guest-only authentication pages
The application SHALL resolve the current session before displaying `/login` or `/register`. An authenticated user SHALL leave either page using the same destination rules as successful login: incomplete onboarding first, then an authorized internal redirect when applicable, then the existing role-appropriate default. Redirects MUST NOT point outside the application, back to a guest-only authentication page, or to a page the user cannot access. Anonymous users SHALL retain access to both forms. Password reset and verification journeys SHALL remain independently accessible according to their existing contracts.

#### Scenario: Direct navigation with a valid session
- **WHEN** an onboarded user with a valid session enters `/login` or `/register` directly or reloads that URL
- **THEN** the application SHALL restore the session and redirect without showing an interactive authentication form or creating a navigation loop

#### Scenario: Onboarding is incomplete
- **WHEN** an authenticated user who has not completed onboarding enters a guest-only authentication page
- **THEN** the application SHALL take that user to onboarding rather than an administrative or customer destination

#### Scenario: Unsafe or unauthorized redirect
- **WHEN** a guest-only authentication URL contains an external, circular or unauthorized redirect target
- **THEN** the application SHALL ignore that target and use the user's authorized default destination

#### Scenario: Expired session
- **WHEN** the session is no longer valid and a visitor enters `/login`
- **THEN** the login form SHALL remain usable without redirecting the visitor as an authenticated user
