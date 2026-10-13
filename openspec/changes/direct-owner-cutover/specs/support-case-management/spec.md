## MODIFIED Requirements

### Requirement: Decision execution and reconsideration
Only authorized platform reviewers with recorded purpose SHALL decide cases; conflicts of interest SHALL require reassignment. Decisions SHALL cite evidence and applicable policy, support acceptance/partial acceptance/dismissal/further investigation, retain earlier decisions and notify permitted parties. Execution tasks for content, reputation and competition corrections SHALL carry responsible actor, due date and evidence. Decisions SHALL NOT order, create or track refunds. Where a decision finds that money is owed between parties, it SHALL record that finding and the parties settle it outside the platform; the case and its interface SHALL NOT assert that money was paid or returned. A case SHALL close only after completion or a reasoned no-action decision; new material evidence SHALL allow controlled reconsideration without deleting earlier evidence.

#### Scenario: Refund judgment
- **WHEN** a reviewer decides a payment or resale dispute in favour of the party who paid
- **THEN** the decision SHALL record the finding and notify the parties without creating a refund task or obligation and without claiming that money moved

#### Scenario: Reopening
- **WHEN** a party supplies accepted new material evidence for a closed case
- **THEN** a new review version SHALL preserve previous decisions and audit records

#### Scenario: Execution incomplete
- **WHEN** the reviewer requests closure while required actions are outstanding
- **THEN** closure SHALL be refused or an explicitly authorized exception recorded, with overdue escalation

#### Scenario: Reported content is lawful
- **WHEN** a reviewer dismisses a report or restores wrongly hidden content
- **THEN** the original content state and reasoned decision SHALL be retained and affected parties notified
