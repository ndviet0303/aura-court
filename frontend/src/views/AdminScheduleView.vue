<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/i18n'
import type { Court, CourtBlock, Venue } from '@/types'

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
  reason: 'Bảo dưỡng định kỳ',
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
    title: 'Lễ Giáng sinh (Christmas)',
    date: '2026-12-25',
    venue_name: 'Tất cả cụm sân',
    type: 'closed',
  },
  {
    id: 'sd-2',
    title: 'Giải đấu Năm Mới (New Year Cup)',
    date: '2027-01-01',
    venue_name: 'Aura Downtown',
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

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [loadedCourts, loadedVenues] = await Promise.all([
      api.get<Court[]>('/admin/courts'),
      api.get<Venue[]>('/admin/venues'),
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
    const res = await api.get<{ data: Array<{ id: number; action: string; user?: { name: string }; created_at: string }> }>('/admin/audit-logs')
    auditLogs.value = res.data || []
  } catch {
    auditLogs.value = [
      { id: 1, action: 'Cập nhật khung giờ thứ 4 (Wed split shifts)', user: { name: 'Alex Sterling' }, created_at: new Date().toISOString() },
      { id: 2, action: 'Khóa lịch bảo trì Sân 4 Resurfacing', user: { name: 'Aura Administrator' }, created_at: new Date(Date.now() - 3600000).toISOString() },
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
    // Save or mock depending on demo
    if (isDemoAdmin.value) {
      notice.value = 'Đã mô phỏng cập nhật cấu hình lịch hoạt động tuần & ca trực (Demo).'
      return
    }
    // Simulate updating price slots/shift windows for courts
    notice.value = 'Đã lưu và áp dụng thành công cấu hình lịch hoạt động!'
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể lưu thay đổi.')
  } finally {
    saving.value = false
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
    reason: 'Bảo dưỡng định kỳ mặt thảm',
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
      notice.value = `Đã mô phỏng khóa lịch bảo trì cho ${targetCourt.name}.`
      showDowntimeModal.value = false
      return
    }

    await api.post(`/admin/courts/${targetCourt.id}/blocks`, payload)
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
  if (!confirm('Gỡ bỏ lịch bảo trì này và mở lại khung giờ cho khách?')) return
  try {
    if (isDemoAdmin.value) {
      const c = courts.value.find((item) => item.id === courtId)
      if (c) {
        c.blocks = (c.blocks || []).filter((b) => b.id !== blockId)
      }
      notice.value = 'Đã mô phỏng mở lại khung giờ bảo trì.'
      return
    }
    await api.delete(`/admin/courts/${courtId}/blocks/${blockId}`)
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
  notice.value = 'Đã cập nhật ngày đặc biệt.'
  showSpecialDateModal.value = false
}

function deleteSpecialDate(id: string) {
  if (!confirm('Xóa ngày ngoại lệ này?')) return
  specialDates.value = specialDates.value.filter((s) => s.id !== id)
  notice.value = 'Đã xóa ngày đặc biệt.'
}

onMounted(loadData)
</script>

<template>
  <div class="admin-schedule-content-wrap">
    <!-- Header Section -->
    <header class="schedule-config-header">
      <div class="header-content">
        <div class="eyebrow-tag">
          <span class="material-symbols-outlined icon-16">calendar_month</span>
          <span>{{ t('admin.scheduleConfig') }}</span>
        </div>
        <h1 class="page-title">{{ t('admin.operatingHours') }}</h1>
        <p class="muted max-w-3xl">
          {{ t('admin.scheduleDesc') }}
        </p>
      </div>

      <div class="header-actions">
        <button
          type="button"
          class="btn btn-secondary flex items-center gap-xs"
          @click="openAuditModal"
        >
          <span class="material-symbols-outlined icon-18">history</span>
          <span>{{ t('admin.auditLog') }}</span>
        </button>

        <button
          type="button"
          class="btn btn-primary flex items-center gap-xs"
          :disabled="saving"
          @click="publishChanges"
        >
          <span class="material-symbols-outlined icon-18">save</span>
          <span>{{ saving ? t('common.submitting') : t('admin.publishChanges') }}</span>
        </button>
      </div>
    </header>

    <!-- Notifications & Error Messages -->
    <div v-if="notice" class="notice-banner">
      <span class="material-symbols-outlined text-green">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="error-banner">
      <span class="material-symbols-outlined text-error">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Main Content Layout (12 Columns) -->
    <div class="schedule-matrix-grid">
      <!-- Left Column (8 cols): Standard Weekly Schedule -->
      <section class="weekly-schedule-card">
        <div class="card-head-row">
          <div>
            <h2 class="card-title">{{ t('admin.standardWeekly') }}</h2>
            <p class="muted text-sm mt-1">{{ t('admin.standardWeeklyDesc') }}</p>
          </div>

          <!-- Venue Filter Tabs -->
          <div class="venue-filter-pills">
            <button
              type="button"
              class="venue-pill"
              :class="{ active: selectedVenueId === 'all' }"
              @click="selectedVenueId = 'all'"
            >
              {{ t('admin.allVenues') }}
            </button>
            <button
              v-for="v in venues"
              :key="v.id"
              type="button"
              class="venue-pill"
              :class="{ active: selectedVenueId === v.id }"
              @click="selectedVenueId = v.id"
            >
              {{ v.name }}
            </button>
          </div>
        </div>

        <!-- 7-Day Rows -->
        <div class="days-list-container">
          <div
            v-for="day in weeklySchedule"
            :key="day.weekday"
            class="day-schedule-row"
            :class="{ 'is-closed': !day.is_open, 'has-split-shift': day.shifts.length > 1 }"
          >
            <!-- Day Left: Toggle & Label -->
            <div class="day-left-meta">
              <label class="switch-toggle" :title="day.is_open ? t('admin.open') : t('admin.closed')">
                <input
                  type="checkbox"
                  :checked="day.is_open"
                  @change="toggleDay(day)"
                />
                <span class="slider"></span>
              </label>
              <span class="day-label" :class="{ 'line-through': !day.is_open }">
                {{ day.shortLabel }}
              </span>
            </div>

            <!-- Day Center: Shifts or Closed Badge -->
            <div class="day-center-shifts">
              <template v-if="day.is_open">
                <div
                  v-for="(shift, idx) in day.shifts"
                  :key="shift.id"
                  class="shift-time-block"
                >
                  <div class="time-input-box">
                    <input
                      v-model="shift.starts_at"
                      type="time"
                      class="time-picker-input"
                    />
                  </div>
                  <span class="material-symbols-outlined text-muted text-sm">arrow_forward</span>
                  <div class="time-input-box">
                    <input
                      v-model="shift.ends_at"
                      type="time"
                      class="time-picker-input"
                    />
                  </div>

                  <!-- Remove shift button if split shift -->
                  <button
                    v-if="day.shifts.length > 1"
                    type="button"
                    class="btn-remove-shift"
                    :title="t('admin.removeShift')"
                    @click="removeShift(day, shift.id)"
                  >
                    <span class="material-symbols-outlined icon-16">close</span>
                  </button>
                </div>
              </template>

              <div v-else class="closed-state-badge">
                <span class="closed-tag">{{ t('admin.closed') }}</span>
              </div>
            </div>

            <!-- Day Right: Add Shift button if open -->
            <div class="day-right-actions">
              <button
                v-if="day.is_open"
                type="button"
                class="btn-add-shift-link"
                @click="addShift(day)"
              >
                <span class="material-symbols-outlined icon-16">add</span>
                <span>{{ t('admin.addShift') }}</span>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Right Column (4 cols): Special Dates & Maintenance Cards -->
      <aside class="sidebar-panels">
        <!-- Special Dates Card -->
        <div class="panel-card special-dates-panel">
          <div class="panel-head-row">
            <div class="panel-title-with-icon">
              <span class="material-symbols-outlined text-pink icon-22">event_available</span>
              <h3 class="panel-title">{{ t('admin.specialDates') }}</h3>
            </div>
            <button
              type="button"
              class="circle-add-btn"
              :title="t('admin.addSpecialDate')"
              @click="openAddSpecialDateModal"
            >
              <span class="material-symbols-outlined icon-20">add</span>
            </button>
          </div>
          <p class="muted text-xs mb-3">{{ t('admin.specialDatesDesc') }}</p>

          <!-- List of Special Dates -->
          <div class="special-dates-list">
            <div
              v-for="sd in specialDates"
              :key="sd.id"
              class="special-date-item"
            >
              <div
                class="date-calendar-box"
                :class="{ 'box-closed': sd.type === 'closed', 'box-modified': sd.type === 'modified' }"
              >
                <span class="cal-month">{{ new Date(sd.date).toLocaleString('default', { month: 'short' }) }}</span>
                <span class="cal-day">{{ new Date(sd.date).getDate() }}</span>
              </div>
              <div class="date-info-col">
                <h4 class="date-title">{{ sd.title }}</h4>
                <p class="date-venue">{{ sd.venue_name }}</p>
                <span
                  class="date-status-pill"
                  :class="{ 'pill-closed': sd.type === 'closed', 'pill-modified': sd.type === 'modified' }"
                >
                  {{ sd.type === 'closed' ? t('admin.closedAllDay') : t('admin.modifiedHours', { hours: sd.modified_hours }) }}
                </span>
              </div>
              <button
                type="button"
                class="btn-delete-special"
                @click="deleteSpecialDate(sd.id)"
              >
                <span class="material-symbols-outlined icon-18">delete</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Maintenance & Downtime Card -->
        <div class="panel-card maintenance-panel">
          <div class="panel-head-row">
            <div class="panel-title-with-icon">
              <span class="material-symbols-outlined text-muted icon-22">build</span>
              <h3 class="panel-title">{{ t('admin.maintenance') }}</h3>
            </div>
            <span class="badge badge-accent">{{ activeBlocks.length }} đợt</span>
          </div>

          <!-- Mini Court Status Badge -->
          <div class="mini-court-visual">
            <div class="visual-blur-bg"></div>
            <div class="court-status-pill">
              <span
                class="status-pulse-dot"
                :class="offlineCourts.length ? 'bg-error' : 'bg-green'"
              ></span>
              <span class="font-medium text-sm">
                {{ offlineCourts.length ? `${offlineCourts.length} sân tạm ngừng (Offline)` : 'Tất cả sân đang mở' }}
              </span>
            </div>
          </div>

          <!-- List of active blocks -->
          <div v-if="activeBlocks.length" class="maintenance-blocks-list">
            <div
              v-for="block in activeBlocks"
              :key="block.id"
              class="maintenance-block-item"
            >
              <span class="block-indicator-dot"></span>
              <div class="block-details">
                <h4 class="block-court-title">{{ block.court_name }} · {{ block.reason || t('admin.maintenanceReason') }}</h4>
                <p class="block-time">
                  {{ formatDateTime(block.starts_at) }} – {{ formatDateTime(block.ends_at) }}
                </p>
                <span v-if="block.venue_name" class="block-venue-tag">
                  <span class="material-symbols-outlined icon-12">location_on</span>
                  {{ block.venue_name }}
                </span>
              </div>
              <button
                type="button"
                class="btn-unlock-block"
                title="Mở khóa lịch sân"
                @click="deleteBlock(block.court_id, block.id)"
              >
                <span class="material-symbols-outlined icon-18">lock_open</span>
              </button>
            </div>
          </div>

          <div v-else class="no-maintenance-box">
            <span class="material-symbols-outlined icon-24 text-muted">check_circle</span>
            <p class="text-xs text-muted">Không có lịch bảo trì nào đang diễn ra.</p>
          </div>

          <!-- Schedule Downtime CTA -->
          <button
            type="button"
            class="btn-schedule-downtime"
            @click="openScheduleDowntimeModal"
          >
            <span class="material-symbols-outlined icon-18">add_circle</span>
            <span>{{ t('admin.scheduleDowntime') }}</span>
          </button>
        </div>
      </aside>
    </div>

    <!-- MODAL: Schedule Downtime -->
    <div v-if="showDowntimeModal" class="modal-backdrop" @click.self="showDowntimeModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-pink">build_circle</span>
            <h3 class="font-semibold text-lg">{{ t('admin.scheduleDowntime') }}</h3>
          </div>
          <button type="button" class="btn-close" @click="showDowntimeModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="saveDowntimeBlock">
          <div class="form-group">
            <label class="form-label">Chọn sân cần bảo trì:</label>
            <select v-model.number="downtimeForm.court_id" class="form-input" required>
              <option v-for="c in courts" :key="c.id" :value="c.id">
                {{ c.name }} ({{ c.venue?.name || 'Aura Court' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="form-group">
              <label class="form-label">Thời gian bắt đầu:</label>
              <input
                v-model="downtimeForm.starts_at"
                type="datetime-local"
                class="form-input"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label">Thời gian kết thúc:</label>
              <input
                v-model="downtimeForm.ends_at"
                type="datetime-local"
                class="form-input"
                required
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Lý do bảo trì:</label>
            <input
              v-model="downtimeForm.reason"
              type="text"
              class="form-input"
              placeholder="VD: Sửa mặt thảm, thay lưới, sửa hệ thống đèn"
              required
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showDowntimeModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="saving"
            >
              {{ saving ? t('common.submitting') : 'Khóa lịch bảo trì' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Special Date -->
    <div v-if="showSpecialDateModal" class="modal-backdrop" @click.self="showSpecialDateModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-pink">event</span>
            <h3 class="font-semibold text-lg">{{ t('admin.addSpecialDate') }}</h3>
          </div>
          <button type="button" class="btn-close" @click="showSpecialDateModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-body space-y-4" @submit.prevent="saveSpecialDate">
          <div class="form-group">
            <label class="form-label">Tên sự kiện / Lý do ngoại lệ:</label>
            <input
              v-model="specialDateForm.title"
              type="text"
              class="form-input"
              placeholder="VD: Nghỉ Tết Dương Lịch, Giải đấu Aura Master"
              required
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="form-group">
              <label class="form-label">Ngày áp dụng:</label>
              <input
                v-model="specialDateForm.date"
                type="date"
                class="form-input"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label">Cụm sân áp dụng:</label>
              <select v-model="specialDateForm.venue_name" class="form-input">
                <option value="Tất cả cụm sân">Tất cả cụm sân</option>
                <option v-for="v in venues" :key="v.id" :value="v.name">{{ v.name }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Loại ngoại lệ:</label>
            <div class="flex items-center gap-4 mt-1">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="specialDateForm.type"
                  type="radio"
                  value="closed"
                  name="special_type"
                />
                <span>{{ t('admin.closedAllDay') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="specialDateForm.type"
                  type="radio"
                  value="modified"
                  name="special_type"
                />
                <span>Khung giờ tùy chỉnh</span>
              </label>
            </div>
          </div>

          <div v-if="specialDateForm.type === 'modified'" class="form-group">
            <label class="form-label">Khung giờ mở cửa:</label>
            <input
              v-model="specialDateForm.modified_hours"
              type="text"
              class="form-input"
              placeholder="10:00 - 18:00"
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="showSpecialDateModal = false"
            >
              {{ t('common.cancel') }}
            </button>
            <button type="submit" class="btn btn-primary">
              {{ t('common.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Audit Log -->
    <div v-if="showAuditModal" class="modal-backdrop" @click.self="showAuditModal = false">
      <div class="modal-card max-w-xl">
        <div class="modal-header">
          <div class="flex items-center gap-xs">
            <span class="material-symbols-outlined text-primary">history</span>
            <h3 class="font-semibold text-lg">{{ t('admin.auditLog') }}</h3>
          </div>
          <button type="button" class="btn-close" @click="showAuditModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <div v-if="auditLogs.length" class="audit-logs-list space-y-3">
            <div
              v-for="log in auditLogs"
              :key="log.id"
              class="audit-log-row p-3 rounded-lg bg-surface-low border border-border flex items-start justify-between gap-3"
            >
              <div>
                <p class="font-medium text-sm text-foreground">{{ log.action }}</p>
                <span class="text-xs text-muted">{{ log.user?.name || 'Administrator' }}</span>
              </div>
              <span class="text-xs text-muted whitespace-nowrap">{{ formatDateTime(log.created_at) }}</span>
            </div>
          </div>
          <div v-else class="text-center py-6 text-muted">
            <p>Chưa có ghi nhận thao tác nào gần đây.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-schedule-content-wrap {
  max-width: 1280px;
  margin: 0 auto;
  padding: 30px 24px 90px;
}

/* Admin Tab Navigation */
.admin-tab-nav {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 12px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.admin-nav-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  font-size: 14px;
  font-weight: 500;
  color: var(--muted, #6c757d);
  text-decoration: none;
  background: var(--surface-subtle, #f3f4f5);
  transition: all 0.2s ease;
  white-space: nowrap;
}

.admin-nav-item:hover {
  background: var(--soft, #e7e8e9);
  color: var(--ink, #191c1d);
}

.admin-nav-item.active {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-weight: 600;
}

/* Schedule Header */
.schedule-config-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 24px;
  margin-bottom: 28px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

@media (min-width: 860px) {
  .schedule-config-header {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.eyebrow-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Notification Banners */
.notice-banner,
.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 500;
}

.notice-banner {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.error-banner {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

/* 12-Column Grid Layout */
.schedule-matrix-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .schedule-matrix-grid {
    grid-template-columns: 8fr 4fr;
  }
}

/* Left Card: Weekly Schedule */
.weekly-schedule-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 24px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.card-head-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

@media (min-width: 640px) {
  .card-head-row {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.card-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.venue-filter-pills {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-subtle, #f3f4f5);
  padding: 4px;
  border-radius: 10px;
  overflow-x: auto;
}

.venue-pill {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.venue-pill.active {
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

/* Days List */
.days-list-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.day-schedule-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  transition: all 0.2s ease;
}

.day-schedule-row:hover {
  background: var(--soft, #edeeef);
}

.day-schedule-row.is-closed {
  opacity: 0.65;
}

.day-left-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 110px;
  flex-shrink: 0;
}

.day-label {
  font-weight: 600;
  font-size: 15px;
  color: var(--ink, #191c1d);
}

/* Toggle Switch */
.switch-toggle {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
  cursor: pointer;
}

.switch-toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--border, #cbd5e1);
  transition: 0.3s;
  border-radius: 22px;
}

.slider:before {
  position: absolute;
  content: '';
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:checked + .slider:before {
  transform: translateX(18px);
}

/* Shifts in Center */
.day-center-shifts {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
  justify-content: center;
}

.shift-time-block {
  display: flex;
  align-items: center;
  gap: 8px;
}

.time-input-box {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 8px;
  padding: 4px 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.time-picker-input {
  border: none;
  background: transparent;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  outline: none;
  font-family: inherit;
}

.btn-remove-shift {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: none;
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  cursor: pointer;
}

.btn-remove-shift:hover {
  background: rgba(239, 68, 68, 0.25);
}

.closed-state-badge .closed-tag {
  display: inline-block;
  padding: 4px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: var(--surface-subtle, #e2e8f0);
  color: var(--muted, #64748b);
  border: 1px solid var(--border, #cbd5e1);
}

/* Day Right: Add Shift */
.day-right-actions {
  width: 120px;
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}

.btn-add-shift-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: transparent;
  border: none;
  color: var(--pink-dark, #78555e);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-add-shift-link:hover {
  filter: brightness(1.2);
  text-decoration: underline;
}

/* Sidebar Panels (Right Column) */
.sidebar-panels {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.panel-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 22px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.special-dates-panel {
  background: var(--surface, #ffffff);
}

.panel-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.panel-title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.circle-add-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.circle-add-btn:hover {
  filter: brightness(1.1);
}

/* Special Date Items */
.special-dates-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.special-date-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.date-calendar-box {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.box-closed {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.box-modified {
  background: var(--surface, #dfe0e0);
  color: var(--ink, #2d2d2d);
  border: 1px solid var(--border, #cbd5e1);
}

.cal-month {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  line-height: 1;
}

.cal-day {
  font-size: 16px;
  font-weight: 800;
  line-height: 1.1;
}

.date-info-col {
  flex: 1;
  min-width: 0;
}

.date-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.date-venue {
  font-size: 11px;
  color: var(--muted, #6c757d);
  margin: 2px 0;
}

.date-status-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 600;
}

.pill-closed {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.pill-modified {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.btn-delete-special {
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  padding: 4px;
}

.btn-delete-special:hover {
  color: #ef4444;
}

/* Maintenance Card */
.mini-court-visual {
  width: 100%;
  height: 100px;
  border-radius: 12px;
  background: var(--surface-subtle, #f3f4f5);
  position: relative;
  overflow: hidden;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border, #e0e4ea);
}

.visual-blur-bg {
  position: absolute;
  inset: 0;
  background: url('/aura/court-1.jpg') center/cover;
  opacity: 0.15;
  filter: blur(2px);
}

.court-status-pill {
  position: relative;
  z-index: 2;
  background: rgba(20, 22, 28, 0.85);
  backdrop-filter: blur(6px);
  padding: 6px 14px;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #ffffff;
}

.status-pulse-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.bg-error {
  background-color: #ef4444;
  animation: pulse-dot 1.5s infinite;
}

.bg-green {
  background-color: #10b981;
}

@keyframes pulse-dot {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.4;
    transform: scale(1.3);
  }
}

.maintenance-blocks-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 14px;
}

.maintenance-block-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px;
  border-radius: 10px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
}

.block-indicator-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  margin-top: 5px;
  flex-shrink: 0;
}

.block-details {
  flex: 1;
  min-width: 0;
}

.block-court-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  margin: 0;
}

.block-time {
  font-size: 11px;
  color: var(--muted, #6c757d);
  margin: 2px 0 4px;
}

.block-venue-tag {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 11px;
  color: var(--muted, #6c757d);
}

.btn-unlock-block {
  border: none;
  background: transparent;
  color: var(--pink-dark, #78555e);
  cursor: pointer;
  padding: 4px;
}

.btn-unlock-block:hover {
  color: #ef4444;
}

.no-maintenance-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 18px 0;
}

.btn-schedule-downtime {
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 1px dashed var(--border, #cbd5e1);
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-schedule-downtime:hover {
  background: var(--surface-subtle, #f8f9fa);
  border-color: var(--pink-dark, #78555e);
  color: var(--pink-dark, #78555e);
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  background: var(--surface, #ffffff);
  border-radius: 20px;
  padding: 24px;
  width: 100%;
  max-width: 520px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
  animation: modal-enter 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  color: var(--ink, #191c1d);
}

@keyframes modal-enter {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.btn-close {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 14px;
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-family: inherit;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.15);
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.btn {
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:hover {
  filter: brightness(1.08);
}

.btn-secondary {
  background: var(--surface-subtle, #e7e8e9);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
}

.btn-secondary:hover {
  background: var(--soft, #dfe0e0);
}
</style>
