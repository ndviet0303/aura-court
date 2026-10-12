import { ref, computed, onMounted, watch } from 'vue'
import { apiOrigin } from '@/core/api/client'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface AdminReport {
  from?: string
  to?: string
  bookings: number
  confirmed: number
  revenue: number
  pendingRefunds: number
  occupancyRate?: number
  byHour?: { hour: string; bookings: number }[]
  byCourt?: {
    court_id: number
    bookings: number
    revenue: number
    court?: { id: number; name: string }
  }[]
}

// Item of GET v2/admin/reservations (C4).
export interface AdminReservation {
  id: number
  code: string
  status: string
  total_amount: number
  created_at?: string
  user?: { name?: string; email?: string } | null
  court?: { id?: number; name: string }
  slots?: { starts_at: string; ends_at?: string; status?: string }[]
  legacy_mapping?: { legacy_code: string; legacy_status: string }[]
}

export const RESERVATION_STATUSES = [
  'held',
  'confirmed',
  'in_use',
  'completed',
  'cancelled',
  'expired',
  'disputed',
  'legacy',
] as const

// A V1 mirror shows its original booking code and status, never its LEGACY-* code.
export const reservationCode = (r: AdminReservation) => r.legacy_mapping?.[0]?.legacy_code ?? r.code
export const reservationStatus = (r: AdminReservation) =>
  r.status === 'legacy' ? (r.legacy_mapping?.[0]?.legacy_status ?? r.status) : r.status

export interface ReviewPayment {
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

export function useAdminDashboard() {
  const loading = ref(false)
  const notice = ref('')
  const error = ref('')

  const report = ref<AdminReport>({
    bookings: 0,
    confirmed: 0,
    revenue: 0,
    pendingRefunds: 0,
    occupancyRate: 0,
    byHour: [],
    byCourt: [],
  })

  const bookings = ref<AdminReservation[]>([])
  const payments = ref<ReviewPayment[]>([])

  const datePreset = ref<'today' | '7days' | 'thisMonth' | 'custom'>('thisMonth')
  const from = ref('')
  const to = ref('')
  const searchFilter = ref('')
  const statusFilter = ref('')
  const chartTab = ref<'hourly' | 'court'>('hourly')

  const csvUrl = computed(() => {
    const query = new URLSearchParams()
    if (from.value) query.set('from', from.value)
    if (to.value) query.set('to', to.value)
    return `${apiOrigin}/api/v1/admin/reports.csv?${query}`
  })

  function formatDate(d: Date): string {
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  function applyPreset(preset: 'today' | '7days' | 'thisMonth' | 'custom') {
    datePreset.value = preset
    const now = new Date()
    if (preset === 'today') {
      from.value = formatDate(now)
      to.value = formatDate(now)
    } else if (preset === '7days') {
      const past = new Date(now)
      past.setDate(now.getDate() - 7)
      from.value = formatDate(past)
      to.value = formatDate(now)
    } else if (preset === 'thisMonth') {
      from.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
      const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0)
      to.value = formatDate(endOfMonth)
    }
    if (preset !== 'custom') {
      loadData()
    }
  }

  // Status is filtered by the server; search covers the loaded page.
  const filteredBookings = computed(() => {
    const kw = searchFilter.value.toLowerCase()
    return bookings.value.filter(
      (b) =>
        !kw ||
        reservationCode(b).toLowerCase().includes(kw) ||
        (b.user?.name || '').toLowerCase().includes(kw),
    )
  })

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [resReport, resBookings, resPayments] = await Promise.allSettled([
        adminApi.getReports<AdminReport>(`from=${from.value}&to=${to.value}`),
        adminApi.getReservations<{ data: AdminReservation[] }>({
          status: statusFilter.value || undefined,
        }),
        adminApi.getPayments<ReviewPayment[]>(),
      ])

      if (resReport.status === 'fulfilled' && resReport.value) {
        report.value = resReport.value
      }
      if (resBookings.status === 'fulfilled' && Array.isArray(resBookings.value?.data)) {
        bookings.value = resBookings.value.data
      }
      if (resPayments.status === 'fulfilled' && Array.isArray(resPayments.value)) {
        payments.value = resPayments.value
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải số liệu báo cáo.')
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  watch(statusFilter, () => loadData())

  onMounted(() => {
    applyPreset('thisMonth')
    loadData()
  })

  return {
    loading,
    notice,
    error,
    report,
    bookings,
    payments,
    datePreset,
    from,
    to,
    searchFilter,
    statusFilter,
    chartTab,
    csvUrl,
    filteredBookings,
    applyPreset,
    loadData,
    showNotice,
  }
}
