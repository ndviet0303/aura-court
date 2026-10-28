import { ref, computed, onMounted, watch } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { ReservationHolder } from '@/features/booking/types/reservation'

// Item of GET /admin/reservations (C4).
export interface AdminReservation {
  id: number
  code: string
  status: string
  total_amount: number
  created_at?: string
  // Who may use the booking: the booker, or the buyer after a transfer (D6); null when it has none.
  holder?: ReservationHolder | null
  court?: { id?: number; code: string }
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
] as const

// A V1 mirror shows its original booking code and status, never its LEGACY-* code.
export const reservationCode = (r: AdminReservation) => r.legacy_mapping?.[0]?.legacy_code ?? r.code
export const reservationStatus = (r: AdminReservation) =>
  r.status === 'legacy' ? (r.legacy_mapping?.[0]?.legacy_status ?? r.status) : r.status
// Phone and email of the booking's holder; empty when it has none.
export const holderContact = (r: AdminReservation) =>
  [r.holder?.phone, r.holder?.email].filter(Boolean).join(' · ')

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

  const bookings = ref<AdminReservation[]>([])
  const payments = ref<ReviewPayment[]>([])

  const searchFilter = ref('')
  const statusFilter = ref('')

  // Status is filtered by the server; search covers the loaded page.
  const filteredBookings = computed(() => {
    const kw = searchFilter.value.toLowerCase()
    return bookings.value.filter(
      (b) =>
        !kw ||
        reservationCode(b).toLowerCase().includes(kw) ||
        (b.holder?.name || '').toLowerCase().includes(kw),
    )
  })

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [resBookings, resPayments] = await Promise.allSettled([
        adminApi.getReservations<{ data: AdminReservation[] }>({
          status: statusFilter.value || undefined,
        }),
        adminApi.getPayments<ReviewPayment[]>(),
      ])

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

  onMounted(() => loadData())

  return {
    loading,
    notice,
    error,
    bookings,
    payments,
    searchFilter,
    statusFilter,
    filteredBookings,
    showNotice,
  }
}
