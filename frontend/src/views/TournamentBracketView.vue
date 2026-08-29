<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { getAvatarUrl } from '@/lib/avatar'

interface Player {
  name: string
  avatar: string
  seed?: string
  club?: string
  score?: number
  isWinner?: boolean
  isServing?: boolean
  sets?: number[]
}

interface BracketMatch {
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

interface GroupStanding {
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

const route = useRoute()
const router = useRouter()

// Current Tournament State
const currentTournamentId = ref<number>(1)
const selectedCategory = ref<'singles_men' | 'doubles_men' | 'mixed_doubles'>('singles_men')
const activeTab = ref<'bracket' | 'standings'>('bracket')
const zoomLevel = ref<number>(1)
const selectedMatch = ref<BracketMatch | null>(null)
const isMatchDetailModalOpen = ref<boolean>(false)

interface TournamentItem {
  id: number
  name: string
  status: string
  dates: string
  location: string
  playersCount: number
}

// Tournaments List
const tournaments: TournamentItem[] = [
  { id: 1, name: "Aura Summer Open '26", status: 'live', dates: '12 - 14 Th08, 2026', location: 'Aura Center Court A, Q.1', playersCount: 32 },
  { id: 2, name: 'Aura Grand Slam Master 2026', status: 'ongoing', dates: '20 - 25 Th09, 2026', location: 'Aura Arena Riverside', playersCount: 64 },
  { id: 3, name: 'Aura Winter Championship', status: 'open', dates: '05 - 10 Th12, 2026', location: 'Aura Complex District 7', playersCount: 32 },
]

const currentTournament = computed<TournamentItem>(() => {
  return tournaments.find((t) => t.id === currentTournamentId.value) || (tournaments[0] as TournamentItem)
})

// Bracket Data: Quarter-Finals (4 matches)
const quarterMatches = ref<BracketMatch[]>([
  {
    id: 1,
    round: 'quarter',
    matchNumber: 1,
    label: 'Trận 1 · Tứ Kết 1',
    time: '09:00',
    court: 'Sân 1',
    status: 'final',
    player1: {
      name: 'E. Hayes (Hoàng)',
      avatar: getAvatarUrl('E. Hayes'),
      seed: 'Seed 1',
      club: 'Aura Elite',
      score: 6,
      isWinner: true,
      sets: [21, 21],
    },
    player2: {
      name: 'M. Chen (Minh)',
      avatar: getAvatarUrl('M. Chen'),
      seed: 'Seed 8',
      club: 'Dragon Court',
      score: 4,
      isWinner: false,
      sets: [18, 16],
    },
  },
  {
    id: 2,
    round: 'quarter',
    matchNumber: 2,
    label: 'Trận 2 · Tứ Kết 2',
    time: '10:15',
    court: 'Sân 2',
    status: 'final',
    player1: {
      name: 'J. Rossi (Khánh)',
      avatar: getAvatarUrl('J. Rossi'),
      seed: 'Seed 5',
      club: 'Saigon Club',
      score: 2,
      isWinner: false,
      sets: [15, 14],
    },
    player2: {
      name: 'D. Kim (Quốc Đạt)',
      avatar: getAvatarUrl('D. Kim'),
      seed: 'Seed 4',
      club: 'Riverside',
      score: 6,
      isWinner: true,
      sets: [21, 21],
    },
  },
  {
    id: 3,
    round: 'quarter',
    matchNumber: 3,
    label: 'Trận 3 · Tứ Kết 3',
    time: '11:30',
    court: 'Sân 3',
    status: 'final',
    player1: {
      name: 'A. Larson (Thành)',
      avatar: getAvatarUrl('A. Larson'),
      seed: 'Seed 3',
      club: 'Metro Badminton',
      score: 7,
      isWinner: true,
      sets: [21, 19, 21],
    },
    player2: {
      name: 'T. Wright (Tuấn)',
      avatar: getAvatarUrl('T. Wright'),
      seed: 'Seed 6',
      club: 'Aura Youth',
      score: 5,
      isWinner: false,
      sets: [17, 21, 18],
    },
  },
  {
    id: 4,
    round: 'quarter',
    matchNumber: 4,
    label: 'Trận 4 · Tứ Kết 4',
    time: '13:00',
    court: 'Sân 4',
    status: 'final',
    player1: {
      name: 'S. Patel (Sơn)',
      avatar: getAvatarUrl('S. Patel'),
      seed: 'Seed 7',
      club: 'Phoenix Team',
      score: 3,
      isWinner: false,
      sets: [16, 17],
    },
    player2: {
      name: 'K. Visser (Việt)',
      avatar: getAvatarUrl('K. Visser'),
      seed: 'Seed 2',
      club: 'Aura Pro Team',
      score: 6,
      isWinner: true,
      sets: [21, 21],
    },
  },
])

// Bracket Data: Semi-Finals (2 matches)
const semiMatches = ref<BracketMatch[]>([
  {
    id: 5,
    round: 'semi',
    matchNumber: 5,
    label: 'Trận 5 · Bán Kết 1',
    time: '14:30',
    court: 'Sân 1 (Center Court)',
    courtId: 101,
    status: 'live',
    player1: {
      name: 'Marcus T. (E. Hayes)',
      avatar: getAvatarUrl('Marcus T.'),
      seed: 'Seed 1',
      club: 'Aura Elite',
      score: 21,
      isServing: true,
      sets: [21, 18, 21],
    },
    player2: {
      name: 'David K. (D. Kim)',
      avatar: getAvatarUrl('David K.'),
      seed: 'Seed 4',
      club: 'Riverside',
      score: 18,
      isServing: false,
      sets: [19, 21, 18],
    },
  },
  {
    id: 6,
    round: 'semi',
    matchNumber: 6,
    label: 'Trận 6 · Bán Kết 2',
    time: '16:00',
    court: 'Sân 2',
    courtId: 102,
    status: 'upcoming',
    player1: {
      name: 'A. Larson (Thành)',
      avatar: getAvatarUrl('A. Larson'),
      seed: 'Seed 3',
      club: 'Metro Badminton',
    },
    player2: {
      name: 'K. Visser (Việt)',
      avatar: getAvatarUrl('K. Visser'),
      seed: 'Seed 2',
      club: 'Aura Pro Team',
    },
  },
])

// Bracket Data: Championship Finals
const finalMatch = ref<BracketMatch>({
  id: 7,
  round: 'final',
  matchNumber: 7,
  label: 'Chung Kết (Grand Finals)',
  time: '18:30 (Chủ Nhật)',
  court: 'Sân 1 (Center Court)',
  status: 'upcoming',
  player1: {
    name: 'Thắng Trận 5 (TBD)',
    avatar: '/aura/court-1.jpg',
    club: 'Winner Semi-Final 1',
  },
  player2: {
    name: 'Thắng Trận 6 (TBD)',
    avatar: '/aura/court-2.jpg',
    club: 'Winner Semi-Final 2',
  },
})

// Group Standings Data (Round Robin Phase)
const groupAStandings = ref<GroupStanding[]>([
  {
    rank: 1,
    name: 'E. Hayes (Hoàng)',
    avatar: getAvatarUrl('E. Hayes'),
    club: 'Aura Elite',
    isQualified: true,
    mp: 3,
    w: 3,
    l: 0,
    setsRatio: '6 - 1',
    points: 9,
  },
  {
    rank: 2,
    name: 'D. Kim (Quốc Đạt)',
    avatar: getAvatarUrl('D. Kim'),
    club: 'Riverside',
    isQualified: true,
    mp: 3,
    w: 2,
    l: 1,
    setsRatio: '5 - 3',
    points: 6,
  },
  {
    rank: 3,
    name: 'M. Reyes (Minh)',
    avatar: getAvatarUrl('M. Reyes'),
    club: 'Dragon Court',
    isQualified: false,
    mp: 3,
    w: 1,
    l: 2,
    setsRatio: '3 - 4',
    points: 3,
  },
  {
    rank: 4,
    name: 'M. Chen (Mỹ Linh)',
    avatar: getAvatarUrl('M. Chen'),
    club: 'Saigon Smashers',
    isQualified: false,
    mp: 3,
    w: 0,
    l: 3,
    setsRatio: '0 - 6',
    points: 0,
  },
])

const groupBStandings = ref<GroupStanding[]>([
  {
    rank: 1,
    name: 'K. Visser (Việt)',
    avatar: getAvatarUrl('K. Visser'),
    club: 'Aura Pro Team',
    isQualified: true,
    mp: 3,
    w: 3,
    l: 0,
    setsRatio: '6 - 0',
    points: 9,
  },
  {
    rank: 2,
    name: 'A. Larson (Thành)',
    avatar: getAvatarUrl('A. Larson'),
    club: 'Metro Badminton',
    isQualified: true,
    mp: 3,
    w: 2,
    l: 1,
    setsRatio: '4 - 3',
    points: 6,
  },
  {
    rank: 3,
    name: 'T. Wright (Tuấn)',
    avatar: getAvatarUrl('T. Wright'),
    club: 'Aura Youth',
    isQualified: false,
    mp: 3,
    w: 1,
    l: 2,
    setsRatio: '3 - 5',
    points: 3,
  },
  {
    rank: 4,
    name: 'S. Patel (Sơn)',
    avatar: getAvatarUrl('S. Patel'),
    club: 'Phoenix Team',
    isQualified: false,
    mp: 3,
    w: 0,
    l: 3,
    setsRatio: '1 - 6',
    points: 0,
  },
])

function openMatchDetails(match: BracketMatch) {
  selectedMatch.value = match
  isMatchDetailModalOpen.value = true
}

function handleImageError(event: Event, fallback = '/aura/court-1.jpg') {
  const target = event.target as HTMLImageElement | null
  if (target && target.src !== fallback) {
    target.src = fallback
  }
}

function zoomIn() {
  if (zoomLevel.value < 1.4) zoomLevel.value += 0.1
}

function zoomOut() {
  if (zoomLevel.value > 0.7) zoomLevel.value -= 0.1
}

function resetZoom() {
  zoomLevel.value = 1
}

onMounted(() => {
  if (route.query.tab === 'standings') {
    activeTab.value = 'standings'
  }
})
</script>

<template>
  <div class="bracket-page">
    <!-- Ambient Background Glow -->
    <div class="ambient-glow-wrapper" aria-hidden="true">
      <div class="glow-orb glow-orb-left"></div>
      <div class="glow-orb glow-orb-right"></div>
    </div>

    <main class="bracket-main-content">
      <div class="bracket-container">
        <!-- Top Breadcrumb & Actions Bar -->
        <div class="top-nav-bar">
          <RouterLink to="/tournaments" class="back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            <span>Danh sách giải đấu</span>
          </RouterLink>

          <!-- Live Score Direct CTA -->
          <RouterLink to="/tournaments/live" class="btn-live-shortcut">
            <span class="live-ping-dot"></span>
            <span>Xem Tỷ Số Trực Tiếp</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </RouterLink>
        </div>

        <!-- Sticky Header with Tournament Title and Tabs -->
        <div class="tournament-header-card">
          <div class="header-main-row">
            <div class="header-meta-left">
              <div class="status-tags-row">
                <span class="badge-live-pulse">
                  <span class="live-ping-dot"></span>
                  LIVE TOURNAMENT
                </span>
                <span class="chip-bracket-type">Single Elimination Bracket</span>
              </div>
              <h1 class="tournament-title">{{ currentTournament.name }}</h1>
              <div class="tournament-sub-meta">
                <span class="meta-item">
                  <span class="material-symbols-outlined">calendar_month</span>
                  <span>{{ currentTournament.dates }}</span>
                </span>
                <span class="meta-item">
                  <span class="material-symbols-outlined">location_on</span>
                  <span>{{ currentTournament.location }}</span>
                </span>
                <span class="meta-item">
                  <span class="material-symbols-outlined">groups</span>
                  <span>{{ currentTournament.playersCount }} Vận Động Viên</span>
                </span>
              </div>
            </div>

            <!-- Header View Switcher & Zoom Controls -->
            <div class="header-controls-right">
              <div class="segmented-tabs-wrap">
                <button
                  type="button"
                  class="tab-btn"
                  :class="{ active: activeTab === 'bracket' }"
                  @click="activeTab = 'bracket'"
                >
                  <span class="material-symbols-outlined text-[16px]">account_tree</span>
                  <span>Sơ Đồ Loại Trực Tiếp (Bracket)</span>
                </button>
                <button
                  type="button"
                  class="tab-btn"
                  :class="{ active: activeTab === 'standings' }"
                  @click="activeTab = 'standings'"
                >
                  <span class="material-symbols-outlined text-[16px]">leaderboard</span>
                  <span>Bảng Điểm Vòng Bảng (Round Robin)</span>
                </button>
              </div>

              <!-- Zoom Tools (Visible in bracket mode) -->
              <div v-if="activeTab === 'bracket'" class="zoom-tools-group">
                <button type="button" class="zoom-btn" title="Thu nhỏ" @click="zoomOut">
                  <span class="material-symbols-outlined">remove</span>
                </button>
                <button type="button" class="zoom-btn zoom-val" title="Đặt lại" @click="resetZoom">
                  {{ Math.round(zoomLevel * 100) }}%
                </button>
                <button type="button" class="zoom-btn" title="Phóng to" @click="zoomIn">
                  <span class="material-symbols-outlined">add</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ================= BRACKET TAB CONTENT ================= -->
        <section v-if="activeTab === 'bracket'" class="bracket-board-card">
          <div class="bracket-canvas-viewport">
            <div
              class="bracket-tree-layout"
              :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'top left' }"
            >
              <!-- SVG Bracket Tree Connector Lines -->
              <svg class="bracket-svg-lines" width="1180" height="760">
                <!-- QF 1 & 2 -> Semi 1 -->
                <path d="M 280 90 L 330 90 L 330 180 L 380 180" fill="none" class="svg-path completed" />
                <path d="M 280 270 L 330 270 L 330 180 L 380 180" fill="none" class="svg-path completed" />

                <!-- QF 3 & 4 -> Semi 2 -->
                <path d="M 280 450 L 330 450 L 330 540 L 380 540" fill="none" class="svg-path completed" />
                <path d="M 280 630 L 330 630 L 330 540 L 380 540" fill="none" class="svg-path completed" />

                <!-- Semi 1 & 2 -> Grand Finals -->
                <path d="M 660 180 L 710 180 L 710 360 L 760 360" fill="none" class="svg-path live" />
                <path d="M 660 540 L 710 540 L 710 360 L 760 360" fill="none" class="svg-path upcoming" />

                <!-- Finals to Trophy -->
                <path d="M 1040 360 L 1100 360" fill="none" class="svg-path trophy-path" />
              </svg>

              <!-- COLUMN 1: QUARTER-FINALS (TỨ KẾT) -->
              <div class="bracket-col col-quarter">
                <div class="col-header">
                  <span class="col-title">TỨ KẾT (QUARTER-FINALS)</span>
                  <span class="col-count">4 Trận</span>
                </div>

                <div class="match-cards-stack gap-stack-qf">
                  <div
                    v-for="match in quarterMatches"
                    :key="match.id"
                    class="match-card"
                    :class="{ 'is-completed': match.status === 'final' }"
                    @click="openMatchDetails(match)"
                  >
                    <div class="match-card-head">
                      <span class="match-lbl">{{ match.label }}</span>
                      <span class="match-badge badge-final">Đã Xong</span>
                    </div>

                    <!-- Player 1 -->
                    <div class="match-player-row" :class="{ 'is-winner': match.player1.isWinner, 'is-loser': !match.player1.isWinner }">
                      <div class="player-left-info">
                        <img :src="match.player1.avatar" class="player-thumb" @error="handleImageError($event)" />
                        <span class="player-label">{{ match.player1.name }}</span>
                      </div>
                      <span class="score-digit">{{ match.player1.score }}</span>
                    </div>

                    <!-- Player 2 -->
                    <div class="match-player-row" :class="{ 'is-winner': match.player2.isWinner, 'is-loser': !match.player2.isWinner }">
                      <div class="player-left-info">
                        <img :src="match.player2.avatar" class="player-thumb" @error="handleImageError($event)" />
                        <span class="player-label">{{ match.player2.name }}</span>
                      </div>
                      <span class="score-digit">{{ match.player2.score }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- COLUMN 2: SEMI-FINALS (BÁN KẾT) -->
              <div class="bracket-col col-semi">
                <div class="col-header">
                  <span class="col-title">BÁN KẾT (SEMI-FINALS)</span>
                  <span class="col-count">2 Trận</span>
                </div>

                <div class="match-cards-stack gap-stack-semi">
                  <div
                    v-for="match in semiMatches"
                    :key="match.id"
                    class="match-card"
                    :class="{ 'is-live': match.status === 'live', 'is-upcoming': match.status === 'upcoming' }"
                    @click="openMatchDetails(match)"
                  >
                    <div class="match-card-head">
                      <span class="match-lbl">{{ match.label }}</span>
                      <span v-if="match.status === 'live'" class="match-badge badge-live">
                        <span class="live-ping-dot-sm"></span>
                        TRỰC TIẾP
                      </span>
                      <span v-else class="match-badge badge-upcoming">Sắp Đấu {{ match.time }}</span>
                    </div>

                    <!-- Player 1 -->
                    <div class="match-player-row" :class="{ 'is-serving': match.player1.isServing }">
                      <div class="player-left-info">
                        <img :src="match.player1.avatar" class="player-thumb" @error="handleImageError($event)" />
                        <span class="player-label">{{ match.player1.name }}</span>
                        <span v-if="match.player1.isServing" class="material-symbols-outlined serve-racket-icon">sports_tennis</span>
                      </div>
                      <span class="score-digit" :class="{ 'highlight-live': match.status === 'live' }">
                        {{ match.player1.score ?? '-' }}
                      </span>
                    </div>

                    <!-- Player 2 -->
                    <div class="match-player-row" :class="{ 'is-serving': match.player2.isServing }">
                      <div class="player-left-info">
                        <img :src="match.player2.avatar" class="player-thumb" @error="handleImageError($event)" />
                        <span class="player-label">{{ match.player2.name }}</span>
                        <span v-if="match.player2.isServing" class="material-symbols-outlined serve-racket-icon">sports_tennis</span>
                      </div>
                      <span class="score-digit" :class="{ 'highlight-live': match.status === 'live' }">
                        {{ match.player2.score ?? '-' }}
                      </span>
                    </div>

                    <!-- Live match action button -->
                    <div v-if="match.status === 'live'" class="match-live-footer">
                      <RouterLink to="/tournaments/live" class="btn-goto-live" @click.stop>
                        <span>Mở Bảng Điểm Live</span>
                        <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                      </RouterLink>
                    </div>
                  </div>
                </div>
              </div>

              <!-- COLUMN 3: CHAMPIONSHIP FINALS (CHUNG KẾT) -->
              <div class="bracket-col col-final">
                <div class="col-header header-final">
                  <span class="material-symbols-outlined text-amber-500">military_tech</span>
                  <span class="col-title text-amber-500 font-bold">CHUNG KẾT (GRAND FINALS)</span>
                </div>

                <div class="match-cards-stack gap-stack-final">
                  <div class="match-card card-grand-final" @click="openMatchDetails(finalMatch)">
                    <div class="match-card-head head-grand-final">
                      <span class="match-lbl">{{ finalMatch.label }}</span>
                      <span class="match-badge badge-grand-trophy">
                        <span class="material-symbols-outlined text-[13px]">emoji_events</span>
                        Cúp Vô Địch
                      </span>
                    </div>

                    <div class="grand-final-box">
                      <div class="finalist-slot">
                        <span class="material-symbols-outlined text-[18px] text-muted">sports_tennis</span>
                        <span class="tbd-text">Thắng Trận Bán Kết 1 (Marcus T. / David K.)</span>
                      </div>

                      <div class="final-vs-badge">VS</div>

                      <div class="finalist-slot">
                        <span class="material-symbols-outlined text-[18px] text-muted">sports_tennis</span>
                        <span class="tbd-text">Thắng Trận Bán Kết 2 (A. Larson / K. Visser)</span>
                      </div>
                    </div>

                    <div class="final-schedule-bar">
                      <span class="material-symbols-outlined text-[14px]">schedule</span>
                      <span>Dự kiến: 18:30 · Sân 1 Center Court</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ================= ROUND ROBIN STANDINGS TAB ================= -->
        <section v-else class="standings-content-wrap">
          <div class="standings-grid-2col">
            <!-- Group A -->
            <div class="standings-card">
              <div class="standings-card-head">
                <div>
                  <h3 class="group-title">BẢNG A (GROUP A STANDINGS)</h3>
                  <p class="group-desc">Top 2 vận động viên xuất sắc nhất tiến vào vòng Tứ Kết</p>
                </div>
                <span class="advance-tag">Top 2 Đi Tiếp (Q)</span>
              </div>

              <div class="table-responsive">
                <table class="standings-table">
                  <thead>
                    <tr>
                      <th class="text-center w-12">HẠNG</th>
                      <th>VẬN ĐỘNG VIÊN</th>
                      <th class="text-center w-12">TRẬN</th>
                      <th class="text-center w-12">THẮNG</th>
                      <th class="text-center w-12">THUA</th>
                      <th class="text-center w-20">SET (W-L)</th>
                      <th class="text-center w-16 text-primary">ĐIỂM</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="row in groupAStandings"
                      :key="row.rank"
                      :class="{ 'row-qualified': row.isQualified }"
                    >
                      <td class="text-center">
                        <span class="rank-badge" :class="`rank-${row.rank}`">{{ row.rank }}</span>
                      </td>
                      <td class="player-td">
                        <img :src="row.avatar" class="avatar-sm" @error="handleImageError($event)" />
                        <div class="player-name-block">
                          <span class="name-str">{{ row.name }}</span>
                          <span v-if="row.isQualified" class="badge-q">Q</span>
                        </div>
                      </td>
                      <td class="text-center font-medium">{{ row.mp }}</td>
                      <td class="text-center font-bold text-win">{{ row.w }}</td>
                      <td class="text-center font-medium text-muted">{{ row.l }}</td>
                      <td class="text-center font-medium">{{ row.setsRatio }}</td>
                      <td class="text-center font-extrabold text-primary text-base">{{ row.points }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Group B -->
            <div class="standings-card">
              <div class="standings-card-head">
                <div>
                  <h3 class="group-title">BẢNG B (GROUP B STANDINGS)</h3>
                  <p class="group-desc">Top 2 vận động viên xuất sắc nhất tiến vào vòng Tứ Kết</p>
                </div>
                <span class="advance-tag">Top 2 Đi Tiếp (Q)</span>
              </div>

              <div class="table-responsive">
                <table class="standings-table">
                  <thead>
                    <tr>
                      <th class="text-center w-12">HẠNG</th>
                      <th>VẬN ĐỘNG VIÊN</th>
                      <th class="text-center w-12">TRẬN</th>
                      <th class="text-center w-12">THẮNG</th>
                      <th class="text-center w-12">THUA</th>
                      <th class="text-center w-20">SET (W-L)</th>
                      <th class="text-center w-16 text-primary">ĐIỂM</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="row in groupBStandings"
                      :key="row.rank"
                      :class="{ 'row-qualified': row.isQualified }"
                    >
                      <td class="text-center">
                        <span class="rank-badge" :class="`rank-${row.rank}`">{{ row.rank }}</span>
                      </td>
                      <td class="player-td">
                        <img :src="row.avatar" class="avatar-sm" @error="handleImageError($event)" />
                        <div class="player-name-block">
                          <span class="name-str">{{ row.name }}</span>
                          <span v-if="row.isQualified" class="badge-q">Q</span>
                        </div>
                      </td>
                      <td class="text-center font-medium">{{ row.mp }}</td>
                      <td class="text-center font-bold text-win">{{ row.w }}</td>
                      <td class="text-center font-medium text-muted">{{ row.l }}</td>
                      <td class="text-center font-medium">{{ row.setsRatio }}</td>
                      <td class="text-center font-extrabold text-primary text-base">{{ row.points }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>

    <!-- ================= MATCH DETAIL POPUP MODAL ================= -->
    <div v-if="isMatchDetailModalOpen && selectedMatch" class="modal-backdrop" @click.self="isMatchDetailModalOpen = false">
      <div class="modal-card">
        <div class="modal-head">
          <div>
            <span class="match-meta-pill">{{ selectedMatch.court }} · {{ selectedMatch.time }}</span>
            <h2 class="modal-title">{{ selectedMatch.label }}</h2>
          </div>
          <button type="button" class="btn-close-modal" @click="isMatchDetailModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="match-vs-box">
            <!-- Player 1 -->
            <div class="player-vs-col">
              <img :src="selectedMatch.player1.avatar" class="player-vs-img" @error="handleImageError($event)" />
              <div class="player-vs-name">{{ selectedMatch.player1.name }}</div>
              <div class="player-vs-sub">{{ selectedMatch.player1.seed || 'VĐV' }} · {{ selectedMatch.player1.club || 'Aura' }}</div>
              <div v-if="selectedMatch.player1.score !== undefined" class="player-vs-score" :class="{ 'text-win': selectedMatch.player1.isWinner }">
                {{ selectedMatch.player1.score }}
              </div>
            </div>

            <div class="vs-divider">VS</div>

            <!-- Player 2 -->
            <div class="player-vs-col">
              <img :src="selectedMatch.player2.avatar" class="player-vs-img" @error="handleImageError($event)" />
              <div class="player-vs-name">{{ selectedMatch.player2.name }}</div>
              <div class="player-vs-sub">{{ selectedMatch.player2.seed || 'VĐV' }} · {{ selectedMatch.player2.club || 'Aura' }}</div>
              <div v-if="selectedMatch.player2.score !== undefined" class="player-vs-score" :class="{ 'text-win': selectedMatch.player2.isWinner }">
                {{ selectedMatch.player2.score }}
              </div>
            </div>
          </div>

          <div v-if="selectedMatch.status === 'live'" class="live-action-banner">
            <p>Trận đấu đang diễn ra sôi động trên sân Center Court!</p>
            <RouterLink to="/tournaments/live" class="btn-live-full">
              <span>Đến Bảng Điểm Trực Tiếp (Live Score)</span>
              <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </RouterLink>
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn-modal-close" @click="isMatchDetailModalOpen = false">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Tournament Bracket View (Sơ Đồ Thi Đấu)
   ========================================================================== */
.bracket-page {
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
  top: -100px;
  left: -100px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.6) 0%, transparent 70%);
}

.glow-orb-right {
  width: 420px;
  height: 420px;
  top: 150px;
  right: -80px;
  background: radial-gradient(circle, rgba(120, 85, 94, 0.35) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-left {
  background: radial-gradient(circle, rgba(248, 180, 196, 0.15) 0%, transparent 70%);
}

:global(html.dark) .glow-orb-right {
  background: radial-gradient(circle, rgba(83, 51, 62, 0.3) 0%, transparent 70%);
}

.bracket-main-content {
  position: relative;
  z-index: 1;
  width: 100%;
  padding: 16px 20px 80px;
}

@media (min-width: 768px) {
  .bracket-main-content {
    padding: 24px 32px 96px;
  }
}

.bracket-container {
  max-width: 1280px;
  margin: 0 auto;
}

/* ================= TOP NAV BAR ================= */
.top-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
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

.btn-live-shortcut {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 9999px;
  background-color: #ffebee;
  color: #c62828;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  border: 1px solid rgba(198, 40, 40, 0.25);
  transition: all 0.2s;
}

.btn-live-shortcut:hover {
  background-color: #ffcdd2;
  transform: translateY(-1px);
}

:global(html.dark) .btn-live-shortcut {
  background-color: rgba(198, 40, 40, 0.25);
  color: #f87171;
  border-color: rgba(248, 113, 113, 0.3);
}

/* ================= TOURNAMENT HEADER CARD ================= */
.tournament-header-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  margin-bottom: 24px;
}

:global(html.dark) .tournament-header-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.header-main-row {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (min-width: 992px) {
  .header-main-row {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-end;
  }
}

.status-tags-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.badge-live-pulse {
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

:global(html.dark) .badge-live-pulse {
  background-color: rgba(198, 40, 40, 0.25);
  color: #f87171;
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

.chip-bracket-type {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 9999px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #5d5f5f);
}

:global(html.dark) .chip-bracket-type {
  background-color: var(--surface-container, #2b2e36);
  color: var(--on-surface-variant, #c0c4cc);
}

.tournament-title {
  font-size: 26px;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--on-surface, #191c1d);
  margin: 0 0 8px;
}

@media (min-width: 768px) {
  .tournament-title {
    font-size: 32px;
  }
}

.tournament-sub-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  font-size: 13px;
  color: var(--on-surface-variant, #5d5f5f);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.meta-item .material-symbols-outlined {
  font-size: 16px;
  color: var(--primary, #78555e);
}

:global(html.dark) .meta-item .material-symbols-outlined {
  color: var(--primary, #f8b4c4);
}

.header-controls-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.segmented-tabs-wrap {
  display: inline-flex;
  padding: 4px;
  background-color: var(--surface-container, #edeeef);
  border-radius: 12px;
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .segmented-tabs-wrap {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn.active {
  background-color: var(--card-bg, #ffffff);
  color: var(--primary, #78555e);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .tab-btn.active {
  background-color: var(--surface-container-high, #353943);
  color: var(--primary, #f8b4c4);
}

.zoom-tools-group {
  display: inline-flex;
  align-items: center;
  background-color: var(--surface-container, #edeeef);
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  overflow: hidden;
}

:global(html.dark) .zoom-tools-group {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.zoom-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: transparent;
  border: none;
  color: var(--on-surface, #191c1d);
  cursor: pointer;
}

.zoom-btn:hover {
  background-color: var(--surface-container-high, #e7e8e9);
}

.zoom-val {
  width: 48px;
  font-size: 11px;
  font-weight: 700;
}

/* ================= BRACKET BOARD CARD ================= */
.bracket-board-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

:global(html.dark) .bracket-board-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.bracket-canvas-viewport {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 16px;
}

.bracket-tree-layout {
  position: relative;
  display: grid;
  grid-template-columns: 280px 280px 320px;
  gap: 100px;
  min-width: 1160px;
  padding: 20px 20px 40px;
  transition: transform 0.2s ease-out;
}

.bracket-svg-lines {
  position: absolute;
  top: 0;
  left: 0;
  pointer-events: none;
  z-index: 0;
}

.svg-path {
  stroke-width: 2;
  transition: all 0.3s;
}

.svg-path.completed {
  stroke: var(--outline-variant, #d3c3c5);
}

:global(html.dark) .svg-path.completed {
  stroke: #444853;
}

.svg-path.live {
  stroke: var(--primary, #78555e);
  stroke-width: 2.5;
  filter: drop-shadow(0 0 6px rgba(120, 85, 94, 0.4));
}

:global(html.dark) .svg-path.live {
  stroke: var(--primary, #f8b4c4);
  filter: drop-shadow(0 0 8px rgba(248, 180, 196, 0.5));
}

.svg-path.upcoming {
  stroke: var(--outline-variant, #d3c3c5);
  stroke-dasharray: 4 4;
}

.svg-path.trophy-path {
  stroke: #f59e0b;
  stroke-width: 2;
}

/* Bracket Columns */
.bracket-col {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
}

.col-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .col-header {
  border-color: var(--border, #353942);
}

.col-title {
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--on-surface-variant, #5d5f5f);
}

.col-count {
  font-size: 11px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
}

.match-cards-stack {
  display: flex;
  flex-direction: column;
}

.gap-stack-qf {
  gap: 36px;
}

.gap-stack-semi {
  gap: 216px;
  padding-top: 54px;
}

.gap-stack-final {
  padding-top: 236px;
}

/* Match Card Structure */
.match-card {
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 14px;
  border: 1px solid var(--border, #e0e4ea);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

:global(html.dark) .match-card {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.match-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
  border-color: var(--primary, #78555e);
}

:global(html.dark) .match-card:hover {
  border-color: var(--primary, #f8b4c4);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}

.match-card.is-live {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 2px rgba(255, 209, 220, 0.8), 0 8px 24px rgba(120, 85, 94, 0.15);
  background-color: var(--card-bg, #ffffff);
}

:global(html.dark) .match-card.is-live {
  border-color: var(--primary, #f8b4c4);
  box-shadow: 0 0 0 2px rgba(248, 180, 196, 0.3), 0 8px 28px rgba(0, 0, 0, 0.4);
  background-color: var(--surface-container-high, #353943);
}

.match-card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 12px;
  background-color: var(--surface-container, #edeeef);
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .match-card-head {
  background-color: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
}

.match-lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
}

.match-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
}

.badge-final {
  background-color: #eceff1;
  color: #546e7a;
}

:global(html.dark) .badge-final {
  background-color: #37474f;
  color: #b0bec5;
}

.badge-live {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background-color: #ffebee;
  color: #c62828;
}

:global(html.dark) .badge-live {
  background-color: rgba(198, 40, 40, 0.3);
  color: #f87171;
}

.live-ping-dot-sm {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background-color: #c62828;
}

.badge-upcoming {
  background-color: var(--surface-subtle, #f5f6f8);
  color: var(--on-surface-variant, #5d5f5f);
}

/* Player Rows in match card */
.match-player-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.match-player-row:last-child {
  border-bottom: none;
}

:global(html.dark) .match-player-row {
  border-color: var(--border, #353942);
}

.match-player-row.is-winner {
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

.match-player-row.is-loser {
  opacity: 0.55;
}

.player-left-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.player-thumb {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  object-fit: cover;
}

.player-label {
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 170px;
}

.serve-racket-icon {
  font-size: 14px;
  color: var(--primary, #78555e);
}

:global(html.dark) .serve-racket-icon {
  color: var(--primary, #f8b4c4);
}

.score-digit {
  font-size: 14px;
  font-weight: 800;
  font-family: monospace;
}

.score-digit.highlight-live {
  color: var(--primary, #78555e);
  font-size: 16px;
}

:global(html.dark) .score-digit.highlight-live {
  color: var(--primary, #f8b4c4);
}

.match-live-footer {
  padding: 8px 12px;
  background: var(--surface-container-low, #faf2f2);
  border-top: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .match-live-footer {
  background: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
}

.btn-goto-live {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 6px 10px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-decoration: none;
}

:global(html.dark) .btn-goto-live {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* Grand Final Card */
.card-grand-final {
  border: 2px dashed #f59e0b;
  background: var(--card-bg, #ffffff);
}

:global(html.dark) .card-grand-final {
  background: var(--surface-container, #2b2e36);
  border-color: #d97706;
}

.head-grand-final {
  background-color: rgba(245, 158, 11, 0.12);
  border-color: rgba(245, 158, 11, 0.25);
}

.badge-grand-trophy {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background-color: #fef3c7;
  color: #b45309;
}

:global(html.dark) .badge-grand-trophy {
  background-color: rgba(245, 158, 11, 0.25);
  color: #fbbf24;
}

.grand-final-box {
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
}

.finalist-slot {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  background-color: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  font-weight: 600;
}

:global(html.dark) .finalist-slot {
  background-color: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
}

.tbd-text {
  color: var(--on-surface-variant, #5d5f5f);
  font-style: italic;
}

.final-vs-badge {
  font-size: 11px;
  font-weight: 900;
  padding: 2px 8px;
  border-radius: 9999px;
  background-color: #f59e0b;
  color: #ffffff;
}

.final-schedule-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  background-color: var(--surface-subtle, #f5f6f8);
  border-top: 1px solid var(--border, #e0e4ea);
  font-size: 11px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
}

:global(html.dark) .final-schedule-bar {
  background-color: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
}

/* ================= STANDINGS TAB ================= */
.standings-grid-2col {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 992px) {
  .standings-grid-2col {
    grid-template-columns: 1fr 1fr;
  }
}

.standings-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 20px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .standings-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.standings-card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .standings-card-head {
  border-color: var(--border, #353942);
}

.group-title {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: var(--on-surface, #191c1d);
  margin: 0 0 2px;
}

.group-desc {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 0;
}

.advance-tag {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 9999px;
  background-color: #e8f5e9;
  color: #2e7d32;
}

:global(html.dark) .advance-tag {
  background-color: rgba(46, 125, 50, 0.25);
  color: #81c784;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.standings-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.standings-table th {
  padding: 10px 8px;
  font-size: 10px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
  border-bottom: 1px solid var(--border, #e0e4ea);
  text-align: left;
}

.standings-table td {
  padding: 12px 8px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .standings-table th,
:global(html.dark) .standings-table td {
  border-color: var(--border, #353942);
}

.row-qualified {
  background-color: rgba(255, 209, 220, 0.1);
}

:global(html.dark) .row-qualified {
  background-color: rgba(248, 180, 196, 0.05);
}

.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  font-size: 11px;
  font-weight: 800;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface-variant, #5d5f5f);
}

.rank-1 {
  background-color: var(--primary, #78555e);
  color: #ffffff;
}

:global(html.dark) .rank-1 {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

.rank-2 {
  background-color: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
}

:global(html.dark) .rank-2 {
  background-color: var(--primary-container, #53333e);
  color: var(--primary, #f8b4c4);
}

.player-td {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar-sm {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
}

.player-name-block {
  display: flex;
  align-items: center;
  gap: 6px;
}

.name-str {
  font-weight: 600;
}

.badge-q {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 900;
  background-color: var(--primary, #78555e);
  color: #ffffff;
}

:global(html.dark) .badge-q {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

/* ================= POPUP MODAL ================= */
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

.modal-card {
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  max-width: 520px;
  width: 100%;
}

:global(html.dark) .modal-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.match-meta-pill {
  font-size: 11px;
  font-weight: 700;
  color: var(--primary, #78555e);
  text-transform: uppercase;
}

:global(html.dark) .match-meta-pill {
  color: var(--primary, #f8b4c4);
}

.modal-title {
  font-size: 18px;
  font-weight: 800;
  margin: 4px 0 0;
}

.btn-close-modal {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--on-surface-variant, #5d5f5f);
}

.match-vs-box {
  display: flex;
  align-items: center;
  justify-content: space-around;
  padding: 20px 10px;
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 14px;
  margin-bottom: 16px;
}

:global(html.dark) .match-vs-box {
  background-color: var(--surface-container, #2b2e36);
}

.player-vs-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.player-vs-img {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  margin-bottom: 8px;
  border: 2px solid var(--border, #e0e4ea);
}

.player-vs-name {
  font-size: 14px;
  font-weight: 700;
}

.player-vs-sub {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
  margin-bottom: 6px;
}

.player-vs-score {
  font-size: 24px;
  font-weight: 900;
}

.vs-divider {
  font-size: 14px;
  font-weight: 900;
  color: var(--outline-variant, #d3c3c5);
}

.live-action-banner {
  padding: 12px;
  border-radius: 10px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-surface, #191c1d);
  text-align: center;
  margin-bottom: 16px;
}

:global(html.dark) .live-action-banner {
  background: var(--primary-container, #53333e);
  color: #ffffff;
}

.live-action-banner p {
  margin: 0 0 10px;
  font-size: 12px;
  font-weight: 600;
}

.btn-live-full {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
}

:global(html.dark) .btn-live-full {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
}

.btn-modal-close {
  padding: 8px 18px;
  border-radius: 9999px;
  background-color: var(--surface-container, #edeeef);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--on-surface, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

:global(html.dark) .btn-modal-close {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: var(--on-surface, #ffffff);
}
</style>
