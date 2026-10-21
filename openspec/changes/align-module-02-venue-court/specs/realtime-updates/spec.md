## MODIFIED Requirements

### Requirement: Broadcast court status updates
The platform SHALL push court status updates to authenticated WebSocket clients subscribed to the relevant venue channel whenever a court's availability state changes. Recording an operational stop that starts later SHALL NOT emit a court status event; the event SHALL be emitted when the stop starts and the court's effective status changes, and on resume.

#### Scenario: Court status changes to booked
- **WHEN** a new reservation successfully changes a court's state to booked
- **THEN** the platform SHALL broadcast the updated court status event to the venue's active WebSocket subscribers

#### Scenario: Court is marked for emergency maintenance
- **WHEN** an owner or assigned staff member records a maintenance stop of an active court starting at the time of confirmation
- **THEN** the platform SHALL broadcast the paused status event to the venue's active WebSocket subscribers immediately
- **AND** a maintenance stop recorded to start later SHALL emit the event only when the court becomes paused at its start, resuming the court SHALL emit its active status event, and no event SHALL be emitted when the status does not change
