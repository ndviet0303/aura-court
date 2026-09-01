import { api } from '@/core/api/client'
import type { VenueWallet, CreditWalletPayload, SpendWalletPayload } from '../types/wallet'

export const walletApi = {
  getWallet: () =>
    api.get<{
      id: string
      balance: number
      currency: string
      points: number
      transactions: any[]
    }>('/wallet'),

  getBalance: () =>
    api.get<{ balance: number; currency: string }>('/wallet/balance'),

  credit: (payload: CreditWalletPayload | { amount: number; method?: string; note?: string }) =>
    api.post<{ message: string; balance: number; transaction_id: number }>('/wallet/topup', payload),

  topup: (amount: number, note?: string) =>
    api.post<{ message: string; balance: number; transaction_id: number }>('/wallet/topup', { amount, note }),

  spend: (payload: SpendWalletPayload) =>
    api.post('/wallet/spend', payload),
}
