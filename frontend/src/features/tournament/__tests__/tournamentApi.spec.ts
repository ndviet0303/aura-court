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
})
