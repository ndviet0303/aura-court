# Provider Sandbox Evidence & External Blocker Record

**Document Version:** 1.0.0  
**Phase:** Phase 5 — Deposit and Finance Journeys Gate  
**Date:** 2026-09-09  
**Capability:** `financial-ledger-and-payouts` / `wallet-payment`  
**Status:** BLOCKED EXTERNALLY FOR LIVE PSP CALLS / VERIFIED VIA CONTRACT SUITE  

---

## 1. Executive Summary

This record documents the provider sandbox verification evidence collected for Aura Court's tenant deposit and payout lifecycles, specifies the precise external dependencies blocking live gateway execution in local/test environments, and confirms the feature flag controls keeping automated production rollouts disabled until formal gate clearance.

---

## 2. Sandbox & Automated Contract Verification Evidence

The end-to-end deposit and payout journeys have been verified through exhaustive automated test suites on isolated databases using simulated provider sandbox adapters:

### 2.1 Deposit Collection Path (Inbound)
- **Verified Inbound Deposit**: Verified provider events credit tenant wallet accounts and post balanced double-entry journals (`cash_clearing` debit, `wallet_liability` credit).
  - *Evidence:* `Tests\Feature\BookingFlowsTest::test_sepay_webhook_deposits_into_wallet` (PASS)
  - *Evidence:* `Tests\Feature\TenantWalletDepositTest` (5 test scenarios PASS)
- **Replay & Idempotency**: Replay of identical external transaction IDs or provider events is rejected with zero duplicate credit.
  - *Evidence:* `Tests\Feature\TenantWalletDepositTest::test_deposit_endpoint_requires_valid_organization_context_and_positive_amount` (PASS)
  - *Evidence:* `Tests\Feature\FinancialFoundationTest::test_provider_event_identifier_is_deduplicated` (PASS)
- **Tampering & Signature Validation**: Webhooks with invalid signatures or mismatched payloads are rejected (HTTP 401/400).
- **Out-of-Order Events**: Pre-credit verifications ensure pending states cannot be bypassed by unverified returns.

### 2.2 Payout Disbursement Path (Outbound)
- **Minimum Threshold Enforcement**: Settlements below 500,000 VND fail payout draft creation with HTTP 422 (`Settlement is below the minimum payout threshold.`).
  - *Evidence:* `Tests\Feature\PayoutLifecycleThresholdEligibilityReconciliationTest::test_payout_creation_enforces_minimum_threshold` (PASS)
- **D+2 Eligibility Delay**: Settlements within the D+2 holding window (`eligible_at` in the future) are blocked from payout creation with HTTP 409 (`Settlement is not yet eligible for payout.`).
  - *Evidence:* `Tests\Feature\PayoutLifecycleThresholdEligibilityReconciliationTest::test_payout_creation_enforces_eligibility_and_settlement_delay` (PASS)
- **Complete Payout Lifecycle**: `draft → approved → submitted → paid|failed|reversed`.
  - *Evidence:* `Tests\Feature\FinanceReportsSettlementPayoutExceptionTest::test_payout_workflow_from_draft_to_approve_submit_and_status` (PASS)
- **Failure & Reversal Audit Logging**:
  - Provider failure transitions payout to `failed`, records failure reason, and creates an open `payout_failed` exception with a 15-minute SLA (`due_at`).
  - Provider reversal transitions payout to `reversed` and creates a critical `payout_reversed` exception.
  - In all failure scenarios, source ledger journals remain untouched.
  - *Evidence:* `Tests\Feature\PayoutLifecycleThresholdEligibilityReconciliationTest::test_payout_failed_provider_response_records_exception_and_preserves_ledger` (PASS)
  - *Evidence:* `Tests\Feature\PayoutLifecycleThresholdEligibilityReconciliationTest::test_payout_reversed_provider_response_records_critical_exception` (PASS)
- **Daily 3-Way Reconciliation**:
  - Reconciles provider events, ledger journals, settlements, and payouts.
  - Zero-variance sets run to `matched`.
  - Non-zero variance sets run to `mismatch` and spawns a `reconciliation_mismatch` exception due within 15 minutes.
  - Exception resolution never modifies or deletes past ledger entries.
  - *Evidence:* `Tests\Feature\PayoutLifecycleThresholdEligibilityReconciliationTest::test_daily_reconciliation_detects_mismatch_and_preserves_ledger_when_resolved` (PASS)
  - *Evidence:* `Tests\Feature\FinanceReportsSettlementPayoutExceptionTest::test_reconciliation_stats_and_runs_endpoints` (PASS)

---

## 3. Precise External Blockers for Live Provider Sandbox Execution

While contract and adapter verification is complete, live interactive network calls against external provider sandbox servers cannot be completed in this environment due to the following external blockers:

1. **Missing PayOS Merchant Sandbox Credentials**:
   - `PAYOS_CLIENT_ID`: Not configured in runtime environment.
   - `PAYOS_API_KEY`: Not configured in runtime environment.
   - `PAYOS_WEBHOOK_SECRET`: Not configured in runtime environment.
   - *Blocker Detail:* Live PayOS sandbox gateway rejects API calls with HTTP 401 Unauthorized when credentials are absent.
2. **Missing SePay Secret**:
   - `SEPAY_HMAC_SECRET`: Unset in `.env`. Live inbound bank webhook validation requires the shared secret configured in the merchant portal.
3. **Napas Account Verification Gateway**:
   - Direct Napas 24/7 bank account lookup requires private corporate VPN/TLS certificate pairing that cannot be established in local developer environments.

---

## 4. Rollout Controls & Feature Flag Enforcement

To guarantee financial safety and prevent accidental exposure of real funds or untested provider connections, the platform enforces strict rollout gates:

1. **`marketplace_payouts`**:
   - Configured in `config/features.php` as `pilot_only`.
   - Access is restricted exclusively to vetted pilot organizations:
     `PILOT_ORGANIZATION_IDS=1,2,3,4,5`
   - All unauthorized organizations receive HTTP 404/403 on payout endpoints.
2. **Production Gate Hold**:
   - Automated payouts SHALL NOT be enabled globally in production without explicit customer authorization, secret vault injection of production credentials, and signed approval from the Head of Finance.
