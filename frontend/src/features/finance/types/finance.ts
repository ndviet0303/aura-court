export interface SettlementRecord {
  id: number
  organization_id?: number
  period_start: string
  period_end: string
  eligible_at?: string | null
  gross_amount: number
  refund_amount?: number
  provider_fee_amount?: number
  commission_amount?: number
  tenant_payable: number
  currency?: string
  status: 'eligible' | 'pending' | 'settled' | string
  snapshotted_at?: string | null
  payouts?: PayoutRecord[]
  // Legacy aliases
  date?: string
  platform_fee?: number
  gateway_fee?: number
  net_amount?: number
  notes?: string
}

export interface PayoutRecord {
  id: number
  organization_id?: number
  settlement_id?: number
  beneficiary_id?: number
  amount: number
  currency?: string
  provider?: string
  provider_payout_id?: string | null
  idempotency_key?: string
  status:
    | 'draft'
    | 'approved'
    | 'submitted'
    | 'processing'
    | 'paid'
    | 'failed'
    | 'reversed'
    | 'pending_approval'
    | 'rejected'
    | string
  failure_reason?: string | null
  approved_at?: string | null
  submitted_at?: string | null
  paid_at?: string | null
  failed_at?: string | null
  reversed_at?: string | null
  created_at: string
  updated_at?: string
  completed_at?: string | null
  reference?: string
  code?: string
  beneficiary_name?: string
  beneficiary_bank?: string
  beneficiary_account?: string
  current_step?: number
  step_description?: string
  tx_hash?: string
  settlement?: SettlementRecord
  beneficiary?: BeneficiaryAccount
}

export interface BeneficiaryAccount {
  id: number
  organization_id?: number
  provider?: string
  provider_beneficiary_id?: string | null
  bank_code: string
  bank_name?: string
  account_number: string
  account_holder: string
  tax_id?: string
  status?: 'verified' | 'pending' | 'rejected' | string
  is_verified?: boolean
  verified_at?: string | null
  updated_at?: string
}

export interface FinanceExceptionItem {
  id: number
  code: string
  type: 'discrepancy' | 'gateway_timeout' | 'chargeback' | 'bank_mismatch' | string
  description: string
  amount: number
  status: 'unresolved' | 'resolved' | string
  created_at: string
  resolution_note?: string
}

/** A direct-owner payment exception as the owner's read-only lookup returns it (FR-81). */
export interface OwnerPaymentException {
  id: number
  code: string
  status: string
  amount: number
  currency: string
  payment_code: string | null
  event_at: string | null
  connection: { bank_code: string | null; account_last_four: string | null }
  purpose: string | null
  /** Set only when the payment code matched a booking's payment request. */
  booking_code: string | null
  detected_at: string
}

/** UC-4.12 steps 4-5: one exception with the booking its code named, null when it named none. */
export interface OwnerPaymentExceptionDetail extends OwnerPaymentException {
  booking: {
    code: string
    status: string
    release_reason: string | null
    total_amount: number
    /** ISO-8601 UTC; null once the booking no longer waits for payment. */
    payment_deadline: string | null
    court: string | null
    venue: string | null
    starts_at: string | null
    ends_at: string | null
    booker: { name: string | null; phone: string | null; email: string | null }
  } | null
}

export interface OwnerPaymentExceptionPage {
  data: OwnerPaymentException[]
  meta: { current_page: number; last_page: number; total: number }
}
