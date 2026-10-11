## ADDED Requirements

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
