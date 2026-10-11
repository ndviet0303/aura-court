export type ContractStatus =
  'active' | 'confirmed' | 'overdue' | 'pending' | 'completed' | 'cancelled' | 'draft'

export interface ContractOccurrence {
  id: number
  sequence: number
  starts_at: string
  ends_at: string
  amount: number
  status: 'confirmed' | 'held' | 'released' | 'cancelled'
  reservation_id?: number | null
}

export interface ContractInvoice {
  id: number
  uuid?: string
  period_start: string
  period_end: string
  due_at: string
  amount: number
  status: 'pending' | 'paid' | 'overdue' | 'cancelled' | 'guaranteed' | 'zero_payable'
  payment_reference?: string | null
  paid_at?: string | null
}

export interface RecurringContractItem {
  id: number
  contract_code: string
  customer_name: string
  phone: string
  email?: string
  court_id: number
  court_name: string
  venue_name: string
  venue_id?: number
  time_slot: string
  weekday: string
  frequency: 'weekly' | 'biweekly' | 'monthly'
  start_date: string
  end_date: string
  total_monthly_amount: number
  deposit_amount: number
  status: ContractStatus
  occurrences_count?: number
  notes?: string
  invoices: ContractInvoice[]
  occurrences?: ContractOccurrence[]
}

export interface ContractStats {
  active_contracts: number
  total_contracts: number
  mrr_amount: number
  overdue_cycles: number
  prime_time_utilization: number
}

export interface ContractsApiResponse {
  data: any[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  stats: ContractStats
}
