## 1. Backend Setup

- [x] 1.1 Install and configure the WebSocket server package (Laravel Reverb or laravel-websockets depending on the Laravel version).
- [x] 1.2 Update `.env` to set `BROADCAST_DRIVER=reverb` (or pusher) and configure the necessary Pusher credentials.
- [x] 1.3 Uncomment `BroadcastServiceProvider` in `config/app.php` and set up the broadcasting routes (`routes/channels.php`) to authorize the `venue.{venue_id}` private channel.

## 2. Event Hooks Implementation

- [x] 2.1 Create a new Laravel Event `CourtStatusUpdated` that implements `ShouldBroadcast`.
- [x] 2.2 Configure the event to broadcast on a `PrivateChannel` scoped to the specific venue ID.
- [x] 2.3 Set up model observers or boot hooks in the `Reservation` (and optionally `Court`) models to dispatch `CourtStatusUpdated` on created, updated, and deleted events.

## 3. Frontend Setup

- [x] 3.1 Install frontend dependencies: `laravel-echo` and `pusher-js`.
- [x] 3.2 Initialize Laravel Echo in the Vue frontend (e.g., in `src/lib/api.ts` or a dedicated `echo.ts` plugin) using the backend's WebSocket credentials.

## 4. Frontend Integration

- [x] 4.1 Update `CourtsView.vue` (and/or `ScheduleView.vue`) to subscribe to the `venue.{venue_id}` channel via Echo on component mount.
- [x] 4.2 Listen for the `CourtStatusUpdated` event on the channel and write the reactive logic to update the local court state without a full page refresh.
- [x] 4.3 Add a reconnect fallback to fetch the latest state via standard HTTP if the WebSocket connection drops and reconnects.
