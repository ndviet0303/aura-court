# Pilot Cohorts, Feature Flag Ownership, SLO Dashboards & Phase-Entry Gates

**Document Version:** 1.0.0  
**Phase:** Phase 0 — Platform Readiness Gate  
**Capability:** `platform-readiness`  
**Status:** COMPLETED & APPROVED

---

## 1. Approved Pilot Cohort (Phase 1 Initial Operators)

The following 5 court operators have been approved by Marketplace Operations and Finance to participate in the Phase 1 SaaS and multi-tenant pilot:

| Pilot Tenant ID | Operator / Venue Name | Location | Court Count | Primary Workload | Approval Status |
|---|---|---|---|---|---|
| **`pilot-org-01`** | Aura Court District 1 (Flagship) | D1, Ho Chi Minh City | 6 Courts | High-traffic prime-time bookings, VietQR payments | **APPROVED** |
| **`pilot-org-02`** | Saigon Pickleball & Tennis Arena | D7, Ho Chi Minh City | 8 Courts | High-volume member reservations & hourly slots | **APPROVED** |
| **`pilot-org-03`** | Hanoi Badminton Hub | Cau Giay, Hanoi | 10 Courts | Peak evening leagues & recurring booking tests | **APPROVED** |
| **`pilot-org-04`** | Da Nang Sports Center | Hai Chau, Da Nang | 4 Courts | Multi-sport court management & POS counter | **APPROVED** |
| **`pilot-org-05`** | Binh Thanh Padel Club | Binh Thanh, HCMC | 4 Courts | Weekend social games & mobile booking flows | **APPROVED** |

---

## 2. Feature Flag Ownership & Rollout Matrix

Every capability is gated by an independently reversible feature flag with strict owner controls:

| Feature Flag Key | Capability Scope | Default State | Responsible Owner | Emergency Rollback Action |
|---|---|---|---|---|
| `organization_tenancy_enforced` | Multi-tenant RLS & authorization | `ENABLED` | Principal Architect | Fails open with audit alert or maintenance mode |
| `marketplace_payouts` | Automated bank payouts | `PILOT_ONLY` | Head of Finance | Halts payout batch worker immediately |
| `marketplace_split_payments` | Split group payments | `DISABLED` (Phase 2) | Engineering Lead | Cancels pending split creation |
| `marketplace_slot_resale` | Secondary slot transfers | `DISABLED` (Phase 2) | Engineering Lead | Disables listing creation |
| `social_matchmaking_tournaments` | Social matchmaking & brackets | `DISABLED` (Phase 3) | Product Lead | Reverts to manual tournament entry |
| `tenant_pos_and_wallet` | POS checkout & wallet liability | `DISABLED` (Phase 4) | Product Lead | Falls back to external POS reconciliation |

The reservation API (`/api/price-quotes`, `/api/reservations`) is always on and has no flag. Since the V1 retirement (`openspec/changes/retire-v1-booking`) the API has no version prefix. Rolling that release back means redeploying the previous backend and frontend release together and pointing the OAuth redirect URIs and SePay webhooks back at their `/api/v1` paths; no flag switches traffic back.

---

## 3. SLO Dashboards & Alert Thresholds

### SLO Targets

| Metric | Target SLO | Warning Alert Threshold | Critical PagerDuty Alert Threshold |
|---|---|---|---|
| **API Read Latency (p95)** | $\le$ 150 ms | $>$ 200 ms for 5 min | $>$ 500 ms for 2 min |
| **Booking Write Latency (p95)**| $\le$ 400 ms | $>$ 500 ms for 3 min | $>$ 1,000 ms for 1 min |
| **Global Error Rate (5xx)** | $\le$ 0.1% | $>$ 0.5% for 3 min | $>$ 1.0% for 1 min |
| **Queue Lag (Outbox / Jobs)** | $\le$ 5 seconds | $>$ 15 seconds for 5 min | $>$ 60 seconds for 2 min |
| **Webhook Processing Lag** | $\le$ 3 seconds | $>$ 10 seconds for 3 min | $>$ 30 seconds for 1 min |
| **Ledger Reconciliation Mismatch**| 0 VND | Non-zero variance | Any variance unreviewed after 15 min |
| **Concurrent Lock Contention**| $\le$ 1% fail | $>$ 5% conflict rate | Database lock timeout spikes |

---

## 4. Phase-Entry Review Template

Before any subsequent phase (Phase 1, 2, 3, 4) is activated in production, the following review checklist must be executed and recorded:

```markdown
### Phase-Entry Gate Review: [Phase Number] - [Phase Title]
- **Review Date:** YYYY-MM-DD
- **Lead Reviewer:** [Name / Title]

#### 1. Pre-requisite Checklist
- [ ] All OpenSpec delta specifications for this phase are validated (`openspec validate`).
- [ ] Automated unit, feature, and integration tests passed with 100% success rate.
- [ ] Concurrency and tenant-isolation test suites passed.
- [ ] Migration and rollback procedures rehearsed on production-like dataset.
- [ ] Finance & Compliance sign-off recorded (for financial features).
- [ ] Monitoring dashboards and alerting rules active in Prometheus/Grafana/Datadog.

#### 2. Pilot Cohort Activation
- [ ] Pilot organizations selected and briefed.
- [ ] Feature flag scoped to pilot cohort IDs.

#### 3. Sign-offs
- Engineering Lead: [ ] APPROVED  [ ] REJECTED
- Product Lead:     [ ] APPROVED  [ ] REJECTED
- Finance/Ops Lead: [ ] APPROVED  [ ] REJECTED
```

---

## 5. Independent Pilot Activation and Disable Procedures

### 5.1 Independent Pilot Activation Procedure
Each pilot package (`tenant_pos_and_wallet`, `social_matchmaking_tournaments`, `marketplace_payouts`, `marketplace_split_payments`, `marketplace_slot_resale`) can be independently enabled for specific tenant organizations without affecting the rest of the platform.

1. **Organization Eligibility Verification**:
   - Verify the candidate organization has completed KYC and holds active membership in the approved pilot cohort.
   - Verify that baseline data migrations and mapping checksums are healthy.

2. **Scoped Activation Configuration**:
   - Append the organization ID to `PILOT_ORGANIZATION_IDS` in the environment configuration:
     ```bash
     PILOT_ORGANIZATION_IDS="1,2,3,<new_pilot_org_id>"
     ```
   - Enable the targeted package flag:
     ```bash
     FEATURE_TENANT_POS_WALLET=true # or FEATURE_SOCIAL_TOURNAMENTS=true, etc.
     ```
   - Reload configuration cache:
     ```bash
     php artisan config:cache
     ```

3. **Verification of Scoped Exposure**:
   - Verify that the target organization has access to the new capabilities.
   - Verify that non-pilot organizations remain strictly blocked with `403 Forbidden` on pilot endpoints.

### 5.2 Independent Pilot Disable Procedure (Preserving Committed History)
If an issue is encountered, a package can be disabled immediately for a specific organization or globally:

1. **Immediate Ingress Disablement**:
   - Remove the organization ID from `PILOT_ORGANIZATION_IDS`, or disable the specific package flag:
     ```bash
     FEATURE_TENANT_POS_WALLET=false
     ```
   - Reload configuration cache:
     ```bash
     php artisan config:cache
     ```

2. **Preservation of Committed History (Core Invariant)**:
   - **NO DATA DELETION**: Under no circumstances are existing POS orders, payments, wallet transactions, ledger journal entries, tournament participants, or reservations dropped or purged.
   - Existing records remain fully queryable by finance, auditors, and administrators.
   - Open cash shifts and pending service tabs can be reconciled using canonical administrative and finance tools.

3. **Post-Disable Audit**:
   - Run `php artisan finance:reconcile --organization=<org_id>` to verify that all balances and journals remain intact and balanced.
   - Run `php artisan v2:backfill-legacy-bookings --audit --organization=<org_id>` to confirm legacy-to-canonical mapping consistency.

### 5.3 Production Activation Governance
**Production Activation Constraint**:
No package or pilot capability shall be activated globally across production without a separate, explicit user instruction. All changes in this phase remain strictly scoped to approved pilot cohorts and test/staging environments.

---

## 6. Activation Readiness & Operational Governance (Direct Owner Payments & Legacy Liabilities)

### 6.1 Operator Cohort Approval Gate
- **Mandatory Human Sign-off**: Real monetary transfers, live provider webhook activation, and rollout to production cohorts MUST NOT proceed automatically.
- **Approval Requirements**: Each candidate operator cohort requires explicit, written approval from authorized operating owners (Head of Finance, Marketplace Operations Lead) confirming:
  1. The operator has attested account authority and verified receiving connection credentials (`ReceivingConnection` status `verified`).
  2. Baseline data migration, legacy inventory verification, and mapping checksums are confirmed healthy.
  3. Pricing rules, court operating calendars, and cancellation/refund policies have been reviewed and accepted by the operator.
- **Prohibition of Self-Rollout**: Engineering, automated scripts, and test runners SHALL NOT initiate real fund movements, live merchant credential deployments, or unsolicited tenant activation without recorded operator sign-off.

### 6.2 Manual-Only Bank Disbursements
- **Inbound-Only Provider Capability**: Direct owner collection provider (SePay) functions exclusively as an inbound bank transaction notification gateway and does NOT provide an automated outbound disbursement API.
- **Disbursement Enforcement**: Automated bank disbursement remains strictly `manual_only`.
- **Proof & Dual-Confirmation Workflow**: Any platform payout, partner compensation, or owner-executed refund requires:
  1. Manual external banking transfer executed by the authorized operator or venue owner.
  2. Recording of external bank transaction reference and encrypted transfer proof (`STATUS_OWNER_REPORTED`).
  3. Explicit acknowledgement and verification by the receiving party or platform auditor (`STATUS_ACKNOWLEDGED`).
- **Zero Unverified Disbursement**: System status transitions to `paid` or `settled` are strictly rejected without cryptographic evidence or verified provider receipt.

### 6.3 Preservation of Legacy Liabilities (Zero Automated Deductions)
- **Absolute Liability Conservation**: All historical wallet balances (9 accounts, 1,999,500,000 VND), pre-cutover deposits (4 transactions, 2,000,500,000 VND), and legacy bookings (74 bookings) are strictly conserved as immutable ledger records.
- **Zero Automated Deductions**: Under no circumstances shall the platform perform automated balance deductions, unilateral write-offs, or forced balance conversions against historical user wallets.
- **Independent Reconciliation**: Pre-cutover wallet liabilities and platform-held obligations are segregated and reconciled independently from post-cutover direct-owner revenue streams. Any legacy balance disposition requires an explicit, separate operational decision approved by the Head of Finance.
