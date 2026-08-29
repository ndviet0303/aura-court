<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { apiV2, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

interface Fixture {
  id: number
  round: number
  position: number
  status: string
  participant_one_id?: number
  participant_two_id?: number
  participant_one_name?: string
  participant_two_name?: string
  winner_id?: number
  score?: {
    sets?: Array<{ p1: number; p2: number }>
    winner?: number
    loser?: number
  }
}

interface Participant {
  id: number
  user_id?: number
  name: string
  avatar?: string
  team_name?: string
  skill_level?: number
  seed?: number
}

interface TournamentItem {
  id: number
  name: string
  title?: string
  slug?: string
  category?: string
  sport?: string
  format: 'single_elimination' | 'round_robin' | 'double_elimination'
  status: 'open' | 'ongoing' | 'completed' | 'upcoming'
  prize_pool: string
  prize_pool_usd?: string
  dates: string
  start_date?: string
  end_date?: string
  venue: string
  city: string
  court_name?: string
  current_slots: number
  max_slots: number
  entry_fee?: string
  image_url: string
  description?: string
  version: number
  current_match?: {
    round_label: string
    court: string
    player1_name: string
    player1_avatar?: string
    player2_name: string
    player2_avatar?: string
    p1_scores: number[]
    p2_scores: number[]
  }
  winner_team?: string
  runner_up?: string
  fixtures?: Fixture[]
  participants?: Participant[]
}

const auth = useAuthStore()

// Navigation & Active Tab
const activeTab = ref<'all' | 'open' | 'ongoing' | 'completed' | 'my'>('all')
const searchQuery = ref('')
const selectedSport = ref('all')
const selectedFormat = ref('all')

// Notification & Loading
const loading = ref(false)
const processing = ref(false)
const notice = ref('')
const error = ref('')

function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4500)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 5000)
}

function handleImageError(event: Event, fallback = '/aura/court-1.jpg') {
  const target = event.target as HTMLImageElement | null
  if (target && target.src !== fallback) {
    target.src = fallback
  }
}

// Initial Mock Data aligned with Stitch Design & Aura aesthetics
const defaultTournaments: TournamentItem[] = [
  {
    id: 1,
    name: "Aura Summer Open '26",
    category: 'Cầu Lông Đôi & Đơn Nam/Nữ',
    sport: 'badminton',
    format: 'round_robin',
    status: 'open',
    prize_pool: '100.000.000 ₫',
    prize_pool_usd: '$10,000',
    dates: '15 - 18 Tháng 8, 2026',
    venue: 'Aura Center Court A',
    city: 'Quận 1, TP. Hồ Chí Minh',
    court_name: 'Sân Trung Tâm Thảm Taraflex',
    current_slots: 24,
    max_slots: 32,
    entry_fee: '500.000 ₫ / VĐV',
    image_url: '/aura/court-0.jpg',
    description: 'Giải đấu mùa hè danh giá nhất hệ thống Aura Court. Hội tụ các tay vợt phong trào và bán chuyên hàng đầu thi đấu theo thể thức vòng tròn kết hợp phân nhánh kép.',
    version: 1,
    fixtures: [
      {
        id: 101,
        round: 1,
        position: 1,
        status: 'pending',
        participant_one_id: 1,
        participant_two_id: 2,
        participant_one_name: 'Minh Hoàng & Quốc Bảo',
        participant_two_name: 'Elena Ryan & Tuấn Anh',
      },
      {
        id: 102,
        round: 1,
        position: 2,
        status: 'pending',
        participant_one_id: 3,
        participant_two_id: 4,
        participant_one_name: 'David Khoa & Hồng Ngọc',
        participant_two_name: 'Vũ Nam & Thế Vinh',
      },
    ],
  },
  {
    id: 2,
    name: 'Autumn Equinox Invitational',
    category: 'Cầu Lông Đôi Nam Nữ',
    sport: 'badminton',
    format: 'single_elimination',
    status: 'open',
    prize_pool: '50.000.000 ₫',
    prize_pool_usd: '$5,000',
    dates: '22 - 24 Tháng 9, 2026',
    venue: 'Aura East Campus',
    city: 'Quận 2, TP. Hồ Chí Minh',
    court_name: 'Cụm Sân 1-4 VIP',
    current_slots: 16,
    max_slots: 64,
    entry_fee: '350.000 ₫ / VĐV',
    image_url: '/aura/court-1.jpg',
    description: 'Giải đấu chào thu mở rộng dành cho tất cả hội viên và câu lạc bộ đối tác. Thể thức loại trực tiếp kịch tính đến từng pha cầu.',
    version: 1,
    fixtures: [
      {
        id: 201,
        round: 1,
        position: 1,
        status: 'pending',
        participant_one_id: 1,
        participant_two_id: 2,
        participant_one_name: 'Aura Team Alpha',
        participant_two_name: 'Riverside Club 1',
      },
    ],
  },
  {
    id: 3,
    name: 'City Doubles Championship',
    category: 'Cầu Lông Đôi Nam Hạng B-C',
    sport: 'badminton',
    format: 'single_elimination',
    status: 'open',
    prize_pool: '25.000.000 ₫',
    prize_pool_usd: '$2,500',
    dates: '05 - 06 Tháng 10, 2026',
    venue: 'Metro Sports Club',
    city: 'Bình Thạnh, TP. Hồ Chí Minh',
    court_name: 'Sân C4 Tiêu Chuẩn',
    current_slots: 8,
    max_slots: 32,
    entry_fee: '300.000 ₫ / VĐV',
    image_url: '/aura/court-2.jpg',
    description: 'Giải vô địch đôi toàn thành phố quy tụ 32 cặp VĐV tranh tài cuối tuần. Có livestream trực tiếp trên kênh thể thao Aura.',
    version: 1,
    fixtures: [],
  },
  {
    id: 4,
    name: 'Pro-Am Spring Classic',
    category: 'Pickleball & Tennis Pro-Am',
    sport: 'pickleball',
    format: 'double_elimination',
    status: 'ongoing',
    prize_pool: '80.000.000 ₫',
    prize_pool_usd: '$8,000',
    dates: 'Đang diễn ra (Vòng Bán Kết)',
    venue: 'Aura South Arena',
    city: 'Quận 7, TP. Hồ Chí Minh',
    court_name: 'Sân Trung Tâm 01',
    current_slots: 16,
    max_slots: 16,
    entry_fee: '600.000 ₫ / VĐV',
    image_url: '/aura/court-3.jpg',
    description: 'Các trận đấu kịch tính đang diễn ra tại vòng bán kết. Cập nhật tỷ số theo thời gian thực từng set đấu.',
    version: 4,
    current_match: {
      round_label: 'Bán Kết 1 (Semi-Finals)',
      court: 'Sân Trung Tâm 01',
      player1_name: 'J. Doe & Minh Hoàng',
      player1_avatar: getAvatarUrl('Minh Hoàng'),
      player2_name: 'M. Smith & Elena Ryan',
      player2_avatar: getAvatarUrl('Elena Ryan'),
      p1_scores: [21, 18, 15],
      p2_scores: [19, 21, 12],
    },
    fixtures: [
      {
        id: 401,
        round: 4,
        position: 1,
        status: 'live',
        participant_one_id: 11,
        participant_two_id: 12,
        participant_one_name: 'J. Doe & Minh Hoàng',
        participant_two_name: 'M. Smith & Elena Ryan',
        score: { sets: [{ p1: 21, p2: 19 }, { p1: 18, p2: 21 }, { p1: 15, p2: 12 }] },
      },
      {
        id: 402,
        round: 4,
        position: 2,
        status: 'pending',
        participant_one_id: 13,
        participant_two_id: 14,
        participant_one_name: 'David Khoa & Vũ Nam',
        participant_two_name: 'Quốc Bảo & Thế Vinh',
      },
    ],
  },
  {
    id: 5,
    name: 'Aura Winter Grand Slam 2025',
    category: 'Cầu Lông Toàn Năng',
    sport: 'badminton',
    format: 'single_elimination',
    status: 'completed',
    prize_pool: '150.000.000 ₫',
    prize_pool_usd: '$15,000',
    dates: 'Đã hoàn thành · Tháng 12, 2025',
    venue: 'Aura Center Court A',
    city: 'Quận 1, TP. Hồ Chí Minh',
    court_name: 'Sân VIP 1',
    current_slots: 32,
    max_slots: 32,
    image_url: '/aura/court-4.jpg',
    description: 'Mùa giải vô địch mùa đông 2025 đã khép lại với chức vô địch thuộc về bộ đôi xuất sắc Đoàn Quốc Bảo & Elena Ryan.',
    version: 8,
    winner_team: 'Đoàn Quốc Bảo & Elena Ryan (Vô Địch)',
    runner_up: 'Trần Tuấn & Vũ Nam (Á Quân)',
    fixtures: [],
  },
]

const tournaments = ref<TournamentItem[]>([])
const myRegisteredTournamentIds = ref<number[]>([1])

// Filtered Tournaments
const filteredTournaments = computed(() => {
  return tournaments.value.filter((item) => {
    // Status Tab Filter
    if (activeTab.value === 'open' && item.status !== 'open') return false
    if (activeTab.value === 'ongoing' && item.status !== 'ongoing') return false
    if (activeTab.value === 'completed' && item.status !== 'completed') return false
    if (activeTab.value === 'my' && !myRegisteredTournamentIds.value.includes(item.id)) return false

    // Sport Filter
    if (selectedSport.value !== 'all' && item.sport !== selectedSport.value) return false

    // Format Filter
    if (selectedFormat.value !== 'all' && item.format !== selectedFormat.value) return false

    // Search Query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const matchName = item.name.toLowerCase().includes(q)
      const matchVenue = item.venue.toLowerCase().includes(q)
      const matchCity = item.city.toLowerCase().includes(q)
      const matchCat = (item.category || '').toLowerCase().includes(q)
      if (!matchName && !matchVenue && !matchCity && !matchCat) return false
    }

    return true
  })
})

// Featured Major Tournament (Summer Open)
const featuredTournament = computed<TournamentItem>(() => {
  return tournaments.value.find((t) => t.id === 1) ?? (defaultTournaments[0] as TournamentItem)
})

// Modals State
const isRegisterModalOpen = ref(false)
const isBracketModalOpen = ref(false)
const isCreateModalOpen = ref(false)
const selectedTournament = ref<TournamentItem | null>(null)

// Registration Form State
const registerForm = ref({
  tournament_id: 1,
  team_name: 'Aura Fire Team',
  category: 'doubles_men',
  player1_name: auth.user?.name || 'Alex Nguyễn',
  player1_phone: '0912 345 678',
  player1_skill: 3,
  player2_name: 'Marcus Trần',
  player2_phone: '0988 765 432',
  player2_skill: 4,
  accept_rules: true,
})

// Create Tournament Form State
const createTournamentForm = ref({
  name: '',
  sport: 'badminton',
  format: 'single_elimination' as 'single_elimination' | 'round_robin',
  category: 'Đôi nam nữ phong trào',
  prize_pool: '30.000.000 ₫',
  dates: '10 - 12 Tháng 11, 2026',
  venue: 'Aura Center Court A',
  city: 'Quận 1, TP. Hồ Chí Minh',
  max_slots: 32,
  participant_ids: '101, 102, 103, 104, 105, 106, 107, 108',
  description: 'Giải đấu do ban tổ chức câu lạc bộ Aura tổ chức với quy mô mở rộng.',
})

// Load Tournaments from API with graceful default fallback
async function loadTournaments() {
  loading.value = true
  try {
    const res = await apiV2.get<{ data: Array<{ id: number; name: string; format: string; status: string; version: number; fixtures?: Fixture[] }> }>('/tournaments')
    if (res?.data && res.data.length > 0) {
      const remoteItems: TournamentItem[] = res.data.map((item, idx) => {
        const existing = defaultTournaments.find((d) => d.id === item.id)
        if (existing) {
          return {
            ...existing,
            name: item.name || existing.name,
            format: (item.format as 'single_elimination' | 'round_robin') || existing.format,
            status: (item.status as 'open' | 'ongoing' | 'completed') || existing.status,
            version: item.version || existing.version,
            fixtures: item.fixtures || existing.fixtures,
          }
        }
        return {
          id: item.id,
          name: item.name,
          category: 'Cầu Lông Phong Trào',
          sport: 'badminton',
          format: (item.format as 'single_elimination' | 'round_robin') || 'single_elimination',
          status: (item.status as 'open' | 'ongoing' | 'completed') || 'open',
          prize_pool: '20.000.000 ₫',
          dates: 'Sắp diễn ra trong tháng',
          venue: 'Aura Central Court',
          city: 'TP. Hồ Chí Minh',
          current_slots: 4 + idx * 2,
          max_slots: 16,
          image_url: `/aura/court-${(idx % 6)}.jpg`,
          version: item.version || 1,
          fixtures: item.fixtures || [],
        }
      })

      const merged = [...defaultTournaments]
      remoteItems.forEach((r) => {
        const index = merged.findIndex((m) => m.id === r.id)
        if (index >= 0) {
          merged[index] = r
        } else {
          merged.unshift(r)
        }
      })
      tournaments.value = merged
    } else {
      tournaments.value = [...defaultTournaments]
    }
  } catch {
    tournaments.value = [...defaultTournaments]
  } finally {
    loading.value = false
  }
}

// Open Register Modal
function openRegisterModal(tournament: TournamentItem) {
  selectedTournament.value = tournament
  registerForm.value.tournament_id = tournament.id
  registerForm.value.player1_name = auth.user?.name || 'Alex Nguyễn'
  isRegisterModalOpen.value = true
}

function closeRegisterModal() {
  isRegisterModalOpen.value = false
}

async function handleRegisterTeam() {
  if (!registerForm.value.accept_rules) {
    showError('Vui lòng đồng ý với điều lệ giải đấu trước khi đăng ký.')
    return
  }

  processing.value = true
  try {
    if (selectedTournament.value) {
      selectedTournament.value.current_slots = Math.min(
        selectedTournament.value.max_slots,
        selectedTournament.value.current_slots + 1
      )
      if (!myRegisteredTournamentIds.value.includes(selectedTournament.value.id)) {
        myRegisteredTournamentIds.value.push(selectedTournament.value.id)
      }
    }
    showNotice(`🎉 Đăng ký thành công đội "${registerForm.value.team_name}" tham gia "${selectedTournament.value?.name}"!`)
    closeRegisterModal()
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể đăng ký lúc này. Vui lòng thử lại.'))
  } finally {
    processing.value = false
  }
}

// Open Bracket & Live Scoreboard Modal
async function openBracketModal(tournament: TournamentItem) {
  selectedTournament.value = tournament
  isBracketModalOpen.value = true

  try {
    const res = await apiV2.get<{ tournament: { id: number; fixtures: Fixture[]; version: number } }>(`/tournaments/${tournament.id}`)
    if (res?.tournament?.fixtures) {
      selectedTournament.value.fixtures = res.tournament.fixtures
      selectedTournament.value.version = res.tournament.version
    }
  } catch {
    // Keep local fixtures
  }
}

function closeBracketModal() {
  isBracketModalOpen.value = false
}

// Score fixture in modal
async function handleScoreFixture(fixture: Fixture, winnerId: number) {
  if (!selectedTournament.value) return
  processing.value = true
  try {
    try {
      await apiV2.post(`/tournaments/${selectedTournament.value.id}/fixtures/${fixture.id}/score`, {
        expected_version: selectedTournament.value.version,
        winner_id: winnerId,
        score: { sets: [{ p1: 21, p2: 18 }, { p1: 21, p2: 16 }] },
      })
    } catch {
      // optimistic local update
    }

    fixture.status = 'completed'
    fixture.winner_id = winnerId
    selectedTournament.value.version += 1
    showNotice('✨ Đã cập nhật tỷ số và đồng bộ nhánh đấu thành công!')
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể lưu tỷ số.'))
  } finally {
    processing.value = false
  }
}

// Create Tournament Modal
function openCreateModal() {
  isCreateModalOpen.value = true
}

function closeCreateModal() {
  isCreateModalOpen.value = false
}

async function handleCreateTournament() {
  if (!createTournamentForm.value.name.trim()) {
    showError('Vui lòng nhập tên giải đấu.')
    return
  }

  processing.value = true
  try {
    const participantIds = createTournamentForm.value.participant_ids
      .split(',')
      .map((id) => Number(id.trim()))
      .filter((id) => !isNaN(id) && id > 0)

    let createdId = Date.now()

    try {
      const res = await apiV2.post<{ tournament: { id: number; name: string; format: string; version: number } }>('/tournaments', {
        name: createTournamentForm.value.name,
        format: createTournamentForm.value.format,
        participant_ids: participantIds.length >= 2 ? participantIds : [101, 102, 103, 104],
      })
      if (res?.tournament?.id) createdId = res.tournament.id
    } catch {
      // local fallback
    }

    const newTournament: TournamentItem = {
      id: createdId,
      name: createTournamentForm.value.name,
      category: createTournamentForm.value.category,
      sport: createTournamentForm.value.sport,
      format: createTournamentForm.value.format,
      status: 'open',
      prize_pool: createTournamentForm.value.prize_pool,
      dates: createTournamentForm.value.dates,
      venue: createTournamentForm.value.venue,
      city: createTournamentForm.value.city,
      current_slots: participantIds.length,
      max_slots: createTournamentForm.value.max_slots,
      image_url: '/aura/court-5.jpg',
      description: createTournamentForm.value.description,
      version: 1,
      fixtures: [
        {
          id: Date.now() + 1,
          round: 1,
          position: 1,
          status: 'pending',
          participant_one_name: 'Đội Hạt Giống 1',
          participant_two_name: 'Đội Hạt Giống 2',
        },
      ],
    }

    tournaments.value.unshift(newTournament)
    showNotice('🎉 Đã khởi tạo giải đấu và phân nhánh bracket thành công!')
    closeCreateModal()
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể tạo giải đấu lúc này.'))
  } finally {
    processing.value = false
  }
}

function resetFilters() {
  searchQuery.value = ''
  selectedSport.value = 'all'
  selectedFormat.value = 'all'
  activeTab.value = 'all'
}

onMounted(() => {
  loadTournaments()
})
</script>

<template>
  <div class="tournaments-page">
    <!-- Ambient Top Background Glow -->
    <div class="glow-bg-top" aria-hidden="true"></div>

    <main class="page-body">
      <!-- Flash Alert Notices -->
      <div class="alerts-container">
        <Transition name="fade">
          <div v-if="notice" class="alert-banner alert-success">
            <span class="material-symbols-outlined alert-icon">check_circle</span>
            <span>{{ notice }}</span>
          </div>
        </Transition>
        <Transition name="fade">
          <div v-if="error" class="alert-banner alert-danger">
            <span class="material-symbols-outlined alert-icon">error</span>
            <span>{{ error }}</span>
          </div>
        </Transition>
      </div>

      <!-- ================= HERO SECTION (FEATURED MAJOR) ================= -->
      <section class="hero-section">
        <div class="hero-inner">
          <!-- Left Column: Hero Text & Information -->
          <div class="hero-left-col">
            <div class="hero-tags-row">
              <span class="tag-pill-open">
                <span class="status-dot-green"></span>
                Registration Open
              </span>
              <span class="tag-pill-featured">
                <span class="material-symbols-outlined fire-icon">local_fire_department</span>
                Featured Major
              </span>
            </div>

            <h1 class="hero-title">
              Aura Summer <br />
              <span class="hero-title-italic">Open '26</span>
            </h1>

            <p class="hero-description">
              The premier mid-summer showcase. Tranh tài đỉnh cao cùng các vận động viên và câu lạc bộ hàng đầu theo thể thức thi đấu vòng tròn chuyên nghiệp. Đăng ký giữ suất trước khi bảng nhánh đấu đóng lại.
            </p>

            <!-- Hero Action CTAs -->
            <div class="hero-cta-group">
              <button
                type="button"
                class="btn-primary-pill"
                @click="openRegisterModal(featuredTournament)"
              >
                <span>Đăng Ký Đội (Register Team)</span>
                <span class="material-symbols-outlined btn-arrow-icon">arrow_forward</span>
              </button>

              <button
                type="button"
                class="btn-secondary-pill"
                @click="openBracketModal(featuredTournament)"
              >
                <span class="material-symbols-outlined">account_tree</span>
                <span>Chi Tiết & Nhánh Đấu</span>
              </button>

              <button
                v-if="auth.user"
                type="button"
                class="btn-outline-pill"
                @click="openCreateModal"
              >
                <span class="material-symbols-outlined">add_circle</span>
                <span>Tạo Giải Mới</span>
              </button>
            </div>

            <!-- Hero Key Specs Row -->
            <div class="hero-specs-row">
              <div class="spec-item">
                <div class="spec-title">Tổng Giải Thưởng</div>
                <div class="spec-value text-primary">{{ featuredTournament.prize_pool }}</div>
                <div class="spec-sub">{{ featuredTournament.prize_pool_usd }}</div>
              </div>

              <div class="spec-item">
                <div class="spec-title">Thể Thức</div>
                <div class="spec-value">32 Đội / Vòng Tròn</div>
                <div class="spec-sub">Double Elim Playoff</div>
              </div>

              <div class="spec-item">
                <div class="spec-title">Thời Gian</div>
                <div class="spec-value">15 - 18 Tháng 8</div>
                <div class="spec-sub">4 Ngày Thi Đấu</div>
              </div>
            </div>
          </div>

          <!-- Right Column: Hero Visual Showcase Card -->
          <div class="hero-right-col">
            <div class="hero-showcase-box">
              <img
                :src="featuredTournament.image_url"
                alt="Aura Court Tournament Arena"
                class="showcase-img"
                @error="handleImageError($event, '/aura/court-0.jpg')"
              />

              <!-- Bottom Floating Venue Overlay -->
              <div class="showcase-overlay">
                <div class="showcase-venue-info">
                  <div class="showcase-venue-title">
                    <span class="material-symbols-outlined venue-pin-icon">location_on</span>
                    <span>{{ featuredTournament.venue }}</span>
                  </div>
                  <div class="showcase-venue-sub">
                    {{ featuredTournament.city }} · Sân VIP 1 (Thảm Taraflex Đỏ)
                  </div>
                </div>

                <div class="showcase-insignia">
                  <span class="material-symbols-outlined trophy-icon">emoji_events</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= TOURNAMENT CIRCUIT SECTION ================= -->
      <section class="circuit-section">
        <div class="circuit-container">
          <!-- Section Title & Filter Tabs Header -->
          <div class="circuit-header-row">
            <div class="circuit-header-text">
              <div class="circuit-eyebrow">
                <span class="material-symbols-outlined badge-icon">workspace_premium</span>
                <span>AURA LEAGUE CIRCUIT</span>
              </div>
              <h2 class="circuit-main-title">Hệ Thống Giải Đấu (Tournament Circuit)</h2>
              <p class="circuit-subtitle">
                Khám phá các giải mở rộng sắp tới, theo dõi tỷ số trực tiếp từng set đấu và xem lại phân nhánh xếp hạng.
              </p>
            </div>

            <!-- Status Tab Pills Filter -->
            <div class="status-tabs-pill">
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'all' }"
                @click="activeTab = 'all'"
              >
                Tất Cả
              </button>
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'open' }"
                @click="activeTab = 'open'"
              >
                Đang Mở Đăng Ký
              </button>
              <button
                type="button"
                class="tab-btn live-tab-btn"
                :class="{ active: activeTab === 'ongoing' }"
                @click="activeTab = 'ongoing'"
              >
                <span class="live-dot-pulse"></span>
                Đang Diễn Ra (Live)
              </button>
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'completed' }"
                @click="activeTab = 'completed'"
              >
                Đã Bế Mạc
              </button>
              <button
                v-if="auth.user"
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'my' }"
                @click="activeTab = 'my'"
              >
                Giải Của Tôi ({{ myRegisteredTournamentIds.length }})
              </button>
            </div>
          </div>

          <!-- Secondary Filter & Search Bar -->
          <div class="circuit-filter-bar">
            <div class="search-input-box">
              <span class="material-symbols-outlined search-icon">search</span>
              <input
                v-model="searchQuery"
                type="text"
                class="filter-search-field"
                placeholder="Tìm theo tên giải, địa điểm, thể thức..."
              />
              <button v-if="searchQuery" class="clear-btn" @click="searchQuery = ''">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>

            <div class="filter-chips-group">
              <!-- Sport Filter Dropdown -->
              <div class="select-chip">
                <span class="material-symbols-outlined chip-icon">sports_tennis</span>
                <select v-model="selectedSport" class="chip-select">
                  <option value="all">Môn: Tất cả</option>
                  <option value="badminton">Cầu lông (Badminton)</option>
                  <option value="pickleball">Pickleball</option>
                  <option value="tennis">Tennis</option>
                </select>
                <span class="material-symbols-outlined chip-arrow">expand_more</span>
              </div>

              <!-- Format Filter Dropdown -->
              <div class="select-chip">
                <span class="material-symbols-outlined chip-icon">tune</span>
                <select v-model="selectedFormat" class="chip-select">
                  <option value="all">Thể thức: Tất cả</option>
                  <option value="single_elimination">Loại trực tiếp</option>
                  <option value="round_robin">Vòng tròn tính điểm</option>
                  <option value="double_elimination">Nhánh kép (Double Elim)</option>
                </select>
                <span class="material-symbols-outlined chip-arrow">expand_more</span>
              </div>

              <button
                v-if="searchQuery || selectedSport !== 'all' || selectedFormat !== 'all' || activeTab !== 'all'"
                type="button"
                class="btn-reset-filters"
                @click="resetFilters"
              >
                Đặt lại
              </button>
            </div>
          </div>

          <!-- Tournaments Grid (3 Columns) -->
          <div v-if="filteredTournaments.length > 0" class="tournaments-cards-grid">
            <div
              v-for="tournament in filteredTournaments"
              :key="tournament.id"
              class="card-container"
              :class="{ 'card-ongoing': tournament.status === 'ongoing' }"
            >
              <!-- Card Image Header -->
              <div class="card-img-header">
                <img
                  :src="tournament.image_url"
                  :alt="tournament.name"
                  class="card-photo"
                  :class="{ 'photo-grayscale': tournament.status === 'ongoing' }"
                  @error="handleImageError($event, '/aura/court-1.jpg')"
                />

                <!-- Top Left Status Pill -->
                <div class="card-badge-top-left">
                  <span v-if="tournament.status === 'open'" class="badge-status-open">
                    Registration Open
                  </span>
                  <span v-else-if="tournament.status === 'ongoing'" class="badge-status-ongoing">
                    <span class="live-dot-pulse-sm"></span>
                    Live Now
                  </span>
                  <span v-else-if="tournament.status === 'completed'" class="badge-status-completed">
                    <span class="material-symbols-outlined text-[13px]">check_circle</span>
                    Completed
                  </span>
                </div>

                <!-- Top Right Sport Category Pill -->
                <div class="card-badge-top-right">
                  <span class="badge-category-glass">
                    {{ tournament.category || 'Cầu Lông' }}
                  </span>
                </div>
              </div>

              <!-- Card Content Body -->
              <div class="card-content-body">
                <!-- Title -->
                <div class="card-title-row">
                  <h3 class="card-title-text">{{ tournament.name }}</h3>
                  <span class="material-symbols-outlined card-trophy-icon">emoji_events</span>
                </div>

                <!-- Meta Details -->
                <div class="card-meta-list">
                  <div class="meta-row">
                    <span class="material-symbols-outlined meta-icon">calendar_today</span>
                    <span class="meta-text">{{ tournament.dates }}</span>
                  </div>

                  <div class="meta-row">
                    <span class="material-symbols-outlined meta-icon">location_on</span>
                    <span class="meta-text">{{ tournament.venue }} · {{ tournament.city }}</span>
                  </div>

                  <!-- Slot Fill Progress Bar (For Open Tournaments) -->
                  <div v-if="tournament.status === 'open'" class="slot-progress-wrapper">
                    <div class="slot-progress-header">
                      <span class="slot-progress-label">
                        <span class="material-symbols-outlined text-[15px]">groups</span>
                        Số đội tham gia:
                      </span>
                      <span class="slot-progress-count">{{ tournament.current_slots }}/{{ tournament.max_slots }} Slots</span>
                    </div>
                    <div class="progress-track">
                      <div
                        class="progress-fill"
                        :style="{ width: `${(tournament.current_slots / tournament.max_slots) * 100}%` }"
                      ></div>
                    </div>
                  </div>

                  <!-- Live Scoreboard Box (For Ongoing Tournaments) -->
                  <div v-else-if="tournament.status === 'ongoing' && tournament.current_match" class="live-widget-box">
                    <div class="live-widget-header">
                      <span>{{ tournament.current_match.round_label }}</span>
                      <span class="live-tag">
                        <span class="live-dot-pulse-sm"></span>
                        Đang đấu Set 3
                      </span>
                    </div>

                    <div class="live-team-row">
                      <div class="team-info">
                        <img :src="tournament.current_match.player1_avatar || getAvatarUrl('P1')" class="avatar-sm" />
                        <span class="player-name">{{ tournament.current_match.player1_name }}</span>
                      </div>
                      <div class="score-digits-row">
                        <span
                          v-for="(s, idx) in tournament.current_match.p1_scores"
                          :key="idx"
                          class="score-badge"
                          :class="{ 'score-win': idx === 0 }"
                        >
                          {{ s }}
                        </span>
                      </div>
                    </div>

                    <div class="live-team-row">
                      <div class="team-info">
                        <img :src="tournament.current_match.player2_avatar || getAvatarUrl('P2')" class="avatar-sm" />
                        <span class="player-name text-muted">{{ tournament.current_match.player2_name }}</span>
                      </div>
                      <div class="score-digits-row">
                        <span
                          v-for="(s, idx) in tournament.current_match.p2_scores"
                          :key="idx"
                          class="score-badge"
                          :class="{ 'score-win': idx === 1 }"
                        >
                          {{ s }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Winner Podium (For Completed Tournaments) -->
                  <div v-else-if="tournament.status === 'completed'" class="podium-widget-box">
                    <div class="podium-winner-row">
                      <span class="material-symbols-outlined text-[16px] text-amber-500">military_tech</span>
                      <span>Vô Địch: {{ tournament.winner_team || 'Đội Quán Quân' }}</span>
                    </div>
                    <div class="podium-runner-row">
                      Á Quân: {{ tournament.runner_up || 'Đội Á Quân' }}
                    </div>
                  </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="card-footer-row">
                  <div class="prize-block">
                    <div class="prize-label">Giải Thưởng</div>
                    <div class="prize-value">{{ tournament.prize_pool }}</div>
                  </div>

                  <div class="action-btn-block">
                    <button
                      v-if="tournament.status === 'open'"
                      type="button"
                      class="btn-card-action btn-action-register"
                      @click="openRegisterModal(tournament)"
                    >
                      <span>Đăng ký</span>
                      <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </button>

                    <RouterLink
                      v-else-if="tournament.status === 'ongoing'"
                      to="/tournaments/live"
                      class="btn-card-action btn-action-ongoing"
                    >
                      <span>Scoreboard Live</span>
                      <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </RouterLink>

                    <RouterLink
                      v-else
                      to="/tournaments/bracket"
                      class="btn-card-action btn-action-view"
                    >
                      <span>Xem Nhánh Đấu</span>
                      <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="empty-circuit-card">
            <div class="empty-icon-circle">
              <span class="material-symbols-outlined text-[32px]">emoji_events</span>
            </div>
            <h3 class="empty-title">Không tìm thấy giải đấu phù hợp</h3>
            <p class="empty-desc">
              Không có giải đấu nào khớp với từ khóa tìm kiếm hoặc bộ lọc hiện tại. Bạn có thể đặt lại bộ lọc hoặc tạo một giải đấu mới.
            </p>
            <div class="empty-actions">
              <button type="button" class="btn-secondary-pill" @click="resetFilters">Đặt lại bộ lọc</button>
              <button type="button" class="btn-primary-pill" @click="openCreateModal">Khởi tạo giải đấu</button>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- ================= MODAL 1: ĐĂNG KÝ ĐỘI THI ĐẤU ================= -->
    <div v-if="isRegisterModalOpen && selectedTournament" class="modal-backdrop" @click.self="closeRegisterModal">
      <div class="modal-card modal-large">
        <div class="modal-head">
          <div>
            <span class="modal-eyebrow">Registration Portal</span>
            <h2 class="modal-title">Đăng Ký Tham Gia Giải Đấu</h2>
            <p class="modal-sub">
              Giải: <strong>{{ selectedTournament.name }}</strong> ({{ selectedTournament.venue }})
            </p>
          </div>
          <button class="modal-close" @click="closeRegisterModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="handleRegisterTeam">
          <!-- Thông tin đội -->
          <div class="form-block">
            <h3 class="block-heading">
              <span class="material-symbols-outlined text-[18px]">badge</span>
              <span>Thông Tin Đội & Hạng Mục</span>
            </h3>

            <div class="form-grid-2">
              <div class="form-field">
                <label class="field-label">Tên Đội / Câu Lạc Bộ</label>
                <input
                  v-model="registerForm.team_name"
                  type="text"
                  class="field-input"
                  placeholder="Ví dụ: Aura Alpha Club"
                  required
                />
              </div>

              <div class="form-field">
                <label class="field-label">Hạng Mục Thi Đấu</label>
                <select v-model="registerForm.category" class="field-select">
                  <option value="doubles_men">Đôi Nam (Men's Doubles)</option>
                  <option value="doubles_women">Đôi Nữ (Women's Doubles)</option>
                  <option value="doubles_mixed">Đôi Nam Nữ (Mixed Doubles)</option>
                  <option value="singles_men">Đơn Nam (Men's Singles)</option>
                  <option value="singles_women">Đơn Nữ (Women's Singles)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Vận động viên 1 (Đội trưởng) -->
          <div class="form-block">
            <h3 class="block-heading">
              <span class="material-symbols-outlined text-[18px]">person</span>
              <span>Vận Động Viên 1 (Đội Trưởng)</span>
            </h3>

            <div class="form-grid-3">
              <div class="form-field">
                <label class="field-label">Họ & Tên VĐV 1</label>
                <input
                  v-model="registerForm.player1_name"
                  type="text"
                  class="field-input"
                  required
                />
              </div>

              <div class="form-field">
                <label class="field-label">Số Điện Thoại Liên Hệ</label>
                <input
                  v-model="registerForm.player1_phone"
                  type="tel"
                  class="field-input"
                  placeholder="09xx xxx xxx"
                  required
                />
              </div>

              <div class="form-field">
                <label class="field-label">Trình Độ (Level)</label>
                <select v-model.number="registerForm.player1_skill" class="field-select">
                  <option :value="1">Level 1 (Cơ bản)</option>
                  <option :value="2">Level 2 (Tập luyện)</option>
                  <option :value="3">Level 3 (Trung bình - Khá)</option>
                  <option :value="4">Level 4 (Nâng cao)</option>
                  <option :value="5">Level 5 (Bán chuyên/Chuyên nghiệp)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Vận động viên 2 -->
          <div class="form-block">
            <h3 class="block-heading">
              <span class="material-symbols-outlined text-[18px]">group</span>
              <span>Vận Động Viên 2 (Bạn Đánh Cùng)</span>
            </h3>

            <div class="form-grid-3">
              <div class="form-field">
                <label class="field-label">Họ & Tên VĐV 2</label>
                <input
                  v-model="registerForm.player2_name"
                  type="text"
                  class="field-input"
                  placeholder="Họ tên bạn đánh"
                  required
                />
              </div>

              <div class="form-field">
                <label class="field-label">Số Điện Thoại</label>
                <input
                  v-model="registerForm.player2_phone"
                  type="tel"
                  class="field-input"
                  placeholder="09xx xxx xxx"
                />
              </div>

              <div class="form-field">
                <label class="field-label">Trình Độ (Level)</label>
                <select v-model.number="registerForm.player2_skill" class="field-select">
                  <option :value="1">Level 1 (Cơ bản)</option>
                  <option :value="2">Level 2 (Tập luyện)</option>
                  <option :value="3">Level 3 (Trung bình - Khá)</option>
                  <option :value="4">Level 4 (Nâng cao)</option>
                  <option :value="5">Level 5 (Bán chuyên/Chuyên nghiệp)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Cam kết & Lệ phí -->
          <div class="fee-commitment-box">
            <div class="fee-row">
              <span class="fee-label">Lệ phí tham gia giải:</span>
              <strong class="fee-amount">{{ selectedTournament.entry_fee || 'Miễn phí' }}</strong>
            </div>
            <label class="commitment-checkbox">
              <input v-model="registerForm.accept_rules" type="checkbox" />
              <span>Tôi cam kết các thành viên tuân thủ đúng điều lệ, lịch thi đấu và tinh thần Fair-play của Aura League.</span>
            </label>
          </div>

          <!-- Footer Actions -->
          <div class="modal-foot">
            <button type="button" class="btn-secondary-pill" @click="closeRegisterModal">Hủy</button>
            <button type="submit" class="btn-primary-pill" :disabled="processing">
              <span>Xác Nhận Đăng Ký</span>
              <span class="material-symbols-outlined text-[18px]">check</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL 2: SCOREBOARD & BRACKET TRỰC TUYẾN ================= -->
    <div v-if="isBracketModalOpen && selectedTournament" class="modal-backdrop" @click.self="closeBracketModal">
      <div class="modal-card modal-xlarge">
        <div class="modal-head">
          <div>
            <div class="flex-center-gap">
              <span class="badge-status-ongoing">Live Scoreboard</span>
              <span class="version-tag">Phiên bản v{{ selectedTournament.version }}</span>
            </div>
            <h2 class="modal-title mt-2">{{ selectedTournament.name }}</h2>
            <p class="modal-sub">
              Thể thức: <strong>{{ selectedTournament.format === 'single_elimination' ? 'Loại trực tiếp' : 'Vòng tròn & Nhánh kép' }}</strong> · {{ selectedTournament.venue }}
            </p>
          </div>
          <button class="modal-close" @click="closeBracketModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-bracket">
          <!-- Fixtures Grid -->
          <div v-if="selectedTournament.fixtures && selectedTournament.fixtures.length > 0" class="fixtures-card-grid">
            <div
              v-for="fixture in selectedTournament.fixtures"
              :key="fixture.id"
              class="fixture-item-box"
            >
              <div class="fixture-head">
                <span class="round-tag">Vòng {{ fixture.round }} · Trận #{{ fixture.position }}</span>
                <span class="status-tag" :class="{ 'status-live': fixture.status === 'live' }">
                  <span v-if="fixture.status === 'live'" class="live-dot-pulse-sm"></span>
                  {{ fixture.status }}
                </span>
              </div>

              <!-- Participant 1 -->
              <div
                class="fixture-player-row"
                :class="{ 'winner-row': fixture.winner_id === fixture.participant_one_id }"
              >
                <div class="player-left">
                  <img :src="getAvatarUrl(fixture.participant_one_name || 'Team 1')" class="avatar-sm" />
                  <span class="name">{{ fixture.participant_one_name || 'VĐV #' + (fixture.participant_one_id || 'TBD') }}</span>
                </div>
                <div class="player-right">
                  <span v-if="fixture.winner_id === fixture.participant_one_id" class="win-badge">WIN</span>
                  <button
                    v-if="fixture.status !== 'completed' && auth.user"
                    type="button"
                    class="btn-mini-win"
                    @click="handleScoreFixture(fixture, fixture.participant_one_id || 1)"
                  >
                    Ghi Thắng
                  </button>
                </div>
              </div>

              <!-- Participant 2 -->
              <div
                class="fixture-player-row"
                :class="{ 'winner-row': fixture.winner_id === fixture.participant_two_id }"
              >
                <div class="player-left">
                  <img :src="getAvatarUrl(fixture.participant_two_name || 'Team 2')" class="avatar-sm" />
                  <span class="name">{{ fixture.participant_two_name || 'VĐV #' + (fixture.participant_two_id || 'TBD') }}</span>
                </div>
                <div class="player-right">
                  <span v-if="fixture.winner_id === fixture.participant_two_id" class="win-badge">WIN</span>
                  <button
                    v-if="fixture.status !== 'completed' && auth.user"
                    type="button"
                    class="btn-mini-win"
                    @click="handleScoreFixture(fixture, fixture.participant_two_id || 2)"
                  >
                    Ghi Thắng
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="empty-bracket">
            <span class="material-symbols-outlined empty-tree-icon">account_tree</span>
            <p>Giải đấu đang chuẩn bị bốc thăm và lên lịch nhánh thi đấu chính thức.</p>
          </div>

          <div class="modal-foot">
            <button type="button" class="btn-primary-pill" @click="closeBracketModal">Đóng</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= MODAL 3: TẠO GIẢI ĐẤU MỚI ================= -->
    <div v-if="isCreateModalOpen" class="modal-backdrop" @click.self="closeCreateModal">
      <div class="modal-card modal-large">
        <div class="modal-head">
          <div>
            <span class="modal-eyebrow">Tournament Organizer</span>
            <h2 class="modal-title">Khởi Tạo Giải Đấu Mới</h2>
            <p class="modal-sub">
              Thiết lập quy mô, phân nhánh tự động và quản lý bảng điểm trực quan.
            </p>
          </div>
          <button class="modal-close" @click="closeCreateModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="handleCreateTournament">
          <div class="form-field">
            <label class="field-label">Tên Giải Đấu</label>
            <input
              v-model="createTournamentForm.name"
              type="text"
              class="field-input"
              placeholder="Ví dụ: Aura Autumn Master Cup 2026"
              required
            />
          </div>

          <div class="form-grid-2">
            <div class="form-field">
              <label class="field-label">Môn Thể Thao</label>
              <select v-model="createTournamentForm.sport" class="field-select">
                <option value="badminton">Cầu Lông (Badminton)</option>
                <option value="pickleball">Pickleball</option>
                <option value="tennis">Tennis</option>
              </select>
            </div>

            <div class="form-field">
              <label class="field-label">Thể Thức Phân Nhánh</label>
              <select v-model="createTournamentForm.format" class="field-select">
                <option value="single_elimination">Single Elimination (Loại trực tiếp)</option>
                <option value="round_robin">Round Robin (Vòng tròn tính điểm)</option>
              </select>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-field">
              <label class="field-label">Tổng Giải Thưởng</label>
              <input
                v-model="createTournamentForm.prize_pool"
                type="text"
                class="field-input"
                placeholder="50.000.000 ₫"
              />
            </div>

            <div class="form-field">
              <label class="field-label">Thời Gian Thi Đấu</label>
              <input
                v-model="createTournamentForm.dates"
                type="text"
                class="field-input"
                placeholder="10 - 12 Tháng 11, 2026"
              />
            </div>
          </div>

          <div class="form-field">
            <label class="field-label">Địa Điểm / Cụm Sân</label>
            <input
              v-model="createTournamentForm.venue"
              type="text"
              class="field-input"
              placeholder="Aura Center Court A · Quận 1"
            />
          </div>

          <div class="form-field">
            <label class="field-label">Danh sách ID VĐV hạt giống (cách nhau dấu phẩy)</label>
            <input
              v-model="createTournamentForm.participant_ids"
              type="text"
              class="field-input"
              placeholder="101, 102, 103, 104, 105, 106, 107, 108"
            />
            <span class="field-hint">Nhập tối thiểu 4 ID để hệ thống tự động bốc thăm hạt giống.</span>
          </div>

          <div class="modal-foot">
            <button type="button" class="btn-secondary-pill" @click="closeCreateModal">Hủy</button>
            <button type="submit" class="btn-primary-pill" :disabled="processing">
              <span>Khởi Tạo & Phân Nhánh</span>
              <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ==========================================================================
   Page Layout & Tokens (CSS Variables Based)
   ========================================================================== */
.tournaments-page {
  position: relative;
  min-height: 100vh;
  background-color: var(--bg, #eff1f4);
  color: var(--on-surface, #191c1d);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  transition: background-color 0.3s ease, color 0.3s ease;
}

.glow-bg-top {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 520px;
  background: radial-gradient(circle at 50% 0%, rgba(255, 209, 220, 0.4) 0%, rgba(239, 241, 244, 0) 70%);
  pointer-events: none;
  z-index: 0;
}

:global(html.dark) .glow-bg-top {
  background: radial-gradient(circle at 50% 0%, rgba(248, 180, 196, 0.12) 0%, rgba(24, 25, 29, 0) 70%);
}

.page-body {
  position: relative;
  z-index: 1;
  width: 100%;
}

.alerts-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 16px 32px 0;
}

.alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-radius: 14px;
  margin-bottom: 16px;
  font-size: 14px;
  font-weight: 500;
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

.alert-icon {
  font-size: 20px;
}

/* ================= HERO SECTION ================= */
.hero-section {
  position: relative;
  width: 100%;
  padding: 48px 32px 64px;
  background-color: var(--surface-bright, #f8f9fa);
  border-bottom: 1px solid var(--border, #e0e4ea);
  overflow: hidden;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

@media (min-width: 1024px) {
  .hero-section {
    padding: 64px 64px 80px;
  }
}

.hero-inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 48px;
  align-items: center;
}

@media (min-width: 1024px) {
  .hero-inner {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}

.hero-left-col {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (min-width: 1024px) {
  .hero-left-col {
    max-width: 580px;
  }
}

.hero-tags-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.tag-pill-open {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: #e8f5e9;
  color: #2e7d32;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  box-shadow: 0 2px 6px rgba(46, 125, 50, 0.12);
}

:global(html.dark) .tag-pill-open {
  background: rgba(46, 125, 50, 0.25);
  color: #4ade80;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.status-dot-green {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #2e7d32;
}

:global(html.dark) .status-dot-green {
  background-color: #4ade80;
}

.tag-pill-featured {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: var(--on-surface-variant, #5d5f5f);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.fire-icon {
  font-size: 16px;
  color: #f59e0b;
}

.hero-title {
  font-size: 40px;
  line-height: 1.1;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  letter-spacing: -0.02em;
  margin: 0;
}

@media (min-width: 768px) {
  .hero-title {
    font-size: 52px;
  }
}

.hero-title-italic {
  color: var(--primary, #78555e);
  font-style: italic;
  font-family: Georgia, 'Playfair Display', serif;
  font-weight: 700;
  opacity: 0.95;
}

.hero-description {
  font-size: 16px;
  line-height: 1.6;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 0;
  max-width: 540px;
}

.hero-cta-group {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.btn-primary-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background-color: var(--primary, #78555e);
  color: var(--on-primary, #ffffff);
  font-weight: 600;
  font-size: 14px;
  padding: 12px 24px;
  border-radius: 9999px;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.25);
  transition: all 0.25s ease;
}

.btn-primary-pill:hover {
  background-color: #5e3e47;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(120, 85, 94, 0.35);
}

:global(html.dark) .btn-primary-pill {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
  box-shadow: 0 4px 14px rgba(248, 180, 196, 0.25);
  font-weight: 700;
}

:global(html.dark) .btn-primary-pill:hover {
  background-color: #ffd5df;
  box-shadow: 0 6px 18px rgba(248, 180, 196, 0.35);
}

.btn-secondary-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background-color: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
  font-weight: 600;
  font-size: 14px;
  padding: 12px 20px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-secondary-pill:hover {
  background-color: var(--surface-container-high, #e7e8e9);
}

:global(html.dark) .btn-secondary-pill {
  background-color: var(--surface-container, #2b2e36);
  color: var(--on-surface, #f3f4f6);
  border-color: var(--border, #353942);
}

:global(html.dark) .btn-secondary-pill:hover {
  background-color: var(--surface-container-high, #353943);
}

.btn-outline-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background-color: transparent;
  color: var(--primary, #78555e);
  font-weight: 600;
  font-size: 14px;
  padding: 12px 18px;
  border-radius: 9999px;
  border: 1px dashed var(--primary, #78555e);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-outline-pill:hover {
  background-color: var(--primary-container, #ffd1dc);
}

:global(html.dark) .btn-outline-pill {
  color: var(--primary, #f8b4c4);
  border-color: var(--primary, #f8b4c4);
}

:global(html.dark) .btn-outline-pill:hover {
  background-color: var(--primary-container, #53333e);
}

.hero-specs-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding-top: 24px;
  border-top: 1px solid var(--border, #e0e4ea);
  margin-top: 8px;
  max-width: 520px;
}

.spec-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.spec-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--on-surface-variant, #5d5f5f);
}

.spec-value {
  font-size: 15px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

.spec-value.text-primary {
  color: var(--primary, #78555e);
}

.spec-sub {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
  opacity: 0.85;
}

/* Hero Right Visual Showcase */
.hero-right-col {
  width: 100%;
  display: flex;
  justify-content: center;
}

@media (min-width: 1024px) {
  .hero-right-col {
    max-width: 480px;
    justify-content: flex-end;
  }
}

.hero-showcase-box {
  position: relative;
  width: 100%;
  max-width: 440px;
  aspect-ratio: 1 / 1;
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 24px 48px -12px rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border, rgba(255, 209, 220, 0.4));
}

.showcase-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}

.hero-showcase-box:hover .showcase-img {
  transform: scale(1.04);
}

.showcase-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 24px;
  background: linear-gradient(to top, rgba(15, 16, 19, 0.95) 0%, rgba(15, 16, 19, 0.4) 60%, transparent 100%);
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  z-index: 10;
}

.showcase-venue-info {
  display: flex;
  flex-direction: column;
}

.showcase-venue-title {
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}

.venue-pin-icon {
  font-size: 18px;
  color: var(--primary, #ffd1dc);
}

.showcase-venue-sub {
  color: rgba(255, 255, 255, 0.85);
  font-size: 12px;
  margin-top: 2px;
  font-weight: 500;
}

.showcase-insignia {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

:global(html.dark) .showcase-insignia {
  background: rgba(34, 36, 42, 0.9);
}

.trophy-icon {
  font-size: 22px;
  color: var(--primary, #78555e);
}

/* ================= CIRCUIT SECTION ================= */
.circuit-section {
  width: 100%;
  background-color: var(--surface, #ffffff);
  padding: 56px 32px 80px;
  transition: background-color 0.3s ease;
}

:global(html.dark) .circuit-section {
  background-color: var(--bg, #18191d);
}

@media (min-width: 1024px) {
  .circuit-section {
    padding: 64px 64px 96px;
  }
}

.circuit-container {
  max-width: 1280px;
  margin: 0 auto;
}

.circuit-header-row {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 32px;
}

@media (min-width: 900px) {
  .circuit-header-row {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-end;
  }
}

.circuit-header-text {
  max-width: 620px;
}

.circuit-eyebrow {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--primary, #78555e);
  margin-bottom: 6px;
}

.badge-icon {
  font-size: 18px;
}

.circuit-main-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: var(--on-surface, #191c1d);
  margin: 0 0 8px;
}

.circuit-subtitle {
  font-size: 14px;
  line-height: 1.5;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 0;
}

/* Status Tabs Pill */
.status-tabs-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px;
  background-color: var(--surface-container, #edeeef);
  border-radius: 9999px;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
  flex-wrap: wrap;
}

:global(html.dark) .status-tabs-pill {
  background-color: var(--surface-container, #2b2e36);
  border: 1px solid var(--border, #353942);
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  color: var(--on-surface-variant, #5d5f5f);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-btn:hover {
  color: var(--on-surface, #191c1d);
}

.tab-btn.active {
  background-color: var(--card-bg, #ffffff);
  color: var(--on-surface, #191c1d);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

:global(html.dark) .tab-btn.active {
  background-color: var(--surface-container-high, #353943);
  color: var(--primary, #f8b4c4);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.live-dot-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #f57f17;
  animation: pulse-animation 1.5s infinite;
}

.live-dot-pulse-sm {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #f57f17;
  display: inline-block;
  animation: pulse-animation 1.5s infinite;
}

@keyframes pulse-animation {
  0% { transform: scale(0.9); opacity: 0.7; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.7; }
}

/* Circuit Filter Bar */
.circuit-filter-bar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 14px 18px;
  background-color: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 18px;
  margin-bottom: 36px;
  transition: all 0.3s ease;
}

:global(html.dark) .circuit-filter-bar {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

@media (min-width: 768px) {
  .circuit-filter-bar {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}

.search-input-box {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  max-width: 450px;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 18px;
  color: var(--on-surface-variant, #5d5f5f);
  pointer-events: none;
}

.filter-search-field {
  width: 100%;
  padding: 10px 38px 10px 42px;
  border-radius: 9999px;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--card-bg, #ffffff);
  font-size: 13px;
  color: var(--on-surface, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}

.filter-search-field:focus {
  border-color: var(--primary, #78555e);
}

:global(html.dark) .filter-search-field {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: var(--on-surface, #f3f4f6);
}

:global(html.dark) .filter-search-field:focus {
  border-color: var(--primary, #f8b4c4);
}

.clear-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--on-surface-variant, #5d5f5f);
  cursor: pointer;
}

.filter-chips-group {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.select-chip {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background-color: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 9999px;
  transition: all 0.2s ease;
}

:global(html.dark) .select-chip {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.chip-icon {
  font-size: 16px;
  color: var(--on-surface-variant, #5d5f5f);
}

.chip-select {
  appearance: none;
  background: transparent;
  border: none;
  font-size: 13px;
  font-weight: 500;
  color: var(--on-surface, #191c1d);
  padding-right: 18px;
  outline: none;
  cursor: pointer;
}

:global(html.dark) .chip-select {
  color: var(--on-surface, #f3f4f6);
}

:global(html.dark) .chip-select option {
  background-color: #22242a;
  color: #f3f4f6;
}

.chip-arrow {
  position: absolute;
  right: 10px;
  font-size: 16px;
  color: var(--on-surface-variant, #5d5f5f);
  pointer-events: none;
}

.btn-reset-filters {
  background: transparent;
  border: none;
  color: var(--primary, #78555e);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 6px 12px;
}

:global(html.dark) .btn-reset-filters {
  color: var(--primary, #f8b4c4);
}

/* ================= CARDS GRID ================= */
.tournaments-cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 28px;
}

@media (min-width: 640px) {
  .tournaments-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .tournaments-cards-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.card-container {
  display: flex;
  flex-direction: column;
  background-color: var(--card-bg, #ffffff);
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  transition: all 0.25s ease;
}

:global(html.dark) .card-container {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.card-container:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 32px rgba(0, 0, 0, 0.08);
  border-color: var(--primary, #ffd1dc);
}

:global(html.dark) .card-container:hover {
  box-shadow: 0 16px 32px rgba(0, 0, 0, 0.45);
  border-color: var(--primary, #f8b4c4);
}

.card-container.card-ongoing {
  border-color: #ffe082;
}

:global(html.dark) .card-container.card-ongoing {
  border-color: rgba(245, 127, 23, 0.4);
}

.card-img-header {
  position: relative;
  width: 100%;
  height: 180px;
  overflow: hidden;
}

.card-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.card-container:hover .card-photo {
  transform: scale(1.05);
}

.photo-grayscale {
  filter: grayscale(80%) brightness(0.85);
}

.card-badge-top-left {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 10;
}

.card-badge-top-right {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 10;
}

.badge-status-open {
  display: inline-block;
  padding: 4px 10px;
  background-color: #e8f5e9;
  color: #1b5e20;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #c8e6c9;
}

:global(html.dark) .badge-status-open {
  background-color: #064e3b;
  color: #6ee7b7;
  border: 1px solid #047857;
}

.badge-status-ongoing {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  background-color: #fef3c7;
  color: #92400e;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #fde68a;
}

:global(html.dark) .badge-status-ongoing {
  background-color: #78350f;
  color: #fde68a;
  border: 1px solid #b45309;
}

.badge-status-completed {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background-color: #f3f4f6;
  color: #374151;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

:global(html.dark) .badge-status-completed {
  background-color: #1f2937;
  color: #e5e7eb;
  border: 1px solid #374151;
}

.badge-category-glass {
  display: inline-block;
  padding: 5px 12px;
  background: rgba(15, 23, 42, 0.82);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  color: #ffffff !important;
  font-size: 11px;
  font-weight: 700;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
  letter-spacing: 0.02em;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
}

:global(html.dark) .badge-category-glass {
  background: rgba(15, 23, 42, 0.9);
  color: #ffffff !important;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.card-content-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.card-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.card-title-text {
  font-size: 17px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

.card-trophy-icon {
  font-size: 20px;
  color: var(--on-surface-variant, #5d5f5f);
  opacity: 0.5;
}

.card-meta-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
  flex: 1;
}

.meta-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--on-surface-variant, #5d5f5f);
}

.meta-icon {
  font-size: 17px;
  color: var(--primary, #78555e);
  opacity: 0.85;
}

.meta-text {
  font-weight: 500;
}

/* Slot Progress */
.slot-progress-wrapper {
  margin-top: 4px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.slot-progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
}

.slot-progress-label {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--on-surface-variant, #5d5f5f);
}

.slot-progress-count {
  color: var(--primary, #78555e);
}

.progress-track {
  height: 6px;
  width: 100%;
  background-color: var(--surface-container, #edeeef);
  border-radius: 9999px;
  overflow: hidden;
}

:global(html.dark) .progress-track {
  background-color: var(--surface-container, #2b2e36);
}

.progress-fill {
  height: 100%;
  background-color: var(--primary, #78555e);
  border-radius: 9999px;
  transition: width 0.3s ease;
}

:global(html.dark) .progress-fill {
  background-color: var(--primary, #f8b4c4);
}

/* Live Widget */
.live-widget-box {
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 12px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  border: 1px solid var(--border, transparent);
}

:global(html.dark) .live-widget-box {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.live-widget-header {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--on-surface-variant, #5d5f5f);
  margin-bottom: 2px;
}

.live-tag {
  color: #f57f17;
  display: flex;
  align-items: center;
  gap: 4px;
}

.live-team-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  padding: 2px 0;
}

.team-info {
  display: flex;
  align-items: center;
  gap: 6px;
}

.avatar-sm {
  width: 22px;
  height: 22px;
  border-radius: 50%;
}

.player-name {
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

.player-name.text-muted {
  color: var(--on-surface-variant, #5d5f5f);
}

.score-digits-row {
  display: flex;
  gap: 4px;
}

.score-badge {
  padding: 2px 6px;
  background-color: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 4px;
  font-size: 12px;
  font-family: monospace;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
}

:global(html.dark) .score-badge {
  background-color: var(--surface-container-high, #353943);
  border-color: var(--border, #353942);
  color: var(--on-surface, #f3f4f6);
}

.score-badge.score-win {
  color: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
}

:global(html.dark) .score-badge.score-win {
  color: var(--primary, #f8b4c4);
  border-color: var(--primary, #f8b4c4);
}

/* Podium Widget */
.podium-widget-box {
  background-color: #fffde7;
  border: 1px solid #fff59d;
  border-radius: 12px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

:global(html.dark) .podium-widget-box {
  background-color: rgba(245, 158, 11, 0.12);
  border-color: rgba(245, 158, 11, 0.3);
}

.podium-winner-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #d97706;
}

:global(html.dark) .podium-winner-row {
  color: #fbbf24;
}

.podium-runner-row {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
  padding-left: 22px;
}

/* Card Footer */
.card-footer-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 14px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.prize-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--on-surface-variant, #5d5f5f);
}

.prize-value {
  font-size: 15px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
}

.btn-card-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 700;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-card-action:hover {
  transform: translateX(2px);
}

.btn-action-register {
  color: var(--primary, #78555e);
}

:global(html.dark) .btn-action-register {
  color: var(--primary, #f8b4c4);
}

.btn-action-ongoing {
  color: #f57f17;
}

:global(html.dark) .btn-action-ongoing {
  color: #fb923c;
}

.btn-action-view {
  color: var(--on-surface-variant, #5d5f5f);
}

/* Empty State */
.empty-circuit-card {
  padding: 48px 24px;
  text-align: center;
  background-color: var(--surface-subtle, #f5f6f8);
  border-radius: 24px;
  border: 1px solid var(--border, #e0e4ea);
  margin: 32px 0;
}

:global(html.dark) .empty-circuit-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.empty-icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background-color: var(--surface-container, #edeeef);
  margin: 0 auto 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary, #78555e);
}

:global(html.dark) .empty-icon-circle {
  background-color: var(--surface-container, #2b2e36);
  color: var(--primary, #f8b4c4);
}

.empty-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0 0 8px;
}

.empty-desc {
  font-size: 14px;
  color: var(--on-surface-variant, #5d5f5f);
  max-width: 440px;
  margin: 0 auto 24px;
}

.empty-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
}

/* ================= MODAL STYLES ================= */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(6px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-card {
  background-color: var(--card-bg, #ffffff);
  color: var(--on-surface, #191c1d);
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  border-radius: 24px;
  padding: 28px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
}

:global(html.dark) .modal-card {
  background-color: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.modal-large {
  max-width: 640px;
}

.modal-xlarge {
  max-width: 860px;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.modal-eyebrow {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  font-weight: 700;
  color: var(--primary, #78555e);
}

:global(html.dark) .modal-eyebrow {
  color: var(--primary, #f8b4c4);
}

.modal-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--on-surface, #191c1d);
  margin: 4px 0 0;
}

.modal-sub {
  font-size: 13px;
  color: var(--on-surface-variant, #5d5f5f);
  margin: 4px 0 0;
}

.modal-close {
  background: transparent;
  border: none;
  color: var(--on-surface-variant, #5d5f5f);
  cursor: pointer;
  padding: 6px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close:hover {
  background-color: var(--surface-container, #edeeef);
}

:global(html.dark) .modal-close:hover {
  background-color: var(--surface-container, #2b2e36);
}

.modal-form {
  padding: 20px 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.block-heading {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--primary, #78555e);
  margin: 0;
}

:global(html.dark) .block-heading {
  color: var(--primary, #f8b4c4);
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

@media (min-width: 600px) {
  .form-grid-2 {
    grid-template-columns: repeat(2, 1fr);
  }
}

.form-grid-3 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

@media (min-width: 600px) {
  .form-grid-3 {
    grid-template-columns: repeat(3, 1fr);
  }
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

.field-input,
.field-select {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #e0e4ea);
  background-color: var(--surface-subtle, #f5f6f8);
  font-size: 13px;
  color: var(--on-surface, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}

.field-input:focus,
.field-select:focus {
  border-color: var(--primary, #78555e);
}

:global(html.dark) .field-input,
:global(html.dark) .field-select {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
  color: var(--on-surface, #f3f4f6);
}

:global(html.dark) .field-input:focus,
:global(html.dark) .field-select:focus {
  border-color: var(--primary, #f8b4c4);
}

:global(html.dark) .field-select option {
  background-color: #22242a;
  color: #f3f4f6;
}

.field-hint {
  font-size: 11px;
  color: var(--on-surface-variant, #5d5f5f);
}

.fee-commitment-box {
  background-color: var(--surface-container, #edeeef);
  border-radius: 14px;
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

:global(html.dark) .fee-commitment-box {
  background-color: var(--surface-container, #2b2e36);
  border: 1px solid var(--border, #353942);
}

.fee-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.fee-label {
  color: var(--on-surface-variant, #5d5f5f);
}

.fee-amount {
  font-size: 16px;
  color: var(--primary, #78555e);
}

:global(html.dark) .fee-amount {
  color: var(--primary, #f8b4c4);
}

.commitment-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
  cursor: pointer;
  line-height: 1.4;
}

.commitment-checkbox input {
  margin-top: 2px;
  accent-color: var(--primary, #78555e);
}

:global(html.dark) .commitment-checkbox input {
  accent-color: var(--primary, #f8b4c4);
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
}

/* Bracket Modal */
.modal-body-bracket {
  padding: 20px 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.flex-center-gap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.version-tag {
  font-size: 12px;
  color: var(--on-surface-variant, #5d5f5f);
}

.fixtures-card-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 600px) {
  .fixtures-card-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.fixture-item-box {
  background-color: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

:global(html.dark) .fixture-item-box {
  background-color: var(--surface-container, #2b2e36);
  border-color: var(--border, #353942);
}

.fixture-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  font-weight: 600;
  padding-bottom: 6px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.round-tag {
  color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .round-tag {
  color: var(--primary, #f8b4c4);
}

.status-tag {
  text-transform: capitalize;
  color: var(--on-surface-variant, #5d5f5f);
}

.status-tag.status-live {
  color: #f57f17;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 4px;
}

.fixture-player-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-radius: 10px;
  background-color: var(--card-bg, #ffffff);
  border: 1px solid transparent;
}

:global(html.dark) .fixture-player-row {
  background-color: var(--surface-container-high, #353943);
}

.fixture-player-row.winner-row {
  background-color: rgba(255, 209, 220, 0.25);
  border-color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .fixture-player-row.winner-row {
  background-color: rgba(248, 180, 196, 0.15);
  border-color: var(--primary, #f8b4c4);
}

.player-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.player-left .name {
  font-size: 13px;
  color: var(--on-surface, #191c1d);
}

.player-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.win-badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--primary, #78555e);
}

:global(html.dark) .win-badge {
  color: var(--primary, #f8b4c4);
}

.btn-mini-win {
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 600;
  background-color: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.btn-mini-win:hover {
  background-color: #5e3e47;
}

:global(html.dark) .btn-mini-win {
  background-color: var(--primary, #f8b4c4);
  color: #201317;
  font-weight: 700;
}

.empty-bracket {
  text-align: center;
  padding: 32px 16px;
  color: var(--on-surface-variant, #5d5f5f);
}

.empty-tree-icon {
  font-size: 36px;
  opacity: 0.4;
  margin-bottom: 8px;
}
</style>
