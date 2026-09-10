## Purpose

Provide a resumable, evidence-based partner application journey that grants business ownership only after authorized review while retaining customer identity and application history.

## Requirements

### Requirement: Eligible and unique application initiation
The platform SHALL allow an authenticated, active, unrestricted user with verified email or phone and recorded partner/data-policy consent to start a draft only if the user is not already a recognized owner. The platform MUST maintain at most one current draft or in-flight application per user and resume that record rather than duplicate it. Eligibility MUST be rechecked on submission.

#### Scenario: Unverified or restricted account
- **WHEN** an unverified, locked, suspended or restricted user attempts to start or submit an application
- **THEN** the platform SHALL reject the operation with verification or support guidance and SHALL NOT grant owner access

#### Scenario: Concurrent starts
- **WHEN** an eligible user starts registration twice concurrently
- **THEN** both results SHALL resolve to the same current application without duplicate drafts

#### Scenario: Existing owner
- **WHEN** a recognized owner opens partner registration
- **THEN** the platform SHALL direct them to their organization context without creating a new partner application

### Requirement: Resumable business information and private evidence
The platform SHALL persist incomplete drafts and conditionally require business identity/type, registration or tax identifier, representative, contact, registered address, intended sports and bank details before submission. Evidence MUST include representative identity, applicable business registration, facility operation rights, bank verification and authorization when the applicant is not the legal representative. Optional brand and invoice details SHALL be supported. Required evidence SHALL follow an explicit business-type policy.

#### Scenario: Resume an incomplete draft
- **WHEN** an applicant saves partial information and returns in a later session
- **THEN** the saved information and missing requirements SHALL be displayed without creating a tenant or public venue

#### Scenario: Invalid or unauthorized evidence
- **WHEN** an upload violates configured file type, size or count limits, or a user requests another applicant's document
- **THEN** the platform SHALL reject the operation without exposing private document content or treating the evidence as complete

#### Scenario: Duplicate business identity
- **WHEN** declared business information matches a potentially conflicting application or partner
- **THEN** ordinary submission SHALL be paused for platform investigation without revealing another party's private data

### Requirement: Versioned submission and correction loop
The platform SHALL accept submission only with all required data/evidence and versioned acceptance of cooperation, fees, settlement/refund, data handling and service responsibility terms. Submission SHALL record a snapshot and time, move the application from `draft` or `revision_required` to `pending`, prevent changes to reviewed content, and issue a receipt notification. A completeness check MUST NOT be presented as proof of legal authenticity.

#### Scenario: Missing requirements
- **WHEN** an applicant submits incomplete information or consent
- **THEN** the platform SHALL return field-level missing requirements, preserve the editable record and not queue review

#### Scenario: Edit during review
- **WHEN** an applicant attempts to modify a pending submission
- **THEN** the platform SHALL reject the mutation and retain the submitted version

#### Scenario: Supplement and resubmit
- **WHEN** a reviewer requests specified corrections and the applicant completes them
- **THEN** the application SHALL move through `revision_required` back to `pending`, retaining prior submissions and review reasons

### Requirement: Authorized and atomic review
Only authorized platform reviewers SHALL approve, reject or request correction of a pending application. Rejection/correction MUST record reasons, reviewer identity, time and reapplication eligibility. Approval MUST atomically recognize the partner, create its organization and grant organization-owner membership once, preserving customer access. Review and notification history MUST remain auditable.

#### Scenario: Approval retry
- **WHEN** an identical approval is retried after success
- **THEN** the recorded outcome SHALL be returned with exactly one organization and owner grant

#### Scenario: Conflicting reviews or failed provisioning
- **WHEN** a reviewer acts on a stale version or organization provisioning cannot complete
- **THEN** the platform SHALL NOT persist a partial approval or duplicate grant and SHALL report the conflict or failure

#### Scenario: Rejected application
- **WHEN** an application is rejected
- **THEN** the applicant SHALL see the reason and whether reapplication is allowed; history SHALL remain immutable and a new linked application SHALL only be permitted by that policy

### Requirement: Partner suspension and restoration
The platform SHALL track post-approval partner status independently from historical application decisions, support suspension and termination with reasons and notification, and retain an audited explanation/support path. Suspension or termination MUST stop new bookings at the partner's venues without deleting records. Restoration from suspension SHALL require resolved causes and fresh eligibility checks; termination SHALL NOT be silently reversed.

#### Scenario: Suspended partner with future bookings
- **WHEN** a partner is suspended with outstanding confirmed bookings
- **THEN** new bookings SHALL be refused while authorized users retain the access necessary for those bookings, payments and refunds

#### Scenario: Restoration with expired plan
- **WHEN** suspension is lifted but the subscription is expired
- **THEN** partner status SHALL be restored without automatically enabling new bookings
