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
| `v2_reservations_enabled` | `/api/v2` reservation write path | `ENABLED_PILOT_ONLY` | Principal Architect | Reverts traffic to `/api/v1` compatibility mode |
| `organization_tenancy_enforced` | Multi-tenant RLS & authorization | `ENABLED` | Principal Architect | Fails open with audit alert or maintenance mode |
| `marketplace_payouts` | Automated bank payouts | `PILOT_ONLY` | Head of Finance | Halts payout batch worker immediately |
| `marketplace_split_payments` | Split group payments | `DISABLED` (Phase 2) | Engineering Lead | Cancels pending split creation |
| `marketplace_slot_resale` | Secondary slot transfers | `DISABLED` (Phase 2) | Engineering Lead | Disables listing creation |
| `social_matchmaking_tournaments` | Social matchmaking & brackets | `DISABLED` (Phase 3) | Product Lead | Reverts to manual tournament entry |
| `tenant_pos_and_wallet` | POS checkout & wallet liability | `DISABLED` (Phase 4) | Product Lead | Falls back to external POS reconciliation |

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
