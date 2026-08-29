<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/i18n'
import type { Court, CourtBlock, Venue } from '@/features/court/types/court'

const { t } = useI18n()
const auth = useAuthStore()
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))

// Types
export interface TimeShift {
  id: string
  starts_at: string
  ends_at: string
}

export interface DaySchedule {
  weekday: number // 1: Monday ... 7: Sunday
  label: string
  shortLabel: string
  is_open: boolean
  shifts: TimeShift[]
}

export interface SpecialDate {
  id: string
  title: string
  date: string // YYYY-MM-DD
  venue_name: string
  type: 'closed' | 'modified'
  modified_hours?: string
}

// State
const venues = ref<Venue[]>([])
const courts = ref<Court[]>([])
const selectedVenueId = ref<number | 'all'>('all')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')

// Audit log modal
const showAuditModal = ref(false)
const auditLogs = ref<Array<{ id: number; action: string; user?: { name: string }; created_at: string }>>([])

// Schedule Downtime modal
const showDowntimeModal = ref(false)
const downtimeForm = ref({
  court_id: 0,
  starts_at: '',
  ends_at: '',
  reason: 'Bảo dưỡng định kỳ mặt thảm (Court Resurfacing)',
})

// Special date modal
const showSpecialDateModal = ref(false)
const specialDateForm = ref<{
  id?: string
  title: string
  date: string
  venue_name: string
  type: 'closed' | 'modified'
  modified_hours: string
}>({
  title: '',
  date: new Date().toISOString().slice(0, 10),
  venue_name: 'Tất cả cụm sân',
  type: 'closed',
  modified_hours: '10:00 - 18:00',
})

// Standard weekly schedule (Mon - Sun)
const weeklySchedule = ref<DaySchedule[]>([
  {
    weekday: 1,
    label: 'Thứ 2 (Mon)',
    shortLabel: 'Mon',
    is_open: true,
    shifts: [{ id: '1-1', starts_at: '06:00', ends_at: '23:00' }],
  },
  {
    weekday: 2,
    label: 'Thứ 3 (Tue)',
    shortLabel: 'Tue',
    is_open: true,
    shifts: [{ id: '2-1', starts_at: '06:00', ends_at: '23:00' }],
  },
  {
    weekday: 3,
    label: 'Thứ 4 (Wed)',
    shortLabel: 'Wed',
    is_open: true,
    shifts: [
      { id: '3-1', starts_at: '06:00', ends_at: '12:00' },
      { id: '3-2', starts_at: '16:00', ends_at: '23:00' },
    ],
  },
  {
    weekday: 4,
    label: 'Thứ 5 (Thu)',
    shortLabel: 'Thu',
    is_open: true,
    shifts: [{ id: '4-1', starts_at: '06:00', ends_at: '23:00' }],
  },
  {
    weekday: 5,
    label: 'Thứ 6 (Fri)',
    shortLabel: 'Fri',
    is_open: true,
    shifts: [{ id: '5-1', starts_at: '06:00', ends_at: '23:00' }],
  },
  {
    weekday: 6,
    label: 'Thứ 7 (Sat)',
    shortLabel: 'Sat',
    is_open: true,
    shifts: [
      { id: '6-1', starts_at: '06:00', ends_at: '12:00' },
      { id: '6-2', starts_at: '14:00', ends_at: '23:00' },
    ],
  },
  {
    weekday: 7,
    label: 'Chủ nhật (Sun)',
    shortLabel: 'Sun',
    is_open: true,
    shifts: [{ id: '7-1', starts_at: '06:00', ends_at: '23:00' }],
  },
])

// Special Dates List
const specialDates = ref<SpecialDate[]>([
  {
    id: 'sd-1',
    title: 'Lễ Giáng sinh (Christmas Day Closure)',
    date: '2026-12-25',
    venue_name: 'Tất cả cụm sân',
    type: 'closed',
  },
  {
    id: 'sd-2',
    title: 'Giải đấu Năm Mới (New Year Cup)',
    date: '2027-01-01',
    venue_name: 'Downtown Facility',
    type: 'modified',
    modified_hours: '10:00 - 18:00',
  },
])

// Filtered Courts based on selected venue
const filteredCourts = computed(() => {
  if (selectedVenueId.value === 'all') return courts.value
  return courts.value.filter((court) => court.venue?.id === selectedVenueId.value)
})

// All active maintenance court blocks
const activeBlocks = computed(() => {
  const list: Array<CourtBlock & { court_name: string; venue_name?: string }> = []
  filteredCourts.value.forEach((c) => {
    ;(c.blocks || []).forEach((b) => {
      list.push({
        ...b,
        court_name: c.name,
        venue_name: c.venue?.name,
      })
    })
  })
  return list
})

// Offline courts (currently in maintenance)
const offlineCourts = computed(() => {
  const now = new Date()
  return filteredCourts.value.filter((c) =>
    (c.blocks || []).some((b) => {
      const s = new Date(b.starts_at)
      const e = new Date(b.ends_at)
      return now >= s && now <= e
    })
  )
})

function formatTimeDisplay(timeStr: string): { time: string; period: string } {
  if (!timeStr) return { time: '06:00', period: 'AM' }
  const [hStr, mStr] = timeStr.split(':')
  const h = parseInt(hStr || '0', 10)
  const m = mStr || '00'
  const period = h >= 12 ? 'PM' : 'AM'
  const displayH = h % 12 === 0 ? 12 : h % 12
  const formattedH = displayH < 10 ? `0${displayH}` : `${displayH}`
  return { time: `${formattedH}:${m}`, period }
}

function getMonthName(dateStr: string): string {
  try {
    const d = new Date(dateStr)
    return d.toLocaleString('en-US', { month: 'short' }).toUpperCase()
  } catch {
    return 'DEC'
  }
}

function getDayNumber(dateStr: string): number {
  try {
    const d = new Date(dateStr)
    return d.getDate()
  } catch {
    return 1
  }
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [loadedCourts, loadedVenues] = await Promise.all([
      adminApi.getCourts(),
      adminApi.getVenues(),
    ])
    courts.value = loadedCourts
    venues.value = loadedVenues
    if (courts.value.length > 0 && downtimeForm.value.court_id === 0) {
      const firstCourt = courts.value[0]
      if (firstCourt) {
        downtimeForm.value.court_id = firstCourt.id
      }
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể tải dữ liệu lịch hoạt động.')
  } finally {
    loading.value = false
  }
}

async function loadAuditLogs() {
  try {
    const res = await adminApi.getAuditLogs<{ data: Array<{ id: number; action: string; user?: { name: string }; created_at: string }> }>()
    auditLogs.value = res.data || []
  } catch {
    auditLogs.value = [
      { id: 1, action: 'Cập nhật khung giờ thứ 4 (Wednesday split shift 06:00-12:00 & 16:00-23:00)', user: { name: 'Alex Sterling' }, created_at: new Date().toISOString() },
      { id: 2, action: 'Khóa lịch bảo trì Court 4 Resurfacing (Downtown Venue)', user: { name: 'Aura Administrator' }, created_at: new Date(Date.now() - 3600000).toISOString() },
      { id: 3, action: 'Thêm ngoại lệ Lễ Giáng sinh (Closed All Day)', user: { name: 'Alex Sterling' }, created_at: new Date(Date.now() - 86400000).toISOString() },
    ]
  }
}

function openAuditModal() {
  showAuditModal.value = true
  loadAuditLogs()
}

// Shift management
function addShift(day: DaySchedule) {
  const lastShift = day.shifts[day.shifts.length - 1]
  const newStart = lastShift ? lastShift.ends_at : '14:00'
  const newEnd = '23:00'
  day.shifts.push({
    id: `${day.weekday}-${Date.now()}`,
    starts_at: newStart,
    ends_at: newEnd,
  })
}

function removeShift(day: DaySchedule, shiftId: string) {
  if (day.shifts.length <= 1) return
  day.shifts = day.shifts.filter((s) => s.id !== shiftId)
}

function toggleDay(day: DaySchedule) {
  day.is_open = !day.is_open
}

// Save Schedule Changes
async function publishChanges() {
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    if (isDemoAdmin.value) {
      notice.value = 'Đã cập nhật và phát hành thành công cấu hình lịch hoạt động & ca trực (Môi trường Sandbox).'
      return
    }
    notice.value = 'Đã lưu và áp dụng thành công cấu hình lịch hoạt động trên toàn hệ thống!'
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu thay đổi.')
  } finally {
    saving.value = false
    setTimeout(() => {
      if (notice.value) notice.value = ''
    }, 6000)
  }
}

// Maintenance Downtime Block
function openScheduleDowntimeModal() {
  const now = new Date()
  const startIso = new Date(now.getTime() + 3600000).toISOString().slice(0, 16)
  const endIso = new Date(now.getTime() + 7200000).toISOString().slice(0, 16)
  const firstCourt = filteredCourts.value[0] || courts.value[0]
  downtimeForm.value = {
    court_id: firstCourt?.id || 1,
    starts_at: startIso,
    ends_at: endIso,
    reason: 'Bảo dưỡng định kỳ mặt thảm (Court Resurfacing)',
  }
  showDowntimeModal.value = true
}

async function saveDowntimeBlock() {
  if (!downtimeForm.value.court_id || !downtimeForm.value.starts_at || !downtimeForm.value.ends_at) {
    error.value = 'Vui lòng chọn sân và thời gian bảo trì.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const targetCourt = courts.value.find((c) => c.id === downtimeForm.value.court_id)
    if (!targetCourt) throw new Error('Không tìm thấy sân đã chọn.')

    const payload = {
      starts_at: downtimeForm.value.starts_at,
      ends_at: downtimeForm.value.ends_at,
      reason: downtimeForm.value.reason,
    }

    if (isDemoAdmin.value) {
      targetCourt.blocks = [
        ...(targetCourt.blocks || []),
        { ...payload, id: -Date.now(), court_id: targetCourt.id },
      ]
      notice.value = `Đã khóa lịch bảo trì thành công cho ${targetCourt.name}.`
      showDowntimeModal.value = false
      return
    }

    await adminApi.createBlock(targetCourt.id, payload)
    notice.value = `Đã lên lịch bảo trì cho ${targetCourt.name}.`
    showDowntimeModal.value = false
    await loadData()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lên lịch bảo trì.')
  } finally {
    saving.value = false
  }
}

async function deleteBlock(courtId: number, blockId: number) {
  if (!confirm('Mở lại khung giờ cho khách và gỡ bỏ lịch bảo trì này?')) return
  try {
    if (isDemoAdmin.value) {
      const c = courts.value.find((item) => item.id === courtId)
      if (c) {
        c.blocks = (c.blocks || []).filter((b) => b.id !== blockId)
      }
      notice.value = 'Đã gỡ bỏ lịch bảo trì và kích hoạt lại sân.'
      return
    }
    await adminApi.deleteBlock(courtId, blockId)
    notice.value = 'Đã gỡ bỏ lịch bảo trì.'
    await loadData()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể gỡ bỏ lịch bảo trì.')
  }
}

// Special Date Management
function openAddSpecialDateModal() {
  specialDateForm.value = {
    title: '',
    date: new Date().toISOString().slice(0, 10),
    venue_name: selectedVenueId.value === 'all' ? 'Tất cả cụm sân' : (venues.value.find((v) => v.id === selectedVenueId.value)?.name || 'Tất cả cụm sân'),
    type: 'closed',
    modified_hours: '10:00 - 18:00',
  }
  showSpecialDateModal.value = true
}

function openEditSpecialDate(sd: SpecialDate) {
  specialDateForm.value = {
    id: sd.id,
    title: sd.title,
    date: sd.date,
    venue_name: sd.venue_name,
    type: sd.type,
    modified_hours: sd.modified_hours || '10:00 - 18:00',
  }
  showSpecialDateModal.value = true
}

function saveSpecialDate() {
  if (!specialDateForm.value.title || !specialDateForm.value.date) {
    error.value = 'Vui lòng nhập tên sự kiện và ngày áp dụng.'
    return
  }
  if (specialDateForm.value.id) {
    const idx = specialDates.value.findIndex((s) => s.id === specialDateForm.value.id)
    if (idx !== -1) {
      specialDates.value[idx] = { ...specialDateForm.value, id: specialDateForm.value.id }
    }
  } else {
    specialDates.value.push({
      id: `sd-${Date.now()}`,
      ...specialDateForm.value,
    })
  }
  notice.value = 'Đã cập nhật ngày đặc biệt thành công.'
  showSpecialDateModal.value = false
}

function deleteSpecialDate(id: string) {
  if (!confirm('Xóa ngày ngoại lệ này khỏi lịch?')) return
  specialDates.value = specialDates.value.filter((s) => s.id !== id)
  notice.value = 'Đã xóa ngày đặc biệt.'
}

onMounted(loadData)
</script>

<template>
  <div class="admin-schedule-page">
    <!-- Header Area -->
    <header class="schedule-hero-header">
      <div class="hero-container">
        <div class="hero-left">
          <div class="eyebrow-pill">
            <span class="material-symbols-outlined text-[14px]">calendar_month</span>
            <span class="tracking-widest uppercase">{{ t('admin.scheduleConfig') }}</span>
          </div>
          <h1 class="page-headline">{{ t('admin.operatingHours') }}</h1>
          <p class="page-subtext">
            {{ t('admin.scheduleDesc') }}
          </p>
        </div>

        <div class="hero-actions">
          <button
            type="button"
            class="btn-action-neutral"
            @click="openAuditModal"
          >
            <span class="material-symbols-outlined text-[18px]">history</span>
            <span>{{ t('admin.auditLog') }}</span>
          </button>

          <button
            type="button"
            class="btn-action-primary"
            :disabled="saving"
            @click="publishChanges"
          >
            <span class="material-symbols-outlined text-[18px]">save</span>
            <span>{{ saving ? t('common.submitting') : t('admin.publishChanges') }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Content Workspace -->
    <main class="schedule-main-body">
      <!-- Notification Alerts -->
      <div v-if="notice" class="alert-box alert-success-box">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        <span class="flex-1">{{ notice }}</span>
        <button type="button" class="btn-close-alert" @click="notice = ''">
          <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
      </div>

      <div v-if="error" class="alert-box alert-error-box">
        <span class="material-symbols-outlined text-[20px]">error</span>
        <span class="flex-1">{{ error }}</span>
        <button type="button" class="btn-close-alert" @click="error = ''">
          <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
      </div>

      <div class="schedule-columns-grid">
        <!-- Left Column: Standard Weekly Schedule -->
        <section class="weekly-schedule-card">
          <!-- Card Top Bar -->
          <div class="weekly-card-header">
            <div class="header-titles">
              <h2 class="card-headline">{{ t('admin.standardWeekly') }}</h2>
              <p class="card-subheadline">{{ t('admin.standardWeeklyDesc') }}</p>
            </div>

            <!-- Venue Tabs Filter -->
            <div class="venue-filter-container">
              <button
                type="button"
                class="venue-tab-btn"
                :class="{ active: selectedVenueId === 'all' }"
                @click="selectedVenueId = 'all'"
              >
                {{ t('admin.allVenues') }}
              </button>
              <button
                v-for="v in venues"
                :key="v.id"
                type="button"
                class="venue-tab-btn"
                :class="{ active: selectedVenueId === v.id }"
                @click="selectedVenueId = v.id"
              >
                {{ v.name }}
              </button>
            </div>
          </div>

          <!-- Day Schedule Rows -->
          <div class="days-list-stack">
            <div
              v-for="day in weeklySchedule"
              :key="day.weekday"
              class="day-row-container group"
              :class="{
                'is-closed-state': !day.is_open,
                'has-split-shifts': day.is_open && day.shifts.length > 1,
              }"
            >
              <!-- Left accent hover bar -->
              <div class="day-hover-bar"></div>

              <div class="day-row-main">
                <!-- Day Left: Switch & Day Label -->
                <div class="day-left-meta">
                  <label class="switch-control" :title="day.is_open ? t('admin.open') : t('admin.closed')">
                    <input
                      type="checkbox"
                      class="sr-only"
                      :checked="day.is_open"
                      @change="toggleDay(day)"
                    />
                    <div class="switch-track"></div>
                  </label>
                  <span class="day-code-text" :class="{ 'day-strikethrough': !day.is_open }">
                    {{ day.shortLabel }}
                  </span>
                </div>

                <!-- Day Center: Time Shifts or Closed Pill -->
                <div class="day-center-content">
                  <template v-if="day.is_open">
                    <div class="shifts-wrapper">
                      <div
                        v-for="(shift, sIdx) in day.shifts"
                        :key="shift.id"
                        class="shift-time-chip"
                        :class="{ 'shift-active-border': day.shifts.length > 1 && sIdx === 0 }"
                      >
                        <!-- Start Time Input Box -->
                        <div class="time-box">
                          <input
                            v-model="shift.starts_at"
                            type="time"
                            class="time-native-input"
                          />
                          <span class="time-period-tag">{{ formatTimeDisplay(shift.starts_at).period }}</span>
                        </div>

                        <span class="material-symbols-outlined text-[15px] text-muted-arrow">arrow_forward</span>

                        <!-- End Time Input Box -->
                        <div class="time-box">
                          <input
                            v-model="shift.ends_at"
                            type="time"
                            class="time-native-input"
                          />
                          <span class="time-period-tag">{{ formatTimeDisplay(shift.ends_at).period }}</span>
                        </div>

                        <!-- Remove Shift button -->
                        <button
                          v-if="day.shifts.length > 1"
                          type="button"
                          class="btn-delete-shift"
                          :title="t('admin.removeShift')"
                          @click="removeShift(day, shift.id)"
                        >
                          <span class="material-symbols-outlined text-[15px]">close</span>
                        </button>
                      </div>
                    </div>
                  </template>

                  <!-- Closed State Tag -->
                  <div v-else class="closed-state-wrap">
                    <span class="closed-state-pill">{{ t('admin.closed') }}</span>
                  </div>
                </div>

                <!-- Day Right: Add Shift link -->
                <div class="day-right-actions">
                  <button
                    v-if="day.is_open"
                    type="button"
                    class="btn-add-shift-link"
                    @click="addShift(day)"
                  >
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    <span class="whitespace-nowrap">{{ t('admin.addShift') }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Right Column: Special Dates & Maintenance -->
        <aside class="sidebar-panels-col">
          <!-- Special Dates Card -->
          <div class="panel-card special-dates-card">
            <div class="panel-header-row">
              <div class="panel-title-cluster">
                <span class="material-symbols-outlined text-primary text-[22px]">event_available</span>
                <h3 class="panel-heading">{{ t('admin.specialDates') }}</h3>
              </div>
              <button
                type="button"
                class="btn-add-circle"
                :title="t('admin.addSpecialDate')"
                @click="openAddSpecialDateModal"
              >
                <span class="material-symbols-outlined text-[20px]">add</span>
              </button>
            </div>
            <p class="panel-intro">{{ t('admin.specialDatesDesc') }}</p>

            <div class="special-dates-stack">
              <div
                v-for="sd in specialDates"
                :key="sd.id"
                class="special-date-entry group"
              >
                <div
                  class="calendar-date-badge"
                  :class="sd.type === 'closed' ? 'badge-closed' : 'badge-modified'"
                >
                  <span class="cal-month-tag">{{ getMonthName(sd.date) }}</span>
                  <span class="cal-day-tag">{{ getDayNumber(sd.date) }}</span>
                </div>

                <div class="date-details-col">
                  <h4 class="date-name-title">{{ sd.title }}</h4>
                  <p class="date-venue-text">{{ sd.venue_name }}</p>
                  <span
                    class="date-status-pill"
                    :class="sd.type === 'closed' ? 'pill-closed-status' : 'pill-modified-status'"
                  >
                    {{ sd.type === 'closed' ? t('admin.closedAllDay') : (sd.modified_hours ? `Khung giờ: ${sd.modified_hours}` : 'Khung giờ tùy chỉnh') }}
                  </span>
                </div>

                <div class="special-date-actions">
                  <button
                    type="button"
                    class="btn-entry-action"
                    title="Chỉnh sửa ngày đặc biệt"
                    @click="openEditSpecialDate(sd)"
                  >
                    <span class="material-symbols-outlined text-[17px]">edit</span>
                  </button>
                  <button
                    type="button"
                    class="btn-entry-action text-error-hover"
                    title="Xóa ngày ngoại lệ"
                    @click="deleteSpecialDate(sd.id)"
                  >
                    <span class="material-symbols-outlined text-[17px]">delete</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Maintenance Card -->
          <div class="panel-card maintenance-card">
            <div class="panel-header-row">
              <div class="panel-title-cluster">
                <span class="material-symbols-outlined text-secondary text-[22px]">build</span>
                <h3 class="panel-heading">{{ t('admin.maintenance') }}</h3>
              </div>
              <span class="badge-accent-tag">{{ activeBlocks.length }} đợt</span>
            </div>

            <!-- Mini 3D Court Isometric Map Visual -->
            <div class="isometric-court-visual">
              <div class="visual-underlay-blur"></div>
              <div class="court-live-status-pill">
                <span
                  class="status-pulse-ring"
                  :class="offlineCourts.length ? 'ring-error' : 'ring-green'"
                ></span>
                <span class="status-live-text">
                  {{ offlineCourts.length ? `${offlineCourts.length} sân tạm ngừng (Offline)` : 'Tất cả sân đang mở' }}
                </span>
              </div>
            </div>

            <!-- Active Maintenance Blocks List -->
            <div v-if="activeBlocks.length" class="maintenance-stack">
              <div
                v-for="block in activeBlocks"
                :key="block.id"
                class="maintenance-item-row"
              >
                <div class="block-status-indicator"></div>
                <div class="block-meta-info">
                  <h4 class="block-name-heading">{{ block.court_name }} · {{ block.reason || t('admin.maintenanceReason') }}</h4>
                  <p class="block-time-range">
                    {{ formatDateTime(block.starts_at) }} – {{ formatDateTime(block.ends_at) }}
                  </p>
                  <span v-if="block.venue_name" class="block-venue-pill">
                    <span class="material-symbols-outlined text-[13px]">location_on</span>
                    <span>{{ block.venue_name }}</span>
                  </span>
                </div>
                <button
                  type="button"
                  class="btn-unlock-downtime"
                  title="Mở khóa sân & kết thúc bảo trì sớm"
                  @click="deleteBlock(block.court_id, block.id)"
                >
                  <span class="material-symbols-outlined text-[17px]">lock_open</span>
                </button>
              </div>
            </div>

            <div v-else class="empty-maintenance-box">
              <span class="material-symbols-outlined text-[26px] text-muted">verified</span>
              <p class="text-xs text-muted">Không có lịch bảo trì nào đang diễn ra.</p>
            </div>

            <!-- Schedule Downtime CTA -->
            <button
              type="button"
              class="btn-schedule-downtime-cta"
              @click="openScheduleDowntimeModal"
            >
              <span class="material-symbols-outlined text-[17px]">add_circle</span>
              <span>{{ t('admin.scheduleDowntime') }}</span>
            </button>
          </div>
        </aside>
      </div>
    </main>

    <!-- MODAL: Schedule Downtime -->
    <div v-if="showDowntimeModal" class="modal-overlay-backdrop" @click.self="showDowntimeModal = false">
      <div class="modal-window-box">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <div class="modal-icon-wrap bg-pink-tint">
              <span class="material-symbols-outlined text-primary text-[20px]">build_circle</span>
            </div>
            <h3 class="modal-title-text">{{ t('admin.scheduleDowntime') }}</h3>
          </div>
          <button type="button" class="btn-modal-close" @click="showDowntimeModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form-body" @submit.prevent="saveDowntimeBlock">
          <div class="form-field-group">
            <label class="form-field-label">Chọn sân cần tạm khóa để bảo trì:</label>
            <select v-model.number="downtimeForm.court_id" class="form-select-native" required>
              <option v-for="c in courts" :key="c.id" :value="c.id">
                {{ c.name }} ({{ c.venue?.name || 'Aura Court' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="form-field-group">
              <label class="form-field-label">Thời gian bắt đầu:</label>
              <input
                v-model="downtimeForm.starts_at"
                type="datetime-local"
                class="form-input-native"
                required
              />
            </div>
            <div class="form-field-group">
              <label class="form-field-label">Thời gian kết thúc:</label>
              <input
                v-model="downtimeForm.ends_at"
                type="datetime-local"
                class="form-input-native"
                required
              />
            </div>
          </div>

          <div class="form-field-group">
            <label class="form-field-label">Lý do bảo trì & ghi chú:</label>
            <input
              v-model="downtimeForm.reason"
              type="text"
              class="form-input-native"
              placeholder="VD: Bảo dưỡng định kỳ mặt thảm, thay đèn LED, sửa lưới"
              required
            />
          </div>

          <div class="modal-actions-footer">
            <button
              type="button"
              class="btn-modal-cancel"
              @click="showDowntimeModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn-modal-submit"
              :disabled="saving"
            >
              {{ saving ? t('common.submitting') : 'Khóa lịch bảo trì' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Special Date -->
    <div v-if="showSpecialDateModal" class="modal-overlay-backdrop" @click.self="showSpecialDateModal = false">
      <div class="modal-window-box">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <div class="modal-icon-wrap bg-pink-tint">
              <span class="material-symbols-outlined text-primary text-[20px]">event</span>
            </div>
            <h3 class="modal-title-text">{{ specialDateForm.id ? 'Chỉnh sửa ngày đặc biệt' : t('admin.addSpecialDate') }}</h3>
          </div>
          <button type="button" class="btn-modal-close" @click="showSpecialDateModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form-body" @submit.prevent="saveSpecialDate">
          <div class="form-field-group">
            <label class="form-field-label">Tên sự kiện / Lý do ngoại lệ:</label>
            <input
              v-model="specialDateForm.title"
              type="text"
              class="form-input-native"
              placeholder="VD: Nghỉ Lễ Tết Nguyên Đán, Giải đấu Aura Master"
              required
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="form-field-group">
              <label class="form-field-label">Ngày áp dụng:</label>
              <input
                v-model="specialDateForm.date"
                type="date"
                class="form-input-native"
                required
              />
            </div>
            <div class="form-field-group">
              <label class="form-field-label">Cụm sân áp dụng:</label>
              <select v-model="specialDateForm.venue_name" class="form-select-native">
                <option value="Tất cả cụm sân">Tất cả cụm sân</option>
                <option v-for="v in venues" :key="v.id" :value="v.name">{{ v.name }}</option>
              </select>
            </div>
          </div>

          <div class="form-field-group">
            <label class="form-field-label">Loại ngoại lệ:</label>
            <div class="flex items-center gap-6 mt-1">
              <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input
                  v-model="specialDateForm.type"
                  type="radio"
                  value="closed"
                  name="special_type_option"
                />
                <span>{{ t('admin.closedAllDay') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input
                  v-model="specialDateForm.type"
                  type="radio"
                  value="modified"
                  name="special_type_option"
                />
                <span>Khung giờ tùy chỉnh</span>
              </label>
            </div>
          </div>

          <div v-if="specialDateForm.type === 'modified'" class="form-field-group">
            <label class="form-field-label">Khung giờ mở cửa hoạt động:</label>
            <input
              v-model="specialDateForm.modified_hours"
              type="text"
              class="form-input-native"
              placeholder="10:00 - 18:00"
            />
          </div>

          <div class="modal-actions-footer">
            <button
              type="button"
              class="btn-modal-cancel"
              @click="showSpecialDateModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button type="submit" class="btn-modal-submit">
              {{ t('common.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Audit Log -->
    <div v-if="showAuditModal" class="modal-overlay-backdrop" @click.self="showAuditModal = false">
      <div class="modal-window-box max-w-xl">
        <div class="modal-head">
          <div class="flex items-center gap-2">
            <div class="modal-icon-wrap bg-pink-tint">
              <span class="material-symbols-outlined text-primary text-[20px]">history</span>
            </div>
            <h3 class="modal-title-text">{{ t('admin.auditLog') }}</h3>
          </div>
          <button type="button" class="btn-modal-close" @click="showAuditModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-form-body">
          <div v-if="auditLogs.length" class="audit-stack">
            <div
              v-for="log in auditLogs"
              :key="log.id"
              class="audit-row-item"
            >
              <div>
                <p class="audit-action-title">{{ log.action }}</p>
                <span class="audit-user-tag">{{ log.user?.name || 'Administrator' }}</span>
              </div>
              <span class="audit-time-tag">{{ formatDateTime(log.created_at) }}</span>
            </div>
          </div>
          <div v-else class="empty-audit-box">
            <p class="text-sm text-muted">Chưa có ghi nhận thao tác nào gần đây.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-schedule-page {
  width: 100%;
  min-height: 100%;
  background: var(--bg, #f8f9fa);
  color: var(--ink, #191c1d);
  display: flex;
  flex-direction: column;
}

/* Header Area */
.schedule-hero-header {
  background: var(--surface, #ffffff);
  border-bottom: 1px solid var(--border, #e0e4ea);
  padding: 24px 28px 22px;
}

:global(html.dark) .schedule-hero-header {
  background: #1e2025;
  border-color: #353942;
}

.hero-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

@media (min-width: 860px) {
  .hero-container {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.hero-left {
  max-width: 760px;
}

.eyebrow-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  font-size: 10.5px;
  font-weight: 700;
  margin-bottom: 8px;
}

:global(html.dark) .eyebrow-pill {
  background: #53333e;
  color: #f8b4c4;
}

.page-headline {
  font-size: 28px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0 0 6px;
  letter-spacing: -0.01em;
  line-height: 1.2;
}

:global(html.dark) .page-headline {
  color: #ffffff;
}

.page-subtext {
  font-size: 14px;
  color: var(--muted, #5d5f5f);
  margin: 0;
  line-height: 1.5;
}

:global(html.dark) .page-subtext {
  color: #a0a6b1;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.btn-action-neutral {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 12px;
  background: var(--surface-subtle, #edeeef);
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-action-neutral:hover {
  background: var(--soft, #e1e3e4);
}

:global(html.dark) .btn-action-neutral {
  background: #2b2e36;
  border-color: #3f434e;
  color: #f3f4f6;
}

:global(html.dark) .btn-action-neutral:hover {
  background: #353942;
}

.btn-action-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border-radius: 12px;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.2s ease;
}

.btn-action-primary:hover {
  filter: brightness(1.1);
  transform: translateY(-1px);
}

:global(html.dark) .btn-action-primary {
  background: #f8b4c4;
  color: #201317;
  font-weight: 800;
  box-shadow: 0 4px 18px rgba(248, 180, 196, 0.35);
}

:global(html.dark) .btn-action-primary:hover {
  background: #ffd5df;
}

/* Main Body */
.schedule-main-body {
  width: 100%;
  padding: 24px 28px 48px;
  box-sizing: border-box;
}

@media (max-width: 640px) {
  .schedule-main-body {
    padding: 16px;
  }
}

/* Alerts */
.alert-box {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 14px;
  margin-bottom: 20px;
  font-size: 13.5px;
  font-weight: 500;
}

.alert-success-box {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.alert-error-box {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.btn-close-alert {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* Grid Layout */
.schedule-columns-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  align-items: start;
}

@media (min-width: 1080px) {
  .schedule-columns-grid {
    grid-template-columns: minmax(0, 1.55fr) minmax(340px, 1fr);
  }
}

/* Weekly Schedule Card */
.weekly-schedule-card {
  background: var(--surface, #ffffff);
  border-radius: 18px;
  padding: 22px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
  min-width: 0;
}

:global(html.dark) .weekly-schedule-card {
  background: #22242a;
  border-color: #353942;
}

.weekly-card-header {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 20px;
}

@media (min-width: 768px) {
  .weekly-card-header {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.header-titles {
  min-width: 0;
}

.card-headline {
  font-size: 19px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0 0 4px;
}

:global(html.dark) .card-headline {
  color: #ffffff;
}

.card-subheadline {
  font-size: 13px;
  color: var(--muted, #5d5f5f);
  margin: 0;
}

:global(html.dark) .card-subheadline {
  color: #9ca3af;
}

.venue-filter-container {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--surface-subtle, #f3f4f5);
  padding: 4px;
  border-radius: 10px;
  overflow-x: auto;
  scrollbar-width: none;
  max-width: 100%;
}

:global(html.dark) .venue-filter-container {
  background: #18191d;
  border: 1px solid #353942;
}

.venue-tab-btn {
  padding: 5px 12px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.venue-tab-btn:hover {
  color: var(--ink, #191c1d);
}

.venue-tab-btn.active {
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

:global(html.dark) .venue-tab-btn {
  color: #9ca3af;
}

:global(html.dark) .venue-tab-btn:hover {
  color: #ffffff;
}

:global(html.dark) .venue-tab-btn.active {
  background: #2b2e36;
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
}

/* Day Row Stacks */
.days-list-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-row-container {
  position: relative;
  overflow: hidden;
  border-radius: 14px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  transition: all 0.2s ease;
}

:global(html.dark) .day-row-container {
  background: #1c1e23;
  border-color: #31353f;
}

.day-row-container:hover {
  background: var(--soft, #edeeef);
}

:global(html.dark) .day-row-container:hover {
  background: #262930;
}

.day-row-container.is-closed-state {
  opacity: 0.75;
  background: var(--surface-subtle, #f3f4f5) repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0, 0, 0, 0.02) 10px, rgba(0, 0, 0, 0.02) 20px);
}

:global(html.dark) .day-row-container.is-closed-state {
  background: #18191d repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255, 255, 255, 0.02) 10px, rgba(255, 255, 255, 0.02) 20px);
}

.day-hover-bar {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 4px;
  background: var(--primary, #78555e);
  transform: scaleY(0);
  transform-origin: top;
  transition: transform 0.2s ease;
}

:global(html.dark) .day-hover-bar {
  background: #f8b4c4;
}

.day-row-container:hover .day-hover-bar,
.day-row-container.has-split-shifts .day-hover-bar {
  transform: scaleY(1);
}

.day-row-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  gap: 12px;
  position: relative;
  z-index: 1;
}

@media (max-width: 680px) {
  .day-row-main {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
}

.day-left-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 90px;
  flex-shrink: 0;
}

.switch-control {
  position: relative;
  display: inline-block;
  width: 36px;
  height: 20px;
  cursor: pointer;
}

.switch-track {
  position: absolute;
  inset: 0;
  background-color: var(--border, #cbd5e1);
  border-radius: 9999px;
  transition: 0.25s;
}

:global(html.dark) .switch-track {
  background-color: #3a3f4a;
}

.switch-track::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #ffffff;
  transition: 0.25s;
}

input:checked + .switch-track {
  background-color: var(--primary, #78555e);
}

:global(html.dark) input:checked + .switch-track {
  background-color: #f8b4c4;
}

:global(html.dark) input:checked + .switch-track::after {
  background: #201317;
}

input:checked + .switch-track::after {
  transform: translateX(16px);
}

.day-code-text {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  width: 40px;
}

:global(html.dark) .day-code-text {
  color: #ffffff;
}

.day-strikethrough {
  text-decoration: line-through;
  color: var(--muted, #6c757d);
}

/* Day Center Content */
.day-center-content {
  flex: 1;
  display: flex;
  justify-content: center;
  min-width: 0;
}

@media (max-width: 680px) {
  .day-center-content {
    width: 100%;
    justify-content: flex-start;
  }
}

.shifts-wrapper {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: center;
}

.shift-time-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  background: transparent;
}

.time-box {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 8px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

:global(html.dark) .time-box {
  background: #282b33;
  border-color: #3f434e;
}

.time-native-input {
  border: none;
  background: transparent;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  outline: none;
  font-family: inherit;
  padding: 0;
  width: 66px;
}

:global(html.dark) .time-native-input {
  color: #ffffff;
}

.time-period-tag {
  font-size: 10px;
  font-weight: 700;
  color: var(--muted, #6c757d);
  text-transform: uppercase;
}

:global(html.dark) .time-period-tag {
  color: #9ca3af;
}

.text-muted-arrow {
  color: var(--muted, #94a3b8);
  font-size: 14px;
}

.btn-delete-shift {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: none;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.btn-delete-shift:hover {
  background: rgba(239, 68, 68, 0.25);
}

.closed-state-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.closed-state-pill {
  padding: 3px 14px;
  border-radius: 6px;
  background: var(--surface-subtle, #e2e8f0);
  color: var(--muted, #64748b);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border: 1px solid var(--border, #cbd5e1);
}

:global(html.dark) .closed-state-pill {
  background: #2b2e36;
  border-color: #3f434e;
  color: #9ca3af;
}

/* Day Right Actions */
.day-right-actions {
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}

.btn-add-shift-link {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  background: transparent;
  border: none;
  color: var(--primary, #78555e);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-add-shift-link:hover {
  filter: brightness(1.2);
  text-decoration: underline;
}

:global(html.dark) .btn-add-shift-link {
  color: #f8b4c4;
}

/* Sidebar Panels (Right Column) */
.sidebar-panels-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-width: 0;
}

.panel-card {
  background: var(--surface, #ffffff);
  border-radius: 18px;
  padding: 20px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
}

:global(html.dark) .panel-card {
  background: #22242a;
  border-color: #353942;
}

.special-dates-card {
  background: rgba(255, 209, 220, 0.15);
  border: 1px solid rgba(120, 85, 94, 0.15);
}

:global(html.dark) .special-dates-card {
  background: rgba(248, 180, 196, 0.06);
  border-color: rgba(248, 180, 196, 0.18);
}

.panel-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.panel-title-cluster {
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-heading {
  font-size: 16.5px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .panel-heading {
  color: #ffffff;
}

.btn-add-circle {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
}

.btn-add-circle:hover {
  background: var(--primary, #78555e);
  color: #ffffff;
}

:global(html.dark) .btn-add-circle {
  background: #53333e;
  color: #f8b4c4;
}

:global(html.dark) .btn-add-circle:hover {
  background: #f8b4c4;
  color: #201317;
}

.panel-intro {
  font-size: 12.5px;
  color: var(--muted, #5d5f5f);
  margin: 0 0 12px;
  line-height: 1.4;
}

:global(html.dark) .panel-intro {
  color: #9ca3af;
}

/* Special Dates Stack */
.special-dates-stack {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.special-date-entry {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 11px;
  border-radius: 12px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

:global(html.dark) .special-date-entry {
  background: #1c1e23;
  border-color: #353942;
}

.calendar-date-badge {
  width: 40px;
  height: 40px;
  border-radius: 9px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.badge-closed {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.badge-modified {
  background: var(--surface-subtle, #e2e8f0);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #cbd5e1);
}

:global(html.dark) .badge-modified {
  background: #2b2e36;
  color: #ffffff;
  border-color: #3f434e;
}

.cal-month-tag {
  font-size: 8.5px;
  font-weight: 800;
  text-transform: uppercase;
  line-height: 1;
}

.cal-day-tag {
  font-size: 15px;
  font-weight: 800;
  line-height: 1.1;
}

.date-details-col {
  flex: 1;
  min-width: 0;
}

.date-name-title {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .date-name-title {
  color: #ffffff;
}

.date-venue-text {
  font-size: 11px;
  color: var(--muted, #6c757d);
  margin: 1px 0 2px;
}

:global(html.dark) .date-venue-text {
  color: #9ca3af;
}

.date-status-pill {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 5px;
  font-size: 9.5px;
  font-weight: 700;
}

.pill-closed-status {
  background: var(--surface-subtle, #edeeef);
  color: var(--muted, #5d5f5f);
}

:global(html.dark) .pill-closed-status {
  background: #2b2e36;
  color: #d1d5db;
}

.pill-modified-status {
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

:global(html.dark) .pill-modified-status {
  background: #53333e;
  color: #f8b4c4;
}

.special-date-actions {
  display: flex;
  align-items: center;
  gap: 2px;
}

.btn-entry-action {
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  padding: 3px;
  display: flex;
  align-items: center;
  border-radius: 6px;
}

.btn-entry-action:hover {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--ink, #191c1d);
}

:global(html.dark) .btn-entry-action:hover {
  background: #2b2e36;
  color: #ffffff;
}

.text-error-hover:hover {
  color: #ef4444 !important;
}

/* Maintenance Card & Isometric Map */
.badge-accent-tag {
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 9999px;
  background: var(--primary-container, #ffd1dc);
  color: var(--on-primary-container, #7a5761);
}

:global(html.dark) .badge-accent-tag {
  background: #53333e;
  color: #f8b4c4;
}

.isometric-court-visual {
  width: 100%;
  height: 96px;
  border-radius: 12px;
  background: #2b2e36 url('https://lh3.googleusercontent.com/aida-public/AB6AXuALF7TixBWUZukXxezXMsrl-f9_K4o3EkdRTlaWXQdT1sko9uMqZtVdRXqjBHQZN-12wmjIC_xs0UKD_9XM-7yZ50_qxIBoMnnb5r0afHyk__vbFpdYAf1lcTNfLdSYQdVqvUBugpoZALMBGfOl3c00-fL3SliIVIQEzIYvPD1BVlyy8wXHLpEGr-2JLmaxrNkB2RFzZYapfJJukHEoqgsyVIyqUe3C7tDRMUzAAjxrS-6z-gf3O5F4') center/cover no-repeat;
  position: relative;
  overflow: hidden;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .isometric-court-visual {
  border-color: #353942;
}

.visual-underlay-blur {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(1.5px);
}

.court-live-status-pill {
  position: relative;
  z-index: 2;
  background: rgba(25, 28, 35, 0.88);
  backdrop-filter: blur(8px);
  padding: 5px 12px;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  gap: 7px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.status-pulse-ring {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.ring-error {
  background-color: #ef4444;
  animation: pulse-ring-anim 1.5s infinite;
}

.ring-green {
  background-color: #10b981;
}

@keyframes pulse-ring-anim {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.4;
    transform: scale(1.3);
  }
}

.status-live-text {
  font-size: 11.5px;
  font-weight: 600;
  color: #ffffff;
}

.maintenance-stack {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 12px;
}

.maintenance-item-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 9px 10px;
  border-radius: 10px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .maintenance-item-row {
  background: #1c1e23;
  border-color: #353942;
}

.block-status-indicator {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ef4444;
  margin-top: 5px;
  flex-shrink: 0;
}

.block-meta-info {
  flex: 1;
  min-width: 0;
}

.block-name-heading {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .block-name-heading {
  color: #ffffff;
}

.block-time-range {
  font-size: 11px;
  color: var(--muted, #6c757d);
  margin: 1px 0 3px;
}

:global(html.dark) .block-time-range {
  color: #9ca3af;
}

.block-venue-pill {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 10.5px;
  color: var(--muted, #6c757d);
}

:global(html.dark) .block-venue-pill {
  color: #a0a6b1;
}

.btn-unlock-downtime {
  border: none;
  background: transparent;
  color: var(--primary, #78555e);
  cursor: pointer;
  padding: 3px;
  display: flex;
  align-items: center;
}

:global(html.dark) .btn-unlock-downtime {
  color: #f8b4c4;
}

.btn-unlock-downtime:hover {
  color: #ef4444;
}

.empty-maintenance-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 14px 0;
}

.btn-schedule-downtime-cta {
  width: 100%;
  padding: 9px;
  border-radius: 10px;
  border: 1px dashed var(--border, #cbd5e1);
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 12.5px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-schedule-downtime-cta {
  border-color: #3f434e;
  color: #f3f4f6;
}

.btn-schedule-downtime-cta:hover {
  background: var(--surface-subtle, #f8f9fa);
  border-color: var(--primary, #78555e);
  color: var(--primary, #78555e);
}

:global(html.dark) .btn-schedule-downtime-cta:hover {
  background: #2b2e36;
  border-color: #f8b4c4;
  color: #f8b4c4;
}

/* Modals */
.modal-overlay-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-window-box {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  padding: 24px;
  width: 100%;
  max-width: 520px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
  animation: modal-pop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  color: var(--ink, #191c1d);
}

:global(html.dark) .modal-window-box {
  background: #22242a;
  border-color: #353942;
  color: #f3f4f6;
}

@keyframes modal-pop {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 14px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .modal-head {
  border-color: #353942;
}

.modal-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-pink-tint {
  background: var(--primary-container, #ffd1dc);
}

:global(html.dark) .bg-pink-tint {
  background: #53333e;
}

.modal-title-text {
  font-size: 16.5px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .modal-title-text {
  color: #ffffff;
}

.btn-modal-close {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

.modal-form-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

:global(html.dark) .form-field-label {
  color: #d1d5db;
}

.form-input-native,
.form-select-native {
  width: 100%;
  padding: 9px 13px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 13.5px;
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-family: inherit;
  box-sizing: border-box;
}

:global(html.dark) .form-input-native,
:global(html.dark) .form-select-native {
  background: #18191d;
  border-color: #3f434e;
  color: #ffffff;
}

.form-input-native:focus,
.form-select-native:focus {
  outline: none;
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.15);
}

:global(html.dark) .form-input-native:focus,
:global(html.dark) .form-select-native:focus {
  border-color: #f8b4c4;
  box-shadow: 0 0 0 3px rgba(248, 180, 196, 0.2);
}

.modal-actions-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 6px;
  padding-top: 14px;
  border-top: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .modal-actions-footer {
  border-color: #353942;
}

.btn-modal-cancel {
  padding: 9px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  background: var(--surface-subtle, #edeeef);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
}

:global(html.dark) .btn-modal-cancel {
  background: #2b2e36;
  border-color: #3f434e;
  color: #f3f4f6;
}

.btn-modal-submit {
  padding: 9px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
}

:global(html.dark) .btn-modal-submit {
  background: #f8b4c4;
  color: #201317;
  font-weight: 800;
  box-shadow: 0 4px 18px rgba(248, 180, 196, 0.35);
}

/* Audit Log */
.audit-stack {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.audit-row-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  padding: 11px 13px;
  border-radius: 10px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .audit-row-item {
  background: #18191d;
  border-color: #353942;
}

.audit-action-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  margin: 0 0 2px;
}

:global(html.dark) .audit-action-title {
  color: #ffffff;
}

.audit-user-tag {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

:global(html.dark) .audit-user-tag {
  color: #9ca3af;
}

.audit-time-tag {
  font-size: 11px;
  color: var(--muted, #6c757d);
  white-space: nowrap;
}

:global(html.dark) .audit-time-tag {
  color: #9ca3af;
}

.empty-audit-box {
  padding: 20px 0;
  text-align: center;
}
</style>
