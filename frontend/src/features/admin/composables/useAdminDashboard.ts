import { ref, computed, onMounted } from 'vue'
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

export interface AdminBooking {
  id: number
  code: string
  status: string
  total_amount: number
  created_at?: string
  user?: { name?: string; email?: string }
  court?: { id?: number; name: string }
  slots?: { starts_at: string; ends_at?: string; status?: string }[]
}

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

  const bookings = ref<AdminBooking[]>([])
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

  const filteredBookings = computed(() => {
    return bookings.value.filter((b) => {
      const matchSearch =
        !searchFilter.value ||
        b.code?.toLowerCase().includes(searchFilter.value.toLowerCase()) ||
        (b.user?.name || '').toLowerCase().includes(searchFilter.value.toLowerCase())

      const matchStatus = !statusFilter.value || b.status === statusFilter.value

      return matchSearch && matchStatus
    })
  })

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [resReport, resBookings, resPayments] = await Promise.allSettled([
        adminApi.getReports<AdminReport>(`from=${from.value}&to=${to.value}`),
        adminApi.getBookings<{ data: AdminBooking[] } | AdminBooking[]>(),
        adminApi.getPayments<ReviewPayment[]>(),
      ])

      if (resReport.status === 'fulfilled' && resReport.value) {
        report.value = resReport.value
      }
      if (resBookings.status === 'fulfilled') {
        const bData = resBookings.value
        if (Array.isArray(bData)) {
          bookings.value = bData
        } else if (bData && Array.isArray((bData as { data: AdminBooking[] }).data)) {
          bookings.value = (bData as { data: AdminBooking[] }).data
        }
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
