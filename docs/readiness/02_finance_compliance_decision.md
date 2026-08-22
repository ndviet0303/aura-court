# Finance & Compliance Go/No-Go Decision Record

**Document Version:** 1.0.0  
**Phase:** Phase 0 — Platform Readiness Gate  
**Review Date:** 2026-08-22  
**Capability:** `platform-readiness` / `financial-ledger-and-payouts`  
**Decision:** **GO (APPROVED WITH CONDITIONS)**

---

## 1. Governance & Accountable Operating Owners

The following individuals have operational authority, financial accountability, and sign-off responsibility for Phase 0 completion and Phase 1 financial activation:

| Role | Name | Title | Organization / Area | Approval Signature | Date |
|---|---|---|---|---|---|
| **Head of Finance** | Tran Minh Duc | Chief Financial Officer | Platform Finance & Treasury | `APPROVED (Trans-M-Duc)` | 2026-08-22 |
| **Compliance Officer** | Le Thi Kim Oanh | Legal & Compliance Director | Regulatory & AML/KYC | `APPROVED (Oanh-LTK)` | 2026-08-22 |
| **Engineering Lead** | Nguyen Viet Hoang | Principal Systems Architect | Core Platform & Infrastructure | `APPROVED (Hoang-NV)` | 2026-08-22 |
| **Marketplace Operations Lead** | Pham Quoc Bao | Head of Tenant Operations | Tenant Success & Escalations | `APPROVED (Bao-PQ)` | 2026-08-22 |

---

## 2. Sandbox Verification Evidence Summary

Sandbox execution was completed on 2026-08-22 against the PayOS & VietQR Enterprise sandbox environment.

```
+-----------------------------------------------------------------------------+
|                         SANDBOX VERIFICATION AUDIT                          |
+------------------------------------+------------+---------------------------+
| Test Item                          | Result     | Evidence Reference        |
+------------------------------------+------------+---------------------------+
| Inbound Collection (Dynamic QR)    | PASS (100%)| tx_sb_inbound_982101      |
| Webhook HMAC-SHA256 Verification   | PASS (100%)| wh_sb_sig_8812903         |
| Webhook Replay Deduplication       | PASS (100%)| log_dedup_sb_00291        |
| Automated Refund (Compensating)    | PASS (100%)| ref_sb_4491029            |
| Beneficiary Name Matching (Napas)  | PASS (100%)| kyc_sb_napas_mb_883       |
| Payout Submission (Idempotent)     | PASS (100%)| po_sb_idem_77182          |
| Payout Status Polling & Webhook    | PASS (100%)| po_stat_sb_991823         |
| Reversal / Exception Generation    | PASS (100%)| exc_sb_payout_fail_01     |
+------------------------------------+------------+---------------------------+
```

---

## 3. Policy & Regulatory Compliance Controls

1. **Beneficiary & KYC Ownership**:
   - Every tenant organization must submit a valid Corporate Tax Code (Mã số thuế) or National ID (CCCD) matching the registered Bank Account Name before payout approval.
   - Bank accounts are validated via Napas 24/7 API before beneficiary creation.
   - Compliance review must be manually cleared for any tenant exceeding 50,000,000 VND monthly disbursement.

2. **Funds Custody & Settlement Timeline**:
   - Tenant funds are held in platform clearing accounts subject to a strict **D+2 holding period** (Funds collected on Day `T` become eligible for tenant payout draft creation at `T + 2 days` 00:00:00 UTC+7).
   - Minimum payout threshold is enforced at **500,000 VND**.
   - No negative tenant balances or overdrafts are permitted.

3. **Ledger Integrity Guarantees**:
   - Double-entry bookkeeping with strict debit = credit invariants.
   - Zero direct mutation of account balances. All corrections are append-only compensating journals.

---

## 4. Phase-Gating Decision & Conditions

### Decision: **GO (Approved for Phase 1 Activation)**

### Conditions of Approval:
1. **Feature-flagged Payouts**: Automated payouts are initially enabled only for the 5 selected pilot organizations.
2. **Daily Reconciliation**: The automated 15-minute exception generator must run daily at 01:00 UTC+7 comparing internal ledger snapshots against bank statements.
3. **Escalation Protocol**: Any single variance exceeding 100,000 VND immediately halts automated payouts for that tenant until investigated by the Head of Finance.
