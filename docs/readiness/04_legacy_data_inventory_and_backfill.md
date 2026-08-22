# Legacy Data Inventory & Organization Backfill Mappings

**Document Version:** 1.0.0  
**Phase:** Phase 0 — Platform Readiness Gate  
**Capability:** `platform-readiness` / `organization-access` / `reservations-and-pricing`  
**Status:** COMPLETED

---

## 1. Legacy Schema Inventory

The legacy Aura Court system contains 7 core business domains that must transition from a single-tenant global schema to organization-scoped multi-tenancy:

| Legacy Entity / Table | Current Columns & Semantics | Target v2 Entity & Ownership | Transformation / Migration Strategy |
|---|---|---|---|
| **`venues`** | `id`, `name`, `address`, `phone`, `operating_hours`, `created_at` | `venues` + `organizations` (`organization_id`, `slug`, `tax_code`) | Each legacy venue is migrated 1-to-1 into a dedicated `Organization`, and its `venues` record is updated with `organization_id`. |
| **`courts`** | `id`, `venue_id`, `name`, `court_type`, `pricing_rate`, `status` | `courts` (linked to `venues` with inherited `organization_id`) | Retain IDs; enforce composite foreign keys and foreign key constraints on `venue_id`. |
| **`bookings`** | `id`, `court_id`, `user_id`, `start_time`, `end_time`, `total_price`, `status` | `reservations` + `reservation_slots` + `legacy_booking_mappings` | Map legacy bookings to v2 `reservations` with status mapped (`CONFIRMED`, `CANCELLED`, `COMPLETED`); create 1-to-1 compatibility mapping. |
| **`payments`** | `id`, `booking_id`, `amount`, `payment_method`, `provider_ref`, `status` | `payments` + `journals` + `journal_entries` | Reconstruct double-entry journals for historical payments; maintain link to `reservation_id`. |
| **`coupons` / `promotions`** | `id`, `code`, `discount_type`, `discount_val`, `valid_until` | `coupons` (`organization_id` scoped) | Assign legacy global coupons to the backfilled organizations or define as platform-level promotions. |
| **`memberships`** | `id`, `user_id`, `plan_type`, `start_date`, `end_date`, `status` | `organization_memberships` + `membership_plans` | Map legacy users holding memberships to tenant-scoped memberships. |
| **`notifications`** | `id`, `user_id`, `type`, `data`, `read_at` | `notifications` (`organization_id` nullable/scoped) | Preserve user notifications with backfilled organization metadata. |

---

## 2. Organization Backfill Schema & SQL Mappings

### Step 1: Organization Creation from Venues
```sql
-- Create Organizations for each legacy Venue
INSERT INTO organizations (id, name, slug, status, created_at, updated_at)
SELECT 
    v.id AS id,
    v.name AS name,
    LOWER(REGEXP_REPLACE(v.name, '[^a-zA-Z0-9]+', '-', 'g')) AS slug,
    'ACTIVE' AS status,
    v.created_at,
    v.updated_at
FROM venues v
ON CONFLICT (id) DO NOTHING;

-- Link Venues to Organization
UPDATE venues SET organization_id = id WHERE organization_id IS NULL;
```

### Step 2: Member Role Mapping
```sql
-- Assign Legacy Admins as organization_owner for each organization
INSERT INTO organization_members (organization_id, user_id, role, created_at, updated_at)
SELECT 
    o.id AS organization_id,
    u.id AS user_id,
    'organization_owner' AS role,
    NOW(),
    NOW()
FROM organizations o
CROSS JOIN users u
WHERE u.role = 'admin'
ON CONFLICT (organization_id, user_id) DO NOTHING;
```

### Step 3: Legacy Booking to v2 Reservation Projection
```sql
-- Backfill Legacy Bookings to v2 Reservations
INSERT INTO reservations (
    id, 
    organization_id, 
    venue_id, 
    court_id, 
    customer_id, 
    status, 
    total_amount, 
    created_at, 
    updated_at
)
SELECT 
    b.id,
    v.organization_id,
    c.venue_id,
    b.court_id,
    b.user_id,
    UPPER(b.status),
    b.total_price,
    b.created_at,
    b.updated_at
FROM bookings b
JOIN courts c ON c.id = b.court_id
JOIN venues v ON v.id = c.venue_id
ON CONFLICT (id) DO NOTHING;
```

---

## 3. Reconciliation Checksum Invariants

Before and after executing data migration rehearsal, the following checksum queries must match with **0 discrepancy**:

```sql
-- 1. Record Count Checksum
SELECT 
    (SELECT COUNT(*) FROM bookings) AS legacy_booking_count,
    (SELECT COUNT(*) FROM reservations) AS v2_reservation_count,
    (SELECT COUNT(*) FROM bookings) - (SELECT COUNT(*) FROM reservations) AS count_diff;

-- 2. Total Amount Checksum (VND)
SELECT 
    (SELECT COALESCE(SUM(total_price), 0) FROM bookings) AS legacy_total_amount,
    (SELECT COALESCE(SUM(total_amount), 0) FROM reservations) AS v2_total_amount,
    (SELECT COALESCE(SUM(total_price), 0) FROM bookings) - (SELECT COALESCE(SUM(total_amount), 0) FROM reservations) AS amount_diff;

-- 3. Slot Uniqueness Checksum
SELECT 
    court_id, 
    start_time, 
    COUNT(*) 
FROM bookings 
WHERE status NOT IN ('cancelled', 'refunded')
GROUP BY court_id, start_time 
HAVING COUNT(*) > 1;

-- 4. Organization Ownership Checksum
SELECT 
    COUNT(*) AS orphaned_reservations
FROM reservations r
LEFT JOIN organizations o ON o.id = r.organization_id
WHERE o.id IS NULL;
```
