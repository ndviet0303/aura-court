import { describe, expect, it, vi } from 'vitest'
import { apiV2 } from '@/core/api/client'
import { posApi } from '../api/posApi'

vi.mock('@/core/api/client', () => ({
  apiV2: { get: vi.fn() },
}))

describe('posApi.getActiveReservations', () => {
  it('labels the reservation on the venue clock, first slot start to last slot end', async () => {
    // V2 reservation slots arrive as UTC instants: 03:00Z is 10:00 in Vietnam.
    vi.mocked(apiV2.get).mockResolvedValue({
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
