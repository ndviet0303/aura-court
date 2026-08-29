import { ref, computed } from 'vue'
import { apiOrigin } from '@/core/api/client'
import { adminApi } from '../api/adminApi'
import { bookingApi } from '@/features/booking/api/bookingApi'
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
  byCourt?: { court_id: number; bookings: number; revenue: number; court?: { id: number; name: string } }[]
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
    bookings: 142,
    confirmed: 128,
    revenue: 38450000,
    pendingRefunds: 2,
    occupancyRate: 84.5,
    byHour: [
      { hour: '06:00', bookings: 4 },
      { hour: '08:00', bookings: 8 },
      { hour: '17:00', bookings: 24 },
      { hour: '19:00', bookings: 32 },
      { hour: '21:00', bookings: 16 },
    ],
    byCourt: [
      { court_id: 1, bookings: 45, revenue: 12500000, court: { id: 1, name: 'Sân 01 (Thảm BWF)' } },
      { court_id: 2, bookings: 38, revenue: 10800000, court: { id: 2, name: 'Sân 02 (VIP)' } },
      { court_id: 3, bookings: 32, revenue: 8900000, court: { id: 3, name: 'Sân 03' } },
    ],
  })

  const bookings = ref<AdminBooking[]>([
    {
      id: 101,
      code: 'BK-89021',
      status: 'confirmed',
      total_amount: 360000,
      created_at: '2026-08-30 08:30',
      user: { name: 'Nguyễn Văn A', email: 'vana@gmail.com' },
      court: { id: 1, name: 'Sân 01' },
      slots: [{ starts_at: '18:00', ends_at: '20:00' }],
    },
    {
      id: 102,
      code: 'BK-89022',
      status: 'pending',
      total_amount: 180000,
      created_at: '2026-08-30 09:15',
      user: { name: 'Trần Thị B', email: 'thib@gmail.com' },
      court: { id: 2, name: 'Sân 02' },
      slots: [{ starts_at: '19:00', ends_at: '20:00' }],
    },
  ])

  const payments = ref<ReviewPayment[]>([
    {
      id: 501,
      amount: 360000,
      transfer_code: 'AURA BK-89021',
      status: 'pending',
      created_at: '2026-08-30 08:32',
      booking: {
        code: 'BK-89021',
        user: { name: 'Nguyễn Văn A', email: 'vana@gmail.com' },
        court: { name: 'Sân 01' },
      },
    },
  ])

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

  function applyPreset(preset: 'today' | '7days' | 'thisMonth' | 'custom') {
    datePreset.value = preset
    const now = new Date()
    const formatDate = (d: Date): string => d.toISOString().split('T')[0] || ''
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
      to.value = formatDate(now)
    }
  }

  const filteredBookings = computed(() => {
    return bookings.value.filter((b) => {
      const matchSearch =
        !searchFilter.value ||
        b.code.toLowerCase().includes(searchFilter.value.toLowerCase()) ||
        (b.user?.name || '').toLowerCase().includes(searchFilter.value.toLowerCase())

      const matchStatus = !statusFilter.value || b.status === statusFilter.value

      return matchSearch && matchStatus
    })
  })

  async function loadData() {
    loading.value = true
    try {
      const res = await adminApi.getReports<AdminReport>(`from=${from.value}&to=${to.value}`)
      if (res) report.value = res
    } catch (e) {
      // fallback
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
