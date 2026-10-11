-- Data fix for openspec change harden-db-integrity (design D4). Run once per environment,
-- after review and before the status CHECK migrations:
--   psql -X -v ON_ERROR_STOP=1 -f docs/operations/harden-db-integrity-booked-slots-fix.sql
-- booking_slots.status 'booked' is outside the code vocabulary; a paid v1 slot is 'confirmed'.
-- bt-server 2026-10-11: 12 rows, all under confirmed bookings, none mirrored to reservation_slots,
-- no collision with booking_slots_active_unique, no overlap with other active slots.
-- Atomic: any remaining 'booked' row aborts the whole fix.

DO $$
DECLARE
  fixed integer;
BEGIN
  UPDATE booking_slots s
  SET status = 'confirmed', updated_at = now() AT TIME ZONE 'UTC'
  FROM bookings b
  WHERE b.id = s.booking_id
    AND s.status = 'booked'
    AND b.status = 'confirmed';
  GET DIAGNOSTICS fixed = ROW_COUNT;

  IF EXISTS (SELECT 1 FROM booking_slots WHERE status = 'booked') THEN
    RAISE EXCEPTION 'booked slots remain under non-confirmed bookings; review them before the CHECK migration';
  END IF;

  RAISE NOTICE 'rewrote % booking_slots from booked to confirmed', fixed;
END $$;
