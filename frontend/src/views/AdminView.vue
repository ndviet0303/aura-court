<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, apiOrigin, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency, formatDateTime } from '@/i18n'

const { t } = useI18n()

type AdminReport = { bookings: number; confirmed: number; revenue: number; pendingRefunds: number; occupancyRate?: number; byHour?: { hour: string; bookings: number }[] }
type AdminBooking = { id: number; code: string; status: string; total_amount: number; user?: { name: string; email?: string }; court?: { name: string }; slots?: { starts_at: string }[] }
type ReviewPayment = { id: number; amount: number; transfer_code?: string; booking?: { code: string; user?: { email: string }; court?: { name: string } } }
type AuditLog = { id: number; action: string; user?: { name: string; email: string }; metadata?: Record<string, unknown>; created_at: string }

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
const isDemoAdmin = computed(() => auth.user?.role === 'admin' && auth.user.email.endsWith('@demo.auracourt.vn'))
const statusLabel = (value: string) => t(`common.status.${value}`, value)
const csvUrl = computed(() => apiOrigin + '/api/v1/admin/reports.csv?from=' + encodeURIComponent(from.value) + '&to=' + encodeURIComponent(to.value))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams()
    if (from.value) query.set('from', from.value)
    if (to.value) query.set('to', to.value)
    if (status.value) query.set('status', status.value)
    report.value = await api.get<AdminReport>('/admin/reports?' + query)
    bookings.value = (await api.get<{ data: AdminBooking[] }>('/admin/bookings?' + query)).data
    payments.value = (await api.get<{ data: ReviewPayment[] }>('/admin/payments')).data
    auditLogs.value = (await api.get<{ data: AuditLog[] }>('/admin/audit-logs')).data
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function reviewPayment(payment: ReviewPayment, nextStatus: 'paid' | 'rejected') {
  if (isDemoAdmin.value) {
    payments.value = payments.value.filter((item) => item.id !== payment.id)
    bookings.value = bookings.value.map((booking) => booking.code === payment.booking?.code ? { ...booking, status: nextStatus === 'paid' ? 'confirmed' : 'cancelled' } : booking)
    notice.value = 'Demo: Simulated review action.'
    return
  }
  try {
    await api.patch('/admin/payments/' + payment.id, { status: nextStatus })
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, t('common.error')) }
}

async function markNoShow(booking: AdminBooking) {
  if (isDemoAdmin.value) {
    bookings.value = bookings.value.map((item) => item.id === booking.id ? { ...item, status: 'no_show' } : item)
    notice.value = 'Demo: Simulated no-show action.'
    return
  }
  try {
    await api.patch('/admin/bookings/' + booking.id + '/no-show', {})
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, t('common.error')) }
}

onMounted(load)
</script>

<template>
  <section class="page-shell">
    <div class="row">
      <div>
        <div class="eyebrow">{{ t('admin.eyebrow') }}</div>
        <h1 class="page-title">{{ t('admin.title') }}</h1>
        <p class="muted">{{ t('admin.reports') }}</p>
      </div>
      <a class="btn" :href="csvUrl">CSV</a>
    </div>
    <form class="panel admin-form" @submit.prevent="load">
      <div class="admin-form-grid compact">
        <label>{{ t('schedule.selectDate') }} (From)<DatePicker v-model="from" /></label>
        <label>{{ t('schedule.selectDate') }} (To)<DatePicker v-model="to" /></label>
        <label>{{ t('common.search') }}<select v-model="status">
          <option value="">{{ t('common.all') }}</option>
          <option value="pending_payment">{{ t('common.status.pending_payment') }}</option>
          <option value="confirmed">{{ t('common.status.confirmed') }}</option>
          <option value="cancelled">{{ t('common.status.cancelled') }}</option>
          <option value="no_show">{{ t('common.status.no_show') }}</option>
        </select></label>
        <button class="btn" :disabled="loading">{{ loading ? t('common.loading') : t('common.search') }}</button>
      </div>
    </form>
    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="notice" class="notice">{{ notice }}</p>
    <div v-if="report" class="grid">
      <div class="card"><p class="muted">{{ t('booking.title') }}</p><h2>{{ report.bookings }}</h2></div>
      <div class="card"><p class="muted">{{ t('common.status.confirmed') }}</p><h2>{{ report.confirmed }}</h2></div>
      <div class="card"><p class="muted">Revenue</p><h2>{{ formatCurrency(report.revenue) }}</h2></div>
      <div class="card"><p class="muted">Occupancy</p><h2>{{ report.occupancyRate || 0 }}%</h2></div>
      <div class="card"><p class="muted">{{ t('common.status.refunded') }}</p><h2>{{ formatCurrency(report.pendingRefunds) }}</h2></div>
    </div>
    <section v-if="report?.byHour?.length" class="card" style="margin-top:22px">
      <h2>Popular Hours</h2>
      <div class="price-list">
        <span v-for="item in report.byHour" :key="item.hour" class="badge">{{ item.hour }} · {{ item.bookings }}</span>
      </div>
    </section>
    <section class="card" style="margin-top:22px">
      <h2>Transactions to review</h2>
      <p v-if="!payments.length" class="muted">No pending payments.</p>
      <div v-else class="table-wrap">
        <table class="table">
          <thead><tr><th>Code</th><th>User</th><th>Amount</th><th>Action</th></tr></thead>
          <tbody>
            <tr v-for="payment in payments" :key="payment.id">
              <td>{{ payment.booking?.code || '—' }}</td>
              <td>{{ payment.booking?.user?.email || '—' }}</td>
              <td>{{ formatCurrency(payment.amount) }}</td>
              <td class="actions">
                <button type="button" class="link-button" @click="reviewPayment(payment, 'paid')">{{ t('common.confirm') }}</button>
                <button type="button" class="link-button danger" @click="reviewPayment(payment, 'rejected')">{{ t('common.cancel') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section class="card" style="margin-top:22px">
      <h2>Recent Bookings</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Code</th><th>Customer</th><th>Court</th><th>Status</th><th>Total</th><th></th></tr></thead>
          <tbody>
            <tr v-for="booking in bookings" :key="booking.id">
              <td>{{ booking.code }}</td>
              <td>{{ booking.user?.name }}</td>
              <td>{{ booking.court?.name }}</td>
              <td>{{ statusLabel(booking.status) }}</td>
              <td>{{ formatCurrency(booking.total_amount) }}</td>
              <td><button v-if="booking.status === 'confirmed'" type="button" class="link-button danger" @click="markNoShow(booking)">{{ t('common.status.no_show') }}</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section class="card" style="margin-top:22px">
      <h2>Audit Logs</h2>
      <p v-if="!auditLogs.length" class="muted">No audit logs.</p>
      <div v-else class="table-wrap">
        <table class="table">
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
          <tbody>
            <tr v-for="log in auditLogs" :key="log.id">
              <td>{{ formatDateTime(log.created_at) }}</td>
              <td>{{ log.user?.name || log.user?.email || '—' }}</td>
              <td>{{ log.action }}</td>
              <td>{{ JSON.stringify(log.metadata || {}) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
