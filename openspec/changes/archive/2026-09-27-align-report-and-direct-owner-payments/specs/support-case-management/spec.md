## Purpose

Provide an end-to-end, evidence-based support and dispute process with bounded visibility, accountable decisions and verifiable follow-through for affected users.

## ADDED Requirements

### Requirement: Case submission and evidence lifecycle
Customers, assigned staff and owners SHALL submit cases referencing permitted bookings, payments, transfers, activities or content. Intake SHALL verify scope/deadline, classify type/severity, identify parties, link duplicate incidents and request missing evidence with deadlines. Evidence and explanations SHALL be private, versioned, access-controlled and retained under a configured retention policy; parties SHALL be able to respond before adverse decisions except audited urgent protective action.

#### Scenario: Incomplete complaint
- **WHEN** a party submits an in-scope complaint missing necessary evidence
- **THEN** the case SHALL persist as awaiting evidence with requested fields, due date and notification rather than a fabricated resolution

#### Scenario: Foreign case
- **WHEN** a principal supplies a case or evidence identifier outside permitted organization/party/reviewer scope
- **THEN** list/detail/export/download/mutation SHALL deny access without disclosing case data

#### Scenario: Duplicate or late complaint
- **WHEN** intake identifies the same incident or an expired filing window
- **THEN** the case SHALL be linked or rejected with an auditable reason; serious exceptions require authorized review

### Requirement: Decision execution and reconsideration
Only authorized platform reviewers with recorded purpose SHALL decide cases; conflicts of interest SHALL require reassignment. Decisions SHALL cite evidence and applicable policy, support acceptance/partial acceptance/dismissal/further investigation, retain earlier decisions and notify permitted parties. Execution tasks for content, reputation, competition corrections and owner refunds SHALL carry responsible actor, due date and evidence. A case SHALL close only after completion or a reasoned no-action decision; new material evidence SHALL allow controlled reconsideration without deleting earlier evidence.

#### Scenario: Refund judgment
- **WHEN** a reviewer grants an owner-paid refund
- **THEN** a linked refund task SHALL be created and tracked, but case/UI SHALL NOT assert payment completion before financial evidence

#### Scenario: Reopening
- **WHEN** a party supplies accepted new material evidence for a closed case
- **THEN** a new review version SHALL preserve previous decisions and audit records

#### Scenario: Execution incomplete
- **WHEN** the reviewer requests closure while required actions are outstanding
- **THEN** closure SHALL be refused or an explicitly authorized exception recorded, with overdue escalation

#### Scenario: Reported content is lawful
- **WHEN** a reviewer dismisses a report or restores wrongly hidden content
- **THEN** the original content state and reasoned decision SHALL be retained and affected parties notified

