import { ref, computed } from 'vue'
import { getAvatarUrl } from '@/lib/avatar'

export interface Player {
  name: string
  avatar: string
  seed?: string
  club?: string
  score?: number
  isWinner?: boolean
  isServing?: boolean
  sets?: number[]
}

export interface BracketMatch {
  id: number
  round: 'quarter' | 'semi' | 'final'
  matchNumber: number
  label: string
  time: string
  court: string
  status: 'final' | 'live' | 'upcoming'
  player1: Player
  player2: Player
  courtId?: number
}

export interface GroupStanding {
  rank: number
  name: string
  avatar: string
  club: string
  isQualified: boolean
  mp: number
  w: number
  l: number
  setsRatio: string
  points: number
}

export interface TournamentItem {
  id: number
  name: string
  status: string
  dates: string
  location: string
  playersCount: number
}

export function useTournamentBracket() {
  const currentTournamentId = ref<number>(1)
  const selectedCategory = ref<'singles_men' | 'doubles_men' | 'mixed_doubles'>('singles_men')
  const activeTab = ref<'bracket' | 'standings'>('bracket')
  const selectedMatch = ref<BracketMatch | null>(null)
  const isMatchDetailModalOpen = ref<boolean>(false)

  const tournaments: TournamentItem[] = [
    { id: 1, name: "Aura Summer Open '26", status: 'live', dates: '12 - 14 Th08, 2026', location: 'Aura Center Court A, Q.1', playersCount: 32 },
    { id: 2, name: 'Aura Grand Slam Master 2026', status: 'ongoing', dates: '20 - 25 Th09, 2026', location: 'Aura Arena Riverside', playersCount: 64 },
    { id: 3, name: 'Aura Winter Championship', status: 'open', dates: '05 - 10 Th12, 2026', location: 'Aura Complex District 7', playersCount: 32 },
  ]

  const currentTournament = computed<TournamentItem>(() => {
    return tournaments.find((t) => t.id === currentTournamentId.value) || (tournaments[0] as TournamentItem)
  })

  const quarterMatches = ref<BracketMatch[]>([
    {
      id: 1,
      round: 'quarter',
      matchNumber: 1,
      label: 'Trận 1 · Tứ Kết 1',
      time: '09:00',
      court: 'Sân 1',
      status: 'final',
      player1: { name: 'E. Hayes (Hoàng)', avatar: getAvatarUrl('E. Hayes'), seed: 'Seed 1', club: 'Aura Elite', score: 2, isWinner: true, sets: [21, 21] },
      player2: { name: 'M. Chen (Minh)', avatar: getAvatarUrl('M. Chen'), seed: 'Seed 8', club: 'Dragon Court', score: 0, sets: [14, 16] },
    },
    {
      id: 2,
      round: 'quarter',
      matchNumber: 2,
      label: 'Trận 2 · Tứ Kết 2',
      time: '10:15',
      court: 'Sân 2',
      status: 'final',
      player1: { name: 'K. Tanaka (Nam)', avatar: getAvatarUrl('K. Tanaka'), seed: 'Seed 4', club: 'Saigon Smash', score: 2, isWinner: true, sets: [21, 19, 21] },
      player2: { name: 'D. Vance (Dũng)', avatar: getAvatarUrl('D. Vance'), seed: 'Seed 5', club: 'Red River', score: 1, sets: [17, 21, 18] },
    },
    {
      id: 3,
      round: 'quarter',
      matchNumber: 3,
      label: 'Trận 3 · Tứ Kết 3',
      time: '11:30',
      court: 'Sân 1',
      status: 'final',
      player1: { name: 'T. Becker (Bảo)', avatar: getAvatarUrl('T. Becker'), seed: 'Seed 3', club: 'V-Power Club', score: 2, isWinner: true, sets: [21, 21] },
      player2: { name: 'R. Al-Sayed', avatar: getAvatarUrl('R. Al-Sayed'), seed: 'Seed 6', club: 'Aura DaNang', score: 0, sets: [12, 15] },
    },
    {
      id: 4,
      round: 'quarter',
      matchNumber: 4,
      label: 'Trận 4 · Tứ Kết 4',
      time: '13:00',
      court: 'Sân 2',
      status: 'final',
      player1: { name: 'A. Miller (An)', avatar: getAvatarUrl('A. Miller'), seed: 'Seed 2', club: 'Aura Elite', score: 2, isWinner: true, sets: [21, 18, 22] },
      player2: { name: 'J. Dupont (Hải)', avatar: getAvatarUrl('J. Dupont'), seed: 'Seed 7', club: 'Lakeside Club', score: 1, sets: [19, 21, 20] },
    },
  ])

  const semiMatches = ref<BracketMatch[]>([
    {
      id: 5,
      round: 'semi',
      matchNumber: 5,
      label: 'Bán Kết 1',
      time: '15:00',
      court: 'Sân Trung Tâm',
      status: 'final',
      player1: { name: 'E. Hayes (Hoàng)', avatar: getAvatarUrl('E. Hayes'), seed: 'Seed 1', club: 'Aura Elite', score: 2, isWinner: true, sets: [21, 21] },
      player2: { name: 'K. Tanaka (Nam)', avatar: getAvatarUrl('K. Tanaka'), seed: 'Seed 4', club: 'Saigon Smash', score: 0, sets: [16, 17] },
    },
    {
      id: 6,
      round: 'semi',
      matchNumber: 6,
      label: 'Bán Kết 2',
      time: '16:30',
      court: 'Sân Trung Tâm',
      status: 'final',
      player1: { name: 'T. Becker (Bảo)', avatar: getAvatarUrl('T. Becker'), seed: 'Seed 3', club: 'V-Power Club', score: 1, sets: [21, 17, 19] },
      player2: { name: 'A. Miller (An)', avatar: getAvatarUrl('A. Miller'), seed: 'Seed 2', club: 'Aura Elite', score: 2, isWinner: true, sets: [19, 21, 21] },
    },
  ])

  const finalMatch = ref<BracketMatch>({
    id: 7,
    round: 'final',
    matchNumber: 7,
    label: 'Chung Kết Vô Địch (Gold Medal)',
    time: '19:00',
    court: 'Sân Trung Tâm (Center Court)',
    status: 'live',
    player1: { name: 'E. Hayes (Hoàng)', avatar: getAvatarUrl('E. Hayes'), seed: 'Seed 1', club: 'Aura Elite', score: 1, isServing: true, sets: [21, 18] },
    player2: { name: 'A. Miller (An)', avatar: getAvatarUrl('A. Miller'), seed: 'Seed 2', club: 'Aura Elite', score: 1, sets: [19, 21] },
  })

  const groupStandings = ref<GroupStanding[]>([
    { rank: 1, name: 'E. Hayes (Hoàng)', avatar: getAvatarUrl('E. Hayes'), club: 'Aura Elite', isQualified: true, mp: 3, w: 3, l: 0, setsRatio: '6-0', points: 9 },
    { rank: 2, name: 'K. Tanaka (Nam)', avatar: getAvatarUrl('K. Tanaka'), club: 'Saigon Smash', isQualified: true, mp: 3, w: 2, l: 1, setsRatio: '4-3', points: 6 },
    { rank: 3, name: 'D. Vance (Dũng)', avatar: getAvatarUrl('D. Vance'), club: 'Red River', isQualified: false, mp: 3, w: 1, l: 2, setsRatio: '3-4', points: 3 },
    { rank: 4, name: 'M. Chen (Minh)', avatar: getAvatarUrl('M. Chen'), club: 'Dragon Court', isQualified: false, mp: 3, w: 0, l: 3, setsRatio: '0-6', points: 0 },
  ])

  function openMatchDetail(match: BracketMatch) {
    selectedMatch.value = match
    isMatchDetailModalOpen.value = true
  }

  return {
    currentTournamentId,
    selectedCategory,
    activeTab,
    selectedMatch,
    isMatchDetailModalOpen,
    tournaments,
    currentTournament,
    quarterMatches,
    semiMatches,
    finalMatch,
    groupStandings,
    openMatchDetail,
  }
}
