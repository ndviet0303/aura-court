import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useTournamentsList } from '../composables/useTournamentsList'
import type { TournamentItem } from '../types/tournament'
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

  it('paid entry keeps the modal open on the owner QR until the receipt registers the player', async () => {
    const payment = {
      id: 5,
      provider: 'sepay',
      reference: 'ACTOURNAMENT0000001',
      transfer_content: 'ACTOURNAMENT0000001',
      amount: 300000,
      currency: 'VND',
      status: 'pending',
      expires_at: null,
      paid_at: null,
      receiver: { bank_code: 'MBBank', account_name: 'Venue Owner', account_number: '0987654321' },
      qr_url: 'https://img.vietqr.io/x.png',
    }
    vi.mocked(tournamentApi.register).mockResolvedValue({
      registered: false,
      participant: null,
      receipt: null,
      payment,
      already_registered: false,
      message: 'Quét mã QR để thanh toán lệ phí đăng ký.',
    })

    const list = useTournamentsList()
    list.selectedTournamentForRegister.value = sampleTournament
    await list.registerAthlete({ name: 'Nguyễn Văn A' })

    expect(list.selectedTournamentForRegister.value).toEqual(sampleTournament)
    expect(list.registrationPayment.value).toEqual(payment)
    expect(list.registeredTournamentIds.value).not.toContain(sampleTournament.id)

    await list.registrationPaid()
    expect(list.registeredTournamentIds.value).toContain(sampleTournament.id)
    expect(list.selectedTournamentForRegister.value).toBeNull()
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
