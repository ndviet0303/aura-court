import { describe, expect, it, vi } from 'vitest'
import { api } from '@/core/api/client'
import { posApi } from '../api/posApi'

vi.mock('@/core/api/client', () => ({
  api: { get: vi.fn() },
}))

describe('posApi.getActiveReservations', () => {
  it('labels the reservation on the venue clock, first slot start to last slot end', async () => {
    // Reservation slots arrive as UTC instants: 03:00Z is 10:00 in Vietnam.
    vi.mocked(api.get).mockResolvedValue({
      data: [
        {
          id: 7,
          status: 'confirmed',
          slots: [
            { starts_at: '2026-10-12T03:00:00.000000Z', ends_at: '2026-10-12T04:00:00.000000Z' },
            { starts_at: '2026-10-12T04:00:00.000000Z', ends_at: '2026-10-12T05:00:00.000000Z' },
          ],
        },
      ],
    })

    const [reservation] = await posApi.getActiveReservations()

    expect(reservation!.time_slot).toBe('10:00 - 12:00')
  })
})

describe('posApi.getVenues', () => {
  it('turns the admin route translatable venue name into display text', async () => {
    vi.mocked(api.get).mockResolvedValue([
      { id: 3, name: { vi: 'Cơ sở Quận 1', en: 'District 1' } },
    ])

    expect(await posApi.getVenues()).toEqual([{ id: 3, name: 'Cơ sở Quận 1' }])
  })
})
