import { describe, it, expect, beforeEach, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useTournamentBracket } from '../composables/useTournamentBracket'
import { useLiveScore } from '../composables/useLiveScore'
import { tournamentApi } from '../api/tournamentApi'

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'tournament-bracket', params: { id: 't-1' }, query: {} }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}))

vi.mock('../api/tournamentApi', () => ({
  tournamentApi: { list: vi.fn(), getById: vi.fn(), scoreFixture: vi.fn() },
}))

function withSetup<T>(composable: () => T): T {
  let result!: T
  mount(
    defineComponent({
      setup() {
        result = composable()
        return () => null
      },
    }),
  )
  return result
}

// What getById hands over for a fresh tournament: nothing scored, ranked, scheduled or placed on a court.
const detail = {
  id: 1,
  uuid: 't-1',
  name: 'Giải Phú Nhuận',
  participants: [
    { id: 11, name: 'An', seed: 1 },
    { id: 12, name: 'Bình', seed: 2 },
  ],
  standings: [
    {
      participant_id: 11,
      rank: null,
      played: 0,
      wins: 0,
      losses: 0,
      points: 0,
      participant: { user: { name: 'An' } },
    },
  ],
  fixtures: [
    {
      id: 101,
      round: 1,
      position: 1,
      status: 'completed',
      participant_one_id: 11,
      participant_two_id: 12,
      winner_id: 11,
      score: null,
    },
  ],
}

describe('public tournament screens show only real data', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(tournamentApi.list).mockResolvedValue([
      {
        id: 1,
        uuid: 't-1',
        name: 'Giải Phú Nhuận',
        status: 'open',
        current_slots: 2,
        max_slots: 16,
      },
    ])
    vi.mocked(tournamentApi.getById).mockResolvedValue(detail)
  })

  it('maps the bracket without invented ranks, clubs, scores, times, courts or photos', async () => {
    const bracket = withSetup(useTournamentBracket)
    await flushPromises()

    expect(bracket.tournaments.value[0]).toMatchObject({ status: 'open', playersCount: 2 })

    const [standing] = bracket.groupStandings.value
    expect(standing).toMatchObject({ rank: null, isQualified: false, mp: 0, club: undefined })
    expect(standing?.avatar).toContain('ui-avatars.com')

    const [match] = bracket.semiMatches.value
    expect(match).toMatchObject({ time: '', court: '', status: 'final' })
    expect(match?.player1).toMatchObject({
      seed: '#1',
      club: undefined,
      score: undefined,
      sets: [],
    })
    expect(match?.player1.isWinner).toBe(true)
    expect(match?.player2.avatar).toContain('ui-avatars.com')
  })

  it('leaves standings empty when the server has none, even with participants', async () => {
    vi.mocked(tournamentApi.getById).mockResolvedValue({ ...detail, standings: [] })
    const bracket = withSetup(useTournamentBracket)
    await flushPromises()

    expect(bracket.groupStandings.value).toEqual([])
  })

  it('scores live matches from the record only and fabricates no stats or rallies', async () => {
    vi.mocked(tournamentApi.getById).mockResolvedValue({
      ...detail,
      fixtures: [
        { ...detail.fixtures[0], id: 102, status: 'ongoing', winner_id: null },
        {
          ...detail.fixtures[0],
          position: 2,
          score: {
            p1: 2,
            p2: 1,
            sets: [
              { p1: 21, p2: 18 },
              { p1: 19, p2: 21 },
              { p1: 21, p2: 15 },
            ],
          },
        },
      ],
    })
    const live = withSetup(useLiveScore)
    await flushPromises()

    expect(live.tournamentTitle.value).toBe('Giải Phú Nhuận')
    expect(live.playEvents.value).toEqual([])

    const [ongoing, completed] = live.fixtures.value
    expect(ongoing).not.toHaveProperty('stats')
    expect(ongoing).toMatchObject({ court_label: '', round_label: 'Tứ Kết 1' })
    expect(ongoing?.player1).toMatchObject({ seed: 'Hạt giống #1', current_score: 0, sets_won: 0 })
    expect(completed?.player1.sets_won).toBe(2)
    expect(completed?.player2.sets_won).toBe(1)
  })
})
