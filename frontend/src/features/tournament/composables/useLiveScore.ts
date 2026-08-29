import { ref, computed, onMounted, onUnmounted } from 'vue'
import { getAvatarUrl } from '@/lib/avatar'

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
  const isUmpireMode = ref(false)
  const notice = ref('')

  const fixtures = ref<MatchFixture[]>([
    {
      id: 101,
      tournament_id: 1,
      tournament_name: "Aura Summer Open '26",
      court_label: 'Sân 1 (Center Court)',
      round_label: 'Championship Finals (Chung Kết)',
      status: 'live',
      player1: {
        name: 'Marcus T. (Minh Hoàng)',
        avatar: getAvatarUrl('Marcus T.'),
        seed: 'Seed 1',
        club: 'Aura Pro Team',
        sets_won: [21, 18],
        current_score: 21,
      },
      player2: {
        name: 'David K. (Elena Ryan)',
        avatar: getAvatarUrl('David K.'),
        seed: 'Seed 3',
        club: 'Independent Club',
        sets_won: [19, 21],
        current_score: 18,
      },
      current_set: 3,
      serving_player: 1,
      version: 7,
      sets_history: [
        { set: 1, p1: 21, p2: 19, winner: 1 },
        { set: 2, p1: 18, p2: 21, winner: 2 },
        { set: 3, p1: 21, p2: 18 },
      ],
      stats: {
        aces: [6, 4],
        winners: [24, 19],
        unforced_errors: [8, 12],
        smash_speed_max: '398 km/h',
        longest_rally: '34 đường cầu',
        duration: '54 phút',
      },
    },
    {
      id: 102,
      tournament_id: 1,
      tournament_name: "Aura Summer Open '26",
      court_label: 'Sân 2 (Show Court)',
      round_label: 'Bán Kết Đơn Nữ',
      status: 'live',
      player1: {
        name: 'Thu Hương (Aura)',
        avatar: getAvatarUrl('Thu Hương'),
        seed: 'Seed 2',
        club: 'Aura HCM',
        sets_won: [21],
        current_score: 14,
      },
      player2: {
        name: 'Mai Lan (Red River)',
        avatar: getAvatarUrl('Mai Lan'),
        seed: 'Seed 4',
        club: 'Red River Club',
        sets_won: [16],
        current_score: 12,
      },
      current_set: 2,
      serving_player: 2,
      version: 4,
      sets_history: [
        { set: 1, p1: 21, p2: 16, winner: 1 },
        { set: 2, p1: 14, p2: 12 },
      ],
      stats: {
        aces: [3, 2],
        winners: [16, 11],
        unforced_errors: [5, 9],
        smash_speed_max: '312 km/h',
        longest_rally: '28 đường cầu',
        duration: '38 phút',
      },
    },
  ])

  const selectedFixtureId = ref(101)

  const activeMatch = computed(() => {
    return fixtures.value.find((f) => f.id === selectedFixtureId.value) || fixtures.value[0]
  })

  const playEvents = ref<MatchEvent[]>([
    { id: 1, player: 'Marcus T.', action: 'Smash chéo sân ghi điểm Match Point', score: '21 - 18', type: 'winner', time: '18:42' },
    { id: 2, player: 'David K.', action: 'Giao cầu hỏng (Fault)', score: '20 - 18', type: 'error', time: '18:41' },
    { id: 3, player: 'Marcus T.', action: 'Bỏ nhỏ tinh tế góc lưới', score: '19 - 18', type: 'winner', time: '18:40' },
    { id: 4, player: 'David K.', action: 'Cứu cầu ngoạn mục sau smash 398km/h', score: '18 - 18', type: 'shot', time: '18:38' },
  ])

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function addPoint(playerIndex: 1 | 2) {
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
  }

  function undoPoint() {
    if (!activeMatch.value) return
    const match = activeMatch.value
    if (match.player1.current_score > 0) match.player1.current_score--
    showNotice('Đã hoàn tác điểm số gần nhất.')
  }

  return {
    isUmpireMode,
    notice,
    fixtures,
    selectedFixtureId,
    activeMatch,
    playEvents,
    showNotice,
    addPoint,
    undoPoint,
  }
}
