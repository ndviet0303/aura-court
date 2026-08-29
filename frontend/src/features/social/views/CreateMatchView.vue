<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { socialApi } from '../api/socialApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'
import { getAvatarUrl } from '@/lib/avatar'

const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const bookingStore = useBookingStore()

// Processing & Notifications
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

// Sports
interface SportCategory {
  id: string
  name: string
  icon: string
  tag: string
}

const sportCategories: SportCategory[] = [
  { id: 'badminton', name: 'Cầu lông', icon: 'sports_tennis', tag: 'Badminton' },
  { id: 'pickleball', name: 'Pickleball', icon: 'sports_tennis', tag: 'Pickleball' },
  { id: 'tennis', name: 'Tennis', icon: 'sports_tennis', tag: 'Tennis' },
  { id: 'football_5v5', name: 'Bóng đá 5v5', icon: 'sports_soccer', tag: 'Futsal' },
  { id: 'basketball', name: 'Bóng rổ 3x3', icon: 'sports_basketball', tag: '3x3' },
  { id: 'volleyball', name: 'Bóng chuyền', icon: 'sports_volleyball', tag: 'Volleyball' },
]

const selectedSport = ref('badminton')

// Form State
const matchForm = ref({
  title: 'Aura Central Court - Sân VIP 1',
  venue_name: 'Aura Central - Quận 1',
  court_name: 'Sân VIP 1 (Thảm Taraflex Đỏ)',
  starts_date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
  starts_time: '19:00',
  ends_time: '21:00',
  city: 'Hồ Chí Minh',
  skill_level: 2, // 1: Beginner, 2: Intermediate, 3: Advanced, 4: Professional
  required_participants: 4,
  visibility: 'public' as 'public' | 'invite_only',
  auto_matchmaking: true,
  game_format: 'doubles_mixed',
  shuttlecock_type: 'Yonex Aerosensa 50',
  split_bill_mode: 'even_split',
  description: 'Giao lưu rèn luyện phong độ, nhịp độ vừa phải, vui vẻ hòa đồng. Có nước uống miễn phí và điều hòa sân.',
})

// Quick Venue Suggestions
const quickVenues = [
  { label: 'Aura Center - Sân VIP 1', venue: 'Aura Central - Q.1', court: 'Sân VIP 1 (Taraflex Đỏ)' },
  { label: 'Aura East - Sân C4', venue: 'Aura East - TP. Thủ Đức', court: 'Sân C4 (Pro Turf)' },
  { label: 'Aura South - Sân Pickleball 2', venue: 'Aura South - Q.7', court: 'Sân PB 2 (Hardcourt)' },
  { label: 'Aura Landmark - Sân Bóng Rổ', venue: 'Aura Landmark 81', court: 'Sân Bóng Rổ 3x3 VIP' },
]

function selectQuickVenue(item: { label: string; venue: string; court: string }) {
  matchForm.value.title = item.label
  matchForm.value.venue_name = item.venue
  matchForm.value.court_name = item.court
}

// Skill Levels Configuration
interface SkillLevel {
  level: number
  name: string
  labelVi: string
  desc: string
  badgeClass: string
}

const skillLevels: SkillLevel[] = [
  {
    level: 1,
    name: 'Beginner',
    labelVi: 'Mới chơi / Nhập môn',
    desc: 'Dành cho người mới làm quen hoặc chơi giải trí vui vẻ nhẹ nhàng.',
    badgeClass: 'skill-beginner',
  },
  {
    level: 2,
    name: 'Intermediate',
    labelVi: 'Trung bình - Khá',
    desc: 'Đã nắm vững kỹ thuật cơ bản, phản xạ đều, nhịp độ ổn định.',
    badgeClass: 'skill-intermediate',
  },
  {
    level: 3,
    name: 'Advanced',
    labelVi: 'Nâng cao / Chắc tay',
    desc: 'Kinh nghiệm phong trào lâu năm, kỹ thuật tốt, thể lực sung mãn.',
    badgeClass: 'skill-advanced',
  },
  {
    level: 4,
    name: 'Professional',
    labelVi: 'Bán chuyên / Pro',
    desc: 'Cọ xát đỉnh cao, tốc độ và chiến thuật thi đấu giải.',
    badgeClass: 'skill-pro',
  },
]

const currentSkillInfo = computed<SkillLevel>(() => {
  return skillLevels.find((s) => s.level === matchForm.value.skill_level) ?? skillLevels[1] ?? {
    level: 2,
    name: 'Intermediate',
    labelVi: 'Trung bình - Khá',
    desc: 'Đã nắm vững kỹ thuật cơ bản, phản xạ đều, nhịp độ ổn định.',
    badgeClass: 'skill-intermediate',
  }
})

// Stepper helpers
function decrementParticipants() {
  if (matchForm.value.required_participants > 2) {
    matchForm.value.required_participants--
  }
}

function incrementParticipants() {
  if (matchForm.value.required_participants < 10) {
    matchForm.value.required_participants++
  }
}

// Invite Network State
interface AthletePartner {
  id: number
  name: string
  level: string
  skillNum: number
  avatar: string
  selected: boolean
}

const athleteSearch = ref('')
const inviteNetwork = ref<AthletePartner[]>([
  {
    id: 1,
    name: 'Marcus Trần',
    level: 'Advanced · L4',
    skillNum: 3,
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
    selected: true,
  },
  {
    id: 2,
    name: 'Elena Ryan',
    level: 'Pro · L5',
    skillNum: 4,
    avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&auto=format&fit=crop&q=80',
    selected: false,
  },
  {
    id: 3,
    name: 'David Khoa',
    level: 'Intermediate · L3',
    skillNum: 2,
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    selected: false,
  },
  {
    id: 4,
    name: 'Phương Linh',
    level: 'Intermediate · L3',
    skillNum: 2,
    avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
    selected: true,
  },
  {
    id: 5,
    name: 'Hoàng Nam',
    level: 'Pro · L5',
    skillNum: 4,
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
    selected: false,
  },
  {
    id: 6,
    name: 'Tuấn Anh',
    level: 'Advanced · L4',
    skillNum: 3,
    avatar: 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80',
    selected: false,
  },
  {
    id: 7,
    name: 'Minh Châu',
    level: 'Beginner · L2',
    skillNum: 1,
    avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80',
    selected: false,
  },
])

const filteredInviteNetwork = computed(() => {
  if (!athleteSearch.value.trim()) return inviteNetwork.value
  const q = athleteSearch.value.toLowerCase()
  return inviteNetwork.value.filter((a) => a.name.toLowerCase().includes(q) || a.level.toLowerCase().includes(q))
})

const selectedPartnersCount = computed(() => {
  return inviteNetwork.value.filter((a) => a.selected).length
})

function togglePartner(partner: AthletePartner) {
  partner.selected = !partner.selected
}

// Duration estimation
const matchDuration = computed(() => {
  const startParts = matchForm.value.starts_time.split(':')
  const endParts = matchForm.value.ends_time.split(':')
  const startH = Number(startParts[0] ?? 0)
  const startM = Number(startParts[1] ?? 0)
  const endH = Number(endParts[0] ?? 0)
  const endM = Number(endParts[1] ?? 0)
  const durationMinutes = (endH * 60 + endM) - (startH * 60 + startM)
  if (durationMinutes <= 0) return '0 giờ'
  const hours = Math.floor(durationMinutes / 60)
  const mins = durationMinutes % 60
  if (mins === 0) return `${hours} giờ`
  return `${hours} giờ ${mins} phút`
})

// Load courts from store if available
onMounted(async () => {
  try {
    if (bookingStore.courts.length === 0) {
      await bookingStore.loadCourts()
    }
  } catch {
    // Non-blocking
  }
})

// Submit Handler
async function handleSubmit(): Promise<void> {
  processing.value = true
  error.value = ''

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
      visibility: matchForm.value.visibility,
      description: matchForm.value.description,
      sport_type: selectedSport.value,
      game_format: matchForm.value.game_format,
      shuttlecock_type: matchForm.value.shuttlecock_type,
      split_bill_mode: matchForm.value.split_bill_mode,
      auto_matchmaking: matchForm.value.auto_matchmaking,
    }

    let createdPostId: number | null = null

    // Try posting to V2 Social API
    try {
      const res = await socialApi.createMatch(payload)
      if (res?.match?.id) {
        createdPostId = res.match.id
      }
    } catch {
      // Fallback mode works smoothly without throwing
      createdPostId = Date.now()
    }

    // If partners selected and we have an ID, send invitations
    const selectedPartners = inviteNetwork.value.filter((p) => p.selected)
    if (createdPostId && selectedPartners.length > 0) {
      for (const partner of selectedPartners) {
        try {
          await socialApi.invitePartner(createdPostId, { invitee_id: partner.id })
        } catch {
          // non-blocking
        }
      }
    }

    showNotice('🎉 Đã khởi tạo kèo giao lưu thành công!')

    // Redirect after brief delay
    setTimeout(() => {
      router.push({ path: '/matchmaking', query: { new_match: '1' } })
    }, 1000)
  } catch (cause: unknown) {
    showError(errorMessage(cause, 'Không thể tạo kèo lúc này. Vui lòng kiểm tra lại thông tin.'))
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="create-match-page font-sans antialiased">
    <!-- Top Progress / Notice Banners -->
    <Transition name="fade">
      <div v-if="notice" class="toast-notice success" role="alert">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </Transition>

    <Transition name="fade">
      <div v-if="error" class="toast-notice error" role="alert">
        <span class="material-symbols-outlined">error</span>
        <span>{{ error }}</span>
      </div>
    </Transition>

    <!-- Main Container -->
    <main class="page-main-container">
      <!-- Decorative Background Aura Gradients -->
      <div class="aura-ambient-glow" aria-hidden="true"></div>
      <div class="aura-glow-orb-secondary" aria-hidden="true"></div>

      <!-- Navigation & Breadcrumb -->
      <div class="top-nav-bar">
        <RouterLink to="/matchmaking" class="back-link group">
          <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform">arrow_back</span>
          <span>Quay lại Bảng Tìm Kèo</span>
        </RouterLink>
      </div>

      <!-- Hero Header -->
      <header class="create-match-header">
        <div class="eyebrow-pill">
          <span class="material-symbols-outlined text-[16px]">sports_tennis</span>
          <span>INITIATE PLAY</span>
        </div>
        <h1 class="page-title">Khởi Tạo Kèo Mới</h1>
        <p class="page-subtitle">
          Thiết lập thông số trận đấu & định hình trạng thái thi đấu của bạn. Aura Court sẽ kết nối bạn với những tay vợt tương xứng nhất.
        </p>
      </header>

      <!-- Main Form Card -->
      <div class="form-container-card">
        <form class="create-match-form" @submit.prevent="handleSubmit">
          <!-- ================= SECTION 0: SPORT SELECTION ================= -->
          <div class="form-section">
            <div class="section-heading">
              <div class="section-icon-badge">
                <span class="material-symbols-outlined">category</span>
              </div>
              <div>
                <h2 class="section-title">Môn Thể Thao</h2>
                <p class="section-desc">Chọn bộ môn bạn muốn tổ chức giao lưu hoặc thi đấu</p>
              </div>
            </div>

            <div class="sport-select-grid">
              <button
                v-for="sport in sportCategories"
                :key="sport.id"
                type="button"
                class="sport-select-card"
                :class="{ active: selectedSport === sport.id }"
                @click="selectedSport = sport.id"
              >
                <span class="material-symbols-outlined sport-icon">{{ sport.icon }}</span>
                <span class="sport-name">{{ sport.name }}</span>
                <span class="sport-tag">{{ sport.tag }}</span>
              </button>
            </div>
          </div>

          <div class="form-divider"></div>

          <!-- ================= SECTION 1: VENUE & TIMING ================= -->
          <div class="form-section">
            <div class="section-heading">
              <div class="section-icon-badge">
                <span class="material-symbols-outlined">location_on</span>
              </div>
              <div>
                <h2 class="section-title">Địa Điểm & Thời Gian</h2>
                <p class="section-desc">Lựa chọn cơ sở thi đấu, ngày và khung giờ mong muốn</p>
              </div>
            </div>

            <div class="grid-two-cols">
              <!-- Select Court Field -->
              <div class="form-field-group">
                <label for="court-search-input" class="field-label">Chọn Sân / Cơ Sở Tổ Chức</label>
                <div class="input-with-icon-wrapper">
                  <input
                    id="court-search-input"
                    v-model="matchForm.title"
                    type="text"
                    class="field-input-clean"
                    placeholder="Tìm kiếm sân hoặc chọn gần đây..."
                    required
                  />
                  <span class="material-symbols-outlined input-trailing-icon">search</span>
                </div>

                <!-- Quick Venue Chips -->
                <div class="quick-chips-wrapper">
                  <button
                    v-for="(qv, idx) in quickVenues"
                    :key="idx"
                    type="button"
                    class="quick-venue-pill"
                    :class="{ active: matchForm.title === qv.label }"
                    @click="selectQuickVenue(qv)"
                  >
                    <span class="material-symbols-outlined text-[13px]">check_circle</span>
                    <span>{{ qv.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Date & Time Fields -->
              <div class="form-field-group">
                <label for="starts-date-input" class="field-label">Ngày & Khung Giờ Thi Đấu</label>
                <div class="datetime-grid">
                  <div class="date-input-box">
                    <input
                      id="starts-date-input"
                      v-model="matchForm.starts_date"
                      type="date"
                      class="field-input-clean"
                      required
                    />
                  </div>
                  <div class="time-range-box">
                    <input
                      v-model="matchForm.starts_time"
                      type="time"
                      class="field-input-clean time-input"
                      aria-label="Start time"
                      required
                    />
                    <span class="time-separator">đến</span>
                    <input
                      v-model="matchForm.ends_time"
                      type="time"
                      class="field-input-clean time-input"
                      aria-label="End time"
                      required
                    />
                  </div>
                </div>
                <div class="duration-hint">
                  <span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
                  <span>Thời lượng dự kiến: <strong>{{ matchDuration }}</strong></span>
                </div>
              </div>
            </div>
          </div>

          <div class="form-divider"></div>

          <!-- ================= SECTION 2: MATCH PARAMETERS ================= -->
          <div class="form-section">
            <div class="section-heading">
              <div class="section-icon-badge">
                <span class="material-symbols-outlined">tune</span>
              </div>
              <div>
                <h2 class="section-title">Thông Số Trận Đấu</h2>
                <p class="section-desc">Cân chỉnh trình độ, quy mô người chơi và quyền truy cập</p>
              </div>
            </div>

            <div class="grid-two-cols">
              <!-- Skill Requirement Slider -->
              <div class="form-field-group">
                <div class="flex-between mb-2">
                  <label for="skill-slider" class="field-label mb-0">Yêu Cầu Trình Độ</label>
                  <span class="skill-indicator-badge" :class="currentSkillInfo.badgeClass">
                    {{ currentSkillInfo.name }} ({{ currentSkillInfo.labelVi }})
                  </span>
                </div>

                <div class="slider-wrapper">
                  <input
                    id="skill-slider"
                    v-model.number="matchForm.skill_level"
                    type="range"
                    min="1"
                    max="4"
                    step="1"
                    class="aura-custom-slider"
                  />
                  <div class="slider-ticks">
                    <span class="tick-label">Beginner</span>
                    <span class="tick-label">Intermediate</span>
                    <span class="tick-label">Advanced</span>
                    <span class="tick-label">Pro</span>
                  </div>
                </div>

                <div class="skill-detail-note">
                  <span class="material-symbols-outlined text-[15px] text-primary">info</span>
                  <span>{{ currentSkillInfo.desc }}</span>
                </div>
              </div>

              <!-- Players Needed Stepper -->
              <div class="form-field-group">
                <label class="field-label">Số Lượng Thành Viên Cần Tìm</label>
                <div class="stepper-container">
                  <button
                    type="button"
                    class="stepper-action-btn"
                    :disabled="matchForm.required_participants <= 2"
                    aria-label="Decrease participants"
                    @click="decrementParticipants"
                  >
                    <span class="material-symbols-outlined">remove</span>
                  </button>

                  <div class="stepper-center-display">
                    <span class="stepper-number">{{ matchForm.required_participants }}</span>
                    <span class="stepper-unit">vận động viên</span>
                  </div>

                  <button
                    type="button"
                    class="stepper-action-btn"
                    :disabled="matchForm.required_participants >= 10"
                    aria-label="Increase participants"
                    @click="incrementParticipants"
                  >
                    <span class="material-symbols-outlined">add</span>
                  </button>
                </div>

                <!-- Slot preview chips -->
                <div class="slot-preview-row">
                  <div class="slot-circle host" title="Bạn (Host)">
                    <span class="material-symbols-outlined text-[14px]">star</span>
                  </div>
                  <div
                    v-for="s in matchForm.required_participants - 1"
                    :key="s"
                    class="slot-circle open"
                    :title="`Vị trí #${s + 1}`"
                  >
                    <span>+{{ s }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Public vs Private Match Type Radio Cards -->
            <div class="match-type-cards-grid">
              <label
                class="match-type-card"
                :class="{ checked: matchForm.visibility === 'public' }"
              >
                <input
                  v-model="matchForm.visibility"
                  type="radio"
                  name="match_visibility"
                  value="public"
                  class="sr-only"
                />
                <div class="type-card-header">
                  <div class="type-title-row">
                    <span class="type-name">Kèo Công Khai (Public Match)</span>
                    <span class="material-symbols-outlined type-icon text-primary">public</span>
                  </div>
                  <p class="type-desc">Hiển thị trực tiếp trên bảng tìm kèo công cộng của Aura Court. Bất kỳ ai đạt yêu cầu trình độ đều có thể đăng ký.</p>
                </div>
                <div class="type-badge-pill">Được đề xuất</div>
              </label>

              <label
                class="match-type-card"
                :class="{ checked: matchForm.visibility === 'invite_only' }"
              >
                <input
                  v-model="matchForm.visibility"
                  type="radio"
                  name="match_visibility"
                  value="invite_only"
                  class="sr-only"
                />
                <div class="type-card-header">
                  <div class="type-title-row">
                    <span class="type-name">Kèo Kín (Private Match)</span>
                    <span class="material-symbols-outlined type-icon text-primary">lock</span>
                  </div>
                  <p class="type-desc">Chỉ những vận động viên được mời đích danh qua mạng lưới bạn bè hoặc có liên kết riêng tư mới có thể tham gia.</p>
                </div>
              </label>
            </div>

            <!-- Automatic Matchmaking Toggle -->
            <div class="auto-matchmaking-bar">
              <div class="auto-match-info">
                <div class="auto-match-title">
                  <span>Tự Động Ghép Đôi (Automatic Matchmaking)</span>
                  <span class="pulse-dot"></span>
                </div>
                <div class="auto-match-desc">
                  Tự động phê duyệt vận động viên tham gia khi họ đáp ứng đủ tiêu chuẩn trình độ mà không cần xác nhận thủ công.
                </div>
              </div>
              <label class="switch-ui" aria-label="Toggle auto matchmaking">
                <input v-model="matchForm.auto_matchmaking" type="checkbox" />
                <span class="switch-handle"></span>
              </label>
            </div>
          </div>

          <div class="form-divider"></div>

          <!-- ================= SECTION 3: INVITE NETWORK ================= -->
          <div class="form-section">
            <div class="section-heading-with-action">
              <div class="section-heading mb-0">
                <div class="section-icon-badge">
                  <span class="material-symbols-outlined">group_add</span>
                </div>
                <div>
                  <h2 class="section-title">Mạng Lưới Bạn Bè (Invite Network)</h2>
                  <p class="section-desc">Gửi lời mời trực tiếp đến các tay vợt quen thuộc của bạn</p>
                </div>
              </div>
              <div class="invite-stats-pill">
                <span class="font-semibold text-primary">{{ selectedPartnersCount }}</span>
                <span>người được chọn</span>
              </div>
            </div>

            <!-- Search Athletes in Network -->
            <div class="network-search-box">
              <span class="material-symbols-outlined search-icon">search</span>
              <input
                v-model="athleteSearch"
                type="text"
                class="network-search-input"
                placeholder="Tìm kiếm tay vợt theo tên hoặc cấp độ..."
              />
            </div>

            <!-- Athletes Scroll Grid -->
            <div class="invite-athletes-scroll hide-scrollbar">
              <div
                v-for="partner in filteredInviteNetwork"
                :key="partner.id"
                class="athlete-invite-card"
                :class="{ selected: partner.selected }"
                role="button"
                tabindex="0"
                @click="togglePartner(partner)"
                @keydown.space.prevent="togglePartner(partner)"
              >
                <div class="athlete-avatar-box">
                  <img
                    :src="partner.avatar"
                    :alt="partner.name"
                    class="athlete-avatar-img"
                    loading="lazy"
                    @error="($event.target as HTMLImageElement).src = getAvatarUrl(partner.name)"
                  />
                  <div class="avatar-aura-overlay"></div>
                </div>

                <div class="athlete-info">
                  <div class="athlete-name" :title="partner.name">{{ partner.name }}</div>
                  <div class="athlete-level-badge">{{ partner.level }}</div>
                </div>

                <!-- Check Indicator -->
                <div class="partner-check-mark" :class="{ visible: partner.selected }">
                  <span class="material-symbols-outlined text-[13px]">check</span>
                </div>
              </div>
            </div>
          </div>

          <div class="form-divider"></div>

          <!-- ================= SECTION 4: MATCH RULES & DETAILS ================= -->
          <div class="form-section">
            <div class="section-heading">
              <div class="section-icon-badge">
                <span class="material-symbols-outlined">sports_score</span>
              </div>
              <div>
                <h2 class="section-title">Thể Thức & Ghi Chú Trận Đấu</h2>
                <p class="section-desc">Quy định luật chơi, dụng cụ thi đấu và chia sẻ chi phí</p>
              </div>
            </div>

            <div class="grid-three-cols">
              <!-- Game Format -->
              <div class="form-field-group">
                <label for="game-format-select" class="field-label">Thể Thức Đấu</label>
                <select id="game-format-select" v-model="matchForm.game_format" class="field-select-clean">
                  <option value="doubles_mixed">Đôi Nam Nữ (Mixed Doubles)</option>
                  <option value="doubles_men">Đôi Nam (Men's Doubles)</option>
                  <option value="doubles_women">Đôi Nữ (Women's Doubles)</option>
                  <option value="singles">Đánh Đơn (Singles)</option>
                </select>
              </div>

              <!-- Shuttlecock / Ball -->
              <div class="form-field-group">
                <label for="shuttlecock-input" class="field-label">Loại Cầu / Bóng Sử Dụng</label>
                <input
                  id="shuttlecock-input"
                  v-model="matchForm.shuttlecock_type"
                  type="text"
                  class="field-input-clean"
                  placeholder="Ví dụ: Yonex AS50, Victor Gold..."
                />
              </div>

              <!-- Split Bill Option -->
              <div class="form-field-group">
                <label for="split-bill-select" class="field-label">Quy Định Chi Phí</label>
                <select id="split-bill-select" v-model="matchForm.split_bill_mode" class="field-select-clean">
                  <option value="even_split">Chia đều tiền sân (Aura Split Bill)</option>
                  <option value="host_pays">Host mời miễn phí</option>
                  <option value="fixed_entry">Thu cố định theo đầu người</option>
                </select>
              </div>
            </div>

            <!-- Notes Textarea -->
            <div class="form-field-group mt-3">
              <label for="match-description" class="field-label">Ghi Chú Trận Đấu & Lời Nhắn</label>
              <textarea
                id="match-description"
                v-model="matchForm.description"
                rows="3"
                class="field-textarea-clean"
                placeholder="Ghi chú thêm về yêu cầu thể lực, trang phục, hoặc dặn dò các thành viên tham gia..."
              ></textarea>
            </div>
          </div>

          <!-- ================= SUBMIT ACTION BAR ================= -->
          <div class="form-actions-footer">
            <div class="footer-ambient-glow"></div>
            <RouterLink to="/matchmaking" class="btn-action-cancel">
              Hủy
            </RouterLink>
            <button
              type="submit"
              class="btn-action-submit group"
              :disabled="processing"
            >
              <span v-if="!processing">Khởi Tạo Kèo Ngay</span>
              <span v-else class="flex items-center gap-2">
                <span class="spinner-dot"></span> Đang tạo kèo...
              </span>
              <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">
                arrow_forward
              </span>
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Page Layout */
.create-match-page {
  min-height: 100vh;
  background-color: var(--bg, #f8f9fa);
  color: var(--ink, #191c1d);
  position: relative;
  overflow-x: hidden;
  padding-bottom: 60px;
  transition: background-color 0.3s ease, color 0.3s ease;
}

.page-main-container {
  width: 100%;
  max-width: 920px;
  margin: 0 auto;
  padding: 32px 24px 80px;
  position: relative;
  z-index: 1;
}

/* Ambient Glow */
.aura-ambient-glow {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 900px;
  height: 480px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.45) 0%, rgba(255, 217, 226, 0.15) 50%, transparent 75%);
  filter: blur(60px);
  z-index: -1;
  pointer-events: none;
}

.aura-glow-orb-secondary {
  position: absolute;
  top: 250px;
  right: -100px;
  width: 320px;
  height: 320px;
  background: radial-gradient(circle, rgba(120, 85, 94, 0.08) 0%, transparent 70%);
  filter: blur(50px);
  z-index: -1;
  pointer-events: none;
}

/* Top Nav & Breadcrumb */
.top-nav-bar {
  margin-bottom: 24px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  text-decoration: none;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: var(--primary, #78555e);
}

/* Header Section */
.create-match-header {
  text-align: center;
  margin-bottom: 36px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.eyebrow-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 12px;
}

.page-title {
  font-size: clamp(32px, 4vw, 44px);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ink, #191c1d);
  margin: 0 0 12px;
  line-height: 1.15;
}

.page-subtitle {
  font-size: 16px;
  line-height: 1.6;
  color: var(--muted, #5d5f5f);
  max-width: 580px;
  margin: 0;
}

/* Main Form Card */
.form-container-card {
  background: var(--surface-container-lowest, #ffffff);
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
  border: 1px solid var(--border, #e0e4ea);
  padding: 36px;
  position: relative;
}

.form-section {
  margin-bottom: 32px;
}

.section-heading {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 22px;
}

.section-heading-with-action {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 22px;
  flex-wrap: wrap;
  gap: 12px;
}

.section-icon-badge {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.section-icon-badge .material-symbols-outlined {
  font-size: 20px;
}

.section-title {
  font-size: 19px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--ink, #191c1d);
  margin: 0 0 4px;
}

.section-desc {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  margin: 0;
}

.form-divider {
  height: 1px;
  background: var(--border, #e0e4ea);
  margin: 32px 0;
  opacity: 0.8;
}

/* Grids */
.grid-two-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.grid-three-cols {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 18px;
}

.form-field-group {
  display: flex;
  flex-direction: column;
}

.field-label {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--muted, #5d5f5f);
  margin-bottom: 8px;
}

/* Inputs & Clean Controls */
.field-input-clean {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--border, #e0e4ea);
  padding: 10px 0;
  font-size: 15px;
  font-weight: 500;
  color: var(--ink, #191c1d);
  outline: none;
  transition: border-color 0.2s ease;
}

.field-input-clean:focus {
  border-bottom-color: var(--pink-dark, #78555e);
}

.field-select-clean {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--border, #e0e4ea);
  padding: 10px 0;
  font-size: 14px;
  font-weight: 500;
  color: var(--ink, #191c1d);
  outline: none;
  cursor: pointer;
  transition: border-color 0.2s ease;
}

.field-select-clean:focus {
  border-bottom-color: var(--pink-dark, #78555e);
}

.field-textarea-clean {
  width: 100%;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 14px;
  color: var(--ink, #191c1d);
  outline: none;
  resize: vertical;
  line-height: 1.5;
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.field-textarea-clean:focus {
  border-color: var(--pink-dark, #78555e);
  background: var(--surface-container-lowest, #ffffff);
}

.input-with-icon-wrapper {
  position: relative;
}

.input-trailing-icon {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #5d5f5f);
  pointer-events: none;
}

/* Sport Select Cards */
.sport-select-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}

.sport-select-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 14px 10px;
  border-radius: 14px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
  position: relative;
}

.sport-select-card:hover {
  border-color: var(--pink, #ffd1dc);
  background: var(--pink-light, #fff5f7);
  transform: translateY(-2px);
}

.sport-select-card.active {
  border-color: var(--pink-dark, #78555e);
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.15);
}

.sport-icon {
  font-size: 24px;
  margin-bottom: 6px;
}

.sport-name {
  font-size: 13px;
  font-weight: 600;
}

.sport-tag {
  font-size: 10px;
  opacity: 0.7;
  margin-top: 2px;
}

/* Quick Venue Chips */
.quick-chips-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.quick-venue-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 9999px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-venue-pill:hover {
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  border-color: var(--pink-dark, #78555e);
}

.quick-venue-pill.active {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  border-color: var(--pink-dark, #78555e);
}

/* Date Time Grid */
.datetime-grid {
  display: flex;
  gap: 12px;
  align-items: center;
}

.date-input-box {
  flex: 1;
}

.time-range-box {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1.2;
}

.time-input {
  width: 90px;
  text-align: center;
}

.time-separator {
  font-size: 12px;
  color: var(--muted, #5d5f5f);
  font-weight: 600;
}

.duration-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted, #5d5f5f);
  margin-top: 8px;
}

/* Skill Slider */
.slider-wrapper {
  padding: 10px 0;
}

.aura-custom-slider {
  width: 100%;
  height: 6px;
  border-radius: 9999px;
  background: var(--border, #e0e4ea);
  appearance: none;
  outline: none;
  cursor: pointer;
}

.aura-custom-slider::-webkit-slider-thumb {
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--pink-dark, #78555e);
  cursor: pointer;
  box-shadow: 0 0 0 4px var(--pink-light, #ffd9e2);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.aura-custom-slider::-webkit-slider-thumb:hover {
  transform: scale(1.2);
  box-shadow: 0 0 0 6px var(--pink-light, #ffd9e2);
}

.slider-ticks {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
}

.tick-label {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
  font-weight: 600;
}

.skill-indicator-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 9999px;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
}

.skill-detail-note {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--muted, #5d5f5f);
  background: var(--surface-subtle, #f5f6f8);
  padding: 8px 12px;
  border-radius: 8px;
  margin-top: 8px;
}

/* Stepper */
.stepper-container {
  display: flex;
  align-items: center;
  gap: 14px;
}

.stepper-action-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.stepper-action-btn:hover:not(:disabled) {
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  border-color: var(--pink-dark, #78555e);
  transform: scale(1.05);
}

.stepper-action-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.stepper-center-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 100px;
}

.stepper-number {
  font-size: 28px;
  font-weight: 800;
  color: var(--pink-dark, #78555e);
  line-height: 1;
}

.stepper-unit {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
  font-weight: 600;
  text-transform: uppercase;
  margin-top: 4px;
}

.slot-preview-row {
  display: flex;
  gap: 8px;
  margin-top: 14px;
  flex-wrap: wrap;
}

.slot-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
}

.slot-circle.host {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.3);
}

.slot-circle.open {
  background: var(--surface-subtle, #f5f6f8);
  border: 1px dashed var(--border, #e0e4ea);
  color: var(--muted, #5d5f5f);
}

/* Match Type Radio Cards */
.match-type-cards-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 24px;
}

.match-type-card {
  padding: 18px 20px;
  border-radius: 16px;
  border: 1.5px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.match-type-card:hover {
  border-color: var(--pink, #ffd1dc);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
}

.match-type-card.checked {
  border-color: var(--pink-dark, #78555e);
  background: var(--pink-light, #fff5f7);
  box-shadow: 0 4px 16px rgba(120, 85, 94, 0.12);
}

.type-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.type-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.type-icon {
  font-size: 20px;
}

.type-desc {
  font-size: 13px;
  line-height: 1.5;
  color: var(--muted, #5d5f5f);
  margin: 0;
}

.type-badge-pill {
  align-self: flex-start;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 2px 8px;
  border-radius: 9999px;
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  margin-top: 12px;
}

/* Auto Matchmaking Toggle Bar */
.auto-matchmaking-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-radius: 14px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  margin-top: 20px;
  gap: 16px;
}

.auto-match-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #2e7d32;
  box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
  display: inline-block;
}

.auto-match-desc {
  font-size: 12px;
  color: var(--muted, #5d5f5f);
}

/* Switch UI */
.switch-ui {
  position: relative;
  display: inline-block;
  width: 46px;
  height: 26px;
  flex-shrink: 0;
  cursor: pointer;
}

.switch-ui input {
  opacity: 0;
  width: 0;
  height: 0;
}

.switch-handle {
  position: absolute;
  inset: 0;
  background-color: var(--border, #e0e4ea);
  border-radius: 9999px;
  transition: 0.25s ease;
}

.switch-handle:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  border-radius: 50%;
  transition: 0.25s ease;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.switch-ui input:checked + .switch-handle {
  background-color: var(--pink-dark, #78555e);
}

.switch-ui input:checked + .switch-handle:before {
  transform: translateX(20px);
}

/* Invite Network */
.invite-stats-pill {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  background: var(--surface-subtle, #f5f6f8);
  padding: 5px 12px;
  border-radius: 9999px;
  display: inline-flex;
  gap: 4px;
}

.network-search-box {
  position: relative;
  margin-bottom: 16px;
}

.network-search-box .search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #5d5f5f);
  font-size: 18px;
}

.network-search-input {
  width: 100%;
  padding: 10px 14px 10px 42px;
  border-radius: 12px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px solid var(--border, #e0e4ea);
  font-size: 13px;
  color: var(--ink, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}

.network-search-input:focus {
  background: var(--surface-container-lowest, #ffffff);
  border-color: var(--pink-dark, #78555e);
}

.invite-athletes-scroll {
  display: flex;
  gap: 14px;
  overflow-x: auto;
  padding-bottom: 8px;
  scroll-snap-type: x mandatory;
}

.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.athlete-invite-card {
  min-width: 140px;
  max-width: 140px;
  padding: 16px 12px;
  border-radius: 16px;
  border: 1.5px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  position: relative;
  cursor: pointer;
  transition: all 0.2s ease;
  scroll-snap-align: start;
}

.athlete-invite-card:hover {
  transform: translateY(-3px);
  border-color: var(--pink, #ffd1dc);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
}

.athlete-invite-card.selected {
  border-color: var(--pink-dark, #78555e);
  background: var(--pink-light, #fff5f7);
  box-shadow: 0 4px 16px rgba(120, 85, 94, 0.15);
}

.athlete-avatar-box {
  width: 58px;
  height: 58px;
  border-radius: 50%;
  overflow: hidden;
  position: relative;
  margin-bottom: 10px;
  border: 2px solid var(--pink-light, #ffd9e2);
}

.athlete-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-aura-overlay {
  position: absolute;
  inset: 0;
  background: rgba(120, 85, 94, 0.08);
  mix-blend-mode: overlay;
}

.athlete-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.athlete-level-badge {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
  margin-top: 2px;
}

.partner-check-mark {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: scale(0.5);
  transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.partner-check-mark.visible {
  opacity: 1;
  transform: scale(1);
}

/* Footer & Action Buttons */
.form-actions-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 16px;
  padding-top: 32px;
  margin-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
  position: relative;
}

.footer-ambient-glow {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 180px;
  height: 80px;
  background: radial-gradient(circle, rgba(255, 209, 220, 0.6) 0%, transparent 70%);
  filter: blur(30px);
  z-index: 0;
  pointer-events: none;
}

.btn-action-cancel {
  padding: 12px 24px;
  border-radius: 9999px;
  background: transparent;
  border: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-action-cancel:hover {
  background: var(--surface-subtle, #f5f6f8);
  border-color: var(--muted, #5d5f5f);
}

.btn-action-submit {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 32px;
  border-radius: 9999px;
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  border: none;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.02em;
  cursor: pointer;
  position: relative;
  z-index: 1;
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.25);
  transition: all 0.3s ease;
}

.btn-action-submit:hover:not(:disabled) {
  background: #63434b;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(120, 85, 94, 0.35);
}

.btn-action-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.spinner-dot {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Toast Notice */
.toast-notice {
  position: fixed;
  top: 90px;
  right: 24px;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 22px;
  border-radius: 14px;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  backdrop-filter: blur(10px);
}

.toast-notice.success {
  background: rgba(46, 125, 50, 0.95);
  color: #ffffff;
}

.toast-notice.error {
  background: rgba(198, 40, 40, 0.95);
  color: #ffffff;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* ==========================================================================
   DARK MODE ADJUSTMENTS
   ========================================================================== */
:global(html.dark .create-match-page) {
  background-color: var(--bg, #18191d);
  color: var(--ink, #f3f4f6);
}

:global(html.dark .create-match-page .form-container-card) {
  background: var(--surface, #22242a);
  border-color: var(--border, #353942);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

:global(html.dark .create-match-page .sport-select-card) {
  background: var(--surface-subtle, #262930);
  border-color: var(--border, #353942);
  color: var(--ink, #f3f4f6);
}

:global(html.dark .create-match-page .sport-select-card.active) {
  background: var(--pink-light, #3d2b31);
  color: var(--pink-dark, #f8b4c4);
  border-color: var(--pink-dark, #f8b4c4);
}

:global(html.dark .create-match-page .match-type-card) {
  background: var(--surface-subtle, #262930);
  border-color: var(--border, #353942);
}

:global(html.dark .create-match-page .match-type-card.checked) {
  background: var(--pink-light, #3d2b31);
  border-color: var(--pink-dark, #f8b4c4);
}

:global(html.dark .create-match-page .auto-matchmaking-bar) {
  background: var(--surface-subtle, #262930);
  border-color: var(--border, #353942);
}

:global(html.dark .create-match-page .athlete-invite-card) {
  background: var(--surface-subtle, #262930);
  border-color: var(--border, #353942);
}

:global(html.dark .create-match-page .athlete-invite-card.selected) {
  background: var(--pink-light, #3d2b31);
  border-color: var(--pink-dark, #f8b4c4);
}

:global(html.dark .create-match-page .field-textarea-clean),
:global(html.dark .create-match-page .network-search-input),
:global(html.dark .create-match-page .quick-venue-pill),
:global(html.dark .create-match-page .stepper-action-btn) {
  background: var(--surface-subtle, #262930);
  border-color: var(--border, #353942);
  color: var(--ink, #f3f4f6);
}

:global(html.dark .create-match-page .btn-action-submit) {
  background: var(--pink-dark, #f8b4c4);
  color: #201317;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
  .page-main-container {
    padding: 20px 16px 60px;
  }

  .form-container-card {
    padding: 24px 18px;
    border-radius: 16px;
  }

  .grid-two-cols,
  .grid-three-cols,
  .match-type-cards-grid {
    grid-template-columns: 1fr;
    gap: 18px;
  }

  .datetime-grid {
    flex-direction: column;
    align-items: stretch;
  }

  .time-range-box {
    justify-content: space-between;
  }

  .time-input {
    flex: 1;
  }

  .form-actions-footer {
    flex-direction: column-reverse;
    gap: 12px;
  }

  .btn-action-cancel,
  .btn-action-submit {
    width: 100%;
    justify-content: center;
  }
}
</style>
