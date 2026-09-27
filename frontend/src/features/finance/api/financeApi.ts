import { api, apiV2 } from '@/core/api/client'
import type { SettlementRecord, PayoutRecord, BeneficiaryAccount, FinanceExceptionItem } from '../types/finance'
import type { SplitGroup } from '../types/split'

export const financeApi = {
  getBeneficiaries: () => apiV2.get<BeneficiaryAccount[]>('/finance/beneficiaries'),

  storeBeneficiary: (payload: {
    bank_code: string
    account_number: string
    account_holder?: string
    tax_id?: string
    provider?: string
  }) => apiV2.post<{ beneficiary: BeneficiaryAccount } | BeneficiaryAccount>('/finance/beneficiaries', payload),

  // Alias for backward compatibility
  createBeneficiary: (payload: {
    bank_code: string
    account_number: string
    account_holder?: string
    tax_id?: string
    provider?: string
  }) => apiV2.post<{ beneficiary: BeneficiaryAccount } | BeneficiaryAccount>('/finance/beneficiaries', payload),

  getSettlements: () => apiV2.get<{ data: SettlementRecord[] } | SettlementRecord[]>('/finance/settlements'),

  snapshotSettlement: (payload: { period_start: string; period_end: string }) =>
    apiV2.post<{ settlement: SettlementRecord } | SettlementRecord>('/finance/settlements/snapshot', payload),

  getPayouts: () => apiV2.get<{ data: PayoutRecord[] } | PayoutRecord[]>('/finance/payouts'),

  createPayout: (payload: { settlement_id: number; beneficiary_id: number }) =>
    apiV2.post<{ payout: PayoutRecord } | PayoutRecord>('/finance/payouts', payload),

  approvePayout: (payoutId: number | string) =>
    apiV2.post<{ payout: PayoutRecord } | PayoutRecord>(`/finance/payouts/${payoutId}/approve`, {}),

  submitPayout: (payoutId: number | string) =>
    apiV2.post<{ payout: PayoutRecord } | PayoutRecord>(`/finance/payouts/${payoutId}/submit`, {}),

  queryPayoutStatus: (payoutId: number | string) =>
    apiV2.post<{ payout: PayoutRecord } | PayoutRecord>(`/finance/payouts/${payoutId}/status`, {}),

  getExceptions: <T = { data: FinanceExceptionItem[] }>() => apiV2.get<T>('/finance/exceptions'),

  resolveException: (id: number | string, payload: { note: string }) =>
    apiV2.post(`/finance/exceptions/${id}/resolve`, payload),

  getSplitGroup: (splitId: number) => apiV2.get<{ split: SplitGroup }>(`/split-groups/${splitId}`),

  getInvoice: <T = unknown>(id: number | string) =>
    apiV2.get<T>(`/monthly-invoices/${id}`),

  getRecurringSeries: <T = unknown>() =>
    apiV2.get<T>('/recurring-series'),


  createReservationSplit: <T = unknown>(reservationId: number | string, payload: any) =>
    apiV2.post<T>(`/reservations/${reservationId}/split`, payload),
}
