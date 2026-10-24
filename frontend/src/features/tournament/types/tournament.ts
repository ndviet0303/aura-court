// Module 07 part C (FR-120..FR-131): one tournament is one sport and one entry type (D17).
export type Sport = 'badminton' | 'football'
export type EntryType = 'single' | 'double' | 'team'
export type Format = 'group' | 'knockout'
export type Tiebreaker = 'head_to_head' | 'score_diff' | 'score_for'
export type TournamentType = 'public' | 'internal'
export type TournamentStatus =
  'draft' | 'open' | 'roster_locked' | 'drawn' | 'in_progress' | 'finished' | 'stopped'
export type ManagerRole = 'owner' | 'official' | 'representative'
export type EntryStatus = 'submitted' | 'approved' | 'rejected' | 'paid' | 'withdrawn' | 'confirmed'
export type MemberRole = 'main' | 'reserve'

export const SPORTS: Sport[] = ['badminton', 'football']
export const ENTRY_TYPES: EntryType[] = ['single', 'double', 'team']
export const FORMATS: Format[] = ['group', 'knockout']
export const TIEBREAKERS: Tiebreaker[] = ['head_to_head', 'score_diff', 'score_for']
export const STATUSES: TournamentStatus[] = [
  'open',
  'roster_locked',
  'drawn',
  'in_progress',
  'finished',
  'stopped',
]

export type TournamentSummary = {
  id: string
  type: TournamentType
  name: string
  sport: Sport
  entry_type: EntryType
  format: Format
  status: TournamentStatus
  venue: { id: number; name: string; address: string | null }
  fee: number
  max_entries: number | null
  accepted_count: number
  registration_deadline: string | null
  payment_deadline: string | null
  starts_at: string | null
  ends_at: string | null
}

export type Points = { win: number; draw: number; loss: number }

export type TournamentDetailInfo = TournamentSummary & {
  team_main: number | null
  team_reserve: number | null
  group_count: number | null
  advance_per_group: number | null
  games_to_win: number | null
  points: Points
  tiebreakers: Tiebreaker[]
  rules: string
  eligibility: string | null
  athlete_fields: string[]
  forfeit_rule: string
  prizes: string | null
  stopped_at: string | null
  stop_reason: string | null
  can_register: boolean
  my_role: ManagerRole | null
}

export type EntryRef = { id: number; name: string }

export type BadmintonScore = { games: [number, number][] }
export type FootballScore = { goals: [number, number]; shootout: [number, number] | null }

export type Fixture = {
  id: number
  stage: 'group' | 'knockout'
  group: string | null
  round: number
  position: number
  entry_one: EntryRef | null
  entry_two: EntryRef | null
  winner_entry_id: number | null
  forfeit_entry_id: number | null
  court: { id: number; code: string } | null
  starts_at: string | null
  ends_at: string | null
  status: 'pending' | 'bye' | 'completed'
  score: BadmintonScore | FootballScore | null
}

export type StandingRow = {
  entry: EntryRef
  played: number
  wins: number
  draws: number
  losses: number
  points: number
  score_for: number
  score_against: number
  score_diff: number
  rank: number
  tied: boolean
}

export type Standings = Record<string, StandingRow[]>

export type Member = {
  full_name: string
  birth_date: string
  extra: Record<string, string>
  role: MemberRole
}

export type EntryPayload = { name: string; members: Member[] }

export type PublicEntry = {
  id: number
  name: string
  group: string | null
  seed: number | null
  members: { full_name: string; role: MemberRole }[]
}

export type ManagedEntry = {
  id: number
  name: string
  status: EntryStatus
  reject_reason: string | null
  group: string | null
  seed: number | null
  representative: { id: number; name: string; email: string | null; phone: string | null } | null
  members: Member[]
  approved_at: string | null
  paid_at: string | null
  created_at: string
}

export type PaymentInstructions = {
  id: number
  reference: string
  transfer_content: string
  amount: number
  currency: 'VND'
  status: 'pending' | 'paid' | 'expired'
  expires_at: string
  paid_at: string | null
  receiver: { bank_code: string; account_name: string; account_number: string }
  qr_url: string
}

export type MyEntry = ManagedEntry & {
  tournament: TournamentSummary
  payment: PaymentInstructions | null
}

export type PageMeta = { current_page: number; last_page: number; total: number }

export type TournamentDetail = {
  tournament: TournamentDetailInfo
  entries: PublicEntry[]
  fixtures: Fixture[]
  standings: Standings
}

export const PERMISSIONS = [
  'edit',
  'publish',
  'allocate',
  'grant',
  'review',
  'roster',
  'lock',
  'draw',
  'schedule',
  'result',
  'correct',
  'stop',
] as const
export type Permission = (typeof PERMISSIONS)[number]

export type Allocation = {
  id: number
  court: { id: number; code: string }
  starts_at: string
  ends_at: string
}
export type Rental = {
  reservation_code: string
  court: { id: number; code: string }
  starts_at: string
  ends_at: string
}
export type Grant = {
  id: number
  role: 'official' | 'representative'
  user: { id: number; name: string; email: string }
}
export type TournamentEvent = {
  id: number
  type: string
  actor: { id: number; name: string } | null
  reason: string | null
  payload: Record<string, unknown>
  created_at: string
}

export type ManagedTournamentDetail = {
  tournament: TournamentDetailInfo & { permissions: Record<Permission, boolean> }
  entries: ManagedEntry[]
  fixtures: Fixture[]
  standings: Standings
  allocations: Allocation[]
  rentals: Rental[]
  grants: Grant[]
  events: TournamentEvent[]
  courts: { id: number; code: string }[]
}

export type ManagedSummary = TournamentSummary & { my_role: ManagerRole }

/** FR-120 setup; the public-only fields are dropped for an internal tournament. */
export type TournamentSetup = {
  name: string
  sport: Sport
  entry_type: EntryType
  team_main: number | null
  team_reserve: number | null
  format: Format
  group_count: number | null
  advance_per_group: number | null
  games_to_win: number | null
  points: Points
  tiebreakers: Tiebreaker[]
  rules: string
  eligibility: string
  athlete_fields: string[]
  forfeit_rule: string
  prizes: string
  max_entries?: number | null
  fee?: number | null
  registration_deadline?: string | null
  payment_deadline?: string | null
  starts_at?: string | null
  ends_at?: string | null
}

export type PublicSetupPayload = TournamentSetup & { venue_id: number }
export type InternalSetupPayload = TournamentSetup & {
  reservation_codes: string[]
  representative_email?: string
}

export type ResultPayload =
  | { games: [number, number][] }
  | { goals: [number, number]; shootout: [number, number] | null }
  | { absent_entry_id: number }

export type RosterRowError = { row: number; field: string; message: string }
export type RosterPreview = { entries: EntryPayload[]; errors: RosterRowError[] }

/** Writes that change fixtures return the refreshed draw so standings and the bracket stay in step. */
export type FixtureChange = {
  fixture: Fixture
  fixtures: Fixture[]
  standings: Standings
  tournament: TournamentDetailInfo
}

export const LIMITS = {
  name: 255,
  rules: 5000,
  eligibility: 2000,
  forfeitRule: 2000,
  prizes: 2000,
  athleteFields: 10,
  teamMain: [2, 30],
  teamReserve: [0, 20],
  groupCount: [1, 16],
  gamesToWin: [1, 3],
  maxEntries: [2, 128],
  minFee: 1000,
} as const
