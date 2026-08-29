import { api, apiV2 } from '@/core/api/client'
import type { SettlementRecord, PayoutRecord, BeneficiaryAccount, FinanceExceptionItem, SplitGroup } from '../types/finance'

export const financeApi = {
  getBeneficiaries: <T = BeneficiaryAccount[]>() => apiV2.get<T>('/finance/beneficiaries'),

  createBeneficiary: (payload: any) => apiV2.post<BeneficiaryAccount>('/finance/beneficiaries', payload),

  getSettlements: <T = { data: SettlementRecord[] }>() => apiV2.get<T>('/finance/settlements'),

  snapshotSettlement: (payload?: any) => apiV2.post<SettlementRecord>('/finance/settlements/snapshot', payload),

  getPayouts: <T = { data: PayoutRecord[] }>() => apiV2.get<T>('/finance/payouts'),

  createPayout: (payload: any) => apiV2.post<PayoutRecord>('/finance/payouts', payload),

  approvePayout: (payoutId: number | string) => apiV2.post(`/finance/payouts/${payoutId}/approve`, {}),

  submitPayout: (payoutId: number | string) => apiV2.post(`/finance/payouts/${payoutId}/submit`, {}),

  queryPayoutStatus: (payoutId: number | string) => apiV2.post(`/finance/payouts/${payoutId}/status`, {}),

  getExceptions: <T = { data: FinanceExceptionItem[] }>() => apiV2.get<T>('/finance/exceptions'),

  resolveException: (id: number | string, payload: { note: string }) =>
    apiV2.post(`/finance/exceptions/${id}/resolve`, payload),

  getSplitGroup: (splitId: number | string) => apiV2.get<SplitGroup>(`/split-groups/${splitId}`),

  paySplitShare: (shareId: number | string, payload: any) => apiV2.post(`/split-shares/${shareId}/pay`, payload),

  coverSplitShortfall: (splitId: number | string) => apiV2.post(`/split-groups/${splitId}/cover-shortfall`),

  getInvoice: <T = unknown>(id: number | string) =>
    apiV2.get<T>(`/monthly-invoices/${id}`),

  getRecurringSeries: <T = unknown>() =>
    apiV2.get<T>('/recurring-series'),

  payInvoice: <T = unknown>(id: number | string, payload: any) =>
    apiV2.post<T>(`/monthly-invoices/${id}/pay`, payload),

  createReservationSplit: <T = unknown>(reservationId: number | string, payload: any) =>
    apiV2.post<T>(`/reservations/${reservationId}/split`, payload),
}
