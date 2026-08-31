import { ref, computed, onMounted, onUnmounted } from 'vue'
import { bookingApi } from '../api/bookingApi'
import type { Court } from '@/features/court/types/court'
import type { V2Reservation, V2PriceQuote } from '../types/v2Reservation'
import { errorMessage } from '@/core/utils/errorMessage'

export function useV2Reservations() {
  const courts = ref<Court[]>([])
  const reservations = ref<V2Reservation[]>([])
  const selectedCourtId = ref<number | null>(null)
  
  // Local Date & Time selection defaults (Vietnam / local timezone aware)
  const getLocalDateString = (offsetDays = 0): string => {
    const d = new Date()
    d.setDate(d.getDate() + offsetDays)
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const selectedDate = ref(getLocalDateString(0))
  const selectedTimeSlot = ref('18:00')

  const activeQuote = ref<V2PriceQuote | null>(null)
  const quoteExpiresInSeconds = ref<number>(0)
  let quoteTimerInterval: ReturnType<typeof setInterval> | null = null

  const loading = ref(false)
  const calculatingQuote = ref(false)
  const holdingReservation = ref(false)
  const confirmingCode = ref<string | null>(null)
  const cancellingCode = ref<string | null>(null)

  const activeTab = ref<'all' | 'held' | 'confirmed' | 'cancelled'>('all')
  const searchQuery = ref('')
  const notice = ref('')
  const error = ref('')

  const selectedReservationForDetails = ref<V2Reservation | null>(null)
  const selectedReservationForQr = ref<V2Reservation | null>(null)

  const money = (value: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

  const formatDate = (value: string) => {
    if (!value) return ''
    try {
      return new Date(value).toLocaleString('vi-VN', {
        timeZone: 'Asia/Ho_Chi_Minh',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    } catch {
      return value
    }
  }

  const formatTimeSlot = (startsAt: string, endsAt?: string) => {
    if (!startsAt) return ''
    const start = (startsAt.includes('T') ? startsAt.split('T')[1]?.slice(0, 5) : startsAt.slice(11, 16)) || '00:00'
    if (endsAt) {
      const end = (endsAt.includes('T') ? endsAt.split('T')[1]?.slice(0, 5) : endsAt.slice(11, 16)) || '00:00'
      return `${start} - ${end}`
    }
    const parts = start.split(':').map(Number)
    const h = parts[0] ?? 0
    const m = parts[1] ?? 0
    const endH = String((h + 1) % 24).padStart(2, '0')
    return `${start} - ${endH}:${String(m).padStart(2, '0')}`
  }

  function startQuoteCountdown(expiresAt: string) {
    if (quoteTimerInterval) clearInterval(quoteTimerInterval)
    const targetTime = new Date(expiresAt).getTime()

    const updateRemaining = () => {
      const diff = Math.max(0, Math.floor((targetTime - Date.now()) / 1000))
      quoteExpiresInSeconds.value = diff
      if (diff <= 0 && quoteTimerInterval) {
        clearInterval(quoteTimerInterval)
        quoteTimerInterval = null
      }
    }

    updateRemaining()
    quoteTimerInterval = setInterval(updateRemaining, 1000)
  }

  const formattedQuoteRemaining = computed(() => {
    const totalSec = quoteExpiresInSeconds.value
    const mins = Math.floor(totalSec / 60)
    const secs = totalSec % 60
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
  })

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [courtsRes, resRes] = await Promise.allSettled([
        bookingApi.getCourts(),
        bookingApi.getReservations<{ data?: V2Reservation[]; current_page?: number }>(),
      ])

      if (courtsRes.status === 'fulfilled' && Array.isArray(courtsRes.value)) {
        courts.value = courtsRes.value
        if (!selectedCourtId.value && courtsRes.value.length > 0 && courtsRes.value[0]?.id) {
          selectedCourtId.value = courtsRes.value[0].id
        }
      }

      if (resRes.status === 'fulfilled') {
        const payload = resRes.value
        if (Array.isArray(payload)) {
          reservations.value = payload
        } else if (payload && Array.isArray(payload.data)) {
          reservations.value = payload.data
        } else {
          reservations.value = []
        }
      }
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể tải dữ liệu lịch đặt v2.')
    } finally {
      loading.value = false
    }
  }

  async function calculateQuote() {
    if (!selectedCourtId.value || !selectedDate.value || !selectedTimeSlot.value) {
      error.value = 'Vui lòng chọn sân và khung giờ chơi.'
      return
    }

    calculatingQuote.value = true
    error.value = ''
    notice.value = ''

    try {
      const slotIso = new Date(`${selectedDate.value}T${selectedTimeSlot.value}:00`).toISOString()
      const response = await bookingApi.getPriceQuote({
        court_id: Number(selectedCourtId.value),
        slots: [slotIso],
      })

      if (response && response.quote) {
        activeQuote.value = response.quote
        startQuoteCountdown(response.quote.expires_at)
        notice.value = 'Đã tạo báo giá động bất biến! Báo giá được bảo đảm trong 15 phút.'
      }
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể tạo báo giá cho khung giờ này.')
    } finally {
      calculatingQuote.value = false
    }
  }

  async function holdReservation() {
    if (!activeQuote.value) return
    holdingReservation.value = true
    error.value = ''
    notice.value = ''

    try {
      await bookingApi.holdReservation({
        quote_token: activeQuote.value.quote_token,
      })
      activeQuote.value = null
      if (quoteTimerInterval) clearInterval(quoteTimerInterval)
      notice.value = 'Đã giữ lịch thành công! Vui lòng xác nhận thanh toán để kích hoạt quyền vào sân.'
      await loadData()
      activeTab.value = 'held'
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể giữ lịch đặt.')
    } finally {
      holdingReservation.value = false
    }
  }

  async function confirmReservation(reservation: V2Reservation) {
    confirmingCode.value = reservation.code
    error.value = ''
    notice.value = ''

    try {
      await bookingApi.confirmReservation(reservation.code)
      notice.value = `Lịch đặt #${reservation.code} đã được xác nhận thành công!`
      await loadData()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể xác nhận lịch đặt.')
    } finally {
      confirmingCode.value = null
    }
  }

  async function cancelReservation(reservation: V2Reservation) {
    if (!confirm('Bạn có chắc chắn muốn hủy lịch giữ chỗ này không?')) return
    cancellingCode.value = reservation.code
    error.value = ''
    notice.value = ''

    try {
      await bookingApi.cancelReservation(reservation.code)
      notice.value = `Đã hủy lịch giữ chỗ #${reservation.code}.`
      await loadData()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể hủy lịch đặt.')
    } finally {
      cancellingCode.value = null
    }
  }

  const filteredReservations = computed(() => {
    let list = reservations.value

    if (activeTab.value !== 'all') {
      list = list.filter((r) => r.status === activeTab.value)
    }

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter(
        (r) =>
          r.code?.toLowerCase().includes(q) ||
          r.court?.name?.toLowerCase().includes(q) ||
          r.court?.venue?.name?.toLowerCase().includes(q)
      )
    }

    return list
  })

  const totalCount = computed(() => reservations.value.length)
  const heldCount = computed(() => reservations.value.filter((r) => r.status === 'held').length)
  const confirmedCount = computed(() => reservations.value.filter((r) => r.status === 'confirmed').length)
  const cancelledCount = computed(() => reservations.value.filter((r) => r.status === 'cancelled' || r.status === 'expired').length)

  const activeHeldReservation = computed(() =>
    reservations.value.find((r) => r.status === 'held')
  )

  const selectedCourt = computed(() =>
    courts.value.find((c) => c.id === selectedCourtId.value)
  )

  function openDetails(reservation: V2Reservation) {
    selectedReservationForDetails.value = reservation
  }

  function closeDetails() {
    selectedReservationForDetails.value = null
  }

  function openQr(reservation: V2Reservation) {
    selectedReservationForQr.value = reservation
  }

  function closeQr() {
    selectedReservationForQr.value = null
  }

  onMounted(() => {
    loadData()
  })

  onUnmounted(() => {
    if (quoteTimerInterval) clearInterval(quoteTimerInterval)
  })

  return {
    courts,
    reservations,
    selectedCourtId,
    selectedCourt,
    selectedDate,
    selectedTimeSlot,
    activeQuote,
    quoteExpiresInSeconds,
    formattedQuoteRemaining,
    loading,
    calculatingQuote,
    holdingReservation,
    confirmingCode,
    cancellingCode,
    activeTab,
    searchQuery,
    notice,
    error,
    selectedReservationForDetails,
    selectedReservationForQr,
    filteredReservations,
    totalCount,
    heldCount,
    confirmedCount,
    cancelledCount,
    activeHeldReservation,
    money,
    formatDate,
    formatTimeSlot,
    loadData,
    calculateQuote,
    holdReservation,
    confirmReservation,
    cancelReservation,
    openDetails,
    closeDetails,
    openQr,
    closeQr,
  }
}
