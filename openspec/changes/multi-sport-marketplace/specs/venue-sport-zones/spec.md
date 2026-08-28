## Purpose

Allows venue owners to configure distinct sport zones within a venue, each with its own sport type, pricing rules, and slot durations.

## ADDED Requirements

### Requirement: Configure Sport Zones
The system SHALL allow venue owners to define one or more Sport Zones within their venue, specifying the sport type (e.g., Badminton, Football 5v5) and the default slot duration (e.g., 60 minutes, 90 minutes).

#### Scenario: Venue owner creates a new sport zone
- **WHEN** a venue owner submits a valid request to create a new sport zone with a 90-minute slot duration for Football
- **THEN** the system SHALL create the sport zone and associate it with the venue

### Requirement: Associate Courts with Sport Zones
The system SHALL require all playable courts to belong to a specific Sport Zone instead of being directly attached to the venue without sport context.

#### Scenario: Venue owner adds a court to a sport zone
- **WHEN** a venue owner adds "Sân 1" to the "Khu Cầu Lông" sport zone
- **THEN** the system SHALL record "Sân 1" as an active court under that sport zone and inherit its slot duration and pricing rules
