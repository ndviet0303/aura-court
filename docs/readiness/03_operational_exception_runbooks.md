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

## 3. Runbook SOP-FIN-03: Outbound Payout Failures & Provider Stalls

### Symptoms
- Automated or manual payout batch transitions to `failed` or remains in `submitted` without terminal response for > 2 hours.

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
   - `COMMISSION_MISMATCH`: Platform commission rate application error.
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
