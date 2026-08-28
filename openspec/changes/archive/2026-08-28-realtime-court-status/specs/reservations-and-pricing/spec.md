## ADDED Requirements

### Requirement: Trigger real-time status hooks
The platform SHALL trigger a court status change hook upon successful creation or cancellation of a reservation, providing the updated state of the affected inventory.

#### Scenario: Successful reservation triggers hook
- **WHEN** an authorized client successfully creates a v2 reservation
- **THEN** the platform SHALL dispatch a status update hook for the reserved court-time
