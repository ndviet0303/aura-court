# Realtime Updates Specification

## Purpose

Provides bi-directional WebSocket infrastructure for real-time state synchronization, enabling clients like the Receptionist dashboard to instantly reflect status changes without polling.

## Requirements

### Requirement: Broadcast court status updates
The platform SHALL push court status updates to authenticated WebSocket clients subscribed to the relevant venue channel whenever a court's availability state changes.

#### Scenario: Court status changes to booked
- **WHEN** a new reservation successfully changes a court's state to booked
- **THEN** the platform SHALL broadcast the updated court status event to the venue's active WebSocket subscribers

#### Scenario: Court is marked for emergency maintenance
- **WHEN** an admin locks a court for maintenance
- **THEN** the platform SHALL broadcast the unavailable status event to the venue's active WebSocket subscribers immediately
