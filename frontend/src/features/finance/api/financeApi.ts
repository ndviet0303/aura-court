import { api, apiV2 } from '@/core/api/client'
import type { SettlementRecord, PayoutRecord, BeneficiaryAccount, FinanceExceptionItem, SplitGroup } from '../types/finance'

export const financeApi = {
  getBeneficiaries: () => apiV2.get<BeneficiaryAccount[]>('/finance/beneficiaries'),

  createBeneficiary: (payload: any) => apiV2.post<BeneficiaryAccount>('/finance/beneficiaries', payload),

  getSettlements: () => apiV2.get<SettlementRecord[]>('/finance/settlements'),

  snapshotSettlement: () => apiV2.post<SettlementRecord>('/finance/settlements/snapshot'),

  getPayouts: () => apiV2.get<PayoutRecord[]>('/finance/payouts'),

  createPayout: (payload: any) => apiV2.post<PayoutRecord>('/finance/payouts', payload),

  approvePayout: (payoutId: number) => apiV2.post(`/finance/payouts/${payoutId}/approve`),

  submitPayout: (payoutId: number) => apiV2.post(`/finance/payouts/${payoutId}/submit`),

  queryPayoutStatus: (payoutId: number) => apiV2.post(`/finance/payouts/${payoutId}/status`),

  getExceptions: () => apiV2.get<FinanceExceptionItem[]>('/finance/exceptions'),

  resolveException: (id: number, payload: { note: string }) =>
    apiV2.post(`/finance/exceptions/${id}/resolve`, payload),

  getSplitGroup: (splitId: number) => apiV2.get<SplitGroup>(`/split-groups/${splitId}`),

  paySplitShare: (shareId: number, payload: any) => apiV2.post(`/split-shares/${shareId}/pay`, payload),

  coverSplitShortfall: (splitId: number) => apiV2.post(`/split-groups/${splitId}/cover-shortfall`),
}
