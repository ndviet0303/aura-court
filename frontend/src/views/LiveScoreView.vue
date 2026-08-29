<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiV2, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

interface MatchEvent {
  id: number
  player: string
  action: string
  score: string
  type: 'winner' | 'error' | 'ace' | 'shot'
  time: string
}

interface MatchFixture {
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

const auth = useAuthStore()
const route = useRoute()

// Available live matches
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
    court_label: 'Sân 2 (Court 2)',
    round_label: 'Semi-Finals 2 (Bán Kết 2)',
    status: 'live',
    player1: {
      name: 'Quốc Bảo & Tuấn Anh',
      avatar: getAvatarUrl('Quốc Bảo'),
      seed: 'Seed 2',
      club: 'Saigon Smashers',
      sets_won: [21],
      current_score: 15,
    },
    player2: {
      name: 'Vũ Nam & Thế Vinh',
      avatar: getAvatarUrl('Vũ Nam'),
      seed: 'Seed 4',
      club: 'Riverside Club',
      sets_won: [16],
      current_score: 17,
    },
    current_set: 2,
    serving_player: 2,
    version: 3,
    sets_history: [
      { set: 1, p1: 21, p2: 16, winner: 1 },
      { set: 2, p1: 15, p2: 17 },
    ],
    stats: {
      aces: [3, 5],
      winners: [18, 20],
      unforced_errors: [10, 9],
      smash_speed_max: '382 km/h',
      longest_rally: '28 đường cầu',
      duration: '32 phút',
    },
  },
  {
    id: 103,
    tournament_id: 1,
    tournament_name: "Aura Summer Open '26",
    court_label: 'Sân 3 (Court 3)',
    round_label: 'Tranh Hạng Ba (Bronze Match)',
    status: 'live',
    player1: {
      name: 'Hồng Ngọc & Alex N.',
      avatar: getAvatarUrl('Hồng Ngọc'),
      seed: 'Seed 5',
      club: 'Aura Youth',
      sets_won: [21],
      current_score: 9,
    },
    player2: {
      name: 'Đức Huy & Hải Đăng',
      avatar: getAvatarUrl('Đức Huy'),
      seed: 'Seed 6',
      club: 'Metro Badminton',
      sets_won: [14],
      current_score: 11,
    },
    current_set: 2,
    serving_player: 2,
    version: 2,
    sets_history: [
      { set: 1, p1: 21, p2: 14, winner: 1 },
      { set: 2, p1: 9, p2: 11 },
    ],
    stats: {
      aces: [2, 3],
      winners: [14, 12],
      unforced_errors: [6, 8],
      smash_speed_max: '365 km/h',
      longest_rally: '22 đường cầu',
      duration: '26 phút',
    },
  },
])

// Selected Active Fixture
const activeFixtureId = ref<number>(101)
const currentMatch = computed<MatchFixture>(() => {
  return fixtures.value.find((f) => f.id === activeFixtureId.value) || (fixtures.value[0] as MatchFixture)
})

// Match Events Stream (Feed)
const matchEvents = ref<MatchEvent[]>([
  {
    id: 1,
    player: 'Marcus T.',
    action: 'Forehand Winner (Cross-court)',
    score: '21-18',
    type: 'winner',
    time: '53:12',
  },
  {
    id: 2,
    player: 'David K.',
    action: 'Unforced Error (Net)',
    score: '20-18',
    type: 'error',
    time: '52:40',
  },
  {
    id: 3,
    player: 'Marcus T.',
    action: 'Smash Winner (Chém cầu hiểm)',
    score: '19-18',
    type: 'winner',
    time: '51:15',
  },
  {
    id: 4,
    player: 'David K.',
    action: 'Passing Shot (Down the line)',
    score: '18-18',
    type: 'shot',
    time: '50:02',
  },
  {
    id: 5,
    player: 'Marcus T.',
    action: 'Ace Serve (112mph / Giao cầu ăn điểm)',
    score: '18-17',
    type: 'ace',
    time: '48:30',
  },
])

// Live stream modal
const isStreamModalOpen = ref(false)
const processing = ref(false)
const notice = ref('')
const error = ref('')

function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 3500)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 4500)
}

// Referee Actions
async function scorePoint(playerNumber: 1 | 2) {
  const match = currentMatch.value
  if (!match) return

  processing.value = true
  try {
    if (playerNumber === 1) {
      match.player1.current_score += 1
      match.serving_player = 1
      matchEvents.value.unshift({
        id: Date.now(),
        player: match.player1.name.split(' ')[0] || 'P1',
        action: 'Điểm số trực tiếp (+1 Pt)',
        score: `${match.player1.current_score}-${match.player2.current_score}`,
        type: 'winner',
        time: 'Vừa xong',
      })
    } else {
      match.player2.current_score += 1
      match.serving_player = 2
      matchEvents.value.unshift({
        id: Date.now(),
        player: match.player2.name.split(' ')[0] || 'P2',
        action: 'Điểm số trực tiếp (+1 Pt)',
        score: `${match.player1.current_score}-${match.player2.current_score}`,
        type: 'winner',
        time: 'Vừa xong',
      })
    }

    match.version += 1
    showNotice(`✨ Cập nhật tỷ số: ${match.player1.current_score} - ${match.player2.current_score}`)

    // Sync to backend if tournament API is connected
    try {
      await apiV2.post(`/tournaments/${match.tournament_id}/fixtures/${match.id}/score`, {
        expected_version: match.version - 1,
        score: {
          sets: match.sets_history.map((s) => ({ p1: s.p1, p2: s.p2 })),
          current_p1: match.player1.current_score,
          current_p2: match.player2.current_score,
        },
      })
    } catch {
      // optimistic update maintained
    }
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể cập nhật tỷ số.'))
  } finally {
    processing.value = false
  }
}

function undoPoint() {
  const match = currentMatch.value
  if (!match) return

  if (match.player1.current_score > 0 || match.player2.current_score > 0) {
    if (match.player1.current_score > match.player2.current_score) {
      match.player1.current_score = Math.max(0, match.player1.current_score - 1)
    } else {
      match.player2.current_score = Math.max(0, match.player2.current_score - 1)
    }
    match.version += 1
    if (matchEvents.value.length > 0) {
      matchEvents.value.shift()
    }
    showNotice('Đã hoàn tác 1 điểm.')
  }
}

function toggleServer() {
  const match = currentMatch.value
  if (!match) return
  match.serving_player = match.serving_player === 1 ? 2 : 1
  showNotice(`Đã đổi quyền phát cầu sang ${match.serving_player === 1 ? match.player1.name : match.player2.name}`)
}

function handleImageError(event: Event, fallback = '/aura/court-1.jpg') {
  const target = event.target as HTMLImageElement | null
  if (target && target.src !== fallback) {
    target.src = fallback
  }
}

// Auto Simulation timer (Demo mode)
let liveTimer: ReturnType<typeof setInterval> | null = null
const isAutoSimulating = ref(false)

function toggleAutoSimulation() {
  isAutoSimulating.value = !isAutoSimulating.value
  if (isAutoSimulating.value) {
    showNotice('Đang mô phỏng tự động...')
    liveTimer = setInterval(() => {
      const match = currentMatch.value
      if (!match) return
      const scoringPlayer: 1 | 2 = Math.random() > 0.5 ? 1 : 2
      if (scoringPlayer === 1) {
        match.player1.current_score += 1
        match.serving_player = 1
      } else {
        match.player2.current_score += 1
        match.serving_player = 2
      }

      const actions = [
        'Smash Winner (Đập cầu dọc biên)',
        'Drop Shot (Bỏ nhỏ tinh tế)',
        'Net Kill (Chụp lưới ghi điểm)',
        'Unforced Error (Đánh cầu ngoài sân)',
        'Ace Serve (Giao cầu hiểm hóc)',
      ]
      const randomAction = actions[Math.floor(Math.random() * actions.length)] || 'Smash Winner'
      const pName = scoringPlayer === 1 ? (match.player1.name.split(' ')[0] || 'P1') : (match.player2.name.split(' ')[0] || 'P2')

      matchEvents.value.unshift({
        id: Date.now(),
        player: pName,
        action: randomAction,
        score: `${match.player1.current_score}-${match.player2.current_score}`,
        type: randomAction.includes('Error') ? 'error' : randomAction.includes('Ace') ? 'ace' : 'winner',
        time: 'Live',
      })

      if (matchEvents.value.length > 20) {
        matchEvents.value.pop()
      }
    }, 4500)
  } else {
    if (liveTimer) clearInterval(liveTimer)
    liveTimer = null
    showNotice('Đã dừng mô phỏng.')
  }
}

onMounted(() => {
  if (route.query.court) {
    const cid = Number(route.query.court)
    if (!isNaN(cid) && fixtures.value.some((f) => f.id === cid)) {
      activeFixtureId.value = cid
    }
  }
})

onUnmounted(() => {
  if (liveTimer) clearInterval(liveTimer)
})
</script>

<template>
  <div class="livescore-page">
    <!-- Ambient Background Glow -->
    <div class="ambient-glow-wrapper" aria-hidden="true">
      <div class="glow-orb glow-orb-left"></div>
      <div class="glow-orb glow-orb-right"></div>
    </div>

    <main class="livescore-main-content">
      <!-- Flash Alert Notices -->
      <div class="alerts-container">
        <Transition name="fade">
          <div v-if="notice" class="alert-banner alert-success">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span>{{ notice }}</span>
          </div>
        </Transition>
        <Transition name="fade">
          <div v-if="error" class="alert-banner alert-danger">
            <span class="material-symbols-outlined text-[18px]">error</span>
            <span>{{ error }}</span>
          </div>
        </Transition>
      </div>

      <div class="livescore-container">
        <!-- Top Breadcrumb & Navigation Bar -->
        <div class="top-nav-bar">
          <RouterLink to="/tournaments" class="back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Danh sách giải đấu</span>
          </RouterLink>

          <!-- Court Switcher Tabs -->
          <div class="court-switcher-pills">
            <button
              v-for="fixture in fixtures"
              :key="fixture.id"
              type="button"
              class="court-tab-btn"
              :class="{ active: fixture.id === activeFixtureId }"
              @click="activeFixtureId = fixture.id"
            >
              <span class="live-ping-dot-sm"></span>
              <span>{{ fixture.court_label.split(' ')[0] }} {{ fixture.court_label.split(' ')[1] }}</span>
            </button>
          </div>

          <!-- Watch Stream CTA -->
          <button
            type="button"
            class="btn-watch-stream-top"
            @click="isStreamModalOpen = true"
          >
            <span class="material-symbols-outlined text-[18px]">play_arrow</span>
            <span>Xem Stream</span>
          </button>
        </div>

        <!-- Match Title & Status Header -->
        <div class="match-meta-header">
          <div class="match-meta-left">
            <div class="meta-tags-row">
              <span class="pulse-live-badge">
                <span class="live-ping-dot"></span>
                LIVE NOW
              </span>
              <span class="court-chip-badge">{{ currentMatch.court_label }}</span>
              <span class="tournament-chip-badge">{{ currentMatch.tournament_name }}</span>
            </div>
            <h1 class="match-title">{{ currentMatch.round_label }}</h1>
          </div>
        </div>

        <!-- ================= JUMBO LIVE SCORE CARD ================= -->
        <section class="jumbo-score-card">
          <div class="score-card-grid">
            <!-- Left Player: Player 1 -->
            <div class="player-col player-left">
              <div class="player-avatar-box" :class="{ 'is-serving': currentMatch.serving_player === 1 }">
                <img
                  :src="currentMatch.player1.avatar"
                  :alt="currentMatch.player1.name"
                  class="player-photo"
                  @error="handleImageError($event, '/aura/court-1.jpg')"
                />
                <span v-if="currentMatch.serving_player === 1" class="serving-icon-badge" title="Đang giao cầu">
                  <span class="material-symbols-outlined text-[13px]">sports_tennis</span>
                </span>
              </div>

              <div class="player-info-box">
                <h2 class="player-name">{{ currentMatch.player1.name }}</h2>
                <div class="player-sub">
                  <span class="seed-text">{{ currentMatch.player1.seed }}</span>
                  <span class="sep">•</span>
                  <span class="club-text">{{ currentMatch.player1.club }}</span>
                </div>
              </div>

              <!-- Set History Scores -->
              <div class="set-history-row">
                <span
                  v-for="(score, idx) in currentMatch.player1.sets_won"
                  :key="idx"
                  class="set-chip"
                  :class="{ 'is-win': idx === 0 }"
                >
                  Set {{ idx + 1 }}: {{ score }}
                </span>
              </div>
            </div>

            <!-- Center Column: Huge Scoreboard -->
            <div class="score-center-col">
              <div class="set-badge-title">SET {{ currentMatch.current_set }} (QUYẾT ĐỊNH)</div>

              <div class="jumbo-score-display">
                <div class="digit-box" :class="{ leading: currentMatch.player1.current_score >= currentMatch.player2.current_score }">
                  {{ currentMatch.player1.current_score }}
                </div>
                <div class="score-colon">:</div>
                <div class="digit-box" :class="{ leading: currentMatch.player2.current_score > currentMatch.player1.current_score }">
                  {{ currentMatch.player2.current_score }}
                </div>
              </div>

              <!-- Serving Indicator -->
              <div class="serving-bar">
                <span class="serving-tag">Serving</span>
                <span
                  class="material-symbols-outlined arrow-indicator"
                  :class="{ 'point-left': currentMatch.serving_player === 1, 'point-right': currentMatch.serving_player === 2 }"
                >
                  {{ currentMatch.serving_player === 1 ? 'arrow_back' : 'arrow_forward' }}
                </span>
                <span class="serving-name">{{ currentMatch.serving_player === 1 ? currentMatch.player1.name.split(' ')[0] : currentMatch.player2.name.split(' ')[0] }}</span>
              </div>
            </div>

            <!-- Right Player: Player 2 -->
            <div class="player-col player-right">
              <div class="player-avatar-box" :class="{ 'is-serving': currentMatch.serving_player === 2 }">
                <img
                  :src="currentMatch.player2.avatar"
                  :alt="currentMatch.player2.name"
                  class="player-photo"
                  @error="handleImageError($event, '/aura/court-2.jpg')"
                />
                <span v-if="currentMatch.serving_player === 2" class="serving-icon-badge" title="Đang giao cầu">
                  <span class="material-symbols-outlined text-[13px]">sports_tennis</span>
                </span>
              </div>

              <div class="player-info-box">
                <h2 class="player-name">{{ currentMatch.player2.name }}</h2>
                <div class="player-sub">
                  <span class="seed-text">{{ currentMatch.player2.seed }}</span>
                  <span class="sep">•</span>
                  <span class="club-text">{{ currentMatch.player2.club }}</span>
                </div>
              </div>

              <!-- Set History Scores -->
              <div class="set-history-row">
                <span
                  v-for="(score, idx) in currentMatch.player2.sets_won"
                  :key="idx"
                  class="set-chip"
                  :class="{ 'is-win': idx === 1 }"
                >
                  Set {{ idx + 1 }}: {{ score }}
                </span>
              </div>
            </div>
          </div>

          <!-- Referee Action Toolbar inside the Card -->
          <div class="referee-toolbar">
            <div class="referee-btn-group">
              <button
                type="button"
                class="btn-point btn-p1"
                :disabled="processing"
                @click="scorePoint(1)"
              >
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span>Điểm P1 ({{ currentMatch.player1.name.split(' ')[0] }})</span>
              </button>

              <button
                type="button"
                class="btn-tool"
                @click="toggleServer"
                title="Đổi bên giao cầu"
              >
                <span class="material-symbols-outlined text-[16px]">swap_horiz</span>
                <span>Đổi Giao</span>
              </button>

              <button
                type="button"
                class="btn-tool"
                @click="undoPoint"
                title="Hoàn tác 1 điểm"
              >
                <span class="material-symbols-outlined text-[16px]">undo</span>
                <span>Hoàn Tác</span>
              </button>

              <button
                type="button"
                class="btn-tool"
                :class="{ active: isAutoSimulating }"
                @click="toggleAutoSimulation"
              >
                <span class="material-symbols-outlined text-[16px]">{{ isAutoSimulating ? 'pause' : 'play_circle' }}</span>
                <span>{{ isAutoSimulating ? 'Dừng Auto' : 'Mô Phỏng Auto' }}</span>
              </button>

              <button
                type="button"
                class="btn-point btn-p2"
                :disabled="processing"
                @click="scorePoint(2)"
              >
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span>Điểm P2 ({{ currentMatch.player2.name.split(' ')[0] }})</span>
              </button>
            </div>
          </div>

          <!-- Bottom Active Rally Indicator Bar -->
          <div class="rally-bar-track">
            <div class="rally-bar-slide"></div>
          </div>
        </section>

        <!-- ================= REAL-TIME MATCH FEED ================= -->
        <section class="feed-card">
          <div class="feed-head">
            <div class="feed-title">
              <span class="material-symbols-outlined text-primary text-[18px]">history</span>
              <span>MATCH FEED (NHẬT KÝ TỪNG PHA CẦU)</span>
            </div>
            <span class="feed-hint">Tự động cập nhật thời gian thực</span>
          </div>

          <div class="ticker-box">
            <div class="ticker-fade-l"></div>
            <div class="ticker-fade-r"></div>

            <div class="ticker-content">
              <div
                v-for="evt in matchEvents"
                :key="evt.id"
                class="ticker-item"
              >
                <span class="evt-name">{{ evt.player }}</span>
                <span class="evt-desc">{{ evt.action }}</span>
                <span class="evt-score">{{ evt.score }}</span>
                <span class="evt-dot">•</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ================= SCORECARD & STATS ================= -->
        <div class="stats-row-grid">
          <!-- Left: Scorecard Breakdown Table -->
          <div class="detail-card">
            <h3 class="card-head-title">
              <span class="material-symbols-outlined text-[20px]">scoreboard</span>
              <span>Bảng Điểm Từng Set (Scorecard)</span>
            </h3>

            <div class="table-wrap">
              <table class="scorecard-table">
                <thead>
                  <tr>
                    <th>VẬN ĐỘNG VIÊN</th>
                    <th class="text-center">SET 1</th>
                    <th class="text-center">SET 2</th>
                    <th class="text-center highlight-col">SET 3 (LIVE)</th>
                    <th class="text-center">TỔNG</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="player-cell">
                      <img :src="currentMatch.player1.avatar" class="avatar-cell" />
                      <strong>{{ currentMatch.player1.name }}</strong>
                    </td>
                    <td class="text-center font-bold text-win">21</td>
                    <td class="text-center">18</td>
                    <td class="text-center highlight-col font-bold text-live">{{ currentMatch.player1.current_score }}</td>
                    <td class="text-center font-bold">{{ 21 + 18 + currentMatch.player1.current_score }}</td>
                  </tr>
                  <tr>
                    <td class="player-cell">
                      <img :src="currentMatch.player2.avatar" class="avatar-cell" />
                      <strong>{{ currentMatch.player2.name }}</strong>
                    </td>
                    <td class="text-center">19</td>
                    <td class="text-center font-bold text-win">21</td>
                    <td class="text-center highlight-col font-bold text-live">{{ currentMatch.player2.current_score }}</td>
                    <td class="text-center font-bold">{{ 19 + 21 + currentMatch.player2.current_score }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right: Insights & Specs -->
          <div class="detail-card">
            <h3 class="card-head-title">
              <span class="material-symbols-outlined text-[20px]">insights</span>
              <span>Chỉ Số Hiệu Suất (Match Insights)</span>
            </h3>

            <div class="insights-wrap">
              <!-- Aces Comparison -->
              <div class="stat-bar-group">
                <div class="stat-labels">
                  <span class="stat-num text-left">{{ currentMatch.stats.aces[0] }}</span>
                  <span class="stat-name">Aces (Giao cầu ăn điểm)</span>
                  <span class="stat-num text-right">{{ currentMatch.stats.aces[1] }}</span>
                </div>
                <div class="stat-track">
                  <div class="stat-fill-l" :style="{ width: `${(currentMatch.stats.aces[0] / (currentMatch.stats.aces[0] + currentMatch.stats.aces[1])) * 100}%` }"></div>
                  <div class="stat-fill-r" :style="{ width: `${(currentMatch.stats.aces[1] / (currentMatch.stats.aces[0] + currentMatch.stats.aces[1])) * 100}%` }"></div>
                </div>
              </div>

              <!-- Winners Comparison -->
              <div class="stat-bar-group">
                <div class="stat-labels">
                  <span class="stat-num text-left">{{ currentMatch.stats.winners[0] }}</span>
                  <span class="stat-name">Winners (Cú đánh ăn điểm)</span>
                  <span class="stat-num text-right">{{ currentMatch.stats.winners[1] }}</span>
                </div>
                <div class="stat-track">
                  <div class="stat-fill-l" :style="{ width: `${(currentMatch.stats.winners[0] / (currentMatch.stats.winners[0] + currentMatch.stats.winners[1])) * 100}%` }"></div>
                  <div class="stat-fill-r" :style="{ width: `${(currentMatch.stats.winners[1] / (currentMatch.stats.winners[0] + currentMatch.stats.winners[1])) * 100}%` }"></div>
                </div>
              </div>

              <!-- 3 Key Specs Box -->
              <div class="key-specs-3col">
                <div class="spec-card">
                  <div class="spec-lbl">Tốc Độ Đập Max</div>
                  <div class="spec-val">{{ currentMatch.stats.smash_speed_max }}</div>
                </div>
                <div class="spec-card">
                  <div class="spec-lbl">Rally Dài Nhất</div>
                  <div class="spec-val">{{ currentMatch.stats.longest_rally }}</div>
                </div>
                <div class="spec-card">
                  <div class="spec-lbl">Thời Gian</div>
                  <div class="spec-val">{{ currentMatch.stats.duration }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- ================= LIVE STREAM MODAL ================= -->
    <div v-if="isStreamModalOpen" class="modal-backdrop" @click.self="isStreamModalOpen = false">
      <div class="modal-card stream-modal">
        <div class="modal-head">
          <div>
            <span class="pulse-live-badge">
              <span class="live-ping-dot"></span>
              LIVESTREAM SIMULATOR
            </span>
            <h2 class="modal-title mt-1">{{ currentMatch.tournament_name }} · {{ currentMatch.court_label }}</h2>
          </div>
          <button class="modal-close" @click="isStreamModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-stream">
          <!-- Court Stage -->
          <div class="court-visual-box">
            <img src="/aura/court-0.jpg" alt="Aura Live Court" class="court-bg-img" />
            <div class="court-scan-line"></div>

            <div class="overlay-match-score">
              <span>{{ currentMatch.player1.name.split(' ')[0] }}</span>
              <strong class="score-text">{{ currentMatch.player1.current_score }} - {{ currentMatch.player2.current_score }}</strong>
              <span>{{ currentMatch.player2.name.split(' ')[0] }}</span>
            </div>

            <div class="shuttle-anim-orb">
              <span class="material-symbols-outlined text-[20px] text-white">sports_tennis</span>
            </div>
          </div>

          <!-- Commentary -->
          <div class="commentary-box">
            <h4 class="commentary-title">
              <span class="material-symbols-outlined text-[16px] text-primary">record_voice_over</span>
              <span>Bình Luận Trực Tiếp Trận Đấu</span>
            </h4>
            <div class="commentary-feed">
              <div class="comm-item">
                <span class="comm-time">53'12"</span>
                <span class="comm-text"><strong>Marcus T.</strong> tung ra cú đập cầu chéo sân sấm sét 398 km/h!</span>
              </div>
              <div class="comm-item">
                <span class="comm-time">52'40"</span>
                <span class="comm-text"><strong>David K.</strong> nỗ lực cứu cầu nhưng không qua lưới. Set 3 đang vô cùng căng thẳng!</span>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-primary-pill" @click="isStreamModalOpen = false">Đóng Cửa Sổ</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ==========================================================================
   LiveScore Page Layout (Flawless Spacing & Navbar Clearance)
   ========================================================================== */
.livescore-page {
  position: relative;
  min-height: 100vh;
  background-color: var(--bg, #eff1f4);
  color: var(--on-surface, #191c1d);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  transition: background-color 0.3s ease, color 0.3s ease;
  overflow-x: hidden;
}

.ambient-glow-wrapper {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  z-index: 0;
}

.glow-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
}

.glow-orb-left {
  width: 480px;
  height: 480px;
  top: -120px;
  left: -120px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.6) 0%, transparent 70%);
}

.glow-orb-right {
  width: 440px;
  height: 440px;
  top: 100px;
  right: -80px;
  background: radial-gradient(circle, rgba(120, 85, 94, 0.35) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-left {
  background: radial-gradient(circle, rgba(248, 180, 196, 0.15) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-right {
  background: radial-gradient(circle, rgba(83, 51, 62, 0.3) 0%, transparent 70%);
}

.livescore-main-content {
  position: relative;
  z-index: 1;
  width: 100%;
  padding: 16px 20px 80px;
}

@media (min-width: 768px) {
  .livescore-main-content {
    padding: 24px 32px 96px;
  }
}

.alerts-container {
  max-width: 1152px;
  margin: 0 auto 12px;
}

.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 8px;
}

.alert-success {
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border: 1px solid rgba(46, 125, 50, 0.25);
}

.alert-danger {
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
  border: 1px solid rgba(198, 40, 40, 0.25);
}

.livescore-container {
  max-width: 1152px;
  margin: 0 auto;
}

/* ================= TOP NAV BAR ================= */
.top-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary, #78555e);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 8px;
  transition: all 0.2s;
}

.back-link:hover {
  background-color: var(--primary-container, #ffd1dc);
}

:global(html.dark) .back-link {
  color: var(--primary, #f8b4c4);
}

:global(html.dark) .back-link:hover {
  background-color: var(--primary-container, #53333e);
}

.court-switcher-pills {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px;
  background-color: var(--surface-container, #edeeef);
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .court-switcher-pills {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.court-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.court-tab-btn.active {
  background-color: var(--card-bg, #ffffff);
  color: var(--on-surface, #191c1d);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .court-tab-btn.active {
  background-color: var(--surface-container-high, #353943);
  color: var(--primary, #f8b4c4);
}

.live-ping-dot-sm {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #f57f17;
}

.btn-watch-stream-top {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-watch-stream-top:hover {
  background-color: #5e3e47;
}

:global(html.dark) .btn-watch-stream-top {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* ================= MATCH META HEADER ================= */
.match-meta-header {
  margin-bottom: 20px;
}

.meta-tags-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.pulse-live-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  background-color: #ffebee;
  color: #c62828;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  border-radius: 9999px;
  border: 1px solid rgba(198, 40, 40, 0.2);
}

:global(html.dark) .pulse-live-badge {
  background-color: rgba(198, 40, 40, 0.25);
  color: #f87171;
  border-color: rgba(248, 113, 113, 0.3);
}

.live-ping-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #c62828;
}

:global(html.dark) .live-ping-dot {
  background-color: #f87171;
}

.court-chip-badge {
  padding: 3px 10px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #5d5f5f);
  font-size: 11px;
  font-weight: 600;
  border-radius: 9999px;
}

:global(html.dark) .court-chip-badge {
  background-color: var(--surface-container, #2b2e36);
  color: var(--on-surface-variant, #c0c4cc);
}

.tournament-chip-badge {
  font-size: 12px;
  font-weight: 600;
  color: var(--primary, #78555e);
}

:global(html.dark) .tournament-chip-badge {
  color: var(--primary, #f8b4c4);
}

.match-title {
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

@media (min-width: 768px) {
  .match-title {
    font-size: 30px;
  }
}

/* ================= JUMBO SCORE CARD ================= */
.jumbo-score-card {
  position: relative;
  background-color: var(--card-bg, #ffffff);
  border-radius: 24px;
  padding: 24px 20px 20px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  margin-bottom: 24px;
  overflow: hidden;
}

:global(html.dark) .jumbo-score-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
  box-shadow: 0 10px 36px rgba(0, 0, 0, 0.35);
}

@media (min-width: 768px) {
  .jumbo-score-card {
    padding: 28px 32px 24px;
  }
}

.score-card-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  align-items: center;
}

@media (min-width: 800px) {
  .score-card-grid {
    grid-template-columns: 1fr auto 1fr;
    gap: 28px;
  }
}

/* Player Column */
.player-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

@media (min-width: 800px) {
  .player-left {
    align-items: flex-end;
    text-align: right;
  }
  .player-right {
    align-items: flex-start;
    text-align: left;
  }
}

.player-avatar-box {
  position: relative;
  width: 68px;
  height: 68px;
  border-radius: 50%;
  padding: 3px;
  background: var(--surface-container, #edeeef);
  border: 2px solid var(--border, #e0e4ea);
  margin-bottom: 10px;
  transition: all 0.3s;
}

:global(html.dark) .player-avatar-box {
  background: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.player-avatar-box.is-serving {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 4px rgba(255, 209, 220, 0.6);
}

:global(html.dark) .player-avatar-box.is-serving {
  border-color: var(--primary, #f8b4c4);
  box-shadow: 0 0 0 4px rgba(248, 180, 196, 0.2);
}

.player-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.serving-icon-badge {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .serving-icon-badge {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

.player-info-box {
  margin-bottom: 8px;
}

.player-name {
  font-size: 17px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  margin: 0 0 2px;
}

.player-sub {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  display: flex;
  align-items: center;
  gap: 6px;
}

@media (min-width: 800px) {
  .player-left .player-sub {
    justify-content: flex-end;
  }
  .player-right .player-sub {
    justify-content: flex-start;
  }
}

.seed-text {
  font-weight: 700;
}

.set-history-row {
  display: flex;
  gap: 6px;
}

.set-chip {
  padding: 3px 8px;
  border-radius: 9999px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #5d5f5f);
  font-size: 11px;
  font-weight: 600;
}

:global(html.dark) .set-chip {
  background-color: var(--surface-container, #2b2e36);
  color: var(--on-surface-variant, #c0c4cc);
}

.set-chip.is-win {
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .set-chip.is-win {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

/* Center Score Column */
.score-center-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.set-badge-title {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: var(--on-surface-variant, #5d5f5f);
  margin-bottom: 6px;
}

.jumbo-score-display {
  display: flex;
  align-items: center;
  gap: 16px;
}

.digit-box {
  font-size: 56px;
  font-weight: 900;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  color: var(--on-surface-variant, #5d5f5f);
  opacity: 0.75;
}

@media (min-width: 768px) {
  .digit-box {
    font-size: 72px;
  }
}

.digit-box.leading {
  color: var(--primary, #78555e);
  opacity: 1;
  text-shadow: 0 4px 16px rgba(120, 85, 94, 0.2);
}

:global(html.dark) .digit-box.leading {
  color: var(--primary, #f8b4c4);
  text-shadow: 0 4px 20px rgba(248, 180, 196, 0.35);
}

.score-colon {
  font-size: 40px;
  font-weight: 800;
  color: var(--outline-variant, #d3c3c5);
}

.serving-bar {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  margin-top: 10px;
  font-size: 11px;
}

:global(html.dark) .serving-bar {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.serving-tag {
  font-weight: 700;
  text-transform: uppercase;
  color: var(--on-surface-variant, #5d5f5f);
}

.arrow-indicator {
  font-size: 16px;
  color: var(--primary, #78555e);
}

:global(html.dark) .arrow-indicator {
  color: var(--primary, #f8b4c4);
}

.serving-name {
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

/* Referee Toolbar inside card */
.referee-toolbar {
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .referee-toolbar {
  border-color: var(--border, #353942);
}

.referee-btn-group {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-point {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 16px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-point:hover {
  background-color: #5e3e47;
  transform: translateY(-1px);
}

:global(html.dark) .btn-point {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

.btn-tool {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-tool:hover {
  background-color: var(--surface-container-high, #e7e8e9);
}

.btn-tool.active {
  background-color: var(--primary, #78555e);
  color: #ffffff;
}

:global(html.dark) .btn-tool {
  background-color: var(--surface-container, #2b2e36);
  color: var(--on-surface, #f3f4f6);
  border-color: var(--border, #353942);
}

:global(html.dark) .btn-tool.active {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* Rally Bar */
.rally-bar-track {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--surface-container, #edeeef);
  overflow: hidden;
}

:global(html.dark) .rally-bar-track {
  background: var(--surface-container, #2b2e36);
}

.rally-bar-slide {
  height: 100%;
  width: 30%;
  background: linear-gradient(90deg, transparent, var(--primary, #78555e), transparent);
  animation: rally-slide-anim 3s infinite alternate ease-in-out;
}

:global(html.dark) .rally-bar-slide {
  background: linear-gradient(90deg, transparent, var(--primary, #f8b4c4), transparent);
}

@keyframes rally-slide-anim {
  0% { transform: translateX(0); }
  100% { transform: translateX(250%); }
}

/* ================= MATCH FEED CARD ================= */
.feed-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 18px;
  padding: 16px 20px;
  border: 1px solid var(--border, #e0e4ea);
  margin-bottom: 24px;
}

:global(html.dark) .feed-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.feed-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.feed-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--on-surface, #191c1d);
}

.feed-hint {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
}

.ticker-box {
  position: relative;
  overflow: hidden;
  height: 38px;
  display: flex;
  align-items: center;
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 10px;
  padding: 0 16px;
}

:global(html.dark) .ticker-box {
  background-color: var(--surface-container, #2b2e36);
}

.ticker-fade-l,
.ticker-fade-r {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 24px;
  z-index: 5;
  pointer-events: none;
}

.ticker-fade-l {
  left: 0;
  background: linear-gradient(to right, var(--surface-subtle, #f5f6f8), transparent);
}

.ticker-fade-r {
  right: 0;
  background: linear-gradient(to left, var(--surface-subtle, #f5f6f8), transparent);
}

:global(html.dark) .ticker-fade-l {
  background: linear-gradient(to right, var(--surface-container, #2b2e36), transparent);
}

:global(html.dark) .ticker-fade-r {
  background: linear-gradient(to left, var(--surface-container, #2b2e36), transparent);
}

.ticker-content {
  display: flex;
  align-items: center;
  gap: 20px;
  white-space: nowrap;
  animation: ticker-loop 22s linear infinite;
}

@keyframes ticker-loop {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.ticker-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.evt-name {
  font-weight: 700;
  color: var(--primary, #78555e);
}

:global(html.dark) .evt-name {
  color: var(--primary, #f8b4c4);
}

.evt-desc {
  color: var(--on-surface-variant, #5d5f5f);
}

.evt-score {
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 10px;
  font-weight: 700;
  font-family: monospace;
}

:global(html.dark) .evt-score {
  background: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
  color: var(--on-surface, #f3f4f6);
}

.evt-dot {
  color: var(--outline-variant, #d3c3c5);
}

/* ================= SCORECARD & STATS ================= */
.stats-row-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

@media (min-width: 860px) {
  .stats-row-grid {
    grid-template-columns: 1.15fr 1fr;
  }
}

.detail-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 20px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

:global(html.dark) .detail-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.card-head-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0 0 16px;
}

.table-wrap {
  width: 100%;
  overflow-x: auto;
}

.scorecard-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.scorecard-table th {
  padding: 8px 10px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.scorecard-table td {
  padding: 12px 10px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .scorecard-table th,
:global(html.dark) .scorecard-table td {
  border-color: var(--border, #353942);
}

.player-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.avatar-cell {
  width: 22px;
  height: 22px;
  border-radius: 50%;
}

.text-win {
  color: var(--primary, #78555e);
}

:global(html.dark) .text-win {
  color: var(--primary, #f8b4c4);
}

.text-live {
  color: #f57f17;
}

.highlight-col {
  background-color: rgba(255, 209, 220, 0.15);
}

:global(html.dark) .highlight-col {
  background-color: rgba(248, 180, 196, 0.08);
}

/* Stat bars & Insights */
.insights-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.stat-bar-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-labels {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

.stat-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
}

.stat-num {
  font-size: 14px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  min-width: 24px;
}

.stat-track {
  height: 8px;
  width: 100%;
  background-color: var(--surface-container, #edeeef);
  border-radius: 9999px;
  overflow: hidden;
  display: flex;
}

:global(html.dark) .stat-track {
  background-color: var(--surface-container, #2b2e36);
}

.stat-fill-l {
  background-color: var(--primary, #78555e);
  height: 100%;
  border-radius: 9999px 0 0 9999px;
  transition: width 0.3s ease;
}

:global(html.dark) .stat-fill-l {
  background-color: var(--primary, #f8b4c4);
}

.stat-fill-r {
  background-color: var(--outline-variant, #d3c3c5);
  height: 100%;
  border-radius: 0 9999px 9999px 0;
  transition: width 0.3s ease;
}

:global(html.dark) .stat-fill-r {
  background-color: var(--outline-variant, #4a4e59);
}

.key-specs-3col {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-top: 6px;
}

.spec-card {
  padding: 14px 10px;
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 12px;
  border: 1px solid var(--border, #e0e4ea);
  text-align: center;
}

:global(html.dark) .spec-card {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.spec-lbl {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--on-surface-variant, #5d5f5f);
  margin-bottom: 4px;
}

.spec-val {
  font-size: 14px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

/* ================= STREAM MODAL ================= */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.stream-modal {
  max-width: 760px;
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 20px;
  border: 1px solid var(--border, #e0e4ea);
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

:global(html.dark) .stream-modal {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.modal-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  margin: 4px 0 0;
}

.modal-close {
  background: transparent;
  border: none;
  color: var(--on-surface-variant, #5d5f5f);
  cursor: pointer;
  padding: 4px;
}

.court-visual-box {
  position: relative;
  width: 100%;
  height: 260px;
  border-radius: 14px;
  overflow: hidden;
  margin: 14px 0;
  border: 1px solid var(--border, #e0e4ea);
}

.court-bg-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: brightness(0.65);
}

.overlay-match-score {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 6px 14px;
  border-radius: 9999px;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.score-text {
  color: #f8b4c4;
  margin: 0 6px;
  font-size: 14px;
}

.shuttle-anim-orb {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--primary, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 16px rgba(255, 209, 220, 0.8);
  animation: shuttle-bounce-anim 2s infinite ease-in-out;
}

@keyframes shuttle-bounce-anim {
  0% { transform: translate(-80px, -30px) scale(0.9); }
  50% { transform: translate(60px, 20px) scale(1.1); }
  100% { transform: translate(-80px, -30px) scale(0.9); }
}

.commentary-box {
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 12px;
  padding: 12px 14px;
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .commentary-box {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.commentary-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--on-surface, #191c1d);
  margin: 0 0 8px;
}

.commentary-feed {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.comm-item {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  font-size: 12px;
  line-height: 1.4;
}

.comm-time {
  font-size: 10px;
  font-weight: 700;
  font-family: monospace;
  color: var(--primary, #78555e);
  padding: 1px 4px;
  background-color: var(--card-bg, #ffffff);
  border-radius: 4px;
}

:global(html.dark) .comm-time {
  color: var(--primary, #f8b4c4);
  background-color: var(--surface-container-high, #353943);
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.btn-primary-pill {
  padding: 8px 18px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  border-radius: 9999px;
  border: none;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
}

:global(html.dark) .btn-primary-pill {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
  font-weight: 700;
}
</style>
