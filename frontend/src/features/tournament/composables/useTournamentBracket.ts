import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { tournamentApi } from '../api/tournamentApi'
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
  status: 'final' | 'live' | 'upcoming' | 'scheduled'
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
  category?: string
}

export function useTournamentBracket() {
  const route = useRoute()
  const loading = ref(false)
  const error = ref('')
  const currentTournamentId = ref<number | null>(null)
  const selectedCategory = ref<'singles_men' | 'doubles_men' | 'mixed_doubles'>('singles_men')
  const activeTab = ref<'bracket' | 'standings'>('bracket')
  const selectedMatch = ref<BracketMatch | null>(null)
  const isMatchDetailModalOpen = ref<boolean>(false)

  const tournaments = ref<TournamentItem[]>([])
  const quarterMatches = ref<BracketMatch[]>([])
  const semiMatches = ref<BracketMatch[]>([])
  const finalMatch = ref<BracketMatch | null>(null)
  const groupStandings = ref<GroupStanding[]>([])

  const currentTournament = computed<TournamentItem | null>(() => {
    if (!currentTournamentId.value) return tournaments.value[0] || null
    return tournaments.value.find((t) => t.id === currentTournamentId.value) || tournaments.value[0] || null
  })

  async function loadTournaments() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list<any[]>()
      if (Array.isArray(res) && res.length > 0) {
        tournaments.value = res.map((t) => ({
          id: t.id,
          name: t.name,
          status: t.status,
          dates: t.dates,
          location: t.venue,
          playersCount: t.max_slots,
          category: t.category,
        }))

        const qId = route?.query?.id ? Number(route.query.id) : null
        if (qId && tournaments.value.some((x) => x.id === qId)) {
          currentTournamentId.value = qId
        } else if ((!currentTournamentId.value || !tournaments.value.some((x) => x.id === currentTournamentId.value)) && tournaments.value[0]) {
          currentTournamentId.value = tournaments.value[0].id
        }
      } else {
        tournaments.value = []
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách giải đấu.')
      tournaments.value = []
    } finally {
      loading.value = false
    }
  }

  async function loadTournamentDetail(id: number) {

    if (!id) return
    loading.value = true
    try {
      const detail = await tournamentApi.getById<any>(id)
      if (detail) {
        // 1. Map Standings from API
        if (Array.isArray(detail.standings) && detail.standings.length > 0) {
          groupStandings.value = detail.standings.map((s: any) => ({
            rank: s.rank || 1,
            name: s.participant?.user?.name || s.participant?.name || `VĐV #${s.participant_id}`,
            avatar: s.participant?.user?.avatar_url || s.participant?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80',
            club: s.participant?.club || ((s.rank || 1) <= 2 ? 'Aura Club' : 'VĐV Tự Do'),
            isQualified: (s.rank || 1) <= 2,
            mp: s.played || (Number(s.wins || 0) + Number(s.losses || 0)),
            w: s.wins || 0,
            l: s.losses || 0,
            setsRatio: `${s.wins || 0}-${s.losses || 0}`,
            points: s.points || 0,
          }))
        } else {
          groupStandings.value = []
        }

        // 2. Map Fixtures from API
        if (Array.isArray(detail.fixtures) && detail.fixtures.length > 0) {
          const participantMap = new Map<number, any>((detail.participants || []).map((p: any) => [p.id, p]))

          const mapFixtureToMatch = (f: any, round: 'quarter' | 'semi' | 'final', idx: number): BracketMatch => {
            const p1 = participantMap.get(f.participant_one_id)
            const p2 = participantMap.get(f.participant_two_id)
            let scoreObj = f.score
            if (typeof scoreObj === 'string') {
              try {
                scoreObj = JSON.parse(scoreObj)
              } catch {
                scoreObj = null
              }
            }
            const p1Score = scoreObj?.p1 ?? (f.winner_id === f.participant_one_id ? 21 : 0)
            const p2Score = scoreObj?.p2 ?? (f.winner_id === f.participant_two_id ? 21 : 0)
            const roundPrefix = round === 'quarter' ? 'Tứ kết' : round === 'semi' ? 'Bán kết' : 'Chung kết'
            const roundCode = round === 'quarter' ? 'QF' : round === 'semi' ? 'SF' : 'FINAL'

            return {
              id: f.id,
              round,
              matchNumber: idx + 1,
              label: `${roundPrefix} ${idx + 1} (${roundCode}${idx + 1})`,
              time: f.scheduled_at ? new Date(f.scheduled_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '08:30',
              court: f.court_name || `Sân A${(idx % 2) + 1}`,
              status: (f.status === 'completed' ? 'final' : f.status === 'ongoing' ? 'live' : 'scheduled') as any,
              player1: {
                name: p1?.name || (p1?.user ? p1.user.name : `VĐV ${f.participant_one_id || 1}`),
                avatar: p1?.avatar || (p1?.user ? p1.user.avatar_url : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80'),
                seed: `#${p1?.seed || idx * 2 + 1}`,
                club: p1?.club || 'Aura Club',
                score: p1Score,
                isWinner: f.winner_id === f.participant_one_id,
                sets: scoreObj?.sets ? scoreObj.sets.map((s: any) => s.p1) : (f.status === 'completed' ? [p1Score] : [0]),
              },
              player2: {
                name: p2?.name || (p2?.user ? p2.user.name : `VĐV ${f.participant_two_id || 2}`),
                avatar: p2?.avatar || (p2?.user ? p2.user.avatar_url : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&auto=format&fit=crop&q=80'),
                seed: `#${p2?.seed || idx * 2 + 2}`,
                club: p2?.club || 'CLB Tự Do',
                score: p2Score,
                isWinner: f.winner_id === f.participant_two_id,
                sets: scoreObj?.sets ? scoreObj.sets.map((s: any) => s.p2) : (f.status === 'completed' ? [p2Score] : [0]),
              },
            }
          }


          const qf = detail.fixtures.filter((f: any) => f.round === 1)
          quarterMatches.value = qf.map((f: any, idx: number) => mapFixtureToMatch(f, 'quarter', idx))

          const sf = detail.fixtures.filter((f: any) => f.round === 2)
          semiMatches.value = sf.map((f: any, idx: number) => mapFixtureToMatch(f, 'semi', idx))

          const finals = detail.fixtures.filter((f: any) => f.round >= 3 || (f.round === 2 && sf.length === 0))
          finalMatch.value = finals[0] ? mapFixtureToMatch(finals[0], 'final', 0) : null
        } else {
          quarterMatches.value = []
          semiMatches.value = []
          finalMatch.value = null
        }
      }
    } catch (e: unknown) {
      quarterMatches.value = []
      semiMatches.value = []
      finalMatch.value = null
      groupStandings.value = []
    } finally {
      loading.value = false
    }
  }

  function openMatchDetail(match: BracketMatch) {
    selectedMatch.value = match
    isMatchDetailModalOpen.value = true
  }

  watch(currentTournamentId, (newId) => {
    if (newId) loadTournamentDetail(newId)
  })

  onMounted(() => {
    loadTournaments()
    if (currentTournamentId.value) {
      loadTournamentDetail(currentTournamentId.value)
    }
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
    loadTournamentDetail,
  }
}
