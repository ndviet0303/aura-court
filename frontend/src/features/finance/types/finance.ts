export interface SettlementRecord {
  id: number
  date: string
  period_start?: string
  period_end?: string
  gross_amount: number
  platform_fee: number
  gateway_fee: number
  net_amount: number
  status: 'settled' | 'pending' | 'processing' | 'disputed' | string
  notes?: string
}

export interface PayoutRecord {
  id: number
  code: string
  created_at: string
  completed_at?: string | null
  amount: number
  status: 'draft' | 'pending_approval' | 'approved' | 'submitted' | 'processing' | 'paid' | 'failed' | 'rejected' | string
  beneficiary_name: string
  beneficiary_bank: string
  beneficiary_account: string
  current_step: number
  step_description?: string
  tx_hash?: string
  reference?: string
}

export interface BeneficiaryAccount {
  id: number
  provider?: string
  bank_code: string
  bank_name: string
  account_number: string
  account_holder: string
  tax_id?: string
  is_verified: boolean
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

export interface SplitGroup {
  id: number
  code: string
  total_amount: number
  status: 'pending' | 'partially_paid' | 'completed' | 'cancelled' | string
  shares: SplitShare[]
}

export interface SplitShare {
  id: number
  user_id?: number
  name: string
  amount: number
  status: 'pending' | 'paid' | string
  paid_at?: string
}

export interface MonthlyInvoiceData {
  id: number | string
  uuid?: string
  organization_id: number
  recurring_series_id: number
  period_start: string
  period_end: string
  due_at: string
  amount: number
  status: 'pending' | 'paid' | 'overdue'
  payment_reference?: string
  paid_at?: string
  series?: {
    id: number
    court_id: number
    frequency: string
    status: string
    occurrence_count?: number
    starts_at?: string
    ends_at?: string
    court?: {
      id: number
      name: string
      venue?: { id: number; name: string; address?: string; google_maps_url?: string }
    }
  }
}

export interface OccurrenceItem {
  id: number
  recurring_series_id: number
  sequence: number
  starts_at: string
  ends_at: string
  amount: number
  status: string
  court_name?: string
  reservation?: {
    id?: number
    code?: string
    status?: string
  }
}

