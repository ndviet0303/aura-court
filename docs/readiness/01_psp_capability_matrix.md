# PSP Capability Test Matrix & Evaluation

**Document Version:** 1.0.0  
**Phase:** Phase 0 — Platform Readiness Gate  
**Capability:** `platform-readiness` / `financial-ledger-and-payouts`  
**Status:** APPROVED (Evaluation complete)

---

## 1. Executive Summary

This document defines the Payment Service Provider (PSP) capability matrix required for Aura Court's multi-tenant marketplace. The platform requires distinct financial workflows:
1. **Gross Inbound Collection**: Customer payment for court reservations, recurring series, split payments, marketplace resales, and POS transactions.
2. **Webhook Verification & Idempotency**: Cryptographically signed notifications preventing replay and duplicate capture.
3. **Refunds & Compensating Entries**: Full and partial refunds reversing reservation payments and split allocations.
4. **Beneficiary Onboarding & KYC**: Verification of court operator bank accounts and identity before disbursing funds.
5. **Outbound Payouts (Disbursements)**: Automated bulk and ad-hoc payouts to tenant bank accounts adhering to D+2 settlement cycles.
6. **Status Retrieval & Polling Fallback**: Deterministic status querying for in-flight transactions when webhooks are delayed.
7. **Idempotent Retries**: Unique request identifiers preventing duplicate financial instructions.

---

## 2. Provider Evaluation Matrix

| Capability Requirement | SePay (Legacy VietQR) | PayOS (VietQR Pro) | VietQR Direct / Napas 247 | MoMo Enterprise | Stripe Connect (Custom) | Minimum Acceptance Criteria | Evaluation Status |
|---|---|---|---|---|---|---|---|
| **1. Dynamic QR Collection** | Supported (Bank webhook) | Supported (Dynamic QR + DeepLink) | Supported (API Generation) | Supported (QR / In-app) | Supported (Cards / Wallets) | Dynamic amount, order code embedding, < 2s rendering | **PASSED** (PayOS / VietQR) |
| **2. Cryptographic Webhook Signing** | ⚠️ Shared API token header | ✅ HMAC-SHA256 signature payload | ⚠️ IP whitelist + Token | ✅ HMAC-SHA256 signature | ✅ HMAC-SHA256 (Stripe-Signature) | Nonce + timestamp + HMAC signature | **PASSED** (PayOS / Stripe) |
| **3. Automated Partial/Full Refunds** | ❌ Manual bank transfer | ✅ Automated API Refund | ⚠️ Manual / Batch | ✅ Automated API Refund | ✅ Automated API Refund | Programmatic refund within < 5s via API | **PASSED** (PayOS / MoMo / Stripe) |
| **4. Beneficiary / KYC Onboarding** | ❌ None (Unmanaged) | ✅ Bank Account Name Lookup API | ✅ NAPAS Account Validation | ✅ Merchant KYC Portal | ✅ Identity + Custom Connect | Realtime account name verification matching tax ID/owner | **PASSED** (PayOS + NAPAS validation) |
| **5. Automated Outbound Payouts** | ❌ None | ✅ Corporate Payout API (24/7) | ✅ Direct Napas 24/7 Gateway | ✅ Disburse API | ✅ Transfers / Payouts API | Programmatic D+2 payout via API with batching | **PASSED** (PayOS Payouts / Napas) |
| **6. Payout & Payment Status Retrieval** | ⚠️ Polling transaction list | ✅ `GET /v2/payment-requests/{id}` | ✅ `GET /v1/transactions/{ref}` | ✅ `GET /v2/gateway/api/query` | ✅ `GET /v1/charges/{id}` | Immediate status polling by idempotency reference | **PASSED** (PayOS / Stripe) |
| **7. Outbound Request Idempotency** | ❌ None | ✅ `Idempotency-Key` header support | ⚠️ Client reference dedup | ✅ Unique `partnerRefId` | ✅ `Idempotency-Key` header | Exact idempotency key dedup with 24h caching | **PASSED** (PayOS / Stripe) |

---

## 3. Sandbox Test Cases & Verification Scenarios

### Scenario Matrix

| ID | Capability | Test Action | Expected Sandbox Response | Invariant Checked | Status |
|---|---|---|---|---|---|
| **PSP-01** | Collection | Create 500,000 VND dynamic payment link | Return payment URL, QR string, and `orderCode` | Unique payment reference generated | **VERIFIED** |
| **PSP-02** | Webhook | Send mock successful payment webhook with valid HMAC | Return HTTP 200 `{"success": true}` | Single ledger journal created | **VERIFIED** |
| **PSP-03** | Webhook Replay | Replay identical webhook payload with same `paymentLinkId` | Return HTTP 200 `{"message": "Duplicate acknowledged"}` | Zero additional journal entries or ledger updates | **VERIFIED** |
| **PSP-04** | Webhook Tampering | Modify amount in payload while retaining original signature | Return HTTP 401 `{"error": "Invalid signature"}` | Transaction remains unconfirmed | **VERIFIED** |
| **PSP-05** | API Refund | Submit partial refund of 200,000 VND on settled transaction | Return refund transaction ID & status `COMPLETED` | Compensating journal entry posted | **VERIFIED** |
| **PSP-06** | KYC Validation | Validate beneficiary bank: Account `9999999999`, Bank `MBBANK` | Return account owner `NGUYEN VAN A` | Account owner verified before saving beneficiary | **VERIFIED** |
| **PSP-07** | Payout Submission | Submit payout of 1,200,000 VND with `Idempotency-Key: pay-tx-001` | Return payout ID and status `PROCESSING` | Balance deducted from tenant settled reserve | **VERIFIED** |
| **PSP-08** | Payout Replay | Resubmit payout with identical `Idempotency-Key: pay-tx-001` | Return original payout ID and status `PROCESSING` | No duplicate payout created | **VERIFIED** |
| **PSP-09** | Payout Status Polling | Poll status for `pay-tx-001` after settlement cycle | Return status `PAID` with gateway reference | Payout transitioned to `paid` | **VERIFIED** |
| **PSP-10** | Payout Reversal | Simulate bank rejection (invalid beneficiary status) | Return status `FAILED` with failure code | Payout transitioned to `failed`, exception raised | **VERIFIED** |

---

## 4. Architectural Provider Adapter Interface

The core platform interacts with payment providers exclusively via the provider-neutral interface contract (`PaymentProviderInterface`):

```php
namespace App\Contracts\Payment;

interface PaymentProviderInterface
{
    public function createPaymentIntent(PaymentIntentRequest $request): PaymentIntentResponse;
    public function verifyWebhookSignature(string $payload, string $signature, array $headers = []): bool;
    public function parseWebhookEvent(string $payload): WebhookEvent;
    public function executeRefund(RefundRequest $request): RefundResponse;
    public function verifyBeneficiary(BeneficiaryValidationRequest $request): BeneficiaryValidationResponse;
    public function submitPayout(PayoutInstruction $instruction): PayoutSubmissionResponse;
    public function queryPayoutStatus(string $providerPayoutId): PayoutStatusResponse;
}
```

---

## 5. Conclusion & Recommendation

The primary provider selected for Vietnam domestic operations is **PayOS (VietQR Pro Enterprise)** paired with **Napas 24/7 Account Verification**, with fallback webhook verification and backward compatibility for legacy SePay notifications. All capability invariants are documented and validated against the sandbox environment.
