<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiV2, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

interface Match {
  id: number
  title: string
  venue_name?: string
  court_name?: string
  city?: string
  starts_at: string
  ends_at?: string
  joined_count: number
  required_participants: number
  visibility: 'public' | 'invite_only' | 'private'
  min_skill?: number
  max_skill?: number
  host_name?: string
  host_avatar?: string
  description?: string
  participants?: Array<{
    id: number
    name: string
    avatar?: string
    skill_level?: number
    is_host?: boolean
  }>
  auto_matchmaking?: boolean
}

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
  score?: Record<string, unknown>
}

interface Tournament {
  id: number
  name: string
  format: string
  status: string
  version: number
  fixtures: Fixture[]
}

interface AthletePartner {
  id: number
  name: string
  level: string
  avatar: string
  selected: boolean
}

const auth = useAuthStore()
const activeTab = ref<'matches' | 'tournaments' | 'profile'>('matches')

// Notification & Loading
const loading = ref(false)
const processing = ref(false)
const error = ref('')
const notice = ref('')

function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4000)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 5000)
}

// Filters
const filterDistance = ref('all')
const filterSkill = ref('all')
const filterAvailability = ref('all')
const filterVisibility = ref('all')
const searchQuery = ref('')

// Profile state
const profile = ref({
  skill_level: 3,
  city: 'Hồ Chí Minh',
  reputation_score: 95,
  availability: ['Thứ 2', 'Thứ 4', 'Thứ 6', 'Chủ Nhật'] as string[],
  preferred_positions: ['Đôi nam', 'Đôi nam nữ'] as string[],
  visibility: 'organization',
})

// Match Form State for "Tạo Kèo Mới"
const isCreateModalOpen = ref(false)
const matchForm = ref({
  title: 'Aura Central Court',
  venue_name: 'Aura Central - Quận 1',
  court_name: 'Sân VIP 1 (Thảm Taraflex Đỏ)',
  starts_date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
  starts_time: '19:00',
  ends_time: '21:00',
  city: 'Hồ Chí Minh',
  skill_level: 3,
  required_participants: 4,
  visibility: 'public' as 'public' | 'invite_only',
  auto_matchmaking: true,
  description: 'Giao lưu cầu lông phong trào, vui vẻ, cầu Yonex xịn, có nước lọc miễn phí.',
})

// Invite Network partners in Modal
const inviteNetwork = ref<AthletePartner[]>([
  {
    id: 1,
    name: 'Marcus Trần',
    level: 'Advanced (Level 4)',
    avatar: getAvatarUrl('Marcus Trần'),
    selected: false,
  },
  {
    id: 2,
    name: 'Elena Ryan',
    level: 'Pro (Level 5)',
    avatar: getAvatarUrl('Elena Ryan'),
    selected: false,
  },
  {
    id: 3,
    name: 'David Khoa',
    level: 'Intermediate (Level 3)',
    avatar: getAvatarUrl('David Khoa'),
    selected: false,
  },
  {
    id: 4,
    name: 'Phương Linh',
    level: 'Intermediate (Level 3)',
    avatar: getAvatarUrl('Phương Linh'),
    selected: false,
  },
  {
    id: 5,
    name: 'Tuấn Anh',
    level: 'Beginner (Level 2)',
    avatar: getAvatarUrl('Tuấn Anh'),
    selected: false,
  },
])

// Match Detail Modal
const isDetailModalOpen = ref(false)
const selectedMatch = ref<Match | null>(null)

// Tournament State
const tournaments = ref<Tournament[]>([])
const selectedTournament = ref<Tournament | null>(null)
const tournamentForm = ref({
  name: 'Aura Summer Open 2026',
  format: 'single_elimination',
  participant_ids: '101, 102, 103, 104',
})

// Demo matches data matching Stitch design
const demoMatches: Match[] = [
  {
    id: 101,
    title: 'Aura Central Court - Kèo Đôi Nam Nữ Giao Lưu',
    venue_name: 'Aura Central Court',
    court_name: 'Sân VIP 1 (Thảm Taraflex)',
    city: 'Quận 1, TP. Hồ Chí Minh',
    starts_at: new Date(Date.now() + 86400000).toISOString().replace(/T.*/, 'T19:00:00.000Z'),
    ends_at: new Date(Date.now() + 86400000).toISOString().replace(/T.*/, 'T21:00:00.000Z'),
    joined_count: 2,
    required_participants: 4,
    visibility: 'public',
    min_skill: 3,
    max_skill: 4,
    host_name: 'Minh Hoàng',
    host_avatar: getAvatarUrl('Minh Hoàng'),
    description: 'Tìm thêm 2 bạn đánh đôi nam hoặc nam nữ trình độ trung bình - khá (Level 3-4). Đánh vui vẻ, chia đều tiền sân & nước.',
    participants: [
      { id: 1, name: 'Minh Hoàng (Host)', avatar: getAvatarUrl('Minh Hoàng'), skill_level: 4, is_host: true },
      { id: 2, name: 'Hồng Ngọc', avatar: getAvatarUrl('Hồng Ngọc'), skill_level: 3, is_host: false },
    ],
    auto_matchmaking: true,
  },
  {
    id: 102,
    title: 'District 1 Sports Hub - Kèo Nâng Cao Cọ Xát',
    venue_name: 'District 1 Sports Hub',
    court_name: 'Sân C4 Tiêu Chuẩn',
    city: 'Quận 1, TP. Hồ Chí Minh',
    starts_at: new Date(Date.now() + 172800000).toISOString().replace(/T.*/, 'T18:00:00.000Z'),
    ends_at: new Date(Date.now() + 172800000).toISOString().replace(/T.*/, 'T20:00:00.000Z'),
    joined_count: 3,
    required_participants: 4,
    visibility: 'invite_only',
    min_skill: 4,
    max_skill: 5,
    host_name: 'Elena Ryan',
    host_avatar: getAvatarUrl('Elena Ryan'),
    description: 'Trận đấu dành cho VĐV bán chuyên và nâng cao (Level 4+). Tốc độ cầu nhanh, chiến thuật đơn & đôi.',
    participants: [
      { id: 3, name: 'Elena Ryan (Host)', avatar: getAvatarUrl('Elena Ryan'), skill_level: 5, is_host: true },
      { id: 4, name: 'Quốc Bảo', avatar: getAvatarUrl('Quốc Bảo'), skill_level: 4, is_host: false },
      { id: 5, name: 'Bảo Trâm', avatar: getAvatarUrl('Bảo Trâm'), skill_level: 4, is_host: false },
    ],
    auto_matchmaking: false,
  },
  {
    id: 103,
    title: 'Riverside Club - Giao Lưu Cuối Tuần Sáng Sớm',
    venue_name: 'Riverside Club',
    court_name: 'Sân 02 View Sông',
    city: 'Bình Thạnh, TP. Hồ Chí Minh',
    starts_at: new Date(Date.now() + 259200000).toISOString().replace(/T.*/, 'T08:00:00.000Z'),
    ends_at: new Date(Date.now() + 259200000).toISOString().replace(/T.*/, 'T11:00:00.000Z'),
    joined_count: 3,
    required_participants: 6,
    visibility: 'public',
    min_skill: 1,
    max_skill: 3,
    host_name: 'David Khoa',
    host_avatar: getAvatarUrl('David Khoa'),
    description: 'Sân thoáng mát, rèn luyện thể lực sáng thứ 7. Chào đón tất cả các bạn mới chơi hoặc cơ bản (Level 1-3).',
    participants: [
      { id: 6, name: 'David Khoa (Host)', avatar: getAvatarUrl('David Khoa'), skill_level: 2, is_host: true },
      { id: 7, name: 'Văn Hùng', avatar: getAvatarUrl('Văn Hùng'), skill_level: 2, is_host: false },
      { id: 8, name: 'Thu Thảo', avatar: getAvatarUrl('Thu Thảo'), skill_level: 1, is_host: false },
    ],
    auto_matchmaking: true,
  },
  {
    id: 104,
    title: 'Aura East Court - Kèo Cầu Lông Đôi Nam Buổi Tối',
    venue_name: 'Aura East Court',
    court_name: 'Sân 05 Chuyên Nghiệp',
    city: 'Quận 2, TP. Hồ Chí Minh',
    starts_at: new Date(Date.now() + 345600000).toISOString().replace(/T.*/, 'T20:00:00.000Z'),
    ends_at: new Date(Date.now() + 345600000).toISOString().replace(/T.*/, 'T22:00:00.000Z'),
    joined_count: 2,
    required_participants: 4,
    visibility: 'public',
    min_skill: 3,
    max_skill: 4,
    host_name: 'Vũ Nam',
    host_avatar: getAvatarUrl('Vũ Nam'),
    description: 'Tìm 2 tay vợt đánh đôi nam năng động, sân có máy lạnh và thảm chuẩn quốc tế.',
    participants: [
      { id: 9, name: 'Vũ Nam (Host)', avatar: getAvatarUrl('Vũ Nam'), skill_level: 3, is_host: true },
      { id: 10, name: 'Thế Vinh', avatar: getAvatarUrl('Thế Vinh'), skill_level: 4, is_host: false },
    ],
    auto_matchmaking: true,
  },
]

const matches = ref<Match[]>([])

// Filtered Matches
const filteredMatches = computed(() => {
  return matches.value.filter((m) => {
    // Search query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const titleMatch = m.title.toLowerCase().includes(q)
      const venueMatch = (m.venue_name || '').toLowerCase().includes(q)
      const hostMatch = (m.host_name || '').toLowerCase().includes(q)
      if (!titleMatch && !venueMatch && !hostMatch) return false
    }

    // Distance / Location filter
    if (filterDistance.value !== 'all') {
      if (filterDistance.value === 'q1' && !m.city?.includes('Quận 1')) return false
      if (filterDistance.value === 'q2' && !m.city?.includes('Quận 2')) return false
      if (filterDistance.value === 'binhthanh' && !m.city?.includes('Bình Thạnh')) return false
    }

    // Skill level filter
    if (filterSkill.value !== 'all') {
      const targetLevel = Number(filterSkill.value)
      if (m.min_skill && m.max_skill) {
        if (targetLevel < m.min_skill || targetLevel > m.max_skill) return false
      }
    }

    // Visibility filter
    if (filterVisibility.value !== 'all') {
      if (filterVisibility.value === 'public' && m.visibility !== 'public') return false
      if (filterVisibility.value === 'private' && m.visibility === 'public') return false
    }

    return true
  })
})

function getSkillLabel(level?: number): string {
  if (!level) return 'Mọi trình độ'
  switch (level) {
    case 1:
      return 'Beginner / Level 1'
    case 2:
      return 'Novice / Level 2'
    case 3:
      return 'Intermediate / Level 3'
    case 4:
      return 'Advanced / Level 4'
    case 5:
      return 'Professional / Level 5'
    default:
      return `Level ${level}`
  }
}

function getSkillName(level: number): string {
  switch (level) {
    case 1: return 'Beginner (Cơ bản)'
    case 2: return 'Novice (Tập luyện)'
    case 3: return 'Intermediate (Trung bình)'
    case 4: return 'Advanced (Nâng cao)'
    case 5: return 'Professional (Chuyên nghiệp)'
    default: return `Level ${level}`
  }
}

function formatDate(iso: string): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    const today = new Date()
    const tomorrow = new Date(Date.now() + 86400000)

    let prefix = ''
    if (d.toDateString() === today.toDateString()) {
      prefix = 'Hôm nay'
    } else if (d.toDateString() === tomorrow.toDateString()) {
      prefix = 'Ngày mai'
    } else {
      prefix = d.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit' })
    }

    const timeStr = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false })
    return `${prefix}, ${timeStr}`
  } catch {
    return iso
  }
}

function resetFilters() {
  filterDistance.value = 'all'
  filterSkill.value = 'all'
  filterAvailability.value = 'all'
  filterVisibility.value = 'all'
  searchQuery.value = ''
}

// Load data from API with fallback
async function load(): Promise<void> {
  loading.value = true
  try {
    const [profileResponse, matchesResponse, tournamentsResponse] = await Promise.allSettled([
      apiV2.get<typeof profile.value>('/social/profile'),
      apiV2.get<{ matches: Match[] }>('/social/matches'),
      apiV2.get<{ data: Tournament[] }>('/tournaments'),
    ])

    if (profileResponse.status === 'fulfilled' && profileResponse.value) {
      profile.value = { ...profile.value, ...profileResponse.value }
    }

    if (matchesResponse.status === 'fulfilled' && matchesResponse.value?.matches?.length) {
      matches.value = matchesResponse.value.matches
    } else {
      matches.value = [...demoMatches]
    }

    if (tournamentsResponse.status === 'fulfilled' && tournamentsResponse.value?.data?.length) {
      tournaments.value = tournamentsResponse.value.data
    }
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không tải được dữ liệu kết nối.'))
    matches.value = [...demoMatches]
  } finally {
    loading.value = false
  }
}

async function saveProfile(): Promise<void> {
  processing.value = true
  try {
    await apiV2.put('/social/profile', profile.value)
    showNotice('Đã lưu thông tin hồ sơ vận động viên thành công.')
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể lưu hồ sơ.'))
  } finally {
    processing.value = false
  }
}

function openCreateModal() {
  isCreateModalOpen.value = true
}

function closeCreateModal() {
  isCreateModalOpen.value = false
}

function togglePartner(partner: AthletePartner) {
  partner.selected = !partner.selected
}

async function handleCreateMatch(): Promise<void> {
  processing.value = true
  try {
    const startsAt = new Date(`${matchForm.value.starts_date}T${matchForm.value.starts_time}:00`).toISOString()
    const endsAt = new Date(`${matchForm.value.starts_date}T${matchForm.value.ends_time}:00`).toISOString()

    const payload = {
      title: matchForm.value.title,
      starts_at: startsAt,
      ends_at: endsAt,
      city: matchForm.value.city,
      min_skill: matchForm.value.skill_level,
      max_skill: Math.min(5, matchForm.value.skill_level + 1),
      required_participants: matchForm.value.required_participants,
      visibility: matchForm.value.visibility === 'public' ? 'public' : 'invite_only',
    }

    // Try API post
    try {
      await apiV2.post('/social/matches', payload)
    } catch {
      // offline/fallback: prepend to local matches
      const newMatch: Match = {
        id: Date.now(),
        title: matchForm.value.title,
        venue_name: matchForm.value.venue_name,
        court_name: matchForm.value.court_name,
        city: matchForm.value.city,
        starts_at: startsAt,
        ends_at: endsAt,
        joined_count: 1,
        required_participants: matchForm.value.required_participants,
        visibility: matchForm.value.visibility,
        min_skill: matchForm.value.skill_level,
        max_skill: Math.min(5, matchForm.value.skill_level + 1),
        host_name: auth.user?.name || 'Aura Athlete',
        host_avatar: getAvatarUrl(auth.user?.name, auth.user?.avatar_url),
        description: matchForm.value.description,
        auto_matchmaking: matchForm.value.auto_matchmaking,
        participants: [
          {
            id: auth.user?.id || 99,
            name: (auth.user?.name || 'Aura Athlete') + ' (Host)',
            avatar: getAvatarUrl(auth.user?.name, auth.user?.avatar_url),
            skill_level: matchForm.value.skill_level,
            is_host: true,
          },
        ],
      }
      matches.value.unshift(newMatch)
    }

    showNotice('🎉 Đã khởi tạo kèo giao lưu thành công!')
    closeCreateModal()
    await load()
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể đăng tạo kèo lúc này.'))
  } finally {
    processing.value = false
  }
}

function openDetailModal(match: Match) {
  selectedMatch.value = match
  isDetailModalOpen.value = true
}

function closeDetailModal() {
  isDetailModalOpen.value = false
  selectedMatch.value = null
}

async function joinMatch(match: Match): Promise<void> {
  processing.value = true
  try {
    try {
      await apiV2.post(`/social/matches/${match.id}/join`)
    } catch {
      // optimistic update
      if (!match.participants) match.participants = []
      const myId = auth.user?.id || 888
      const alreadyJoined = match.participants.some((p) => p.id === myId || (auth.user?.name && p.name === auth.user.name))
      if (!alreadyJoined) {
        match.joined_count = Math.min(match.required_participants, match.joined_count + 1)
        match.participants.push({
          id: myId,
          name: auth.user?.name || 'Bạn',
          avatar: getAvatarUrl(auth.user?.name, auth.user?.avatar_url),
          skill_level: profile.value.skill_level,
          is_host: false,
        })
      }
    }
    showNotice('✨ Bạn đã tham gia kèo giao lưu thành công!')
    if (selectedMatch.value?.id === match.id) {
      closeDetailModal()
    }
    await load()
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể tham gia trận đấu.'))
  } finally {
    processing.value = false
  }
}

// Tournament methods
async function createTournament(): Promise<void> {
  processing.value = true
  try {
    await apiV2.post('/tournaments', {
      name: tournamentForm.value.name,
      format: tournamentForm.value.format,
      participant_ids: tournamentForm.value.participant_ids.split(',').map((id) => Number(id.trim())),
    })
    showNotice('Đã tạo bracket giải đấu thành công.')
    await load()
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể tạo giải đấu.'))
  } finally {
    processing.value = false
  }
}

async function openTournament(tournament: Tournament): Promise<void> {
  try {
    const res = await apiV2.get<{ tournament: Tournament }>(`/tournaments/${tournament.id}`)
    selectedTournament.value = res.tournament
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không tải được bracket giải đấu.'))
  }
}

async function scoreFixture(fixture: Fixture): Promise<void> {
  if (!selectedTournament.value || !fixture.participant_one_id) return
  try {
    await apiV2.post(`/tournaments/${selectedTournament.value.id}/fixtures/${fixture.id}/score`, {
      expected_version: selectedTournament.value.version,
      winner_id: fixture.participant_one_id,
      score: { winner: 21, loser: 18 },
    })
    showNotice('Đã ghi nhận điểm số và đồng bộ realtime.')
    await openTournament(selectedTournament.value)
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Bracket đã thay đổi; vui lòng tải lại giải đấu.'))
  }
}

onMounted(load)
</script>

<template>
  <div class="matchmaking-page font-sans antialiased">
    <!-- Ambient Background Glow -->
    <div class="glow-bg-top" aria-hidden="true"></div>

    <div class="page-container">
      <!-- Top Breadcrumb & Sub Navigation Tabs -->
      <div class="subnav-tabs-wrapper">
        <div class="subnav-tabs">
          <button
            class="subnav-tab-btn"
            :class="{ active: activeTab === 'matches' }"
            @click="activeTab = 'matches'"
          >
            <span class="material-symbols-outlined icon">sports_tennis</span>
            <span>Tìm Kèo Giao Lưu</span>
          </button>
          <button
            class="subnav-tab-btn"
            :class="{ active: activeTab === 'tournaments' }"
            @click="activeTab = 'tournaments'"
          >
            <span class="material-symbols-outlined icon">emoji_events</span>
            <span>Giải Đấu & Xếp Hạng</span>
          </button>
          <button
            class="subnav-tab-btn"
            :class="{ active: activeTab === 'profile' }"
            @click="activeTab = 'profile'"
          >
            <span class="material-symbols-outlined icon">person</span>
            <span>Hồ Sơ VĐV & Elo</span>
          </button>
        </div>
      </div>

      <!-- Flash Notification / Alert -->
      <Transition name="fade">
        <div v-if="notice" class="alert-banner alert-success">
          <span class="material-symbols-outlined">check_circle</span>
          <span>{{ notice }}</span>
        </div>
      </Transition>
      <Transition name="fade">
        <div v-if="error" class="alert-banner alert-danger">
          <span class="material-symbols-outlined">error</span>
          <span>{{ error }}</span>
        </div>
      </Transition>

      <!-- ================= TAB 1: TÌM KÈO GIAO LƯU ================= -->
      <section v-if="activeTab === 'matches'" class="tab-content">
        <!-- Hero Header -->
        <div class="page-header-row">
          <div class="header-text-block">
            <span class="header-eyebrow">Aura Matchmaking & Network</span>
            <h1 class="page-main-title">Tìm Kèo Giao Lưu</h1>
            <p class="page-subtitle">
              Kết nối với những người chơi cùng đam mê. Khám phá đối thủ xứng tầm, tìm bạn đánh đôi và trải nghiệm phong độ đỉnh cao tại Aura Court.
            </p>
          </div>

          <RouterLink to="/matchmaking/create" class="btn-create-match">
            <span class="material-symbols-outlined icon">add</span>
            <span>Tạo Kèo Mới</span>
          </RouterLink>
        </div>

        <!-- Filter Bar (Stitch Design) -->
        <div class="filters-bar">
          <div class="search-input-box">
            <span class="material-symbols-outlined search-icon">search</span>
            <input
              v-model="searchQuery"
              type="text"
              class="filter-search-field"
              placeholder="Tìm kiếm theo tên sân, cơ sở hoặc người tạo..."
            />
            <button v-if="searchQuery" class="clear-search-btn" @click="searchQuery = ''">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="filter-chips-scroll hide-scrollbar">
            <!-- Distance Chip -->
            <div class="filter-dropdown-chip">
              <span class="material-symbols-outlined chip-icon">location_on</span>
              <select v-model="filterDistance" class="chip-select">
                <option value="all">Khu vực: Tất cả</option>
                <option value="q1">Quận 1 (Aura Central)</option>
                <option value="q2">Quận 2 (Aura East)</option>
                <option value="binhthanh">Bình Thạnh (Riverside)</option>
              </select>
              <span class="material-symbols-outlined chip-arrow">expand_more</span>
            </div>

            <!-- Skill Level Chip -->
            <div class="filter-dropdown-chip">
              <span class="material-symbols-outlined chip-icon">star</span>
              <select v-model="filterSkill" class="chip-select">
                <option value="all">Trình độ: Tất cả</option>
                <option value="1">Cơ bản (Level 1)</option>
                <option value="2">Tập luyện (Level 2)</option>
                <option value="3">Trung bình (Level 3)</option>
                <option value="4">Nâng cao (Level 4)</option>
                <option value="5">Chuyên nghiệp (Level 5)</option>
              </select>
              <span class="material-symbols-outlined chip-arrow">expand_more</span>
            </div>

            <!-- Visibility Chip -->
            <div class="filter-dropdown-chip">
              <span class="material-symbols-outlined chip-icon">public</span>
              <select v-model="filterVisibility" class="chip-select">
                <option value="all">Loại kèo: Tất cả</option>
                <option value="public">Công khai (Public)</option>
                <option value="private">Kèo kín (Invite Only)</option>
              </select>
              <span class="material-symbols-outlined chip-arrow">expand_more</span>
            </div>

            <div class="filter-divider"></div>

            <button class="btn-reset-filters" @click="resetFilters">
              Đặt lại bộ lọc
            </button>
          </div>
        </div>

        <!-- Matches Grid -->
        <div v-if="filteredMatches.length > 0" class="matches-grid">
          <div
            v-for="match in filteredMatches"
            :key="match.id"
            class="match-card group"
          >
            <!-- Top Gradient Hover Line -->
            <div class="card-gradient-top"></div>

            <!-- Card Header -->
            <div class="card-header-flex">
              <div class="host-info-flex">
                <div class="host-avatar-ring">
                  <img
                    :src="getAvatarUrl(match.host_name, match.host_avatar)"
                    :alt="match.host_name || 'Host'"
                    class="host-avatar-img"
                  />
                </div>
                <div class="court-location-meta">
                  <h3 class="court-venue-title">{{ match.venue_name || match.title }}</h3>
                  <p class="match-time-badge">
                    <span class="material-symbols-outlined icon-time">calendar_today</span>
                    <span>{{ formatDate(match.starts_at) }}</span>
                  </p>
                </div>
              </div>

              <!-- Visibility Badge -->
              <span
                v-if="match.visibility === 'public'"
                class="badge-pill badge-public"
              >
                Public
              </span>
              <span
                v-else
                class="badge-pill badge-private"
              >
                <span class="material-symbols-outlined text-[14px]">lock</span>
                Private Invite
              </span>
            </div>

            <!-- Match Card Description Snippet -->
            <p v-if="match.description" class="match-desc-snippet">
              {{ match.description }}
            </p>

            <!-- Card Info Meta Footer -->
            <div class="card-meta-row">
              <div class="meta-item">
                <span class="material-symbols-outlined icon-meta">leaderboard</span>
                <span class="meta-text">{{ getSkillLabel(match.min_skill) }}</span>
              </div>
              <div class="meta-separator"></div>
              <div class="meta-item">
                <span class="material-symbols-outlined icon-meta text-primary">group_add</span>
                <span class="meta-text text-primary font-medium">
                  {{ match.required_participants - match.joined_count }}/{{ match.required_participants }} chỗ trống
                </span>
              </div>
            </div>

            <!-- Card Actions -->
            <div class="card-actions-row">
              <button
                class="btn-card-secondary"
                @click="openDetailModal(match)"
              >
                Xem chi tiết
              </button>
              <button
                v-if="match.joined_count < match.required_participants"
                class="btn-card-primary"
                :disabled="processing"
                @click="joinMatch(match)"
              >
                {{ match.visibility === 'public' ? 'Tham Gia Ngay' : 'Gửi Yêu Cầu' }}
              </button>
              <button
                v-else
                class="btn-card-full"
                disabled
              >
                Đã Đủ Người
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state-card">
          <div class="empty-state-icon">
            <span class="material-symbols-outlined">sports_tennis</span>
          </div>
          <h3 class="empty-state-title">Chưa tìm thấy kèo phù hợp</h3>
          <p class="empty-state-desc">
            Không có kèo giao lưu nào khớp với bộ lọc hiện tại. Bạn có thể đặt lại bộ lọc hoặc tự khởi tạo một kèo mới ngay bây giờ!
          </p>
          <div class="empty-state-actions">
            <button class="btn-reset-filters" @click="resetFilters">Đặt lại bộ lọc</button>
            <RouterLink to="/matchmaking/create" class="btn-create-match">Tạo Kèo Mới Ngay</RouterLink>
          </div>
        </div>
      </section>

      <!-- ================= TAB 2: GIẢI ĐẤU & XẾP HẠNG ================= -->
      <section v-else-if="activeTab === 'tournaments'" class="tab-content">
        <div class="page-header-row">
          <div class="header-text-block">
            <span class="header-eyebrow">Aura League & Tournaments</span>
            <h1 class="page-main-title">Giải Đấu & Scoreboard</h1>
            <p class="page-subtitle">
              Tạo giải đấu phân nhánh tự động, quản lý bracket trực quan và cập nhật tỷ số trận đấu theo chuẩn đồng bộ phiên bản.
            </p>
          </div>
          <RouterLink to="/tournaments" class="btn-create-match flex items-center gap-2">
            <span class="material-symbols-outlined icon">emoji_events</span>
            <span>Màn Hình Giải Đấu Stitch</span>
          </RouterLink>
        </div>

        <div class="tournament-layout-grid">
          <!-- Form Tạo Giải Đấu -->
          <div class="aura-card tournament-form-card">
            <div class="card-section-title">
              <span class="material-symbols-outlined icon">add_circle</span>
              <h2>Khởi Tạo Giải Đấu Mới</h2>
            </div>

            <form class="aura-form" @submit.prevent="createTournament">
              <div class="form-group">
                <label class="form-label">Tên giải đấu</label>
                <input
                  v-model="tournamentForm.name"
                  type="text"
                  class="form-input"
                  placeholder="Ví dụ: Aura Summer Open 2026"
                  required
                />
              </div>

              <div class="form-group">
                <label class="form-label">Thể thức thi đấu</label>
                <select v-model="tournamentForm.format" class="form-input">
                  <option value="single_elimination">Single Elimination (Loại trực tiếp)</option>
                  <option value="round_robin">Round Robin (Vòng tròn tính điểm)</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">ID Vận động viên tham gia (cách nhau dấu phẩy)</label>
                <input
                  v-model="tournamentForm.participant_ids"
                  type="text"
                  class="form-input"
                  placeholder="101, 102, 103, 104"
                  required
                />
                <span class="form-hint">Nhập tối thiểu 4 ID người chơi để tạo nhánh đấu tự động.</span>
              </div>

              <button class="btn-submit-aura" :disabled="processing">
                <span>Khởi Tạo Phân Nhánh</span>
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </form>
          </div>

          <!-- Danh sách giải đấu hiện có -->
          <div class="aura-card tournament-list-card">
            <div class="card-section-title">
              <span class="material-symbols-outlined icon">emoji_events</span>
              <h2>Các Giải Đấu Đang Diễn Ra</h2>
            </div>

            <div v-if="tournaments.length > 0" class="tournament-items-list">
              <div
                v-for="t in tournaments"
                :key="t.id"
                class="tournament-item"
                :class="{ selected: selectedTournament?.id === t.id }"
              >
                <div class="t-item-info">
                  <h4 class="t-name">{{ t.name }}</h4>
                  <p class="t-meta">
                    {{ t.format === 'single_elimination' ? 'Loại trực tiếp' : 'Vòng tròn' }} · Phiên bản v{{ t.version }} · Trạng thái: {{ t.status }}
                  </p>
                </div>
                <button class="btn-open-bracket" @click="openTournament(t)">
                  Mở Scoreboard
                </button>
              </div>
            </div>

            <div v-else class="tournament-empty">
              <p class="text-on-surface-variant">Chưa có giải đấu nào được kích hoạt. Hãy tạo giải đấu đầu tiên ở bên trái.</p>
            </div>
          </div>
        </div>

        <!-- Chi tiết Bracket & Scoreboard của giải được chọn -->
        <div v-if="selectedTournament" class="aura-card bracket-view-card">
          <div class="bracket-header">
            <div>
              <span class="header-eyebrow">Scoreboard Trực Tuyến</span>
              <h2 class="bracket-title">{{ selectedTournament.name }} (v{{ selectedTournament.version }})</h2>
            </div>
            <span class="badge-pill badge-public">{{ selectedTournament.format }}</span>
          </div>

          <div class="fixtures-grid">
            <div
              v-for="fixture in selectedTournament.fixtures"
              :key="fixture.id"
              class="fixture-item"
            >
              <div class="fixture-meta">
                <span class="round-badge">Vòng {{ fixture.round }}</span>
                <span class="position-tag">Trận #{{ fixture.position }}</span>
                <span class="status-pill">{{ fixture.status }}</span>
              </div>

              <div class="fixture-players">
                <div class="player-slot" :class="{ winner: fixture.winner_id === fixture.participant_one_id }">
                  <span class="player-name">VĐV #{{ fixture.participant_one_id || 'TBD' }}</span>
                </div>
                <div class="vs-text">VS</div>
                <div class="player-slot" :class="{ winner: fixture.winner_id === fixture.participant_two_id }">
                  <span class="player-name">VĐV #{{ fixture.participant_two_id || 'TBD' }}</span>
                </div>
              </div>

              <div v-if="fixture.status === 'pending'" class="fixture-actions">
                <button class="btn-score-fixture" @click="scoreFixture(fixture)">
                  Ghi Điểm & Thắng
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= TAB 3: HỒ SƠ VĐV & ELO ================= -->
      <section v-else-if="activeTab === 'profile'" class="tab-content">
        <div class="page-header-row">
          <div class="header-text-block">
            <span class="header-eyebrow">Player Reputation & Elo</span>
            <h1 class="page-main-title">Hồ Sơ Vận Động Viên</h1>
            <p class="page-subtitle">
              Quản lý trình độ kỹ năng cá nhân, vị trí thi đấu yêu thích và theo dõi điểm uy tín thể thao tại Aura Court.
            </p>
          </div>
        </div>

        <div class="profile-layout-grid">
          <!-- Thẻ chỉ số VĐV -->
          <div class="aura-card athlete-stats-card">
            <div class="athlete-header-row">
              <div class="athlete-avatar-lg">
                <img
                  :src="getAvatarUrl(auth.user?.name, auth.user?.avatar_url)"
                  alt="Avatar"
                  class="w-full h-full object-cover"
                />
              </div>
              <div class="athlete-main-meta">
                <h3 class="athlete-name">{{ auth.user?.name || 'Alex Nguyễn' }}</h3>
                <span class="athlete-level-badge">{{ getSkillName(profile.skill_level) }}</span>
                <p class="athlete-city">{{ profile.city || 'TP. Hồ Chí Minh' }}</p>
              </div>
            </div>

            <div class="reputation-box">
              <div class="rep-header">
                <span class="rep-label">Điểm Uy Tín Thể Thao (Reputation)</span>
                <span class="rep-value">{{ profile.reputation_score }}/100</span>
              </div>
              <div class="rep-progress-track">
                <div class="rep-progress-fill" :style="{ width: `${profile.reputation_score}%` }"></div>
              </div>
              <p class="rep-hint">Điểm uy tín cao giúp bạn được ưu tiên duyệt vào các kèo đấu Private và giải đấu chính thức.</p>
            </div>
          </div>

          <!-- Form cập nhật hồ sơ -->
          <div class="aura-card profile-form-card">
            <div class="card-section-title">
              <span class="material-symbols-outlined icon">edit_note</span>
              <h2>Cập Nhật Thông Số Kỹ Năng</h2>
            </div>

            <form class="aura-form" @submit.prevent="saveProfile">
              <div class="form-group">
                <label class="form-label">
                  <span>Trình độ kỹ năng (Skill Level):</span>
                  <strong class="text-primary">{{ getSkillName(profile.skill_level) }}</strong>
                </label>
                <input
                  v-model.number="profile.skill_level"
                  type="range"
                  min="1"
                  max="5"
                  class="aura-slider"
                />
                <div class="slider-labels">
                  <span>Level 1 (Beginner)</span>
                  <span>Level 3 (Inter)</span>
                  <span>Level 5 (Pro)</span>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Khu vực / Thành phố</label>
                <input
                  v-model="profile.city"
                  type="text"
                  class="form-input"
                  placeholder="Ví dụ: Quận 1, TP. Hồ Chí Minh"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Vị trí sở trường</label>
                <div class="tags-selector">
                  <span
                    v-for="pos in ['Đơn nam', 'Đơn nữ', 'Đôi nam', 'Đôi nữ', 'Đôi nam nữ']"
                    :key="pos"
                    class="tag-chip"
                    :class="{ active: profile.preferred_positions.includes(pos) }"
                    @click="
                      profile.preferred_positions.includes(pos)
                        ? profile.preferred_positions = profile.preferred_positions.filter(p => p !== pos)
                        : profile.preferred_positions.push(pos)
                    "
                  >
                    {{ pos }}
                  </span>
                </div>
              </div>

              <button class="btn-submit-aura" :disabled="processing">
                <span>Lưu Thay Đổi Hồ Sơ</span>
                <span class="material-symbols-outlined">save</span>
              </button>
            </form>
          </div>
        </div>
      </section>
    </div>

    <!-- ================= MODAL 1: TẠO KÈO MỚI (STITCH DESIGN) ================= -->
    <div v-if="isCreateModalOpen" class="modal-backdrop" @click.self="closeCreateModal">
      <div class="modal-container-lg">
        <div class="modal-header">
          <div class="modal-header-text">
            <span class="text-label-sm uppercase tracking-widest text-primary font-semibold">Initiate Play</span>
            <h2 class="text-headline-lg font-bold text-on-surface">Tạo Kèo Giao Lưu Mới</h2>
            <p class="text-body-md text-on-surface-variant">Thiết lập thông số trận đấu. Hệ thống sẽ kết nối những người chơi phù hợp nhất.</p>
          </div>
          <button class="modal-close-btn" @click="closeCreateModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body-form" @submit.prevent="handleCreateMatch">
          <!-- PHẦN 1: VENUE & TIMING -->
          <div class="modal-form-section">
            <div class="section-title-row">
              <div class="section-icon-badge">
                <span class="material-symbols-outlined text-primary">location_on</span>
              </div>
              <h3 class="section-title">Địa Điểm & Thời Gian</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-lg mt-md">
              <div class="form-group">
                <label class="form-label">Chọn Sân / Cơ Sở</label>
                <div class="relative">
                  <input
                    v-model="matchForm.title"
                    type="text"
                    class="form-input-minimal pr-10"
                    placeholder="Tìm kiếm sân hoặc chọn gần đây..."
                    required
                  />
                  <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                </div>
                <!-- Recent chips -->
                <div class="flex flex-wrap gap-xs mt-2">
                  <button
                    type="button"
                    class="quick-venue-chip"
                    @click="matchForm.title = 'Aura Central Court - Sân VIP 1'"
                  >
                    Aura Center - Sân 1
                  </button>
                  <button
                    type="button"
                    class="quick-venue-chip"
                    @click="matchForm.title = 'Aura East Court - Sân C4'"
                  >
                    Aura East - Sân C4
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Ngày & Giờ Thi Đấu</label>
                <div class="flex gap-sm">
                  <input
                    v-model="matchForm.starts_date"
                    type="date"
                    class="form-input-minimal flex-1"
                    required
                  />
                  <input
                    v-model="matchForm.starts_time"
                    type="time"
                    class="form-input-minimal w-28"
                    required
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="section-separator"></div>

          <!-- PHẦN 2: MATCH PARAMETERS -->
          <div class="modal-form-section">
            <div class="section-title-row">
              <div class="section-icon-badge">
                <span class="material-symbols-outlined text-primary">tune</span>
              </div>
              <h3 class="section-title">Thông Số Trận Đấu</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-lg mt-md">
              <!-- Trình độ -->
              <div class="form-group">
                <div class="flex justify-between items-center mb-1">
                  <label class="form-label mb-0">Yêu cầu trình độ</label>
                  <span class="text-label-sm font-semibold text-primary">
                    {{ getSkillName(matchForm.skill_level) }}
                  </span>
                </div>
                <input
                  v-model.number="matchForm.skill_level"
                  type="range"
                  min="1"
                  max="4"
                  class="aura-slider"
                />
                <div class="flex justify-between text-xs text-on-surface-variant/70 mt-1">
                  <span>Beginner</span>
                  <span>Pro</span>
                </div>
              </div>

              <!-- Số lượng người cần tìm -->
              <div class="form-group">
                <label class="form-label">Tổng số người cần (Players Needed)</label>
                <div class="stepper-box">
                  <button
                    type="button"
                    class="stepper-btn"
                    @click="matchForm.required_participants = Math.max(2, matchForm.required_participants - 1)"
                  >
                    <span class="material-symbols-outlined">remove</span>
                  </button>
                  <span class="stepper-value">{{ matchForm.required_participants }}</span>
                  <button
                    type="button"
                    class="stepper-btn"
                    @click="matchForm.required_participants = Math.min(8, matchForm.required_participants + 1)"
                  >
                    <span class="material-symbols-outlined">add</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Public vs Private Radio Boxes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-md mt-md">
              <label
                class="radio-option-card"
                :class="{ checked: matchForm.visibility === 'public' }"
              >
                <input
                  v-model="matchForm.visibility"
                  type="radio"
                  value="public"
                  class="sr-only"
                />
                <div class="radio-card-content">
                  <div class="flex justify-between items-center">
                    <span class="font-semibold text-on-surface">Kèo Công Khai (Public Match)</span>
                    <span class="material-symbols-outlined text-primary">public</span>
                  </div>
                  <span class="text-xs text-on-surface-variant">Hiển thị trực tiếp trên bảng tìm kèo công cộng.</span>
                </div>
              </label>

              <label
                class="radio-option-card"
                :class="{ checked: matchForm.visibility === 'invite_only' }"
              >
                <input
                  v-model="matchForm.visibility"
                  type="radio"
                  value="invite_only"
                  class="sr-only"
                />
                <div class="radio-card-content">
                  <div class="flex justify-between items-center">
                    <span class="font-semibold text-on-surface">Kèo Kín (Private Invite)</span>
                    <span class="material-symbols-outlined text-primary">lock</span>
                  </div>
                  <span class="text-xs text-on-surface-variant">Chỉ người có link mời hoặc được tick chọn mới được vào.</span>
                </div>
              </label>
            </div>

            <!-- Auto matchmaking toggle -->
            <div class="auto-match-toggle-row">
              <div>
                <div class="font-semibold text-on-surface">Tự Động Ghép Đôi (Automatic Matchmaking)</div>
                <div class="text-xs text-on-surface-variant">Tự động duyệt người tham gia nếu họ đáp ứng đủ trình độ yêu cầu.</div>
              </div>
              <label class="switch-control">
                <input v-model="matchForm.auto_matchmaking" type="checkbox" />
                <span class="switch-slider"></span>
              </label>
            </div>
          </div>

          <div class="section-separator"></div>

          <!-- PHẦN 3: INVITE NETWORK -->
          <div class="modal-form-section">
            <div class="flex justify-between items-center">
              <div class="section-title-row">
                <div class="section-icon-badge">
                  <span class="material-symbols-outlined text-primary">group_add</span>
                </div>
                <h3 class="section-title">Mạng Lưới Bạn Bè (Invite Network)</h3>
              </div>
              <span class="text-label-sm text-primary">Tick chọn để gửi lời mời</span>
            </div>

            <div class="invite-network-scroll hide-scrollbar">
              <div
                v-for="partner in inviteNetwork"
                :key="partner.id"
                class="partner-card"
                :class="{ selected: partner.selected }"
                @click="togglePartner(partner)"
              >
                <div class="partner-avatar-wrapper">
                  <img :src="partner.avatar" :alt="partner.name" class="partner-avatar-img" />
                </div>
                <div class="partner-name">{{ partner.name }}</div>
                <div class="partner-level">{{ partner.level }}</div>

                <div v-if="partner.selected" class="partner-check-icon">
                  <span class="material-symbols-outlined text-[14px]">check</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Action Footer -->
          <div class="modal-actions-footer">
            <button
              type="button"
              class="btn-cancel-modal"
              @click="closeCreateModal"
            >
              Hủy
            </button>
            <button
              type="submit"
              class="btn-submit-modal"
              :disabled="processing"
            >
              <span>Khởi Tạo Kèo Ngay</span>
              <span class="material-symbols-outlined">arrow_forward</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL 2: CHI TIẾT KÈO ĐẤU ================= -->
    <div v-if="isDetailModalOpen && selectedMatch" class="modal-backdrop" @click.self="closeDetailModal">
      <div class="modal-container-md">
        <div class="modal-header">
          <div class="modal-header-text">
            <span class="badge-pill badge-public mb-1">{{ selectedMatch.visibility === 'public' ? 'Public' : 'Private Invite' }}</span>
            <h2 class="text-headline-md font-bold text-on-surface">{{ selectedMatch.title }}</h2>
          </div>
          <button class="modal-close-btn" @click="closeDetailModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-detail">
          <div class="detail-info-grid">
            <div class="detail-item">
              <span class="material-symbols-outlined text-primary">location_on</span>
              <div>
                <div class="detail-label">Địa điểm</div>
                <div class="detail-val">{{ selectedMatch.venue_name || 'Aura Court' }} · {{ selectedMatch.city || 'TP. Hồ Chí Minh' }}</div>
              </div>
            </div>

            <div class="detail-item">
              <span class="material-symbols-outlined text-primary">calendar_today</span>
              <div>
                <div class="detail-label">Thời gian thi đấu</div>
                <div class="detail-val">{{ formatDate(selectedMatch.starts_at) }}</div>
              </div>
            </div>

            <div class="detail-item">
              <span class="material-symbols-outlined text-primary">leaderboard</span>
              <div>
                <div class="detail-label">Yêu cầu trình độ</div>
                <div class="detail-val">{{ getSkillLabel(selectedMatch.min_skill) }}</div>
              </div>
            </div>

            <div class="detail-item">
              <span class="material-symbols-outlined text-primary">group</span>
              <div>
                <div class="detail-label">Số lượng thành viên</div>
                <div class="detail-val font-semibold text-primary">
                  {{ selectedMatch.joined_count }} / {{ selectedMatch.required_participants }} người đã tham gia
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedMatch.description" class="detail-desc-box">
            <h4 class="font-semibold text-on-surface mb-1">Ghi chú từ người tạo kèo:</h4>
            <p class="text-body-md text-on-surface-variant">{{ selectedMatch.description }}</p>
          </div>

          <!-- Danh sách người tham gia -->
          <div class="detail-participants-section">
            <h4 class="font-semibold text-on-surface mb-sm">Danh sách thành viên:</h4>
            <div class="participants-list">
              <div
                v-for="p in selectedMatch.participants || []"
                :key="p.id"
                class="participant-row"
              >
                <img :src="getAvatarUrl(p.name, p.avatar)" :alt="p.name" class="p-avatar" />
                <div class="p-info">
                  <div class="p-name">{{ p.name }}</div>
                  <div class="p-skill">Trình độ: {{ getSkillLabel(p.skill_level) }}</div>
                </div>
                <span v-if="p.is_host" class="badge-host">Host</span>
              </div>
            </div>
          </div>

          <!-- Action -->
          <div class="modal-actions-footer mt-lg">
            <button class="btn-cancel-modal" @click="closeDetailModal">Đóng</button>
            <button
              v-if="selectedMatch.joined_count < selectedMatch.required_participants"
              class="btn-submit-modal"
              :disabled="processing"
              @click="joinMatch(selectedMatch)"
            >
              <span>Tham Gia Kèo Này</span>
              <span class="material-symbols-outlined">sports_tennis</span>
            </button>
            <button v-else class="btn-card-full" disabled>
              Kèo Đã Đầy Chỗ
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.matchmaking-page {
  position: relative;
  min-height: 100vh;
  background-color: var(--bg, #f8f9fa);
  color: var(--on-surface, #191c1d);
  padding-bottom: 80px;
}

.glow-bg-top {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 480px;
  background: radial-gradient(circle at 50% 0%, rgba(255, 209, 220, 0.35) 0%, rgba(248, 249, 250, 0) 70%);
  pointer-events: none;
  z-index: 0;
}

.page-container {
  position: relative;
  z-index: 1;
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 24px 64px;
}

@media (min-width: 1024px) {
  .page-container {
    padding: 32px 48px 80px;
  }
}

/* Sub Navigation Tabs */
.subnav-tabs-wrapper {
  display: flex;
  justify-content: center;
  margin-bottom: 32px;
}

.subnav-tabs {
  display: inline-flex;
  gap: 8px;
  padding: 6px;
  background: var(--surface-container, #edeeef);
  border-radius: 9999px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.subnav-tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 9999px;
  color: var(--on-surface-variant, #4f4446);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.25s ease;
}

.subnav-tab-btn .icon {
  font-size: 18px;
}

.subnav-tab-btn:hover {
  color: var(--primary, #78555e);
}

.subnav-tab-btn.active {
  background: #ffffff;
  color: var(--primary, #78555e);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

/* Alerts */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  font-size: 14px;
  font-weight: 500;
}

.alert-success {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #c8e6c9;
}

.alert-danger {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

/* Page Header Row */
.page-header-row {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 32px;
}

@media (min-width: 768px) {
  .page-header-row {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-end;
  }
}

.header-eyebrow {
  display: block;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--primary, #78555e);
  margin-bottom: 6px;
}

.page-main-title {
  font-size: 36px;
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -0.02em;
  color: var(--on-surface, #191c1d);
  margin: 0 0 10px;
}

@media (min-width: 768px) {
  .page-main-title {
    font-size: 44px;
  }
}

.page-subtitle {
  font-size: 16px;
  line-height: 1.6;
  color: var(--on-surface-variant, #4f4446);
  max-width: 650px;
  margin: 0;
}

.btn-create-match {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  border-radius: 9999px;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
}

.btn-create-match:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.35);
  background: #684850;
}

/* Filter Bar */
.filters-bar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 32px;
}

.search-input-box {
  position: relative;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--on-surface-variant, #4f4446);
  font-size: 20px;
}

.filter-search-field {
  width: 100%;
  height: 48px;
  padding: 0 44px;
  background: #ffffff;
  border: 1px solid var(--outline-variant, #d3c3c5);
  border-radius: 9999px;
  font-size: 15px;
  color: var(--on-surface, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}

.filter-search-field:focus {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 3px rgba(255, 209, 220, 0.4);
}

.clear-search-btn {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
}

.filter-chips-scroll {
  display: flex;
  align-items: center;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 8px;
}

.filter-dropdown-chip {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: var(--surface-container, #edeeef);
  border-radius: 9999px;
  flex-shrink: 0;
  transition: background 0.2s;
}

.filter-dropdown-chip:hover {
  background: var(--surface-container-high, #e7e8e9);
}

.chip-icon {
  font-size: 18px;
  color: var(--primary, #78555e);
}

.chip-select {
  appearance: none;
  background: transparent;
  border: none;
  font-size: 14px;
  font-weight: 500;
  color: var(--on-surface, #191c1d);
  padding-right: 18px;
  cursor: pointer;
  outline: none;
}

.chip-arrow {
  position: absolute;
  right: 10px;
  font-size: 16px;
  color: var(--on-surface-variant, #4f4446);
  pointer-events: none;
}

.filter-divider {
  width: 1px;
  height: 24px;
  background: var(--outline-variant, #d3c3c5);
  margin: 0 4px;
  flex-shrink: 0;
}

.btn-reset-filters {
  background: transparent;
  border: none;
  color: var(--primary, #78555e);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-reset-filters:hover {
  text-decoration: underline;
}

/* Matches Grid */
.matches-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .matches-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Match Card */
.match-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

.match-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
  border-color: rgba(255, 209, 220, 0.8);
}

.card-gradient-top {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary, #78555e) 0%, rgba(255, 209, 220, 0.8) 50%, transparent 100%);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.match-card:hover .card-gradient-top {
  opacity: 1;
}

.card-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.host-info-flex {
  display: flex;
  align-items: center;
  gap: 14px;
}

.host-avatar-ring {
  width: 52px;
  height: 52px;
  border-radius: 9999px;
  overflow: hidden;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
}

.host-avatar-img {
  width: 100%;
  height: 100%;
  object-cover: cover;
}

.court-location-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.court-venue-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

.match-time-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--on-surface-variant, #4f4446);
  margin: 0;
}

.icon-time {
  font-size: 16px;
}

.badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 9999px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

.badge-public {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

.badge-private {
  background: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface-variant, #4f4446);
}

.match-desc-snippet {
  font-size: 14px;
  line-height: 1.5;
  color: var(--on-surface-variant, #4f4446);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-meta-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--outline-variant, #e1e3e4);
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.icon-meta {
  font-size: 18px;
  color: var(--on-surface-variant, #4f4446);
}

.meta-text {
  font-size: 13px;
  font-weight: 500;
  color: var(--on-surface-variant, #4f4446);
}

.meta-separator {
  width: 1px;
  height: 16px;
  background: var(--outline-variant, #d3c3c5);
}

.card-actions-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 4px;
}

.btn-card-secondary {
  padding: 10px 16px;
  background: var(--surface-container, #edeeef);
  color: var(--on-surface, #191c1d);
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-card-secondary:hover {
  background: var(--surface-container-high, #e7e8e9);
}

.btn-card-primary {
  padding: 10px 16px;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-card-primary:hover {
  background: #684850;
}

.btn-card-full {
  padding: 10px 16px;
  background: var(--surface-container-high, #e7e8e9);
  color: var(--on-surface-variant, #4f4446);
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  border: none;
  cursor: not-allowed;
  opacity: 0.7;
}

/* Empty State */
.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 48px 24px;
  background: #ffffff;
  border-radius: 16px;
  border: 1px dashed var(--outline-variant, #d3c3c5);
  margin-top: 24px;
}

.empty-state-icon {
  width: 64px;
  height: 64px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  margin-bottom: 16px;
}

.empty-state-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0 0 8px;
}

.empty-state-desc {
  font-size: 15px;
  color: var(--on-surface-variant, #4f4446);
  max-width: 480px;
  margin: 0 0 24px;
}

.empty-state-actions {
  display: flex;
  gap: 16px;
}

/* Aura Card General */
.aura-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.card-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
}

.card-section-title .icon {
  font-size: 24px;
  color: var(--primary, #78555e);
}

.card-section-title h2 {
  font-size: 20px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

/* Aura Form */
.aura-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

.form-input {
  height: 46px;
  padding: 0 14px;
  background: var(--surface-bright, #f8f9fa);
  border: 1px solid var(--outline-variant, #d3c3c5);
  border-radius: 8px;
  font-size: 14px;
  color: var(--on-surface, #191c1d);
  outline: none;
  transition: all 0.2s;
}

.form-input:focus {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 3px rgba(255, 209, 220, 0.4);
}

.form-hint {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.btn-submit-aura {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-submit-aura:hover {
  background: #684850;
}

/* Tournament Grid Layout */
.tournament-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  margin-bottom: 28px;
}

@media (min-width: 1024px) {
  .tournament-layout-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.tournament-items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tournament-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px;
  background: var(--surface-bright, #f8f9fa);
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #e1e3e4);
}

.t-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0 0 4px;
}

.t-meta {
  font-size: 13px;
  color: var(--on-surface-variant, #4f4446);
  margin: 0;
}

.btn-open-bracket {
  padding: 8px 14px;
  background: #ffffff;
  border: 1px solid var(--outline-variant, #d3c3c5);
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary, #78555e);
  cursor: pointer;
}

.btn-open-bracket:hover {
  background: var(--primary-container, #ffd1dc);
}

/* Bracket View Card */
.bracket-view-card {
  margin-top: 24px;
}

.bracket-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.bracket-title {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
}

.fixtures-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.fixture-item {
  padding: 16px;
  background: var(--surface-bright, #f8f9fa);
  border-radius: 10px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.fixture-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
}

.round-badge {
  color: var(--primary, #78555e);
}

.fixture-players {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.player-slot {
  padding: 8px 12px;
  background: #ffffff;
  border-radius: 6px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  font-size: 13px;
  font-weight: 600;
}

.player-slot.winner {
  border-color: var(--primary, #78555e);
  background: rgba(255, 209, 220, 0.2);
}

.vs-text {
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  color: var(--on-surface-variant, #4f4446);
}

.btn-score-fixture {
  width: 100%;
  padding: 8px;
  background: var(--primary, #78555e);
  color: #ffffff;
  border-radius: 6px;
  border: none;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

/* Profile Layout Grid */
.profile-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .profile-layout-grid {
    grid-template-columns: 360px 1fr;
  }
}

.athlete-header-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.athlete-avatar-lg {
  width: 72px;
  height: 72px;
  border-radius: 9999px;
  overflow: hidden;
  border: 3px solid var(--primary-container, #ffd1dc);
}

.athlete-name {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 4px;
}

.athlete-level-badge {
  display: inline-block;
  padding: 3px 10px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 12px;
  font-weight: 600;
  border-radius: 9999px;
  margin-bottom: 4px;
}

.athlete-city {
  font-size: 13px;
  color: var(--on-surface-variant, #4f4446);
  margin: 0;
}

.reputation-box {
  padding: 16px;
  background: var(--surface-bright, #f8f9fa);
  border-radius: 12px;
  border: 1px solid var(--outline-variant, #e1e3e4);
}

.rep-header {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 8px;
}

.rep-value {
  color: var(--primary, #78555e);
  font-weight: 700;
}

.rep-progress-track {
  height: 8px;
  background: var(--surface-container-high, #e7e8e9);
  border-radius: 9999px;
  overflow: hidden;
  margin-bottom: 8px;
}

.rep-progress-fill {
  height: 100%;
  background: var(--primary, #78555e);
  border-radius: 9999px;
}

.rep-hint {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
  margin: 0;
  line-height: 1.4;
}

.aura-slider {
  width: 100%;
  accent-color: var(--primary, #78555e);
  cursor: pointer;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.tags-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-chip {
  padding: 6px 14px;
  background: var(--surface-bright, #f8f9fa);
  border: 1px solid var(--outline-variant, #d3c3c5);
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.tag-chip.active {
  background: var(--primary-container, #ffd1dc);
  border-color: var(--primary, #78555e);
  color: var(--on-primary-container, #7a5761);
  font-weight: 600;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-container-lg {
  width: 100%;
  max-width: 800px;
  max-height: 90vh;
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  overflow-y: auto;
  padding: 32px;
}

.modal-container-md {
  width: 100%;
  max-width: 580px;
  max-height: 90vh;
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  overflow-y: auto;
  padding: 32px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 20px;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
  padding: 4px;
}

.modal-body-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.modal-form-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-icon-badge {
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  display: flex;
  align-items: center;
  justify-content: center;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--on-surface, #191c1d);
  margin: 0;
}

.section-separator {
  height: 1px;
  background: var(--outline-variant, #e1e3e4);
  width: 100%;
}

.form-input-minimal {
  width: 100%;
  padding: 10px 0;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--surface-container-high, #e7e8e9);
  font-size: 15px;
  color: var(--on-surface, #191c1d);
  outline: none;
  transition: border-color 0.2s;
}

.form-input-minimal:focus {
  border-color: var(--primary, #78555e);
}

.quick-venue-chip {
  padding: 4px 12px;
  background: var(--surface-container, #edeeef);
  border-radius: 9999px;
  border: none;
  font-size: 12px;
  font-weight: 500;
  color: var(--on-surface-variant, #4f4446);
  cursor: pointer;
  transition: all 0.2s;
}

.quick-venue-chip:hover {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

.stepper-box {
  display: flex;
  align-items: center;
  gap: 12px;
}

.stepper-btn {
  width: 44px;
  height: 44px;
  border-radius: 9999px;
  background: var(--surface-container, #edeeef);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s;
}

.stepper-btn:hover {
  background: var(--surface-container-high, #e7e8e9);
}

.stepper-value {
  font-size: 22px;
  font-weight: 700;
  min-width: 40px;
  text-align: center;
}

.radio-option-card {
  display: flex;
  padding: 16px;
  background: #ffffff;
  border: 1px solid var(--outline-variant, #d3c3c5);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.radio-option-card.checked {
  border-color: var(--primary, #78555e);
  background: rgba(255, 209, 220, 0.15);
}

.radio-card-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}

.auto-match-toggle-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: var(--surface-bright, #f8f9fa);
  border-radius: 12px;
  margin-top: 8px;
}

.switch-control {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
  flex-shrink: 0;
}

.switch-control input {
  opacity: 0;
  width: 0;
  height: 0;
}

.switch-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background-color: #ccc;
  transition: 0.3s;
  border-radius: 26px;
}

.switch-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .switch-slider {
  background-color: var(--primary, #78555e);
}

input:checked + .switch-slider:before {
  transform: translateX(22px);
}

/* Invite Network */
.invite-network-scroll {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding: 6px 2px 14px;
}

.partner-card {
  position: relative;
  min-width: 130px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 12px;
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid var(--outline-variant, #e1e3e4);
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.partner-card:hover {
  border-color: var(--primary, #78555e);
}

.partner-card.selected {
  border-color: var(--primary, #78555e);
  background: rgba(255, 209, 220, 0.15);
}

.partner-avatar-wrapper {
  width: 56px;
  height: 56px;
  border-radius: 9999px;
  overflow: hidden;
}

.partner-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.partner-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
  text-align: center;
}

.partner-level {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
  text-align: center;
}

.partner-check-icon {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 20px;
  height: 20px;
  border-radius: 9999px;
  background: var(--primary, #78555e);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-actions-footer {
  display: flex;
  justify-content: flex-end;
  gap: 14px;
  padding-top: 16px;
}

.btn-cancel-modal {
  padding: 10px 22px;
  background: transparent;
  border: 1px solid var(--outline-variant, #d3c3c5);
  border-radius: 9999px;
  font-size: 14px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
  cursor: pointer;
}

.btn-submit-modal {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  background: var(--primary, #78555e);
  border: none;
  border-radius: 9999px;
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.2s;
}

.btn-submit-modal:hover {
  background: #684850;
}

/* Detail Modal Styles */
.detail-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  padding: 16px;
  background: var(--surface-bright, #f8f9fa);
  border-radius: 12px;
  margin-bottom: 16px;
}

.detail-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.detail-label {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.detail-val {
  font-size: 14px;
  font-weight: 600;
  color: var(--on-surface, #191c1d);
}

.detail-desc-box {
  padding: 14px;
  background: rgba(255, 209, 220, 0.15);
  border-radius: 10px;
  border-left: 3px solid var(--primary, #78555e);
  margin-bottom: 16px;
}

.participants-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.participant-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--surface-bright, #f8f9fa);
  border-radius: 8px;
}

.p-avatar {
  width: 36px;
  height: 36px;
  border-radius: 9999px;
  object-fit: cover;
}

.p-info {
  flex: 1;
}

.p-name {
  font-size: 14px;
  font-weight: 600;
}

.p-skill {
  font-size: 12px;
  color: var(--on-surface-variant, #4f4446);
}

.badge-host {
  padding: 2px 8px;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
  font-size: 11px;
  font-weight: 700;
  border-radius: 4px;
  text-transform: uppercase;
}

/* Animations */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* The page contains a few intentionally white legacy surfaces; map them to
   the dark theme so cards, forms and modals keep the same visual hierarchy. */
:global(html.dark .matchmaking-page .subnav-tab-btn.active),
:global(html.dark .matchmaking-page .filter-search-field),
:global(html.dark .matchmaking-page .match-card),
:global(html.dark .matchmaking-page .empty-state-card),
:global(html.dark .matchmaking-page .aura-card),
:global(html.dark .matchmaking-page .btn-open-bracket),
:global(html.dark .matchmaking-page .player-slot),
:global(html.dark .matchmaking-page .modal-container-lg),
:global(html.dark .matchmaking-page .modal-container-md),
:global(html.dark .matchmaking-page .radio-option-card),
:global(html.dark .matchmaking-page .partner-card) {
  background: var(--surface, #22242a);
}

:global(html.dark .matchmaking-page .btn-create-match),
:global(html.dark .matchmaking-page .btn-card-primary),
:global(html.dark .matchmaking-page .btn-submit-aura),
:global(html.dark .matchmaking-page .btn-score-fixture),
:global(html.dark .matchmaking-page .btn-submit-modal),
:global(html.dark .matchmaking-page .partner-check-icon) {
  color: var(--on-primary, #201317);
}
</style>
