import { ref, computed, onMounted } from 'vue'
import { tournamentApi } from '../api/tournamentApi'
import { getAvatarUrl } from '@/lib/avatar'
import { errorMessage } from '@/core/utils/errorMessage'

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
  const loading = ref(false)
  const error = ref('')
  const currentTournamentId = ref<number>(1)
  const selectedCategory = ref<'singles_men' | 'doubles_men' | 'mixed_doubles'>('singles_men')
  const activeTab = ref<'bracket' | 'standings'>('bracket')
  const selectedMatch = ref<BracketMatch | null>(null)
  const isMatchDetailModalOpen = ref<boolean>(false)

  const tournaments = ref<TournamentItem[]>([])

  const currentTournament = computed<TournamentItem | null>(() => {
    return tournaments.value.find((t) => t.id === currentTournamentId.value) || tournaments.value[0] || null
  })

  const quarterMatches = ref<BracketMatch[]>([])
  const semiMatches = ref<BracketMatch[]>([])
  const finalMatch = ref<BracketMatch | null>(null)
  const groupStandings = ref<GroupStanding[]>([])

  async function loadTournaments() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list<any[]>()
      const list = Array.isArray(res) ? res : []
      tournaments.value = list.map((t: any) => ({
        id: t.id,
        name: t.name || 'Giải đấu Aura',
        status: t.status || 'open',
        dates: t.dates || `${t.start_date || '01/08'} - ${t.end_date || '15/08'}`,
        location: t.venue?.name || t.location || 'Aura Center',
        playersCount: t.max_participants || t.participants_count || 32,
      }))
      if (tournaments.value.length > 0 && tournaments.value[0]) {
        currentTournamentId.value = tournaments.value[0].id
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách giải đấu.')
    } finally {
      loading.value = false
    }
  }

  function openMatchDetail(match: BracketMatch) {
    selectedMatch.value = match
    isMatchDetailModalOpen.value = true
  }

  onMounted(() => {
    loadTournaments()
  })

  return {
    loading,
    error,
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
    loadTournaments,
  }
}
