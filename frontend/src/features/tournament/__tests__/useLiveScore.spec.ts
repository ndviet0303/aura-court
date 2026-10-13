import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useLiveScore } from '../composables/useLiveScore'
import { tournamentApi } from '../api/tournamentApi'
import type { TournamentItem } from '../types/tournament'

vi.mock('vue-router', () => ({ useRoute: () => ({ params: { id: '7' } }) }))
vi.mock('../api/tournamentApi', () => ({
  tournamentApi: { getById: vi.fn(), list: vi.fn(), scoreFixture: vi.fn() },
}))

// What tournamentApi.getById hands the page: one fixture between participants 11 and 12.
const tournament = {
  id: 7,
  name: 'Aura Cup',
  version: 4,
  participants: [
    { id: 11, name: 'An' },
    { id: 12, name: 'Bình' },
  ],
  fixtures: [
    {
      id: 31,
      round: 1,
      position: 1,
      status: 'pending',
      participant_one_id: 11,
      participant_two_id: 12,
    },
  ],
} as unknown as TournamentItem

async function mountLiveScore() {
  let state!: ReturnType<typeof useLiveScore>
  mount(
    defineComponent({
      setup() {
        state = useLiveScore()
        return () => null
      },
    }),
  )
  await flushPromises()
  return state
}

function rally(state: ReturnType<typeof useLiveScore>, player: 1 | 2, points = 21) {
  for (let i = 0; i < points; i++) state.addPoint(player)
}

describe('useLiveScore result submission', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(tournamentApi.getById).mockResolvedValue(tournament)
  })

  it('submits the finished match once as a result with its winner and every set', async () => {
    const state = await mountLiveScore()
    rally(state, 1)
    rally(state, 2)
    rally(state, 2, 5)
    rally(state, 1)
    expect(tournamentApi.scoreFixture).not.toHaveBeenCalled()

    vi.mocked(tournamentApi.scoreFixture).mockResolvedValue({})
    await state.submitResult()

    expect(tournamentApi.scoreFixture).toHaveBeenCalledExactlyOnceWith(7, 31, {
      expected_version: 4,
      winner_id: 11,
      score: {
        p1: 21,
        p2: 5,
        sets: [
          { set: 1, p1: 21, p2: 0, winner: 1 },
          { set: 2, p1: 0, p2: 21, winner: 2 },
          { set: 3, p1: 21, p2: 5, winner: 1 },
        ],
      },
    })
    expect(state.activeMatch.value?.pending_result).toBeUndefined()
    expect(state.activeMatch.value?.tournament_version).toBe(5)

    state.undoPoint()
    expect(state.activeMatch.value?.status).toBe('completed')
  })

  it('shows a rejected result to the umpire and retries against the resynced version', async () => {
    const state = await mountLiveScore()
    rally(state, 2)
    rally(state, 2)
    vi.mocked(tournamentApi.getById).mockResolvedValueOnce({ ...tournament, version: 6 })
    vi.mocked(tournamentApi.scoreFixture)
      .mockRejectedValueOnce({ status: 409, code: 'RESOURCE_CONFLICT', message: 'conflict' })
      .mockResolvedValueOnce({})

    await state.submitResult()

    expect(state.activeMatch.value?.pending_result?.error).toBe(
      'Tài nguyên đã bị thay đổi hoặc có xung đột. Vui lòng thử lại.',
    )
    expect(tournamentApi.getById).toHaveBeenLastCalledWith(7)

    await state.submitResult()

    expect(tournamentApi.scoreFixture).toHaveBeenLastCalledWith(
      7,
      31,
      expect.objectContaining({ expected_version: 6, winner_id: 12 }),
    )
    expect(state.activeMatch.value?.pending_result).toBeUndefined()
  })
})
