<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, apiOrigin, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDateTime } from '@/i18n'

const { t } = useI18n()
const auth = useAuthStore()

type AdminReport = {
  from?: string
  to?: string
  bookings: number
  confirmed: number
  revenue: number
  pendingRefunds: number
  occupancyRate?: number
  byHour?: { hour: string; bookings: number }[]
  byCourt?: { court_id: number; bookings: number; revenue: number; court?: { id: number; name: string } }[]
}

type AdminBooking = {
  id: number
  code: string
  status: string
  total_amount: number
  created_at?: string
  user?: { name?: string; email?: string }
  court?: { id?: number; name: string }
  slots?: { starts_at: string; ends_at?: string; status?: string }[]
}

type ReviewPayment = {
  id: number
  amount: number
  transfer_code?: string
  status?: string
  created_at?: string
  booking?: {
    code: string
    user?: { name?: string; email: string }
    court?: { name: string }
  }
}

type CourtItem = {
  id: number
  name: string
  description?: string
  is_active: boolean
  venue?: { name: string }
  blocks?: { starts_at: string; ends_at: string; reason?: string }[]
}

type AuditLog = {
  id: number
  action: string
  user?: { name: string; email?: string }
  metadata?: Record<string, unknown>
  created_at: string
}

const report = ref<AdminReport | null>(null)
const bookings = ref<AdminBooking[]>([])
const payments = ref<ReviewPayment[]>([])
const courts = ref<CourtItem[]>([])
const auditLogs = ref<AuditLog[]>([])

// Date range preset: 'today' | '7days' | 'thisMonth' | 'custom'
const datePreset = ref<'today' | '7days' | 'thisMonth' | 'custom'>('thisMonth')
const from = ref('')
const to = ref('')
const searchFilter = ref('')
const statusFilter = ref('')
const chartTab = ref<'hourly' | 'court'>('hourly')

const error = ref('')
const loading = ref(false)
const notice = ref('')

const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))
const statusLabel = (value: string) => t(`common.status.${value}`, value)

const csvUrl = computed(() => {
  const query = new URLSearchParams()
  if (from.value) query.set('from', from.value)
  if (to.value) query.set('to', to.value)
  return `${apiOrigin}/api/v1/admin/reports.csv?${query}`
})

function applyPreset(preset: 'today' | '7days' | 'thisMonth' | 'custom') {
  datePreset.value = preset
  const now = new Date()
  const formatDate = (d: Date): string => d.toISOString().split('T')[0] || ''

  if (preset === 'today') {
    from.value = formatDate(now)
    to.value = formatDate(now)
  } else if (preset === '7days') {
    const past7 = new Date(now.getTime() - 6 * 24 * 60 * 60 * 1000)
    from.value = formatDate(past7)
    to.value = formatDate(now)
  } else if (preset === 'thisMonth') {
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
    from.value = formatDate(firstDay)
    to.value = formatDate(now)
  }
  load()
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams()
    if (from.value) query.set('from', from.value)
    if (to.value) query.set('to', to.value)
    if (statusFilter.value) query.set('status', statusFilter.value)

    const [reportRes, bookingsRes, paymentsRes, courtsRes, auditRes] = await Promise.allSettled([
      api.get<AdminReport>(`/admin/reports?${query}`),
      api.get<{ data: AdminBooking[] }>(`/admin/bookings?${query}`),
      api.get<{ data: ReviewPayment[] }>('/admin/payments'),
      api.get<CourtItem[]>('/courts'),
      api.get<{ data: AuditLog[] }>('/admin/audit-logs'),
    ])

    if (reportRes.status === 'fulfilled') {
      report.value = reportRes.value
    }
    if (bookingsRes.status === 'fulfilled') {
      bookings.value = bookingsRes.value.data || []
    }
    if (paymentsRes.status === 'fulfilled') {
      payments.value = paymentsRes.value.data || []
    }
    if (courtsRes.status === 'fulfilled') {
      courts.value = courtsRes.value || []
    }
    if (auditRes.status === 'fulfilled') {
      auditLogs.value = auditRes.value.data || []
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

// Live Court State Computation
const liveCourtStatusList = computed(() => {
  const now = new Date()

  return courts.value.map((c) => {
    // Check if court has maintenance block today
    const isMaintenance = c.blocks?.some((b) => {
      const start = new Date(b.starts_at)
      const end = new Date(b.ends_at)
      return now >= start && now <= end
    }) || !c.is_active

    if (isMaintenance) {
      return { court: c, status: 'maintenance', label: 'Bảo trì', badgeClass: 'status-maint' }
    }

    // Check if there is a confirmed booking slot currently active
    const activeBooking = bookings.value.find((b) => {
      if (b.court?.id !== c.id && b.court?.name !== c.name) return false
      if (b.status !== 'confirmed') return false
      return b.slots?.some((s) => {
        const slotDate = new Date(s.starts_at)
        return (
          slotDate.toDateString() === now.toDateString() &&
          slotDate.getHours() === now.getHours()
        )
      })
    })

    if (activeBooking) {
      return { court: c, status: 'live', label: 'Đang chơi', badgeClass: 'status-live', customer: activeBooking.user?.name || activeBooking.user?.email }
    }

    // Check next upcoming slot today
    const upcomingSlot = bookings.value
      .filter((b) => (b.court?.id === c.id || b.court?.name === c.name) && b.status === 'confirmed')
      .flatMap((b) => b.slots || [])
      .map((s) => new Date(s.starts_at))
      .filter((d) => d.toDateString() === now.toDateString() && d.getHours() > now.getHours())
      .sort((a, b) => a.getTime() - b.getTime())[0]

    if (upcomingSlot) {
      const nextTime = `${String(upcomingSlot.getHours()).padStart(2, '0')}:00`
      return { court: c, status: 'booked_next', label: `Đặt: ${nextTime}`, badgeClass: 'status-next' }
    }

    return { court: c, status: 'available', label: 'Sẵn sàng', badgeClass: 'status-avail' }
  })
})

const liveActiveCount = computed(() => liveCourtStatusList.value.filter((i) => i.status === 'live').length)

// Filtered Bookings
const filteredBookings = computed(() => {
  let list = bookings.value
  const q = searchFilter.value.trim().toLowerCase()
  if (q) {
    list = list.filter((b) => {
      const code = b.code?.toLowerCase() || ''
      const name = b.user?.name?.toLowerCase() || ''
      const email = b.user?.email?.toLowerCase() || ''
      const courtName = b.court?.name?.toLowerCase() || ''
      return code.includes(q) || name.includes(q) || email.includes(q) || courtName.includes(q)
    })
  }
  return list
})

// Normalized Hourly Chart Data (06:00 to 22:00)
const hourlyChartData = computed(() => {
  const rawHours = report.value?.byHour || []
  const hourMap = new Map(rawHours.map((item) => [item.hour, item.bookings]))
  const standardHours = [
    '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
    '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
    '18:00', '19:00', '20:00', '21:00', '22:00'
  ]

  let maxBookings = 1
  const list = standardHours.map((hour) => {
    const count = hourMap.get(hour) || 0
    if (count > maxBookings) maxBookings = count
    return { hour, count }
  })

  return list.map((item) => ({
    ...item,
    heightPct: Math.max(10, Math.round((item.count / maxBookings) * 100)),
    isPeak: item.count >= maxBookings * 0.7 && item.count > 0,
  }))
})

// Court Revenue Breakdown Normalized
const courtRevenueData = computed(() => {
  const rawList = report.value?.byCourt || []
  const totalRev = report.value?.revenue || 1
  return rawList.map((item) => {
    const courtName = item.court?.name || `Sân #${item.court_id}`
    const pct = Math.min(100, Math.round((item.revenue / totalRev) * 100))
    return {
      courtId: item.court_id,
      name: courtName,
      revenue: item.revenue,
      bookings: item.bookings,
      pct,
    }
  })
})

async function reviewPayment(payment: ReviewPayment, nextStatus: 'paid' | 'rejected') {
  if (isDemoAdmin.value) {
    payments.value = payments.value.filter((item) => item.id !== payment.id)
    bookings.value = bookings.value.map((booking) =>
      booking.code === payment.booking?.code ? { ...booking, status: nextStatus === 'paid' ? 'confirmed' : 'cancelled' } : booking
    )
    notice.value = 'Demo: Đã mô phỏng duyệt thanh toán thành công.'
    return
  }
  try {
    await api.patch(`/admin/payments/${payment.id}`, { status: nextStatus })
    notice.value = nextStatus === 'paid' ? 'Đã duyệt thanh toán thành công.' : 'Đã từ chối giao dịch.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

async function markNoShow(booking: AdminBooking) {
  if (!confirm(`Đánh dấu vắng mặt (No-Show) cho mã đặt sân ${booking.code}?`)) return
  if (isDemoAdmin.value) {
    bookings.value = bookings.value.map((item) => (item.id === booking.id ? { ...item, status: 'no_show' } : item))
    notice.value = 'Demo: Đã mô phỏng đánh dấu vắng mặt.'
    return
  }
  try {
    await api.patch(`/admin/bookings/${booking.id}/no-show`, {})
    notice.value = 'Đã đánh dấu vắng mặt cho lượt đặt sân.'
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

function formatSlotTime(b: AdminBooking) {
  if (!b.slots || !b.slots.length) return '—'
  const first = b.slots[0]
  if (!first) return '—'
  try {
    const d = new Date(first.starts_at)
    const timeStr = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
    const dateStr = `${d.getDate()}/${d.getMonth() + 1}`
    return `${timeStr} (${dateStr})`
  } catch {
    return '—'
  }
}

function formatTimeAgo(isoString: string) {
  try {
    const diff = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000)
    if (diff < 60) return 'Vừa xong'
    if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
    if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
    return `${Math.floor(diff / 86400)} ngày trước`
  } catch {
    return isoString
  }
}

onMounted(() => {
  applyPreset('thisMonth')
})
</script>

<template>
  <div class="admin-dashboard-container">
    <!-- Top Action Bar -->
    <header class="dashboard-header-bar">
      <div class="header-titles">
        <h1 class="page-title">Tổng quan Vận hành & Doanh thu</h1>
        <p class="page-subtitle">Cập nhật chỉ số hoạt động sân, hàng đợi duyệt thanh toán và lượt đặt mới nhất.</p>
      </div>

      <div class="header-controls">
        <!-- Date Preset Filter -->
        <div class="date-preset-pills">
          <button
            type="button"
            class="pill-btn"
            :class="{ active: datePreset === 'today' }"
            @click="applyPreset('today')"
          >
            Hôm nay
          </button>
          <button
            type="button"
            class="pill-btn"
            :class="{ active: datePreset === '7days' }"
            @click="applyPreset('7days')"
          >
            7 ngày
          </button>
          <button
            type="button"
            class="pill-btn"
            :class="{ active: datePreset === 'thisMonth' }"
            @click="applyPreset('thisMonth')"
          >
            Tháng này
          </button>
          <button
            type="button"
            class="pill-btn"
            :class="{ active: datePreset === 'custom' }"
            @click="datePreset = 'custom'"
          >
            Tùy chọn
          </button>
        </div>

        <button
          type="button"
          class="btn-action-neutral"
          :disabled="loading"
          title="Làm mới dữ liệu"
          @click="load"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-anim': loading }">refresh</span>
          <span>Làm mới</span>
        </button>

        <a :href="csvUrl" class="btn-action-primary" download title="Xuất báo cáo định dạng CSV">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>Xuất CSV</span>
        </a>
      </div>
    </header>

    <!-- Custom Date Range Bar (Show when custom selected) -->
    <transition name="fade">
      <div v-if="datePreset === 'custom'" class="custom-date-strip">
        <div class="date-input-group">
          <label>Từ ngày:</label>
          <DatePicker v-model="from" />
        </div>
        <div class="date-input-group">
          <label>Đến ngày:</label>
          <DatePicker v-model="to" />
        </div>
        <button type="button" class="btn-apply-filter" :disabled="loading" @click="load">
          <span class="material-symbols-outlined icon-18">filter_alt</span>
          <span>Áp dụng</span>
        </button>
      </div>
    </transition>

    <!-- Notifications -->
    <div v-if="notice" class="toast-strip toast-success">
      <span class="material-symbols-outlined icon-18">check_circle</span>
      <span>{{ notice }}</span>
      <button type="button" class="btn-close-toast" @click="notice = ''">
        <span class="material-symbols-outlined icon-16">close</span>
      </button>
    </div>
    <div v-if="error" class="toast-strip toast-error">
      <span class="material-symbols-outlined icon-18">error</span>
      <span>{{ error }}</span>
      <button type="button" class="btn-close-toast" @click="error = ''">
        <span class="material-symbols-outlined icon-16">close</span>
      </button>
    </div>

    <!-- 4 KPI Metrics Row -->
    <section class="kpi-row-grid">
      <!-- KPI 1: Revenue -->
      <div class="kpi-card">
        <div class="kpi-meta-top">
          <span class="kpi-title">Doanh thu tổng</span>
          <div class="kpi-icon-badge bg-pink-tonal">
            <span class="material-symbols-outlined text-pink-dark">payments</span>
          </div>
        </div>
        <div class="kpi-value text-pink-dark">
          {{ formatCurrency(report?.revenue || 0) }}
        </div>
        <div class="kpi-meta-bottom">
          <span class="badge-growth">
            <span class="material-symbols-outlined icon-14">trending_up</span>
            <span>+14.2%</span>
          </span>
          <span class="kpi-hint">so với kỳ trước</span>
        </div>
      </div>

      <!-- KPI 2: Confirmed Bookings -->
      <div class="kpi-card">
        <div class="kpi-meta-top">
          <span class="kpi-title">Lượt đặt thành công</span>
          <div class="kpi-icon-badge bg-green-tonal">
            <span class="material-symbols-outlined text-green">check_circle</span>
          </div>
        </div>
        <div class="kpi-value">
          {{ report?.confirmed || 0 }} <span class="kpi-denominator">/ {{ report?.bookings || 0 }}</span>
        </div>
        <div class="kpi-meta-bottom">
          <span class="badge-rate">
            {{ report?.bookings ? Math.round(((report?.confirmed || 0) / report.bookings) * 100) : 100 }}% hoàn tất
          </span>
          <span class="kpi-hint">tỷ lệ giữ chỗ</span>
        </div>
      </div>

      <!-- KPI 3: Occupancy Rate -->
      <div class="kpi-card">
        <div class="kpi-meta-top">
          <span class="kpi-title">Tỷ lệ lấp đầy sân</span>
          <div class="kpi-icon-badge bg-orange-tonal">
            <span class="material-symbols-outlined text-orange">pie_chart</span>
          </div>
        </div>
        <div class="kpi-value">
          {{ report?.occupancyRate || 0 }}%
        </div>
        <div class="kpi-meta-bottom">
          <div class="mini-progress-bar">
            <div class="mini-progress-fill" :style="{ width: `${Math.min(100, report?.occupancyRate || 0)}%` }"></div>
          </div>
          <span class="kpi-hint">công suất mở cửa</span>
        </div>
      </div>

      <!-- KPI 4: Live Active Courts -->
      <div class="kpi-card">
        <div class="kpi-meta-top">
          <span class="kpi-title">Sân đang hoạt động</span>
          <div class="kpi-icon-badge bg-purple-tonal">
            <span class="material-symbols-outlined text-purple">sensors</span>
          </div>
        </div>
        <div class="kpi-value">
          {{ liveActiveCount }} <span class="kpi-denominator">/ {{ courts.length || 4 }}</span>
        </div>
        <div class="kpi-meta-bottom">
          <span class="live-dot-indicator">
            <span class="dot-pulse"></span>
            <span>Trực tiếp thời gian thực</span>
          </span>
        </div>
      </div>
    </section>

    <!-- Main Workspace 2-Column Balanced Layout -->
    <div class="dashboard-main-layout">
      <!-- Left Column (Primary Operations & Tables) -->
      <div class="main-left-col">
        <!-- 1. Live Court Matrix (Top of left column) -->
        <section class="content-panel">
          <div class="panel-header">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-pink-dark icon-20">grid_view</span>
              <h2 class="panel-heading">Sơ đồ Sân Trực tiếp (Live Court Matrix)</h2>
            </div>
            <RouterLink to="/admin/schedule" class="panel-action-link">
              <span>Lịch chi tiết</span>
              <span class="material-symbols-outlined icon-16">arrow_forward</span>
            </RouterLink>
          </div>

          <div class="court-status-matrix">
            <div
              v-for="item in liveCourtStatusList"
              :key="item.court.id"
              class="court-slot-card"
              :class="item.badgeClass"
            >
              <div class="court-slot-header">
                <span class="court-slot-name">{{ item.court.name }}</span>
                <span class="slot-badge" :class="item.badgeClass">
                  <span v-if="item.status === 'live'" class="dot-pulse-mini"></span>
                  {{ item.label }}
                </span>
              </div>

              <div class="court-slot-body">
                <div v-if="item.status === 'live'" class="slot-customer-info text-pink-dark">
                  <span class="material-symbols-outlined icon-14">sports_tennis</span>
                  <span class="truncate">{{ item.customer || 'Khách đang chơi' }}</span>
                </div>
                <div v-else-if="item.status === 'booked_next'" class="slot-customer-info text-orange">
                  <span class="material-symbols-outlined icon-14">schedule</span>
                  <span>{{ item.label }}</span>
                </div>
                <div v-else-if="item.status === 'maintenance'" class="slot-customer-info text-muted">
                  <span class="material-symbols-outlined icon-14">build</span>
                  <span>Bảo trì</span>
                </div>
                <div v-else class="slot-customer-info text-green">
                  <span class="material-symbols-outlined icon-14">check_circle</span>
                  <span>Sẵn sàng nhận khách</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 2. Recent Bookings Table -->
        <section class="content-panel">
          <div class="panel-header wrap-header">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-pink-dark icon-20">receipt_long</span>
              <h2 class="panel-heading">Lượt Đặt Sân Gần Đây</h2>
              <span class="count-badge-pill">{{ filteredBookings.length }}</span>
            </div>

            <!-- Table Filters -->
            <div class="table-controls-inline">
              <div class="search-input-wrap">
                <span class="material-symbols-outlined icon-16 text-muted">search</span>
                <input
                  v-model="searchFilter"
                  type="text"
                  placeholder="Tìm mã, khách, sân..."
                  class="search-text-input"
                />
                <button v-if="searchFilter" type="button" class="btn-clear-input" @click="searchFilter = ''">
                  <span class="material-symbols-outlined icon-14">close</span>
                </button>
              </div>

              <select v-model="statusFilter" class="status-dropdown" @change="load">
                <option value="">Tất cả</option>
                <option value="confirmed">Đã xác nhận</option>
                <option value="pending_payment">Chờ TT</option>
                <option value="cancelled">Đã hủy</option>
                <option value="no_show">No-Show</option>
              </select>
            </div>
          </div>

          <div v-if="!filteredBookings.length" class="empty-list-box">
            <span class="material-symbols-outlined icon-28 text-muted opacity-40">inbox</span>
            <p class="empty-main">Chưa có lượt đặt sân trong bộ lọc đã chọn</p>
          </div>

          <div v-else class="table-scroll-wrap">
            <table class="compact-data-table">
              <thead>
                <tr>
                  <th>Mã</th>
                  <th>Khách hàng</th>
                  <th>Sân & Giờ</th>
                  <th>Trạng thái</th>
                  <th>Tổng tiền</th>
                  <th class="text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in filteredBookings.slice(0, 8)" :key="b.id">
                  <td>
                    <span class="badge-code">{{ b.code }}</span>
                  </td>
                  <td>
                    <div class="customer-compact">
                      <span class="customer-name-text">{{ b.user?.name || b.user?.email || 'Khách vãng lai' }}</span>
                    </div>
                  </td>
                  <td>
                    <div class="court-slot-text">
                      <span class="court-name-sub">{{ b.court?.name || 'Sân' }}</span>
                      <span class="time-sub">{{ formatSlotTime(b) }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="status-badge" :class="`pill-${b.status}`">
                      {{ statusLabel(b.status) }}
                    </span>
                  </td>
                  <td>
                    <span class="price-val">{{ formatCurrency(b.total_amount) }}</span>
                  </td>
                  <td class="text-right">
                    <button
                      v-if="b.status === 'confirmed'"
                      type="button"
                      class="btn-noshow-inline"
                      title="Đánh dấu vắng mặt"
                      @click="markNoShow(b)"
                    >
                      <span>No-Show</span>
                    </button>
                    <span v-else class="text-muted text-xs">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 3. Demand & Revenue Analytics -->
        <section class="content-panel">
          <div class="panel-header">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-pink-dark icon-20">bar_chart</span>
              <h2 class="panel-heading">Phân tích Khung giờ & Tỷ trọng Doanh thu</h2>
            </div>

            <div class="sub-tab-switch">
              <button
                type="button"
                class="sub-tab-btn"
                :class="{ active: chartTab === 'hourly' }"
                @click="chartTab = 'hourly'"
              >
                Khung giờ
              </button>
              <button
                type="button"
                class="sub-tab-btn"
                :class="{ active: chartTab === 'court' }"
                @click="chartTab = 'court'"
              >
                Theo sân
              </button>
            </div>
          </div>

          <!-- Hourly Bars Chart -->
          <div v-if="chartTab === 'hourly'" class="hourly-chart-area">
            <div class="chart-sub-header">
              <span class="text-muted text-xs">Phân bổ lượng đặt sân theo từng khung giờ từ 06:00 đến 22:00</span>
              <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1"><span class="legend-color is-peak"></span> Giờ cao điểm</span>
                <span class="flex items-center gap-1"><span class="legend-color"></span> Tiêu chuẩn</span>
              </div>
            </div>

            <div class="hourly-bar-graph">
              <div
                v-for="item in hourlyChartData"
                :key="item.hour"
                class="graph-col"
                :title="`${item.hour}: ${item.count} lượt đặt`"
              >
                <div class="bar-tube-wrap">
                  <div
                    class="bar-tube"
                    :class="{ 'is-peak': item.isPeak }"
                    :style="{ height: `${item.heightPct}%` }"
                  >
                    <span v-if="item.count > 0" class="bar-badge-val">{{ item.count }}</span>
                  </div>
                </div>
                <span class="graph-hour-label">{{ item.hour.replace(':00', 'h') }}</span>
              </div>
            </div>
          </div>

          <!-- Court Breakdown -->
          <div v-else class="court-breakdown-area">
            <div v-if="!courtRevenueData.length" class="empty-list-box py-4">
              <p class="empty-main">Chưa có dữ liệu phân bổ theo sân trong khoảng thời gian này</p>
            </div>
            <div v-else class="court-progress-list">
              <div v-for="c in courtRevenueData" :key="c.courtId" class="court-progress-item">
                <div class="court-meta-row">
                  <span class="font-bold text-xs text-ink">{{ c.name }}</span>
                  <span class="text-muted text-xs">{{ c.bookings }} lượt · <strong class="text-pink-dark">{{ formatCurrency(c.revenue) }}</strong></span>
                </div>
                <div class="court-track-bg">
                  <div class="court-track-fill" :style="{ width: `${c.pct}%` }"></div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Right Column (Action Queues & Governance) -->
      <div class="main-right-col">
        <!-- 1. Pending Approvals Queue -->
        <section class="content-panel">
          <div class="panel-header">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-orange icon-20">pending_actions</span>
              <h2 class="panel-heading">Duyệt Chuyển khoản</h2>
            </div>
            <span v-if="payments.length" class="alert-counter-badge">
              {{ payments.length }} cần duyệt
            </span>
          </div>

          <div v-if="!payments.length" class="empty-queue-box">
            <span class="material-symbols-outlined icon-24 text-green">verified</span>
            <p class="empty-main text-xs">Không có giao dịch chờ duyệt</p>
            <p class="empty-sub text-xs">Mọi thanh toán đã được đối soát tự động.</p>
          </div>

          <div v-else class="approval-queue-list">
            <div v-for="p in payments" :key="p.id" class="approval-card">
              <div class="approval-card-info">
                <div class="flex items-center justify-between">
                  <span class="badge-code">{{ p.transfer_code || p.booking?.code || 'CK' }}</span>
                  <strong class="text-green text-sm">{{ formatCurrency(p.amount) }}</strong>
                </div>
                <div class="approval-client-meta">
                  <span class="client-name">{{ p.booking?.user?.name || p.booking?.user?.email || 'Khách' }}</span>
                  <span class="text-muted">· {{ p.booking?.court?.name || 'Sân' }}</span>
                </div>
              </div>

              <div class="approval-actions">
                <button
                  type="button"
                  class="btn-approve-sm"
                  title="Duyệt giao dịch này"
                  @click="reviewPayment(p, 'paid')"
                >
                  <span class="material-symbols-outlined icon-14">check</span>
                  <span>Duyệt</span>
                </button>
                <button
                  type="button"
                  class="btn-reject-sm"
                  title="Từ chối giao dịch"
                  @click="reviewPayment(p, 'rejected')"
                >
                  <span class="material-symbols-outlined icon-14">close</span>
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- 2. Facility Operational Summary -->
        <section class="content-panel">
          <div class="panel-header">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-pink-dark icon-20">summarize</span>
              <h2 class="panel-heading">Tổng quan Hoạt động</h2>
            </div>
          </div>

          <div class="summary-stats-list">
            <div class="summary-stat-row">
              <span class="stat-label">Tổng lượt giữ chỗ</span>
              <span class="stat-val">{{ report?.bookings || 0 }} lượt</span>
            </div>
            <div class="summary-stat-row">
              <span class="stat-label">Đã thanh toán xác nhận</span>
              <span class="stat-val text-green font-bold">{{ report?.confirmed || 0 }} lượt</span>
            </div>
            <div class="summary-stat-row">
              <span class="stat-label">Doanh thu trung bình / lượt</span>
              <span class="stat-val font-semibold">
                {{ formatCurrency(report?.confirmed ? Math.round((report?.revenue || 0) / report.confirmed) : 0) }}
              </span>
            </div>
            <div class="summary-stat-row">
              <span class="stat-label">Hoàn tiền chờ xử lý</span>
              <span class="stat-val" :class="(report?.pendingRefunds || 0) > 0 ? 'text-orange font-bold' : 'text-muted'">
                {{ formatCurrency(report?.pendingRefunds || 0) }}
              </span>
            </div>
          </div>
        </section>

        <!-- 3. Audit Activity Log (Feed) -->
        <section class="content-panel">
          <div class="panel-header">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-muted icon-20">history</span>
              <h2 class="panel-heading">Nhật ký Kiểm toán</h2>
            </div>
          </div>

          <div v-if="!auditLogs.length" class="empty-queue-box py-3">
            <p class="empty-sub text-xs">Chưa có nhật ký kiểm toán mới.</p>
          </div>

          <div v-else class="audit-feed-list">
            <div v-for="log in auditLogs.slice(0, 5)" :key="log.id" class="audit-feed-item">
              <div class="audit-feed-dot"></div>
              <div class="audit-feed-content">
                <div class="flex items-center justify-between">
                  <span class="audit-user-name">{{ log.user?.name || log.user?.email || 'Hệ thống' }}</span>
                  <span class="audit-relative-time">{{ formatTimeAgo(log.created_at) }}</span>
                </div>
                <span class="audit-action-text">{{ log.action }}</span>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-dashboard-container {
  max-width: 1360px;
  margin: 0 auto;
  padding: 20px 24px 80px;
  color: var(--ink);
}

/* Header Bar */
.dashboard-header-bar {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-bottom: 20px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
}

@media (min-width: 1024px) {
  .dashboard-header-bar {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
  margin: 0 0 2px;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 13px;
  color: var(--muted);
  margin: 0;
}

.header-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.date-preset-pills {
  display: flex;
  align-items: center;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 2px;
}

.pill-btn {
  padding: 5px 10px;
  border-radius: 8px;
  border: none;
  background: transparent;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.pill-btn:hover {
  color: var(--ink);
}

.pill-btn.active {
  background: var(--pink);
  color: var(--pink-dark);
  font-weight: 700;
}

.btn-action-neutral {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action-neutral:hover {
  background: var(--surface-subtle);
}

.btn-action-primary {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 14px;
  border-radius: 10px;
  border: none;
  background: var(--pink);
  color: var(--pink-dark);
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action-primary:hover {
  opacity: 0.9;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

/* Custom Date Strip */
.custom-date-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
  padding: 12px 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 16px;
}

.date-input-group {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
}

.btn-apply-filter {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: none;
  background: var(--pink-dark);
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

/* Toasts */
.toast-strip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: 12px;
  font-weight: 600;
}

.toast-success {
  background: var(--success-bg);
  color: var(--success-text);
  border: 1px solid var(--success-text);
}

.toast-error {
  background: var(--error-bg);
  color: var(--error-text);
  border: 1px solid var(--error-text);
}

.btn-close-toast {
  margin-left: auto;
  border: none;
  background: transparent;
  cursor: pointer;
  color: currentColor;
}

/* KPI Row (4 Cards) */
.kpi-row-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}

@media (min-width: 640px) {
  .kpi-row-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .kpi-row-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.kpi-meta-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.kpi-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.kpi-icon-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-pink-tonal {
  background: var(--live-bg);
}

.bg-green-tonal {
  background: var(--success-bg);
}

.bg-orange-tonal {
  background: var(--warning-bg);
}

.bg-purple-tonal {
  background: var(--purple-bg);
}

.text-pink-dark {
  color: var(--pink-dark);
}

.text-green {
  color: var(--success-text);
}

.text-orange {
  color: var(--warning-text);
}

.text-purple {
  color: var(--purple-text);
}

.kpi-value {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
  line-height: 1.1;
  margin-bottom: 8px;
}

.kpi-denominator {
  font-size: 14px;
  font-weight: 600;
  color: var(--muted);
}

.kpi-meta-bottom {
  display: flex;
  align-items: center;
  gap: 6px;
}

.badge-growth {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 1px 5px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  background: var(--success-bg);
  color: var(--success-text);
}

.badge-rate {
  padding: 1px 5px;
  border-radius: 4px;
  background: var(--live-bg);
  color: var(--pink-dark);
  font-size: 10px;
  font-weight: 700;
}

.kpi-hint {
  font-size: 11px;
  color: var(--muted);
}

.mini-progress-bar {
  flex: 1;
  height: 5px;
  border-radius: 999px;
  background: var(--surface-subtle);
  overflow: hidden;
}

.mini-progress-fill {
  height: 100%;
  background: var(--warning-text);
  border-radius: 999px;
}

.live-dot-indicator {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 600;
  color: var(--success-text);
}

.dot-pulse {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--success-text);
  animation: pulse 1.5s infinite;
}

/* 2-Column Main Workspace Layout */
.dashboard-main-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1080px) {
  .dashboard-main-layout {
    grid-template-columns: 1.65fr 1fr;
  }
}

.main-left-col,
.main-right-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Content Panels */
.content-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.panel-header.wrap-header {
  flex-wrap: wrap;
  gap: 10px;
}

.panel-heading {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.panel-action-link {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 11px;
  font-weight: 600;
  color: var(--pink-dark);
  text-decoration: none;
}

.panel-action-link:hover {
  text-decoration: underline;
}

.count-badge-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--surface-subtle);
  color: var(--muted);
  border: 1px solid var(--border);
}

/* Live Court Matrix (Compact 4x2 Grid) */
.court-status-matrix {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

@media (min-width: 640px) {
  .court-status-matrix {
    grid-template-columns: repeat(4, 1fr);
  }
}

.court-slot-card {
  border-radius: 10px;
  padding: 10px;
  border: 1px solid var(--border);
  background: var(--surface);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 72px;
  transition: all 0.15s ease;
}

.court-slot-card.status-live {
  background: var(--live-bg);
  border-color: var(--live-border);
}

.court-slot-card.status-avail {
  background: var(--surface);
  border-color: var(--border);
}

.court-slot-card.status-next {
  background: var(--surface-subtle);
  border-color: var(--border);
}

.court-slot-card.status-maint {
  background: var(--surface-subtle);
  border-color: var(--border);
  opacity: 0.6;
}

.court-slot-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

.court-slot-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.slot-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 9px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 999px;
}

.slot-badge.status-live {
  background: var(--pink);
  color: var(--pink-dark);
}

.slot-badge.status-avail {
  background: var(--success-bg);
  color: var(--success-text);
}

.slot-badge.status-next {
  background: var(--warning-bg);
  color: var(--warning-text);
}

.slot-badge.status-maint {
  background: var(--soft);
  color: var(--muted);
}

.dot-pulse-mini {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--pink-dark);
  animation: pulse 1s infinite;
}

.slot-customer-info {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  font-weight: 600;
}

/* Compact Data Table */
.table-controls-inline {
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-input-wrap {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
}

.search-text-input {
  border: none;
  outline: none;
  font-size: 11px;
  color: var(--ink);
  background: transparent;
  width: 130px;
}

.btn-clear-input {
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0;
  color: var(--muted);
}

.status-dropdown {
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  font-size: 11px;
  font-weight: 600;
  color: var(--ink);
  outline: none;
}

.table-scroll-wrap {
  overflow-x: auto;
}

.compact-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.compact-data-table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--muted);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid var(--border);
}

.compact-data-table td {
  padding: 10px;
  border-bottom: 1px solid var(--border);
  color: var(--ink);
  vertical-align: middle;
}

.compact-data-table tbody tr:hover {
  background: var(--surface-subtle);
}

.badge-code {
  font-family: monospace;
  font-weight: 700;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  color: var(--ink);
}

.customer-compact {
  display: flex;
  flex-direction: column;
}

.customer-name-text {
  font-weight: 600;
  color: var(--ink);
}

.court-slot-text {
  display: flex;
  flex-direction: column;
}

.court-name-sub {
  font-weight: 700;
  color: var(--pink-dark);
  font-size: 11px;
}

.time-sub {
  font-size: 10px;
  color: var(--muted);
}

.status-badge {
  display: inline-flex;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
}

.pill-confirmed {
  background: var(--success-bg);
  color: var(--success-text);
}

.pill-pending_payment {
  background: var(--warning-bg);
  color: var(--warning-text);
}

.pill-cancelled {
  background: var(--error-bg);
  color: var(--error-text);
}

.pill-no_show {
  background: var(--soft);
  color: var(--muted);
}

.price-val {
  font-weight: 700;
  color: var(--ink);
}

.btn-noshow-inline {
  display: inline-flex;
  padding: 3px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--error-text);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-noshow-inline:hover {
  background: var(--error-bg);
}

/* Demand & Revenue Analytics */
.sub-tab-switch {
  display: flex;
  align-items: center;
  background: var(--surface-subtle);
  border-radius: 8px;
  padding: 2px;
  border: 1px solid var(--border);
}

.sub-tab-btn {
  padding: 3px 8px;
  border-radius: 6px;
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
}

.sub-tab-btn.active {
  background: var(--surface);
  color: var(--ink);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.chart-sub-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.legend-color {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  background: var(--outline);
}

.legend-color.is-peak {
  background: var(--pink-dark);
}

.hourly-bar-graph {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 130px;
  padding-top: 16px;
  border-bottom: 1px solid var(--border);
  gap: 3px;
}

.graph-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  justify-content: flex-end;
  position: relative;
}

.bar-tube-wrap {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.bar-tube {
  width: 100%;
  max-width: 18px;
  border-radius: 3px 3px 0 0;
  background: var(--outline);
  transition: height 0.4s ease;
  position: relative;
}

.bar-tube.is-peak {
  background: var(--pink-dark);
}

.bar-badge-val {
  position: absolute;
  top: -16px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 9px;
  font-weight: 700;
  color: var(--ink);
}

.graph-hour-label {
  font-size: 9px;
  color: var(--muted);
  margin-top: 4px;
  font-weight: 600;
}

/* Court Breakdown */
.court-progress-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.court-progress-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.court-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.court-track-bg {
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: var(--surface-subtle);
  overflow: hidden;
}

.court-track-fill {
  height: 100%;
  border-radius: 999px;
  background: var(--pink-dark);
}

/* Right Column: Pending Approvals & Operations */
.alert-counter-badge {
  padding: 1px 6px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  background: var(--warning-bg);
  color: var(--warning-text);
  border: 1px solid var(--warning-text);
}

.empty-queue-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 18px 12px;
  gap: 2px;
}

.empty-main {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  margin: 2px 0 0;
}

.empty-sub {
  color: var(--muted);
  margin: 0;
}

.empty-list-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 24px 12px;
  gap: 4px;
}

.approval-queue-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 280px;
  overflow-y: auto;
  padding-right: 2px;
}

.approval-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  border-radius: 10px;
  background: var(--surface-subtle);
  border: 1px solid var(--border);
}

.approval-card-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  flex: 1;
}

.approval-client-meta {
  font-size: 11px;
}

.client-name {
  font-weight: 600;
  color: var(--ink);
}

.approval-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
}

.btn-approve-sm {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 4px 8px;
  border-radius: 6px;
  border: none;
  background: var(--success-bg);
  color: var(--success-text);
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-approve-sm:hover {
  opacity: 0.85;
}

.btn-reject-sm {
  display: inline-flex;
  align-items: center;
  padding: 4px 6px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--error-text);
  font-size: 10px;
  cursor: pointer;
}

.btn-reject-sm:hover {
  background: var(--error-bg);
}

/* Operational Summary */
.summary-stats-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-stat-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  padding: 6px 0;
  border-bottom: 1px dashed var(--border);
}

.summary-stat-row:last-child {
  border-bottom: none;
}

.stat-label {
  color: var(--muted);
}

.stat-val {
  color: var(--ink);
}

/* Audit Activity Feed */
.audit-feed-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.audit-feed-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 11px;
}

.audit-feed-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--pink-dark);
  margin-top: 4px;
  flex-shrink: 0;
}

.audit-feed-content {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex: 1;
}

.audit-user-name {
  font-weight: 700;
  color: var(--ink);
}

.audit-relative-time {
  font-size: 10px;
  color: var(--muted);
}

.audit-action-text {
  color: var(--muted);
  font-size: 10px;
}

/* Utility Icons */
.icon-14 { font-size: 14px; }
.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }
.icon-20 { font-size: 20px; }
.icon-24 { font-size: 24px; }
.icon-28 { font-size: 28px; }

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}
</style>
