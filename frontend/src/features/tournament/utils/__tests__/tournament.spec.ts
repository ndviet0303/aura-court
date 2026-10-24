import { describe, expect, it } from 'vitest'
import type { Fixture } from '../../types/tournament'
import { emptyMember, entryProblem, knockoutRounds, memberLimits, roundName } from '../tournament'

const single = { entry_type: 'single' as const, team_main: null, team_reserve: null }
const double = { entry_type: 'double' as const, team_main: null, team_reserve: null }
const team = { entry_type: 'team' as const, team_main: 2, team_reserve: 1 }

function member(role: 'main' | 'reserve' = 'main') {
  return {
    ...emptyMember(['CLB'], role),
    full_name: 'An',
    birth_date: '2000-01-01',
    extra: { CLB: 'X' },
  }
}

describe('memberLimits', () => {
  it('follows the entry type', () => {
    expect(memberLimits(single)).toEqual({ main: 1, reserve: 0 })
    expect(memberLimits(double)).toEqual({ main: 2, reserve: 0 })
    expect(memberLimits(team)).toEqual({ main: 2, reserve: 1 })
  })
})

describe('entryProblem', () => {
  it('rejects a double with three players (BR-150)', () => {
    expect(entryProblem('Đôi A', [member(), member()], double, ['CLB'])).toBeNull()
    expect(entryProblem('Đôi A', [member(), member(), member()], double, ['CLB'])).toBe(
      'tournament.register.memberCount',
    )
  })

  it('allows reserves only up to team_reserve', () => {
    expect(entryProblem('Đội', [member(), member(), member('reserve')], team, ['CLB'])).toBeNull()
    expect(
      entryProblem('Đội', [member(), member(), member('reserve'), member('reserve')], team, [
        'CLB',
      ]),
    ).toBe('tournament.register.memberCount')
  })

  it('needs a name and every athlete field', () => {
    expect(entryProblem(' ', [member()], single, ['CLB'])).toBe('tournament.register.nameRequired')
    expect(entryProblem('A', [{ ...member(), extra: { CLB: '' } }], single, ['CLB'])).toBe(
      'tournament.register.memberIncomplete',
    )
  })
})

describe('knockoutRounds', () => {
  const fixture = (id: number, round: number, position: number, stage = 'knockout') =>
    ({ id, round, position, stage }) as Fixture

  it('groups knockout fixtures by round in bracket order and skips group fixtures', () => {
    const rounds = knockoutRounds([
      fixture(1, 2, 1),
      fixture(2, 1, 2),
      fixture(3, 1, 1),
      fixture(4, 1, 1, 'group'),
    ])
    expect(rounds.map((r) => [r.round, r.fixtures.map((f) => f.id)])).toEqual([
      [1, [3, 2]],
      [2, [1]],
    ])
  })

  it('names rounds from the final back', () => {
    expect(roundName(3, 3)[0]).toBe('tournament.bracket.final')
    expect(roundName(2, 3)[0]).toBe('tournament.bracket.semi')
    expect(roundName(1, 4)).toEqual(['tournament.bracket.round', { round: 1 }])
  })
})
