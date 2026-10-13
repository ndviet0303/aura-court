import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useTournamentsList } from '../composables/useTournamentsList'
import type { TournamentItem } from '../types/tournament'
import { usePaymentProcessing } from '@/features/booking/composables/usePaymentProcessing'
import { tournamentApi } from '../api/tournamentApi'
import { useAuthStore } from '@/stores/auth'

const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
  useRoute: () => ({
    query: { type: 'tournament', tournament_uuid: 'tourney-uuid-1', name: 'Athlete Demo' },
    params: { code: 'ORD1' },
  }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('../api/tournamentApi', () => ({
  tournamentApi: {
    list: vi.fn(),
    getById: vi.fn(),
    checkInParticipant: vi.fn(),
    register: vi.fn(),
    getRegistration: vi.fn(),
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

describe('Task 1.2 Regression: Local tournament registration and payment confirmation', () => {
  const sampleTournament: TournamentItem = {
    id: 1,
    uuid: 'tourney-uuid-1',
    name: 'Aura Master Cup 2026',
    slug: 'aura-master-cup-2026',
    sport: 'badminton',
    format: 'single_elimination',
    status: 'open',
    prize_pool: '20.000.000 ₫',
    dates: '15/09/2026',
    venue: 'Aura Arena',
    city: 'Hồ Chí Minh',
    current_slots: 5,
    max_slots: 16,
    entry_fee: '300.000 ₫',
    image_url: '/courts/badminton-0.jpg',
    category: 'Cầu Lông Đơn',
    version: 1,
  }

  beforeEach(() => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const auth = useAuthStore()
    auth.user = { id: 1, name: 'Nguyễn Văn A' } as any
    vi.clearAllMocks()
    vi.mocked(tournamentApi.list).mockResolvedValue([sampleTournament])
  })

  it('Task 8.9 Fix: registerAthlete calls tournamentApi.register with authoritative server persistence and receipt', async () => {
    vi.mocked(tournamentApi.register).mockResolvedValue({
      participant: { id: 88, user_id: 1, name: 'Nguyễn Văn A' },
      receipt: { receipt_code: 'RCP-TRN-1-88', entry_fee: 300000, status: 'confirmed' },
      already_registered: false,
      message: 'Đăng ký tham gia giải thành công.',
    } as any)

    const list = useTournamentsList()
    list.selectedTournamentForRegister.value = sampleTournament

    // Register athlete
    await list.registerAthlete({
      name: 'Nguyễn Văn A',
      skillLevel: 3,
    })

    // 1. Authoritative backend register API was called with tournament UUID and payload
    expect(tournamentApi.register).toHaveBeenCalledWith('tourney-uuid-1', {
      name: 'Nguyễn Văn A',
      skillLevel: 3,
    })

    // 2. Tournament was added to registered list
    expect(list.registeredTournamentIds.value).toContain(sampleTournament.id)
  })

  it('Task 8.9 Fix: handleWalletPay calls tournamentApi.register and stores authoritative receipt', async () => {
    vi.mocked(tournamentApi.getById).mockResolvedValue(sampleTournament)
    vi.mocked(tournamentApi.register).mockResolvedValue({
      participant: { id: 88, user_id: 1, name: 'Nguyễn Văn A' },
      receipt: { receipt_code: 'RCP-TRN-1-88', entry_fee: 300000, status: 'confirmed' },
      already_registered: false,
      message: 'Đăng ký tham gia giải đấu thành công.',
    } as any)

    const payment = usePaymentProcessing()
    await payment.startTimers() // initializes session

    expect(payment.isTournament.value).toBe(true)

    // Trigger wallet payment for tournament
    await payment.handleWalletPay()

    // Authoritative backend registration endpoint was called
    expect(tournamentApi.register).toHaveBeenCalledWith(
      'tourney-uuid-1',
      expect.objectContaining({
        name: 'Athlete Demo',
      }),
    )
    expect(payment.status.value).toBe('confirmed')
    expect(payment.showSuccessModal.value).toBe(true)
  })

  it('charges the published fee, and nothing when none is published', async () => {
    for (const [entry_fee, amount] of [
      ['300.000 ₫ / VĐV', 300000],
      [undefined, 0],
    ] as const) {
      vi.mocked(tournamentApi.getById).mockResolvedValue({ ...sampleTournament, entry_fee })
      const payment = usePaymentProcessing()
      await payment.startTimers()

      expect(payment.paymentAmount.value).toBe(amount)
      expect(payment.payment.value?.amount).toBe(amount)
    }
  })

  it('offers nothing to pay when the tournament cannot be loaded', async () => {
    vi.mocked(tournamentApi.getById).mockRejectedValue(new Error('Not found'))
    const payment = usePaymentProcessing()
    await payment.startTimers()

    expect(payment.tournament.value).toBeNull()
    expect(payment.payment.value).toBeNull()
    expect(payment.error.value).toBe('Not found')

    await payment.handleWalletPay()
    expect(tournamentApi.register).not.toHaveBeenCalled()
  })

  it('Task 8.9 Fix: persisted tournament registration is preserved on reload from server participants', async () => {
    const tournamentWithParticipant: TournamentItem = {
      ...sampleTournament,
      participants: [{ id: 88, user_id: 1, name: 'Nguyễn Văn A' } as any],
    }
    vi.mocked(tournamentApi.list).mockResolvedValue([tournamentWithParticipant])

    const session2 = useTournamentsList()
    await session2.loadTournaments()
    expect(session2.loading.value).toBe(false)

    // In session 2, 'my' tab shows the registered tournament because it came from authoritative server data!
    session2.activeTab.value = 'my'
    expect(session2.filteredTournaments.value).toHaveLength(1)
    expect(session2.filteredTournaments.value[0]?.id).toBe(sampleTournament.id)
  })
})
