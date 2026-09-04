import { api } from '@/core/api/client'
import type { VenueWallet, SpendWalletPayload } from '../types/wallet'

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

  spend: (payload: SpendWalletPayload) =>
    api.post('/wallet/spend', payload),
}
