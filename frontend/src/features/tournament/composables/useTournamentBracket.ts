import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
  uuid?: string
  name: string
  status: string
  dates: string
  location: string
  playersCount: number
  category?: string
  sport?: string
  format?: string
  prize_pool?: string
}

export function useTournamentBracket() {
  const route = useRoute()
  const router = useRouter()

  const loading = ref(false)
  const detailLoading = ref(false)
  const error = ref('')
  const currentTournamentKey = ref<string | number | null>(null)
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
    if (!currentTournamentKey.value) return tournaments.value[0] || null
    const key = String(currentTournamentKey.value)
    return (
      tournaments.value.find((t) => t.uuid === key || String(t.id) === key) ||
      tournaments.value[0] ||
      null
    )
  })

  function getRouteKey(): string | null {
    const pId = route?.params?.id
    if (pId) return String(pId)
    const qId = route?.query?.id
    if (qId) return String(qId)
    return null
  }

  async function loadTournaments() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list<any[]>()
      if (Array.isArray(res) && res.length > 0) {
        tournaments.value = res.map((t) => ({
          id: t.id,
          uuid: t.uuid,
          name: t.name,
          status: t.status || 'open',
          dates: t.dates,
          location: t.venue,
          playersCount: t.max_slots || 16,
          category: t.category,
          sport: t.sport,
          format: t.format,
          prize_pool: t.prize_pool,
        }))

        const targetKey = getRouteKey()
        const matched = targetKey
          ? tournaments.value.find((x) => x.uuid === targetKey || String(x.id) === targetKey)
          : null

        if (matched) {
          currentTournamentKey.value = matched.uuid || matched.id
        } else if (tournaments.value[0]) {
          const first = tournaments.value[0]
          currentTournamentKey.value = first.uuid || first.id
          // Sync URL if no key was present in route
          if (!targetKey && route.name === 'tournament-bracket') {
            router.replace({
              name: 'tournament-bracket',
              params: { id: first.uuid || first.id },
            })
          }
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

  async function loadTournamentDetail(idOrUuid: string | number) {
    if (!idOrUuid) return
    detailLoading.value = true
    try {
      const detail = await tournamentApi.getById<any>(idOrUuid)
      if (detail) {
        // 1. Map Standings from API
        if (Array.isArray(detail.standings) && detail.standings.length > 0) {
          groupStandings.value = detail.standings.map((s: any) => ({
            rank: s.rank || 1,
            name: s.participant?.user?.name || s.participant?.name || `VĐV #${s.participant_id}`,
            avatar:
              s.participant?.user?.avatar_url ||
              s.participant?.avatar ||
              `https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80`,
            club: s.participant?.club || ((s.rank || 1) <= 2 ? 'Aura Elite Club' : 'VĐV Tự Do'),
            isQualified: (s.rank || 1) <= 2,
            mp: s.played || Number(s.wins || 0) + Number(s.losses || 0) || 1,
            w: s.wins || 0,
            l: s.losses || 0,
            setsRatio: `${s.wins || 0}-${s.losses || 0}`,
            points: s.points || 0,
          }))
        } else if (Array.isArray(detail.participants) && detail.participants.length > 0) {
          // Generate default standings from participants
          groupStandings.value = detail.participants.map((p: any, idx: number) => ({
            rank: idx + 1,
            name: p.name || (p.user ? p.user.name : `VĐV #${p.id}`),
            avatar:
              p.avatar ||
              (p.user
                ? p.user.avatar_url
                : `https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80`),
            club: p.club || (idx < 2 ? 'Aura Elite Club' : 'CLB Tự Do'),
            isQualified: idx < 2,
            mp: 3,
            w: Math.max(0, 3 - idx),
            l: Math.min(3, idx),
            setsRatio: `${Math.max(0, 3 - idx)}-${Math.min(3, idx)}`,
            points: Math.max(0, (8 - idx) * 350),
          }))
        } else {
          groupStandings.value = []
        }

        // 2. Map Fixtures from API
        if (Array.isArray(detail.fixtures) && detail.fixtures.length > 0) {
          const participantMap = new Map<number, any>(
            (detail.participants || []).map((p: any) => [p.id, p]),
          )

          const mapFixtureToMatch = (
            f: any,
            round: 'quarter' | 'semi' | 'final',
            idx: number,
          ): BracketMatch => {
            const p1 = f.participant_one_id ? participantMap.get(f.participant_one_id) : null
            const p2 = f.participant_two_id ? participantMap.get(f.participant_two_id) : null

            let scoreObj = f.score
            if (typeof scoreObj === 'string') {
              try {
                scoreObj = JSON.parse(scoreObj)
              } catch {
                scoreObj = null
              }
            }

            const p1Score =
              scoreObj?.p1 ?? (f.winner_id && f.winner_id === f.participant_one_id ? 21 : 0)
            const p2Score =
              scoreObj?.p2 ?? (f.winner_id && f.winner_id === f.participant_two_id ? 21 : 0)

            const roundPrefix =
              round === 'quarter' ? 'Tứ kết' : round === 'semi' ? 'Bán kết' : 'Chung kết'
            const roundCode = round === 'quarter' ? 'QF' : round === 'semi' ? 'SF' : 'FINAL'

            const defaultAvt1 =
              'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80'
            const defaultAvt2 =
              'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&auto=format&fit=crop&q=80'

            return {
              id: f.id,
              round,
              matchNumber: idx + 1,
              label: `${roundPrefix} ${idx + 1} (${roundCode}${idx + 1})`,
              time: f.scheduled_at
                ? new Date(f.scheduled_at).toLocaleTimeString('vi-VN', {
                    hour: '2-digit',
                    minute: '2-digit',
                  })
                : '08:30',
              court: f.court_name || `Sân A${(idx % 3) + 1}`,
              status: (f.status === 'completed'
                ? 'final'
                : f.status === 'ongoing'
                  ? 'live'
                  : 'scheduled') as any,
              player1: {
                name:
                  p1?.name ||
                  (p1?.user
                    ? p1.user.name
                    : f.participant_one_id
                      ? `VĐV #${f.participant_one_id}`
                      : 'Chờ xác định'),
                avatar: p1?.avatar || (p1?.user ? p1.user.avatar_url : defaultAvt1),
                seed: p1?.seed ? `#${p1.seed}` : `#${idx * 2 + 1}`,
                club: p1?.club || 'Aura Club',
                score: p1Score,
                isWinner: !!f.winner_id && f.winner_id === f.participant_one_id,
                sets: scoreObj?.sets
                  ? scoreObj.sets.map((s: any) => s.p1)
                  : f.status === 'completed'
                    ? [p1Score]
                    : [0],
              },
              player2: {
                name:
                  p2?.name ||
                  (p2?.user
                    ? p2.user.name
                    : f.participant_two_id
                      ? `VĐV #${f.participant_two_id}`
                      : 'Chờ xác định'),
                avatar: p2?.avatar || (p2?.user ? p2.user.avatar_url : defaultAvt2),
                seed: p2?.seed ? `#${p2.seed}` : `#${idx * 2 + 2}`,
                club: p2?.club || 'CLB Tự Do',
                score: p2Score,
                isWinner: !!f.winner_id && f.winner_id === f.participant_two_id,
                sets: scoreObj?.sets
                  ? scoreObj.sets.map((s: any) => s.p2)
                  : f.status === 'completed'
                    ? [p2Score]
                    : [0],
              },
            }
          }

          const maxRound = Math.max(...detail.fixtures.map((f: any) => f.round || 1))

          if (maxRound <= 2) {
            // 4-player tournament: round 1 is SF, round 2 is Final
            const sf = detail.fixtures.filter((f: any) => f.round === 1)
            semiMatches.value = sf.map((f: any, idx: number) => mapFixtureToMatch(f, 'semi', idx))
            quarterMatches.value = []

            const finals = detail.fixtures.filter((f: any) => f.round === 2)
            finalMatch.value = finals[0] ? mapFixtureToMatch(finals[0], 'final', 0) : null
          } else {
            // 8-player tournament: round 1 is QF, round 2 is SF, round 3 is Final
            const qf = detail.fixtures.filter((f: any) => f.round === 1)
            quarterMatches.value = qf.map((f: any, idx: number) =>
              mapFixtureToMatch(f, 'quarter', idx),
            )

            const sf = detail.fixtures.filter((f: any) => f.round === 2)
            semiMatches.value = sf.map((f: any, idx: number) => mapFixtureToMatch(f, 'semi', idx))

            const finals = detail.fixtures.filter((f: any) => f.round >= 3)
            finalMatch.value = finals[0] ? mapFixtureToMatch(finals[0], 'final', 0) : null
          }
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
      detailLoading.value = false
    }
  }

  function selectTournament(key: string | number) {
    if (currentTournamentKey.value === key) return
    currentTournamentKey.value = key
    router.push({ name: 'tournament-bracket', params: { id: key } })
  }

  function openMatchDetail(match: BracketMatch) {
    selectedMatch.value = match
    isMatchDetailModalOpen.value = true
  }

  // Watch currentTournamentKey to load detail
  watch(currentTournamentKey, (newKey) => {
    if (newKey) loadTournamentDetail(newKey)
  })

  // Watch route parameter changes (e.g. back/forward button or URL change)
  watch(
    () => route.params.id,
    (newParamId) => {
      if (newParamId) {
        const key = String(newParamId)
        if (key !== String(currentTournamentKey.value)) {
          currentTournamentKey.value = key
        }
      }
    },
  )

  onMounted(() => {
    loadTournaments()
  })

  return {
    loading,
    detailLoading,
    error,
    currentTournamentId: currentTournamentKey,
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
    selectTournament,
    loadTournaments,
    loadTournamentDetail,
  }
}
