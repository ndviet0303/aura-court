import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { useWallet } from '../composables/useWallet'
import { walletApi } from '../api/walletApi'
import VenueWalletsList from '../components/VenueWalletsList.vue'
import { i18n } from '@/i18n'

vi.mock('../api/walletApi', () => ({ walletApi: { getWallet: vi.fn() } }))

const serverWallet = {
  id: 'wallet-org-7',
  organization_id: 7,
  balance: 4488000,
  currency: 'VND',
  points: 2864,
  membership_tier: 'Gold Elite All-Access',
  transactions: [],
}

async function loadWallet() {
  const wallet = useWallet(7)
  await wallet.loadWalletData()
  const list = mount(VenueWalletsList, {
    props: { wallets: wallet.wallets.value },
    global: { plugins: [i18n] },
  })
  return { wallet, list }
}

describe('Wallet loyalty points and membership tier', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders ledger points and the active membership tier from the server', async () => {
    vi.mocked(walletApi.getWallet).mockResolvedValueOnce(serverWallet)

    const { wallet, list } = await loadWallet()

    // Not balance / 1000 (4,488) and not a hard-coded Gold tier.
    expect(wallet.wallets.value[0]).toMatchObject({
      balance: 4488000,
      points: 2864,
      tier_label: 'Gold Elite All-Access',
    })
    expect(wallet.totalPoints.value).toBe(2864)
    expect(list.find('.tier-pill').text()).toBe('GOLD ELITE ALL-ACCESS')
    expect(list.find('.pts-value').text()).toBe(`${(2864).toLocaleString()} pts`)
  })

  it('shows no tier when the customer has no active membership', async () => {
    vi.mocked(walletApi.getWallet).mockResolvedValueOnce({
      ...serverWallet,
      points: 0,
      membership_tier: null,
    })

    const { wallet, list } = await loadWallet()

    expect(wallet.wallets.value[0]?.tier_label).toBeNull()
    expect(wallet.totalPoints.value).toBe(0)
    expect(list.find('.tier-pill').exists()).toBe(false)
  })
})
