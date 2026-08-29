export interface Participant {
  id: number
  seed?: number
  name: string
  club?: string
  avatar?: string
  initials?: string
  checkedIn?: boolean
  paid?: boolean
  racketChecked?: boolean
  category?: string
  phone?: string
  level?: string
  status?: 'checked_in' | 'pending' | 'no_show'
  checkInTime?: string
  estimatedArrival?: string
  warmupCourt?: string
  qrCode?: string
  gender?: 'M' | 'F'
}

export interface FixturePlayer {
  id?: number
  seed?: number
  name: string
  avatar?: string
  club?: string
  sets: number[]
  currentScore?: number
  isWinner?: boolean
  isServing?: boolean
}

export interface FixtureMatch {
  id: number
  roundNumber: number // 1: R16, 2: QF, 3: SF, 4: Finals
  roundName: string
  position: number
  matchNumber: number
  courtName: string
  courtId?: number
  scheduledTime: string
  status: 'pending' | 'live' | 'completed' | 'walkover'
  player1: FixturePlayer
  player2: FixturePlayer
  refereeName?: string
  winnerId?: number
  nextMatchId?: number
  nextMatchSlot?: 1 | 2
}

export interface TournamentAdminData {
  id: number
  name: string
  category: string
  sport: string
  format: 'single_elimination' | 'round_robin' | 'double_elimination'
  status: 'draft' | 'open' | 'ongoing' | 'completed'
  prizePool: string
  prizePoolUsd: string
  startDate: string
  endDate: string
  datesFormatted: string
  venue: string
  city: string
  courtsAvailable: number
  maxSlots: number
  entryFee: string
  description: string
  version: number
  seedingRule: 'standard' | 'random' | 'club_split'
}
