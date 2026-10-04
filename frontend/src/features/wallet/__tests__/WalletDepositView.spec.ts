import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useWallet } from '../composables/useWallet'
import { walletApi } from '../api/walletApi'
import { mount, flushPromises } from '@vue/test-utils'
import WalletView from '../views/WalletView.vue'
import { i18n } from '@/i18n'

vi.mock('vue-router', () => ({ useRoute: () => ({ query: { organization_id: '73' } }) }))

vi.mock('../api/walletApi', () => ({
  walletApi: {
    getWallet: vi.fn(),
    getBalance: vi.fn(),
    spend: vi.fn(),
    initiateDeposit: vi.fn(),
    getDepositStatus: vi.fn(),
    getDeposits: vi.fn(),
  },
}))

describe('Wallet Deposit and Balance Management', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('prohibits fallback balances when server request fails', async () => {
    vi.mocked(walletApi.getWallet).mockRejectedValueOnce(new Error('API failure'))

    const wallet = useWallet()
    await wallet.loadWalletData()

    expect(wallet.wallets.value).toEqual([])
    expect(wallet.totalBalance.value).toBe(0)
    expect(wallet.error.value).toBe('API failure')
  })

  it('populates actual tenant balance from authoritative server response', async () => {
    vi.mocked(walletApi.getWallet).mockResolvedValueOnce({
      id: 'wallet-org-1',
      organization_id: 1,
      balance: 750000,
      currency: 'VND',
      points: 750,
      transactions: [
        {
          id: 1,
          type: 'credit',
          amount: 750000,
          balance_after: 750000,
          reference: 'DEP-123',
          created_at: '2026-09-09T10:00:00Z',
        },
      ],
    })

    const wallet = useWallet()
    await wallet.loadWalletData()

    expect(wallet.error.value).toBe('')
    expect(wallet.totalBalance.value).toBe(750000)
    expect(wallet.wallets.value).toHaveLength(1)
    expect(wallet.wallets.value[0]?.balance).toBe(750000)
    expect(wallet.filteredTransactions.value).toHaveLength(1)
  })

  it('initiates deposit via server API and exposes server transfer info', async () => {
    vi.mocked(walletApi.getWallet).mockResolvedValueOnce({
      id: 'wallet-org-1',
      organization_id: 1,
      balance: 100000,
      currency: 'VND',
      points: 100,
      transactions: [],
    })

    vi.mocked(walletApi.initiateDeposit).mockResolvedValueOnce({
      id: 99,
      reference: 'DEP-SERVER-001',
      organization_id: 1,
      amount: 500000,
      currency: 'VND',
      status: 'pending',
      provider: 'sepay',
      transfer_info: {
        bank: 'TPBank',
        account_number: '83403032005',
        account_name: 'AURA COURT',
        code: 'ORD NAP 1 DEP-SERVER-001',
        amount: 500000,
        qr_url: 'https://img.vietqr.io/image/TPBank-83403032005-compact2.png',
      },
      created_at: '2026-09-09T10:00:00Z',
    })

    const wallet = useWallet()
    await wallet.loadWalletData()
    wallet.openDepositModal()
    wallet.depositAmount.value = 500000

    await wallet.proceedToPaymentStep()

    expect(walletApi.initiateDeposit).toHaveBeenCalledWith({
      amount: 500000,
      organization_id: 1,
      provider: 'sepay',
    })
    expect(wallet.depositStep.value).toBe('sepay_pay')
    expect(wallet.activeDeposit.value?.reference).toBe('DEP-SERVER-001')
    expect(wallet.transferInfo.value.memo).toBe('ORD NAP 1 DEP-SERVER-001')
  })

  it('keeps pending status and does not falsely credit balance on unverified confirmation', async () => {
    vi.mocked(walletApi.getWallet).mockResolvedValue({
      id: 'wallet-org-1',
      organization_id: 1,
      balance: 200000,
      currency: 'VND',
      points: 200,
      transactions: [],
    })

    vi.mocked(walletApi.initiateDeposit).mockResolvedValueOnce({
      id: 101,
      reference: 'DEP-PENDING-01',
      organization_id: 1,
      amount: 300000,
      currency: 'VND',
      status: 'pending',
      provider: 'sepay',
      transfer_info: {
        bank: 'TPBank',
        account_number: '83403032005',
        account_name: 'AURA COURT',
        code: 'ORD NAP 1 DEP-PENDING-01',
        amount: 300000,
        qr_url: 'https://img.vietqr.io/image/test.png',
      },
      created_at: '2026-09-09T10:00:00Z',
    })

    vi.mocked(walletApi.getDepositStatus).mockResolvedValueOnce({
      id: 101,
      reference: 'DEP-PENDING-01',
      organization_id: 1,
      amount: 300000,
      currency: 'VND',
      status: 'pending',
      provider: 'sepay',
      wallet_balance: 200000,
      created_at: '2026-09-09T10:00:00Z',
    })

    const wallet = useWallet()
    await wallet.loadWalletData()
    wallet.openDepositModal()
    wallet.depositAmount.value = 300000
    await wallet.proceedToPaymentStep()

    await wallet.confirmDeposit()

    expect(walletApi.getDepositStatus).toHaveBeenCalledWith('DEP-PENDING-01')
    expect(wallet.totalBalance.value).toBe(200000)
    expect(wallet.notice.value).toContain('chờ ngân hàng/SePay xác thực')
  })

  it('updates balance when deposit status returns succeeded', async () => {
    vi.mocked(walletApi.getWallet)
      .mockResolvedValueOnce({
        id: 'wallet-org-1',
      organization_id: 1,
        balance: 200000,
        currency: 'VND',
        points: 200,
        transactions: [],
      })
      .mockResolvedValueOnce({
        id: 'wallet-org-1',
      organization_id: 1,
        balance: 500000,
        currency: 'VND',
        points: 500,
        transactions: [],
      })

    vi.mocked(walletApi.initiateDeposit).mockResolvedValueOnce({
      id: 102,
      reference: 'DEP-SUCCEED-01',
      organization_id: 1,
      amount: 300000,
      currency: 'VND',
      status: 'pending',
      provider: 'sepay',
      transfer_info: {
        bank: 'TPBank',
        account_number: '83403032005',
        account_name: 'AURA COURT',
        code: 'ORD NAP 1 DEP-SUCCEED-01',
        amount: 300000,
        qr_url: 'https://img.vietqr.io/image/test.png',
      },
      created_at: '2026-09-09T10:00:00Z',
    })

    vi.mocked(walletApi.getDepositStatus).mockResolvedValueOnce({
      id: 102,
      reference: 'DEP-SUCCEED-01',
      organization_id: 1,
      amount: 300000,
      currency: 'VND',
      status: 'succeeded',
      provider: 'sepay',
      wallet_balance: 500000,
      succeeded_at: '2026-09-09T10:05:00Z',
      created_at: '2026-09-09T10:00:00Z',
    })

    const wallet = useWallet()
    await wallet.loadWalletData()
    wallet.openDepositModal()
    wallet.depositAmount.value = 300000
    await wallet.proceedToPaymentStep()

    await wallet.confirmDeposit()

    expect(wallet.totalBalance.value).toBe(500000)
    expect(wallet.notice.value).toContain('Nạp tiền thành công')
  })

  it('keeps the wallet selected by the route when submitting a blocked direct-cohort deposit', async () => {
    vi.mocked(walletApi.getWallet).mockResolvedValue({
      id: 'wallet-org-73', organization_id: 73, balance: 500000, currency: 'VND', points: 0, transactions: [],
    })
    vi.mocked(walletApi.initiateDeposit).mockRejectedValue({
      status: 409, code: 'DIRECT_OWNER_PAYMENTS_ENABLED', message: 'Thanh toán trực tiếp cho chủ sân.',
    })

    const wrapper = mount(WalletView, { global: { plugins: [i18n] } })
    await flushPromises()
    expect(walletApi.getWallet).toHaveBeenCalledWith(73)
    await wrapper.find('.wallet-header .btn-primary').trigger('click')
    await wrapper.find('.modal-backdrop button.btn-primary').trigger('click')
    await flushPromises()

    expect(walletApi.initiateDeposit).toHaveBeenCalledWith({ amount: 200000, organization_id: 73, provider: 'sepay' })
    expect(wrapper.text()).toContain('Thanh toán trực tiếp cho chủ sân.')
    expect(wrapper.text()).toContain('500.000')
    wrapper.unmount()
  })

})
