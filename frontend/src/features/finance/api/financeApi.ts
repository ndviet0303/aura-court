import { api } from '@/core/api/client'
import type {
  SettlementRecord,
  PayoutRecord,
  BeneficiaryAccount,
  FinanceExceptionItem,
  OwnerPaymentExceptionPage,
} from '../types/finance'
import type { SplitGroup } from '../types/split'

export const financeApi = {
  getBeneficiaries: () => api.get<BeneficiaryAccount[]>('/finance/beneficiaries'),

  storeBeneficiary: (payload: {
    bank_code: string
    account_number: string
    account_holder?: string
    tax_id?: string
    provider?: string
  }) =>
    api.post<{ beneficiary: BeneficiaryAccount } | BeneficiaryAccount>(
      '/finance/beneficiaries',
      payload,
    ),

  // Alias for backward compatibility
  createBeneficiary: (payload: {
    bank_code: string
    account_number: string
    account_holder?: string
    tax_id?: string
    provider?: string
  }) =>
    api.post<{ beneficiary: BeneficiaryAccount } | BeneficiaryAccount>(
      '/finance/beneficiaries',
      payload,
    ),

  getSettlements: () =>
    api.get<{ data: SettlementRecord[] } | SettlementRecord[]>('/finance/settlements'),

  snapshotSettlement: (payload: { period_start: string; period_end: string }) =>
    api.post<{ settlement: SettlementRecord } | SettlementRecord>(
      '/finance/settlements/snapshot',
      payload,
    ),

  getPayouts: () => api.get<{ data: PayoutRecord[] } | PayoutRecord[]>('/finance/payouts'),

  createPayout: (payload: { settlement_id: number; beneficiary_id: number }) =>
    api.post<{ payout: PayoutRecord } | PayoutRecord>('/finance/payouts', payload),

  approvePayout: (payoutId: number | string) =>
    api.post<{ payout: PayoutRecord } | PayoutRecord>(`/finance/payouts/${payoutId}/approve`, {}),

  submitPayout: (payoutId: number | string) =>
    api.post<{ payout: PayoutRecord } | PayoutRecord>(`/finance/payouts/${payoutId}/submit`, {}),

  queryPayoutStatus: (payoutId: number | string) =>
    api.post<{ payout: PayoutRecord } | PayoutRecord>(`/finance/payouts/${payoutId}/status`, {}),

  getExceptions: <T = { data: FinanceExceptionItem[] }>() => api.get<T>('/finance/exceptions'),

  resolveException: (id: number | string, payload: { note: string }) =>
    api.post(`/finance/exceptions/${id}/resolve`, payload),

  getOwnerPaymentExceptions: (page = 1) =>
    api.get<OwnerPaymentExceptionPage>(`/owner-payment-exceptions?page=${page}`),

  getSplitGroup: (splitId: number) => api.get<{ split: SplitGroup }>(`/split-groups/${splitId}`),

  createReservationSplit: <T = unknown>(reservationId: number | string, payload: any) =>
    api.post<T>(`/reservations/${reservationId}/split`, payload),
}
