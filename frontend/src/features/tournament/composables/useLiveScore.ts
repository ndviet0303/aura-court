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
  const selectedFixtureId = ref(101)
  const playEvents = ref<MatchEvent[]>([])

  const activeMatch = computed(() => {
    return fixtures.value.find((f) => f.id === selectedFixtureId.value) || fixtures.value[0] || null
  })

  async function loadLiveMatches() {
    loading.value = true
    error.value = ''
    try {
      const res = await tournamentApi.list<any[]>()
      const list = Array.isArray(res) ? res : []
      if (list.length > 0) {
        fixtures.value = list.map((t: any, idx: number) => ({
          id: t.id || idx + 100,
          tournament_id: t.id || 1,
          tournament_name: t.name || "Aura Open '26",
          court_label: `Sân ${idx + 1}`,
          round_label: t.stage || 'Vòng bảng',
          status: 'live',
          player1: {
            name: t.player1?.name || 'VĐV 1',
            avatar: getAvatarUrl(t.player1?.name || 'Player 1'),
            seed: 'Seed 1',
            club: 'Aura Pro',
            sets_won: [21],
            current_score: 0,
          },
          player2: {
            name: t.player2?.name || 'VĐV 2',
            avatar: getAvatarUrl(t.player2?.name || 'Player 2'),
            seed: 'Seed 2',
            club: 'Independent',
            sets_won: [18],
            current_score: 0,
          },
          current_set: 1,
          serving_player: 1,
          version: 1,
          sets_history: [],
          stats: {
            aces: [0, 0],
            winners: [0, 0],
            unforced_errors: [0, 0],
            smash_speed_max: '350 km/h',
            longest_rally: '15 đường cầu',
            duration: '10 phút',
          },
        }))
        selectedFixtureId.value = fixtures.value[0]?.id || 101
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải điểm số trực tiếp.')
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
      // optimistic score
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
