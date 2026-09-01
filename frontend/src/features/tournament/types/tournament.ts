export interface Fixture {
  id: number
  round: number
  position: number
  status: string
  participant_one_id?: number
  participant_two_id?: number
  participant_one_name?: string
  participant_two_name?: string
  winner_id?: number
  score?: {
    sets?: Array<{ p1: number; p2: number }>
    winner?: number
    loser?: number
  } | Record<string, unknown>
}

export interface Participant {
  id: number
  user_id?: number
  name: string
  avatar?: string
  team_name?: string
  skill_level?: number
  seed?: number
  club?: string
  checked_in?: boolean
  paid?: boolean
  phone?: string
  category?: string
  [key: string]: any
}

export interface CircuitLeaderboardItem {
  rank: number
  name: string
  points: number
  tournamentsWon: number
  avatar: string
  city?: string
  tier?: string
}

export interface TournamentItem {
  id: number
  uuid?: string
  name: string
  title?: string
  slug?: string
  category?: string
  sport?: 'badminton' | 'pickleball' | 'tennis' | 'football_5v5' | string
  format: 'single_elimination' | 'round_robin' | 'double_elimination' | string
  status: 'open' | 'ongoing' | 'completed' | 'upcoming' | string
  prize_pool: string
  prize_pool_usd?: string
  dates: string
  start_date?: string
  end_date?: string
  venue: string
  city: string
  court_name?: string
  current_slots: number
  max_slots: number
  entry_fee?: string
  image_url: string
  description?: string
  version: number
  current_match?: {
    round_label: string
    court: string
    player1_name: string
    player1_avatar?: string
    player2_name: string
    player2_avatar?: string
    p1_scores: number[]
    p2_scores: number[]
  }
  winner_team?: string
  runner_up?: string
  fixtures?: Fixture[]
  participants?: Participant[]
  [key: string]: any
}

export interface TournamentRegistrationPayload {
  name?: string
  phone?: string
  partnerName?: string
  skillLevel?: number
  notes?: string
  checked_in?: boolean
  [key: string]: any
}


