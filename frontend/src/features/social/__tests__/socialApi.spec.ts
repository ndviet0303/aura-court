import { describe, expect, it, vi } from 'vitest'
import { api } from '@/core/api/client'
import { socialApi } from '../api/socialApi'

vi.mock('@/core/api/client', () => ({ api: { get: vi.fn() } }))

describe('socialApi.getFeed', () => {
  it('counts reaction rows per type for the feed badges', async () => {
    vi.mocked(api.get).mockResolvedValue({
      data: [
        {
          id: 1,
          reactions: [
            { reaction_type: 'like' },
            { reaction_type: 'like' },
            { reaction_type: 'fire' },
          ],
        },
        { id: 2, reactions: [] },
      ],
      current_page: 1,
      last_page: 3,
    })

    const res = await socialApi.getFeed()

    expect(res.data.map((p) => p.reactions_counts)).toEqual([{ like: 2, fire: 1 }, {}])
    expect(res.last_page).toBe(3)
  })
})
