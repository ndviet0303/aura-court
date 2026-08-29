import { apiV2 } from '@/core/api/client'
import type { VenueWallet, CreditWalletPayload, SpendWalletPayload } from '../types/wallet'

export const walletApi = {
  getWallet: () => apiV2.get<{ balance: number } & Partial<VenueWallet>>('/wallet'),

  credit: (payload: CreditWalletPayload) =>
    apiV2.post('/wallet/credit', payload),

  spend: (payload: SpendWalletPayload) =>
    apiV2.post('/wallet/spend', payload),
}
