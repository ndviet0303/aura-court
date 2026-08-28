## Why

The Receptionist/Cashier needs to see the real-time status of courts (Available, Booked, In Use, Maintenance) without constantly refreshing the page. This ensures smooth operations, prevents double-booking confusion, and allows immediate response to sudden changes (e.g., emergency court closures or new online bookings).

## What Changes

- Introduce a WebSocket infrastructure for real-time bi-directional communication with clients.
- Add event hooks for booking creation and cancellation.
- When a booking hook is triggered, broadcast a WebSocket event with the updated court status to all subscribed clients (e.g., the Receptionist dashboard).
- Update the frontend Court Status view to subscribe to these WebSocket events and update the UI reactively.

## Capabilities

### New Capabilities
- `realtime-updates`: Real-time WebSocket infrastructure and event broadcasting for court status.

### Modified Capabilities
- `reservations-and-pricing`: Trigger event hooks on reservation creation and cancellation to notify the real-time update service.

## Impact

- **Backend**: Need to set up a WebSocket server/service (e.g., Laravel WebSockets, Socket.io, or equivalent), event broadcasters, and listeners on the Booking models.
- **Frontend**: Integrate a WebSocket client (e.g., Echo, Socket.io-client) in the Receptionist/Admin dashboard to listen for court status events.
- **Infrastructure**: Potential need for a message broker (Redis) depending on the WebSocket implementation choice.
