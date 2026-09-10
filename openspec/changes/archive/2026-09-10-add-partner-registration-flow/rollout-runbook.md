# Partner Registration & Publication Flow: Rollout and Rollback Runbook

## 1. Rollout Configuration & Feature Controls

The partner registration, publication lifecycle, and platform subscription subsystems are governed by runtime environment controls defined in `backend/config/partner.php`:

| Environment Variable | Type | Default | Description |
|---|---|---|---|
| `PARTNER_ENFORCE_V2_REGISTRATION` | boolean | `true` | Requires application review and verification before an organization is provisioned with partner capabilities. |
| `PARTNER_ENFORCE_PUBLICATION_CHECK` | boolean | `false` | When `true`, venues must have an approved `VenuePublicationRevision` before appearing in catalogs or accepting bookings. |
| `PARTNER_ENFORCE_SUBSCRIPTION_CHECK` | boolean | `false` | When `true`, venues require an active `PlatformSubscription` on their organization to take customer bookings. |
| `PARTNER_ENFORCE_COHORTS` | string | `*` | Comma-separated list of organization IDs under strict enforcement, or `*` for all organizations. |
| `PARTNER_SHADOW_MODE` | boolean | `true` | Evaluates v2 eligibility alongside legacy rules and logs discrepancy warnings without blocking operations. |

---

## 2. Production Enablement Prerequisites

Before enabling publication and subscription enforcement in production:

1. **Private Document Storage**:
   - Ensure `FILESYSTEM_PRIVATE_DISK` points to an isolated private storage disk (e.g., S3 bucket with strict private ACL, KMS server-side encryption, and blocked public access).
2. **Platform Service Plans**:
   - Seed and verify commercial platform plans in `platform_plans` table (`standard`, `pro`, `enterprise`) with verified VND pricing, billing cycles, quotas (`max_venues`, `max_courts`, `max_staff`), and published `terms_version` (e.g., `v1.0`).
3. **Provider-Neutral Payment Gateway**:
   - Configure sandbox/production payment provider credentials (e.g., PayOS client ID, API key, checksum key).
   - Verify provider callback webhook signature verification in `backend/app/Infrastructure/Payment/PayOSSubscriptionPaymentAdapter.php`.
4. **Notification Channels**:
   - Verify outbox worker is running (`php artisan queue:work` processing `outbox_messages` table).
   - Ensure email/SMS templates exist for `platform.application.approved`, `platform.application.rejected`, `platform.application.supplement_requested`, and `platform.subscription.expired`.

---

## 3. Migration Reconciliation Procedure

To reconcile existing organizations, venues, and owners created before this change:

### Step 3.1: Read-Only Audit
Run the read-only legacy partner and venue audit to produce an inventory report without mutating database state:
```bash
php artisan partner:reconcile-legacy --format=json --output-file=storage/app/reconciliation-report.json
```
The report itemizes:
- Organizations with missing approved application evidence (`missing_application_evidence`).
- Published venues without approved publication revisions (`venues_missing_publication_revisions`).
- Organizations without active platform subscriptions (`missing_platform_subscriptions`).
- Outstanding future customer bookings requiring obligation protection (`total_outstanding_bookings`).

### Step 3.2: Prepare Reviewed Mappings
Construct an audited JSON mappings file based on physical verification contracts:
```json
{
  "mappings": [
    {
      "organization_id": 10,
      "decision": "verified",
      "reviewer_id": 1,
      "notes": "Verified business registration certificate from physical contract",
      "plan_code": "standard",
      "venues": [
        { "venue_id": 20, "decision": "published" }
      ]
    },
    {
      "organization_id": 11,
      "decision": "unresolved",
      "notes": "Pending physical lease verification"
    }
  ]
}
```

### Step 3.3: Dry-Run Backfill
Simulate the backfill without persisting database changes:
```bash
php artisan partner:backfill-mappings --file=storage/app/reviewed-mappings.json --dry-run
```

### Step 3.4: Production Backfill Execution
Execute the backfill within an audited transaction:
```bash
php artisan partner:backfill-mappings --file=storage/app/reviewed-mappings.json
```
- Every verified item writes immutable records to `partner_review_histories` and `audit_logs`.
- Unresolved organizations are left untouched without fabricated approvals or blanket free subscriptions.
- The command is idempotent: rerunning with identical mappings produces zero duplicate records.

---

## 4. Rollback Runbook & Safety Invariants

### Rollback Triggers:
1. High volume of shadow discrepancy warnings (`partner_rollout.shadow_discrepancy`) in monitoring.
2. Gateway callback communication failure or widespread webhook timeouts.
3. Unanticipated disruption of existing customer booking obligations.

### Rollback Execution:
In the event of an operational issue, roll back feature flags in `.env`:
```env
PARTNER_ENFORCE_PUBLICATION_CHECK=false
PARTNER_ENFORCE_SUBSCRIPTION_CHECK=false
PARTNER_SHADOW_MODE=true
```
Run `php artisan config:clear`.

### Safety Invariants During Rollback:
- **No Self-Grant Reopening**: Selecting `court_owner` in personal onboarding remains permanently disabled at the code level; rolling back configuration flags does not reopen immediate owner privileges.
- **Data Preservation**: Rollback never deletes newly created applications, subscriptions, payment records, or audit history.
- **Obligations Protected**: Existing confirmed customer bookings and wallet settlement obligations remain fully honored and actionable.

---

## 5. Operational Sign-Off Protocol

**MANDATORY REQUIREMENT**:
No production data migration, cohort activation, or live gateway enablement may be executed without explicit written operational approval from the Platform Lead and Finance Operations.

Sign-off checklist:
- [ ] Reconciliation audit completed and reviewed.
- [ ] Mappings file verified against legal registration records.
- [ ] Dry-run backfill successfully validated on staging environment.
- [ ] Payment gateway sandbox transactions verified end-to-end.
- [ ] Operational runbook acknowledged by on-call support team.
