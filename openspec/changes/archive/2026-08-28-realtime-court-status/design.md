## Context

Currently, the court status dashboard relies on page refreshes or periodic polling to reflect updates, which can lead to double bookings or out-of-sync operations. See `proposal.md` for the motivation to move to a real-time WebSocket approach. The backend is built on Laravel and the frontend on Vue.js.

## Goals / Non-Goals

**Goals:**
- Push real-time court state updates to active clients.
- Use a scalable, Pusher-compatible WebSocket server (e.g., Laravel Reverb or Laravel WebSockets).
- Connect the frontend Vue application using Laravel Echo to listen to private/presence channels scoped to a Venue.

**Non-Goals:**
- Implementing real-time chat or user-to-user messaging.
- Refactoring the entire booking flow; we are only adding event hooks to the existing flow.

## Decisions

**Decision 1: WebSocket Server Implementation**
- **Approach**: Use Laravel Reverb (if Laravel 11) or Laravel WebSockets package (if Laravel 10 and below) as a first-party Pusher replacement.
- **Alternatives Considered**: 
  - *SaaS Pusher/Ably*: Easier setup but introduces recurring costs and external dependency.
  - *Socket.io/Node.js*: Requires maintaining a separate Node.js service alongside the PHP backend, adding deployment overhead.
- **Rationale**: Keeps the infrastructure self-hosted and tightly integrated with Laravel's native event broadcasting system.

**Decision 2: Channel Scoping**
- **Approach**: Broadcast events on a `PrivateChannel('venue.{venue_id}')`.
- **Alternatives Considered**: Global channel for all courts.
- **Rationale**: A Receptionist at Venue A doesn't need to receive WebSocket events for Venue B. Scoping by venue reduces unnecessary payload traffic and improves security.

**Decision 3: Event Hooks**
- **Approach**: Fire a `CourtStatusUpdated` Laravel event from the Eloquent model's `saved` or `created/deleted` hooks (specifically on `Reservation` or `Court` models).
- **Rationale**: Leverages Laravel's native event dispatcher, which automatically serializes the event and pushes it to the WebSocket server if it implements the `ShouldBroadcast` interface.

## Risks / Trade-offs

- **[Risk] WebSocket connection drops**: Clients might lose sync if the connection drops and an event fires while they are disconnected.
  - *Mitigation*: The frontend should catch disconnect events and perform a standard HTTP fetch of the current status upon successful reconnection.
- **[Risk] Over-broadcasting**: Firing too many events (e.g. during a bulk update).
  - *Mitigation*: Ensure batch operations dispatch a single venue-wide refresh event or use `ShouldBroadcastNow` selectively.
