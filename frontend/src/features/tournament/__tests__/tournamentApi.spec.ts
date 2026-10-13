import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiV2 } from '@/core/api/client'
import { tournamentApi } from '../api/tournamentApi'
import type { TournamentItem } from '../types/tournament'

vi.mock('@/core/api/client', () => ({ apiV2: { get: vi.fn(), post: vi.fn() } }))

const settings = {
  sport: 'pickleball',
  category: 'Đơn phong trào - DUPR 3.0+',
  venue: 'Aura Sports Complex Tây Hồ',
  city: 'Hà Nội',
  image_url: '/courts/pickleball-outdoor.jpg',
  description: 'Giải pickleball ngoài trời ven Hồ Tây.',
  start_date: '2026-10-10',
  end_date: '2026-10-17',
}

describe('tournamentApi mapping', () => {
  beforeEach(() => vi.clearAllMocks())

  it('reads venue, city, image, dates and lifecycle status from the stored settings', async () => {
    vi.mocked(apiV2.get).mockResolvedValue({
      data: [
        { id: 1, name: 'A', status: 'in_progress', participants_count: 8, settings },
        { id: 2, name: 'B', status: 'finalized', participants_count: 8, settings },
        { id: 3, name: 'C', status: 'ready', participants_count: 4, settings },
        { id: 4, name: 'D', status: 'open', participants_count: 5, settings },
      ],
    })

    const [ongoing, completed, ready, open] = await tournamentApi.list<TournamentItem[]>()

    expect(ongoing).toMatchObject({
      venue: 'Aura Sports Complex Tây Hồ',
      city: 'Hà Nội',
      image_url: '/courts/pickleball-outdoor.jpg',
      category: 'Đơn phong trào - DUPR 3.0+',
      description: 'Giải pickleball ngoài trời ven Hồ Tây.',
      dates: '10/10/2026 - 17/10/2026',
      start_date: '2026-10-10',
      end_date: '2026-10-17',
      status: 'ongoing',
      current_slots: 8,
    })
    expect([completed?.status, ready?.status, open?.status]).toEqual([
      'completed',
      'upcoming',
      'open',
    ])
  })

  it('counts loaded participants on the detail payload', async () => {
    vi.mocked(apiV2.get).mockResolvedValue({
      tournament: {
        id: 5,
        status: 'open',
        settings,
        participants: [{ id: 1 }, { id: 2 }, { id: 3 }],
      },
    })

    const detail = await tournamentApi.getById<TournamentItem>(5)

    expect(apiV2.get).toHaveBeenCalledWith('/tournaments/5')
    expect(detail.current_slots).toBe(3)
    expect(detail.participants).toHaveLength(3)
  })

  it('leaves values the API omits empty instead of inventing them', async () => {
    vi.mocked(apiV2.get).mockResolvedValue({
      tournament: {
        id: 6,
        name: 'E',
        status: 'open',
        // Model accessors: entry_fee is null without a published fee; max_participants is the enforced cap.
        entry_fee: null,
        max_participants: 16,
        settings: {},
        participants: [{ id: 7, seed: 1, user: { name: 'An' } }],
      },
    })

    const detail = await tournamentApi.getById<TournamentItem>(6)

    expect(detail).toMatchObject({
      sport: undefined,
      category: undefined,
      prize_pool: undefined,
      venue: undefined,
      city: undefined,
      description: undefined,
      entry_fee: undefined,
      dates: '',
      max_slots: 16,
      current_slots: 1,
    })
    expect(detail.participants?.[0]).toMatchObject({
      name: 'An',
      club: undefined,
      level: undefined,
      phone: undefined,
      category: undefined,
      avatar: undefined,
    })
  })

  it('shows only the start day, in the venue timezone, when no end date is stored', async () => {
    vi.mocked(apiV2.get).mockResolvedValue({
      tournament: { id: 7, status: 'open', starts_at: '2026-10-14T20:00:00.000000Z', settings: {} },
    })

    const detail = await tournamentApi.getById<TournamentItem>(7)

    expect(detail.dates).toBe('15/10/2026')
  })
})
