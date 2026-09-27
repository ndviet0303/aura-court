# Operational Exception Runbooks

**Document Version:** 1.0.0  
**Phase:** Phase 0 — Platform Readiness Gate  
**Capability:** `platform-readiness` / `financial-ledger-and-payouts`  
**Audience:** Platform Engineering, Finance Operations, Tier-2 Support

---

## 1. Runbook SOP-FIN-01: Inbound Payment Discrepancies & Stalled Webhooks

### Symptoms
- Customer claims payment made via VietQR/MoMo, but booking remains in `pending` or timed out.
- Provider webhook not received within 60 seconds or webhook signature verification failed.

### Step-by-Step Resolution
1. **Locate Payment Attempt**:
   - Query transaction logs by `booking_id` or customer phone number:
     ```sql
     SELECT id, booking_id, amount, status, provider_event_id, idempotency_key, created_at 
     FROM payments WHERE booking_id = :booking_id;
     ```
2. **Verify Provider State via Provider API**:
   - Execute provider query tool:
     ```bash
     php artisan payment:query-provider --reference=<order_code>
     ```
3. **Handle Scenarios**:
   - **Case A: Provider confirmed `PAID`, platform missed webhook**:
     - Manually dispatch event processor to replay idempotent capture:
       ```bash
       php artisan payment:reprocess-event --provider-event-id=<event_id>
       ```
     - Confirm booking slot moves to `confirmed` and double-entry journal is created.
   - **Case B: Provider shows `FAILED` or `EXPIRED`**:
     - Instruct customer to retry. Inventory hold will auto-release at expiry.
   - **Case C: Customer transferred wrong amount (under/over-payment)**:
     - Record a finance exception:
       ```bash
       php artisan finance:create-exception --type=UNDERPAYMENT --amount-diff=<diff> --reference=<order_code>
       ```
     - Finance initiates refund via compensating journal entry.

---

## 2. Runbook SOP-FIN-02: KYC Verification & Beneficiary Rejection

### Symptoms
- Tenant submits bank account for payout setup; status displays `REJECTED` or `KYC_MISMATCH`.

### Step-by-Step Resolution
1. **Inspect Beneficiary Record**:
   - Query beneficiary validation audit:
     ```sql
     SELECT b.*, o.name AS org_name, o.tax_code 
     FROM organization_beneficiaries b 
     JOIN organizations o ON o.id = b.organization_id 
     WHERE b.organization_id = :org_id;
     ```
2. **Review Failure Reason**:
   - `ACCOUNT_NOT_FOUND`: Check bank code and account digits.
   - `NAME_MISMATCH`: The registered organization name or representative name differs from the NAPAS bank account holder name.
3. **Resolution**:
   - Contact tenant owner via verified email/phone to submit bank confirmation letter or official business registration (Giấy phép ĐKKD).
   - If document is verified by Compliance Officer, execute authorized manual override:
     ```bash
     php artisan kyc:override-beneficiary --org-id=:org_id --officer-id=:officer_id --reason="Verified via business license"
     ```

---

## 3. Runbook SOP-FIN-03: Outbound Payout Failures & Manual Disbursement Governance

### Operational Scope & Governance
- **Direct-Owner Architecture**: Post-cutover booking payments flow directly to verified owner accounts via VietQR; they do NOT enter platform wallet custody or generate platform payout obligations.
- **Manual-Only Disbursement**: The collection provider (SePay) does NOT support automated outbound bank disbursement. All outbound disbursements and payouts remain strictly `manual_only`.
- **Legacy Liability Preservation**: Historical tenant wallet balances and pre-cutover platform payables are strictly preserved with **zero automated deduction**. Outbound payouts apply exclusively to verified, reconciled pre-cutover obligations.

### Symptoms
- Manual payout batch transitions to `failed` or remains in `submitted` without external bank verification for > 2 hours.
### Step-by-Step Resolution
1. **Identify Stuck Payouts**:
   ```sql
   SELECT p.id, p.organization_id, p.amount, p.status, p.provider_reference, p.created_at 
   FROM payouts p 
   WHERE p.status = 'submitted' AND p.updated_at < NOW() - INTERVAL '2 hours';
   ```
2. **Synchronize Status with Provider**:
   ```bash
   php artisan payout:sync-status --payout-id=:payout_id
   ```
3. **Handle Outcomes**:
   - **If Provider reports `SUCCESS`**: State updates to `paid`, tenant settled reserve is closed.
   - **If Provider reports `FAILED`**:
     - Payout moves to `failed`.
     - Underlying ledger entries are **never deleted or edited**.
     - A compensating credit is posted back to the tenant's settled balance.
     - Finance exception is logged and tenant notified to update beneficiary information.

---

## 4. Runbook SOP-FIN-04: Daily 15-Minute Reconciliation Exceptions

### Symptoms
- Automated daily reconciliation job creates an exception alert in Slack `#finance-alerts` with non-zero variance.

### SLA
- **15 minutes** from mismatch detection to initial triage.

### Step-by-Step Resolution
1. **View Daily Reconciliation Report**:
   ```bash
   php artisan reconciliation:view-report --date=$(date +%Y-%m-%d)
   ```
2. **Identify Variance Category**:
   - `PROVIDER_LEDGER_MISMATCH`: Sum of provider captured transactions != Sum of platform `Gross Collection` journal debits.
   - `COMMISSION_MISMATCH`: Historical platform commission rate application error or unexpected post-retirement commission. Note: Platform commissions are retired (0% on all new activity). For historical events, verify recorded allocations against the original immutable journal. For unexpected new commission, verify zero-commission enforcement and reject unauthorized commission accruals.
   - `PAYOUT_DISCREPANCY`: Payout total != Disbursed bank batch sum.
3. **Investigate Specific Transactions**:
   ```sql
   SELECT * FROM finance_exceptions WHERE status = 'OPEN' ORDER BY created_at DESC;
   ```
4. **Corrective Action**:
   - Post compensating journal entries with reference to the `finance_exception_id`.
   - Mark exception resolved with sign-off comments and audit log.

---

## 5. Runbook SOP-ENG-05: Emergency Financial Kill-Switch

### Triggers
- Suspected exploit, double-spending anomaly, or major gateway instability.

### Immediate Action (Within 2 minutes)
1. **Activate Kill Switch**:
   ```bash
   php artisan feature-flag:disable marketplace_payouts
   php artisan feature-flag:disable marketplace_split_payments
   ```
2. **Verify System Enters Safe Read-Only Financial State**:
   - Inbound reservations can continue via direct fallback.
   - All automated disbursements, split share distributions, and marketplace transfers are blocked.
3. **Notify Leadership Team**:
   - Post to `#incident-room` with timestamp, trigger metric, and mitigation status.

---

## 6. Capability Flags & Package Entry/Exit Governance

### Active Capability Flags
| Flag Name | Scope | Default | Description | Entry Gate | Exit Gate |
|---|---|---|---|---|---|
| `v2_reservations_enabled` | Global | `true` | V2 reservation and pricing engine | Full parity regression pass | Rollback to legacy mappings verified |
| `organization_tenancy_enforced` | Global | `true` | Multi-tenant isolation and role matrix | TenantIsolation tests pass | No cross-tenant access in audit logs |
| `tenant_pos_and_wallet` | Pilot-only | `false` | POS checkout, inventory, wallet payments, shift reconciliation | Balanced posting, no client-side receipts, stock atomicity | Zero unmapped orders, cash drawers reconciled |
| `social_matchmaking_tournaments` | Pilot-only | `false` | Social feed, friendships, matchmaking, tournament registration/brackets | Audience isolation, wallet entry fee debit, idempotent retry | No unmoderated flagged posts, bracket integrity confirmed |
| `marketplace_payouts` | Pilot-only | `false` | Automated provider payout batches | Sandbox UAT, D+2 threshold adherence | Reconciled bank statements, 0 unmapped transfers |
| `marketplace_split_payments` | Pilot-only | `false` | Bill split groups and multi-payer shares | Split ledger balance tests pass | All group transactions settled or refunded |
| `marketplace_slot_resale` | Pilot-only | `false` | Secondary marketplace court transfers | Transfer atomicity, anti-scalp rules verified | 0 duplicate slot ownership claims |

### Entry / Exit Protocol
1. **Pilot Admission**: Organization must be enumerated in `PILOT_ORGANIZATION_IDS` and achieve passing readiness checks.
2. **Package Promotion**: All regression suites (`tests/Feature`, `type-check`, `playwright`) must be green.
3. **Emergency Disablement**: Setting flag to `false` disables specialized routes while preserving all committed financial and operational history.

---

## 7. Runbook SOP-OPS-06: POS Stock Variance & Inventory Discrepancies

### Symptoms
- Cashier reports actual shelf inventory differs from on-hand quantity in POS view.
- Sale rejection due to `INSUFFICIENT_STOCK` when physical item is present, or vice-versa.

### Step-by-Step Resolution
1. **Identify Inventory Drift**:
   - Query stock movement ledger for the product:
     ```sql
     SELECT id, product_id, change_amount, reason, created_at 
     FROM pos_stock_movements 
     WHERE product_id = :product_id 
     ORDER BY created_at DESC LIMIT 20;
     ```
2. **Perform Physical Count Audit**:
   - Count physical stock at the venue.
   - Calculate variance: `diff = physical_count - recorded_stock`.
3. **Post Authorized Stock Adjustment**:
   - Staff with `organization_owner` or `venue_manager` role executes adjustment API:
     ```bash
     curl -X POST /api/v2/pos/products/{id}/adjust-stock \
       -H "Authorization: Bearer <token>" \
       -H "X-Organization-ID: <org_id>" \
       -d '{"adjustment": <diff>, "reason": "Physical count audit discrepancy approved by manager"}'
     ```
4. **Audit Recording**:
   - The adjustment creates an immutable `pos_stock_movements` record referencing the actor and rationale.

---

## 8. Runbook SOP-MOD-07: Social Content Moderation & Dispute Handling

### Symptoms
- Community post or comment flagged by users for terms of service violation or inappropriate behavior.
- Player dispute raised regarding match score, reputation penalty, or tournament bracket conflict.

### Step-by-Step Resolution
1. **Review Flagged Content**:
   - Query open moderation reports:
     ```sql
     SELECT id, post_id, comment_id, reporter_id, reason, status, created_at 
     FROM social_reports 
     WHERE status = 'pending' ORDER BY created_at ASC;
     ```
2. **Apply Moderation Decision**:
   - Authorized moderators (`organization_owner`, `venue_manager`) hide offensive content:
     ```bash
     curl -X POST /api/v2/social/posts/{id}/hide \
       -H "Authorization: Bearer <token>" \
       -H "X-Organization-ID: <org_id>" \
       -d '{"reason": "Inappropriate content violating community guidelines"}'
     ```
   - Hidden content is immediately filtered from public and friend feeds while preserving the record for auditing.
3. **Resolve Disputes**:
   - For score or reputation disputes, review match logs and referee notes, then resolve via:
     ```bash
     curl -X POST /api/v2/social/reputation/disputes/{id}/resolve \
       -H "Authorization: Bearer <token>" \
       -H "X-Organization-ID: <org_id>" \
       -d '{"decision": "upheld|dismissed", "note": "Verified by venue match coordinator"}'
     ```
4. **Audit and Restore**:
   - If a post was hidden erroneously, authorized managers can restore it via the restore endpoint, recording the justification in the audit trail.
