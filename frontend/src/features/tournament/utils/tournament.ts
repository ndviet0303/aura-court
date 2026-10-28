import { resolveErrorDescriptor } from '@/core/utils/errorMessage'
import { venueDateTime, venueInstant } from '@/core/utils/venueTime'
import type {
  ConflictBooking,
  Conflicts,
  EntryType,
  Fixture,
  FootballScore,
  Member,
  MemberRole,
  TournamentDetailInfo,
} from '../types/tournament'

type RosterShape = Pick<TournamentDetailInfo, 'entry_type' | 'team_main' | 'team_reserve'>

/** BR-150: single 1, double 2, team `team_main` starters plus at most `team_reserve` reserves. */
export function memberLimits(t: RosterShape): { main: number; reserve: number } {
  const fixed: Record<EntryType, number> = { single: 1, double: 2, team: t.team_main ?? 0 }
  return { main: fixed[t.entry_type], reserve: t.entry_type === 'team' ? (t.team_reserve ?? 0) : 0 }
}

export function emptyMember(fields: string[], role: MemberRole = 'main'): Member {
  return {
    full_name: '',
    birth_date: '',
    extra: Object.fromEntries(fields.map((f) => [f, ''])),
    role,
  }
}

export function initialMembers(t: RosterShape, fields: string[]): Member[] {
  return Array.from({ length: memberLimits(t).main }, () => emptyMember(fields))
}

/** The i18n key of the first problem in an entry, or null; the server checks the same rules again. */
export function entryProblem(
  name: string,
  members: Member[],
  t: RosterShape,
  fields: string[],
): string | null {
  if (!name.trim()) return 'tournament.register.nameRequired'
  const limits = memberLimits(t)
  const main = members.filter((m) => m.role === 'main').length
  if (main !== limits.main || members.length - main > limits.reserve) {
    return 'tournament.register.memberCount'
  }
  const incomplete = members.some(
    (m) => !m.full_name.trim() || !m.birth_date || fields.some((f) => !m.extra[f]?.trim()),
  )
  return incomplete ? 'tournament.register.memberIncomplete' : null
}

/** Knockout fixtures by round, each round in bracket order. */
export function knockoutRounds(fixtures: Fixture[]): { round: number; fixtures: Fixture[] }[] {
  const rounds = new Map<number, Fixture[]>()
  for (const f of fixtures.filter((x) => x.stage === 'knockout')) {
    rounds.set(f.round, [...(rounds.get(f.round) ?? []), f])
  }
  return [...rounds.entries()]
    .sort(([a], [b]) => a - b)
    .map(([round, list]) => ({ round, fixtures: list.sort((a, b) => a.position - b.position) }))
}

/** i18n key and params naming a knockout round counted back from the final. */
export function roundName(round: number, lastRound: number): [string, Record<string, number>] {
  const fromEnd = lastRound - round
  if (fromEnd === 0) return ['tournament.bracket.final', {}]
  if (fromEnd === 1) return ['tournament.bracket.semi', {}]
  if (fromEnd === 2) return ['tournament.bracket.quarter', {}]
  return ['tournament.bracket.round', { round }]
}

/**
 * UC-7.33 step 2: the fixtures that take the result of `fixture`, as the server counts them: a knockout fixture
 * feeds one fixture of each later round; a group fixture feeds the whole knockout.
 */
export function dependentFixtures(fixture: Fixture, fixtures: Fixture[]): Fixture[] {
  return fixtures.filter((later) => {
    if (later.stage !== 'knockout') return false
    if (fixture.stage === 'group') return true
    const gap = later.round - fixture.round
    return gap > 0 && Math.ceil(fixture.position / 2 ** gap) === later.position
  })
}

/** Scheduled fixtures first by time, then the rest in draw order. */
export function scheduleOrder(fixtures: Fixture[]): Fixture[] {
  const key = (f: Fixture) => [f.stage === 'group' ? 0 : 1, f.round, f.group ?? '', f.position]
  return [...fixtures].sort((a, b) => {
    if (a.starts_at && b.starts_at) return a.starts_at.localeCompare(b.starts_at)
    if (a.starts_at || b.starts_at) return a.starts_at ? -1 : 1
    const [ka, kb] = [key(a), key(b)]
    return ka < kb ? -1 : ka > kb ? 1 : 0
  })
}

export function isFootballScore(score: Fixture['score']): score is FootballScore {
  return !!score && 'goals' in score
}

/** "21-18 · 19-21" or "2-2"; a shootout is shown apart by the caller. */
export function scoreText(score: Fixture['score']): string {
  if (!score) return ''
  if (isFootballScore(score)) return score.goals.join('-')
  return score.games.map((g) => g.join('-')).join(' · ')
}

/** A picker value "YYYY-MM-DDTHH:mm" on the venue clock from an API instant. */
export const toPicker = (iso?: string | null) => (iso ? venueDateTime(iso).replace(' ', 'T') : '')
/** The API instant of a picker value, or null when it is empty. */
export const fromPicker = (local: string) => (local ? venueInstant(local) : null)

export const formatMoney = (amount: number) => `${new Intl.NumberFormat('vi-VN').format(amount)} đ`

/** The coded message, or Laravel's field messages for a plain 422 (e.g. `members.0.extra`). */
export function apiProblem(error: unknown, fallback: string): string {
  const d = resolveErrorDescriptor(error, fallback)
  return d.code === 'VALIDATION_ERROR' && d.details.length ? d.details.join(' ') : d.message
}

/** The bookings or fixture ids a refused write lists next to its message. */
export function conflictsOf(error: unknown): Conflicts {
  const body = (error ?? {}) as { bookings?: ConflictBooking[]; fixture_ids?: number[] }
  return { bookings: body.bookings ?? [], fixtureIds: body.fixture_ids ?? [] }
}
