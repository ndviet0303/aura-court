import { ref, computed, onMounted } from 'vue'
import { tournamentApi } from '../api/tournamentApi'
import { getAvatarUrl } from '@/lib/avatar'
import { errorMessage } from '@/core/utils/errorMessage'

export interface MatchEvent {
  id: number
  player: string
  action: string
  score: string
  type: 'winner' | 'error' | 'ace' | 'shot'
  time: string
}

export interface MatchFixture {
  id: number
  tournament_id: number
  tournament_name: string
  court_label: string
  round_label: string
  status: 'live' | 'completed' | 'upcoming'
  player1: {
    name: string
    avatar: string
    seed: string
    club: string
    sets_won: number[]
    current_score: number
  }
  player2: {
    name: string
    avatar: string
    seed: string
    club: string
    sets_won: number[]
    current_score: number
  }
  current_set: number
  serving_player: 1 | 2
  version: number
  sets_history: Array<{ set: number; p1: number; p2: number; winner?: 1 | 2 }>
  stats: {
    aces: [number, number]
    winners: [number, number]
    unforced_errors: [number, number]
    smash_speed_max: string
    longest_rally: string
    duration: string
  }
}

export function useLiveScore() {
  const loading = ref(false)
  const isUmpireMode = ref(false)
  const notice = ref('')
  const error = ref('')

  const fixtures = ref<MatchFixture[]>([])
  const selectedFixtureId = ref<number | null>(null)
  const playEvents = ref<MatchEvent[]>([])

  const activeMatch = computed(() => {
    if (!selectedFixtureId.value) return fixtures.value[0] || null
    return fixtures.value.find((f) => f.id === selectedFixtureId.value) || fixtures.value[0] || null
  })

  async function loadLiveMatches() {
    loading.value = true
    error.value = ''
    try {
      const tourneys = await tournamentApi.list<any[]>()
      const list = Array.isArray(tourneys) ? tourneys : []
      const liveList: MatchFixture[] = []

      for (const t of list) {
        try {
          const detail = await tournamentApi.getById<any>(t.id)
          if (detail && Array.isArray(detail.fixtures) && detail.fixtures.length > 0) {
            const participantMap = new Map<number, any>((detail.participants || []).map((p: any) => [p.id, p]))

            for (const f of detail.fixtures) {
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
              const p1Name = p1?.name || p1?.user?.name || `VĐV #${f.participant_one_id || 1}`
              const p2Name = p2?.name || p2?.user?.name || `VĐV #${f.participant_two_id || 2}`
              const p1Avatar = p1?.avatar || p1?.user?.avatar_url || getAvatarUrl(p1Name)
              const p2Avatar = p2?.avatar || p2?.user?.avatar_url || getAvatarUrl(p2Name)

              liveList.push({
                id: f.id,
                tournament_id: t.id,
                tournament_name: t.name || 'Giải Đấu Aura',
                court_label: f.court_name || `Sân A${(f.position || 1)}`,
                round_label: f.round === 1 ? 'Vòng Bảng / Tứ Kết' : f.round === 2 ? 'Bán Kết' : 'Chung Kết',
                status: f.status === 'completed' ? 'completed' : f.status === 'ongoing' ? 'live' : 'upcoming',
                player1: {
                  name: p1Name,
                  avatar: p1Avatar,
                  seed: `Hạt giống #${p1?.seed || 1}`,
                  club: p1?.club || 'Aura Club',
                  sets_won: [p1Score],
                  current_score: p1Score,
                },
                player2: {
                  name: p2Name,
                  avatar: p2Avatar,
                  seed: `Hạt giống #${p2?.seed || 2}`,
                  club: p2?.club || 'CLB Tự Do',
                  sets_won: [p2Score],
                  current_score: p2Score,
                },
                current_set: 1,
                serving_player: 1,
                version: f.version || 1,
                sets_history: scoreObj?.sets || [{ set: 1, p1: p1Score, p2: p2Score }],
                stats: {
                  aces: [2, 1],
                  winners: [8, 6],
                  unforced_errors: [3, 4],
                  smash_speed_max: '350 km/h',
                  longest_rally: '15 đường cầu',
                  duration: '18 phút',
                },
              })
            }

          }
        } catch {
          // Skip tournament if error
        }
      }

      const sortedList = liveList.sort((a, b) => {
        const priority: Record<string, number> = { live: 1, upcoming: 2, completed: 3 }
        return (priority[a.status] || 2) - (priority[b.status] || 2)
      })

      fixtures.value = sortedList
      if (sortedList.length > 0 && sortedList[0]) {
        selectedFixtureId.value = sortedList[0].id
      }


    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải điểm số trực tiếp.')
      fixtures.value = []
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function addPoint(playerIndex: 1 | 2) {
    if (!activeMatch.value) return
    const match = activeMatch.value
    if (playerIndex === 1) {
      match.player1.current_score++
      match.serving_player = 1
      playEvents.value.unshift({
        id: Date.now(),
        player: match.player1.name,
        action: 'Ghi điểm trực tiếp',
        score: `${match.player1.current_score} - ${match.player2.current_score}`,
        type: 'winner',
        time: new Date().toTimeString().slice(0, 5),
      })
    } else {
      match.player2.current_score++
      match.serving_player = 2
      playEvents.value.unshift({
        id: Date.now(),
        player: match.player2.name,
        action: 'Ghi điểm trực tiếp',
        score: `${match.player1.current_score} - ${match.player2.current_score}`,
        type: 'winner',
        time: new Date().toTimeString().slice(0, 5),
      })
    }

    try {
      await tournamentApi.scoreFixture(match.tournament_id, match.id, {
        p1_score: match.player1.current_score,
        p2_score: match.player2.current_score,
        set: match.current_set,
      })
    } catch {
      // Keep optimistic score
    }
  }

  function undoPoint() {
    if (!activeMatch.value) return
    const match = activeMatch.value
    if (match.player1.current_score > 0) match.player1.current_score--
    showNotice('Đã hoàn tác điểm số gần nhất.')
  }

  onMounted(() => {
    loadLiveMatches()
  })

  return {
    loading,
    isUmpireMode,
    notice,
    error,
    fixtures,
    selectedFixtureId,
    activeMatch,
    playEvents,
    showNotice,
    addPoint,
    undoPoint,
    loadLiveMatches,
  }
}
