import { api } from '@/core/api/client'
import type { VenueWallet, SpendWalletPayload } from '../types/wallet'

export const walletApi = {
  getWallet: (organizationId?: number) =>
    api.get<{
      id: string
      organization_id: number
      balance: number
      currency: string
      points: number
      transactions: any[]
    }>('/wallet', { params: organizationId === undefined ? undefined : { organization_id: organizationId } }),

  getBalance: () =>
    api.get<{ balance: number; currency: string }>('/wallet/balance'),

  spend: (payload: SpendWalletPayload) =>
    api.post('/wallet/spend', payload),

  initiateDeposit: (payload: {
    amount: number
    organization_id?: number
    provider?: string
    idempotency_key?: string
  }) =>
    api.post<{
      id: number
      reference: string
      organization_id: number
      amount: number
      currency: string
      status: string
      provider: string
      transfer_info: {
        bank: string
        account_number: string
        account_name: string
        code: string
        amount: number
        qr_url: string
      }
      created_at: string
    }>('/wallet/deposits', payload),

  getDepositStatus: (reference: string) =>
    api.get<{
      id: number
      reference: string
      organization_id: number
      amount: number
      currency: string
      status: string
      provider: string
      transfer_info?: any
      wallet_balance: number
      succeeded_at?: string
      failed_at?: string
      created_at: string
    }>(`/wallet/deposits/${encodeURIComponent(reference)}`),

  getDeposits: (organizationId?: number) =>
    api.get<{
      deposits: Array<{
        id: number
        reference: string
        organization_id: number
        amount: number
        currency: string
        status: string
        provider: string
        succeeded_at?: string
        failed_at?: string
        created_at: string
      }>
    }>('/wallet/deposits', {
      params: organizationId ? { organization_id: organizationId } : undefined,
    }),
}
