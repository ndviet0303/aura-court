<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, apiOrigin, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDateTime } from '@/i18n'

const { t } = useI18n()

type AdminReport = {
  bookings: number
  confirmed: number
  revenue: number
  pendingRefunds: number
  occupancyRate?: number
  byHour?: { hour: string; bookings: number }[]
}

type AdminBooking = {
  id: number
  code: string
  status: string
  total_amount: number
  user?: { name: string; email?: string }
  court?: { name: string }
  slots?: { starts_at: string }[]
}

type ReviewPayment = {
  id: number
  amount: number
  transfer_code?: string
  booking?: { code: string; user?: { email: string }; court?: { name: string } }
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
const auditLogs = ref<AuditLog[]>([])
const from = ref('')
const to = ref('')
const status = ref('')
const error = ref('')
const loading = ref(false)
const notice = ref('')
const auth = useAuthStore()
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email?.endsWith('@demo.auracourt.vn'))
const statusLabel = (value: string) => t(`common.status.${value}`, value)
const csvUrl = computed(() => `${apiOrigin}/api/v1/admin/reports.csv?from=${encodeURIComponent(from.value)}&to=${encodeURIComponent(to.value)}`)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams()
    if (from.value) query.set('from', from.value)
    if (to.value) query.set('to', to.value)
    if (status.value) query.set('status', status.value)
    report.value = await api.get<AdminReport>(`/admin/reports?${query}`)
    bookings.value = (await api.get<{ data: AdminBooking[] }>(`/admin/bookings?${query}`)).data || []
    payments.value = (await api.get<{ data: ReviewPayment[] }>('/admin/payments')).data || []
    auditLogs.value = (await api.get<{ data: AuditLog[] }>('/admin/audit-logs')).data || []
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

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

onMounted(load)
</script>

<template>
  <main class="page-shell admin-dashboard-shell">
    <!-- Admin Navigation Tab Bar -->
    <nav class="admin-tab-nav" aria-label="Admin Navigation">
      <RouterLink to="/admin" class="admin-nav-item active">
        <span class="material-symbols-outlined">analytics</span>
        <span>{{ t('admin.reports') }}</span>
      </RouterLink>
      <RouterLink to="/admin/manage" class="admin-nav-item">
        <span class="material-symbols-outlined">sports_tennis</span>
        <span>{{ t('admin.manageCourts') }}</span>
      </RouterLink>
      <RouterLink to="/admin/schedule" class="admin-nav-item">
        <span class="material-symbols-outlined">schedule</span>
        <span>{{ t('admin.scheduleManage') }}</span>
      </RouterLink>
      <RouterLink to="/admin/venues" class="admin-nav-item">
        <span class="material-symbols-outlined">location_on</span>
        <span>{{ t('admin.manageVenues') }}</span>
      </RouterLink>
      <RouterLink to="/admin/pricing" class="admin-nav-item">
        <span class="material-symbols-outlined">price_change</span>
        <span>{{ t('admin.pricingCalendar') }}</span>
      </RouterLink>
      <RouterLink to="/admin/organization" class="admin-nav-item">
        <span class="material-symbols-outlined">corporate_fare</span>
        <span>{{ t('admin.organization') }}</span>
      </RouterLink>
    </nav>

    <!-- Header Section -->
    <header class="admin-dashboard-header">
      <div class="header-content">
        <div class="eyebrow-tag">
          <span class="material-symbols-outlined icon-16">analytics</span>
          <span>BÁO CÁO & THỐNG KÊ · ANALYTICS</span>
        </div>
        <h1 class="page-title">{{ t('admin.title') }}</h1>
        <p class="muted max-w-2xl">
          Theo dõi tổng quan doanh thu, tỷ lệ lấp đầy sân, số lượt đặt và các giao dịch chuyển khoản cần duyệt.
        </p>
      </div>

      <div class="header-actions">
        <a class="btn btn-secondary flex items-center gap-xs" :href="csvUrl" download>
          <span class="material-symbols-outlined icon-18">download</span>
          <span>Xuất Báo Cáo CSV</span>
        </a>
      </div>
    </header>

    <!-- Notifications -->
    <div v-if="notice" class="notice-banner">
      <span class="material-symbols-outlined text-green">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="error-banner">
      <span class="material-symbols-outlined text-error">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- Filter Bar -->
    <form class="dashboard-filter-card" @submit.prevent="load">
      <div class="filter-item">
        <label class="filter-label">Từ ngày:</label>
        <DatePicker v-model="from" />
      </div>
      <div class="filter-item">
        <label class="filter-label">Đến ngày:</label>
        <DatePicker v-model="to" />
      </div>
      <div class="filter-item">
        <label class="filter-label">Trạng thái:</label>
        <select v-model="status" class="filter-select">
          <option value="">{{ t('common.all') }}</option>
          <option value="pending_payment">{{ t('common.status.pending_payment') }}</option>
          <option value="confirmed">{{ t('common.status.confirmed') }}</option>
          <option value="cancelled">{{ t('common.status.cancelled') }}</option>
          <option value="no_show">{{ t('common.status.no_show') }}</option>
        </select>
      </div>
      <button class="btn btn-primary" :disabled="loading">
        <span class="material-symbols-outlined icon-18">search</span>
        <span>{{ loading ? t('common.loading') : t('common.search') }}</span>
      </button>
    </form>

    <!-- KPI Stats Cards -->
    <section v-if="report" class="kpi-stats-grid">
      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-blue">
          <span class="material-symbols-outlined">book_online</span>
        </div>
        <div>
          <span class="kpi-label">Tổng lượt đặt</span>
          <h3 class="kpi-val">{{ report.bookings }}</h3>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-green">
          <span class="material-symbols-outlined">check_circle</span>
        </div>
        <div>
          <span class="kpi-label">Đã xác nhận</span>
          <h3 class="kpi-val">{{ report.confirmed }}</h3>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-pink">
          <span class="material-symbols-outlined">payments</span>
        </div>
        <div>
          <span class="kpi-label">Doanh thu tổng</span>
          <h3 class="kpi-val text-primary">{{ formatCurrency(report.revenue) }}</h3>
        </div>
      </div>

      <div class="kpi-card">
        <div class="kpi-icon-wrap icon-orange">
          <span class="material-symbols-outlined">pie_chart</span>
        </div>
        <div>
          <span class="kpi-label">Tỷ lệ lấp đầy</span>
          <h3 class="kpi-val">{{ report.occupancyRate || 0 }}%</h3>
        </div>
      </div>
    </section>

    <!-- Popular Hours Breakdown -->
    <section v-if="report?.byHour?.length" class="panel-card mt-6">
      <div class="panel-head-row">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-pink icon-20">schedule</span>
          <h3 class="panel-title">Khung giờ đông khách nhất</h3>
        </div>
      </div>
      <div class="popular-hours-grid">
        <div v-for="item in report.byHour" :key="item.hour" class="hour-chip">
          <span class="hour-time">{{ item.hour }}</span>
          <span class="hour-count">{{ item.bookings }} lượt</span>
        </div>
      </div>
    </section>

    <!-- Transactions to Review -->
    <section class="panel-card mt-6">
      <div class="panel-head-row">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-coral icon-22">pending_actions</span>
          <h3 class="panel-title">Giao dịch thanh toán cần duyệt ({{ payments.length }})</h3>
        </div>
      </div>

      <div v-if="!payments.length" class="empty-panel-box">
        <span class="material-symbols-outlined icon-24 text-muted">verified</span>
        <p class="muted text-sm">Tất cả giao dịch thanh toán đã được xử lý tự động.</p>
      </div>

      <div v-else class="table-wrap">
        <table class="aura-admin-table">
          <thead>
            <tr>
              <th>Mã giao dịch</th>
              <th>Khách hàng</th>
              <th>Số tiền</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in payments" :key="payment.id">
              <td>
                <span class="code-badge">{{ payment.transfer_code || payment.booking?.code }}</span>
              </td>
              <td>{{ payment.booking?.user?.email || 'Khách' }}</td>
              <td>
                <strong class="text-green">{{ formatCurrency(payment.amount) }}</strong>
              </td>
              <td class="table-actions-cell">
                <button
                  type="button"
                  class="btn-table-action btn-approve"
                  @click="reviewPayment(payment, 'paid')"
                >
                  Xác nhận Đã thanh toán
                </button>
                <button
                  type="button"
                  class="btn-table-action btn-reject"
                  @click="reviewPayment(payment, 'rejected')"
                >
                  Từ chối
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Recent Bookings Table -->
    <section class="panel-card mt-6">
      <div class="panel-head-row">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-pink icon-22">receipt_long</span>
          <h3 class="panel-title">Lượt đặt sân gần đây ({{ bookings.length }})</h3>
        </div>
      </div>

      <div v-if="!bookings.length" class="empty-panel-box">
        <p class="muted text-sm">Chưa có lượt đặt sân nào trong bộ lọc đã chọn.</p>
      </div>

      <div v-else class="table-wrap">
        <table class="aura-admin-table">
          <thead>
            <tr>
              <th>Mã Đặt</th>
              <th>Khách Hàng</th>
              <th>Sân</th>
              <th>Trạng Thái</th>
              <th>Tổng Tiền</th>
              <th>Hành Động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bookings" :key="b.id">
              <td>
                <span class="code-badge">{{ b.code }}</span>
              </td>
              <td>{{ b.user?.name || b.user?.email || 'Khách vãng lai' }}</td>
              <td>{{ b.court?.name || 'Sân tiêu chuẩn' }}</td>
              <td>
                <span class="status-pill" :class="`status-${b.status}`">
                  {{ statusLabel(b.status) }}
                </span>
              </td>
              <td>
                <strong>{{ formatCurrency(b.total_amount) }}</strong>
              </td>
              <td>
                <button
                  v-if="b.status === 'confirmed'"
                  type="button"
                  class="btn-no-show"
                  @click="markNoShow(b)"
                >
                  Vắng mặt
                </button>
                <span v-else class="text-xs text-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Audit Logs -->
    <section class="panel-card mt-6">
      <div class="panel-head-row">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-muted icon-20">history</span>
          <h3 class="panel-title">Nhật ký Kiểm toán (Audit Logs)</h3>
        </div>
      </div>

      <div v-if="!auditLogs.length" class="empty-panel-box">
        <p class="muted text-sm">Chưa có lịch sử thao tác.</p>
      </div>

      <div v-else class="table-wrap">
        <table class="aura-admin-table">
          <thead>
            <tr>
              <th>Thời gian</th>
              <th>Người thực hiện</th>
              <th>Hành động</th>
              <th>Chi tiết</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in auditLogs" :key="log.id">
              <td class="text-xs">{{ formatDateTime(log.created_at) }}</td>
              <td class="font-medium text-xs">{{ log.user?.name || log.user?.email || 'Quản trị viên' }}</td>
              <td class="text-xs">{{ log.action }}</td>
              <td class="text-xs text-muted">{{ JSON.stringify(log.metadata || {}) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>

<style scoped>
.admin-dashboard-shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 30px 24px 90px;
}

/* Header */
.admin-dashboard-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 24px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

@media (min-width: 860px) {
  .admin-dashboard-header {
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

/* Filter Card */
.dashboard-filter-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 16px 20px;
  border: 1px solid var(--border, #e0e4ea);
  margin-bottom: 24px;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 16px;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 160px;
}

.filter-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #6c757d);
}

.filter-select {
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
  min-height: 40px;
}

/* KPI Stats */
.kpi-stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

@media (min-width: 1024px) {
  .kpi-stats-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.kpi-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 18px;
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-blue {
  background: #e3f2fd;
  color: #1976d2;
}

.icon-green {
  background: #e8f5e9;
  color: #2e7d32;
}

.icon-pink {
  background: rgba(255, 209, 220, 0.4);
  color: #78555e;
}

.icon-orange {
  background: #fff3e0;
  color: #ef6c00;
}

.kpi-label {
  font-size: 12px;
  color: var(--muted, #6c757d);
  font-weight: 500;
}

.kpi-val {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 2px 0 0;
  line-height: 1;
}

/* Popular Hours */
.popular-hours-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.hour-chip {
  padding: 6px 12px;
  border-radius: 8px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
  gap: 8px;
}

.hour-time {
  font-weight: 700;
  font-size: 13px;
  color: var(--ink, #191c1d);
}

.hour-count {
  font-size: 11px;
  color: #78555e;
  font-weight: 600;
}

/* Panel Card */
.panel-card {
  background: var(--surface, #ffffff);
  border-radius: 16px;
  padding: 22px;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.panel-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.panel-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

.empty-panel-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 24px 0;
}

/* Table */
.aura-admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-admin-table th {
  text-align: left;
  padding: 10px 12px;
  color: var(--muted, #6c757d);
  font-weight: 600;
  border-bottom: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.aura-admin-table td {
  padding: 12px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
}

.code-badge {
  font-family: monospace;
  font-weight: 700;
  padding: 3px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, #f3f4f5);
  font-size: 12px;
}

.status-pill {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.status-confirmed {
  background: #e8f5e9;
  color: #2e7d32;
}

.status-pending_payment {
  background: #fff3e0;
  color: #ef6c00;
}

.status-cancelled {
  background: #ffebee;
  color: #c62828;
}

.status-no_show {
  background: #f1f5f9;
  color: #64748b;
}

.table-actions-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-table-action {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

.btn-approve {
  background: #e8f5e9;
  color: #2e7d32;
}

.btn-reject {
  background: #ffebee;
  color: #c62828;
}

.btn-no-show {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid var(--border, #cbd5e1);
  background: transparent;
  color: #c62828;
  cursor: pointer;
}

.btn-no-show:hover {
  background: #ffebee;
}

/* Notifications */
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
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #c8e6c9;
}

.error-banner {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

.btn {
  padding: 10px 18px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.btn-primary {
  background: #78555e;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.2);
}

.btn-primary:hover {
  background: #5e3e47;
}

.btn-secondary {
  background: var(--surface-subtle, #e7e8e9);
  color: var(--ink, #191c1d);
}

.btn-secondary:hover {
  background: var(--soft, #dfe0e0);
}
</style>
