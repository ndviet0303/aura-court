## ADDED Requirements

### Requirement: Partner entry independent of personal onboarding
The platform SHALL offer partner registration to eligible existing customers as well as newly onboarded users. Selecting owner intent during personal onboarding MUST NOT grant ownership, replace customer identity or create active business inventory. Completing personal onboarding SHALL NOT prevent later partner registration.

#### Scenario: Existing customer becomes an applicant
- **WHEN** a customer who previously completed onboarding chooses to become a partner
- **THEN** the platform SHALL open their application journey without requiring a new account or repeating personal onboarding

#### Scenario: Legacy owner intent request
- **WHEN** a client submits `court_owner` to the personal onboarding endpoint
- **THEN** the platform SHALL NOT create owner privileges, an organization or active courts and SHALL provide the partner-registration next step
