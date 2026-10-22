import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/features/auth/api/authApi'
import { bookingErrorMessage } from '@/features/booking/utils/reservation'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, AvailabilitySlot, Venue } from '../types/court'
import { courtApi } from '../api/courtApi'

export type ScheduleTimeFilter = 'all' | 'morning' | 'afternoon' | 'evening' | 'peak'

export function useCourtSchedule() {
  const route = useRoute()
  const router = useRouter()
  const store = useBookingStore()
  const auth = useAuthStore()

  // Calendar days in the browser's own day: toISOString() gives the UTC day, which is still
  // yesterday before 07:00 in Vietnam.
  const isoDay = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const shiftDay = (iso: string, days: number): string => {
    const d = new Date(`${iso}T00:00`)
    d.setDate(d.getDate() + days)
    return isoDay(d)
  }
  const getLocalDateString = (offsetDays = 0): string => shiftDay(isoDay(new Date()), offsetDays)

  const date = ref(getLocalDateString(0))
  const courtIdParam = computed(() => String(route.params.id || ''))
  const selectedCourtId = ref<number | null>(null)
  const selectedVenueId = ref<number | null>(null)
  const timeFilter = ref<ScheduleTimeFilter>('all')
  const error = ref('')
  const notice = ref('')
  const loading = ref(false)
  const checkoutLoading = ref(false)

  const court = computed<Court | null>(
    () => store.courts.find((c: Court) => c.id === selectedCourtId.value) || null,
  )
  const directOwnerPayments = computed(
    () => court.value?.venue?.payment_model === 'direct_owner_v1',
  )

  const venuesList = computed<Venue[]>(() => {
    const map = new Map<number, Venue>()
    for (const c of store.courts) {
      if (c.venue && !map.has(c.venue.id)) {
        map.set(c.venue.id, c.venue)
      }
    }
    return Array.from(map.values())
  })

  const filteredCourts = computed<Court[]>(() => {
    if (!selectedVenueId.value) return store.courts
    return store.courts.filter(
      (c: Court) => c.venue?.id === selectedVenueId.value || c.venue_id === selectedVenueId.value,
    )
  })

  const carouselDays = computed(() => {
    const days = []
    const todayStr = getLocalDateString(0)
    for (let i = 0; i < 14; i++) {
      const iso = shiftDay(todayStr, i)
      const d = new Date(`${iso}T00:00`)
      const dayNum = d.getDate()
      const dayName = d.toLocaleDateString('vi-VN', { weekday: 'short' })
      const monthName = `Thg ${d.getMonth() + 1}`
      const isToday = iso === todayStr
      const isWeekend = d.getDay() === 0 || d.getDay() === 6
      days.push({ iso, dayNum, dayName, monthName, isToday, isWeekend })
    }
    return days
  })

  const activeDateLabel = computed(() => {
    try {
      const d = new Date(`${date.value}T00:00`)
      return d.toLocaleDateString('vi-VN', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      })
    } catch {
      return date.value
    }
  })

  const filteredSlots = computed<AvailabilitySlot[]>(() => {
    return store.slots.filter((slot: AvailabilitySlot) => {
      const hour = Number(
        new Date(slot.startsAt).toLocaleTimeString('en-US', {
          timeZone: 'Asia/Ho_Chi_Minh',
          hour: '2-digit',
          hour12: false,
        }),
      )
      if (timeFilter.value === 'morning') return hour < 12
      if (timeFilter.value === 'afternoon') return hour >= 12 && hour < 17
      if (timeFilter.value === 'evening') return hour >= 17
      if (timeFilter.value === 'peak') return hour >= 17 && hour <= 21
      return true
    })
  })

  const availableSlotsCount = computed(() => {
    return store.slots.filter((s: AvailabilitySlot) => s.available).length
  })

  const totalSlotsCount = computed(() => store.slots.length)

  const totalHours = computed(() => (store.selection?.minutes ?? 0) / 60)

  async function fetchAvailability() {
    if (!court.value) return
    loading.value = true
    error.value = ''
    store.slots = []
    try {
      await store.loadAvailability(court.value.id, date.value)
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể tải lịch sân cho ngày đã chọn.')
    } finally {
      loading.value = false
    }
  }

  async function init() {
    loading.value = true
    error.value = ''
    selectedCourtId.value = null
    selectedVenueId.value = null
    store.selectedCourt = null
    store.selectedSlots = []
    store.slots = []
    // A QR deep link fills in its private code (module 3 D9); it is checked against the chosen court.
    if (typeof route.query.voucher === 'string') store.setVoucherLink(route.query.voucher)
    try {
      if (store.courts.length === 0) {
        await store.loadCourts()
      }
      const key = courtIdParam.value
      if (key) {
        // The cached list may omit a newly published court or contain stale eligibility.
        const requestedCourt = await courtApi.getById(key)
        const index = store.courts.findIndex((c: Court) => c.id === requestedCourt.id)
        if (index >= 0) store.courts[index] = requestedCourt
        else store.courts.push(requestedCourt)
        selectedCourtId.value = requestedCourt.id
        selectedVenueId.value = requestedCourt.venue?.id || requestedCourt.venue_id || null
      } else if (store.courts[0]) {
        selectedCourtId.value = store.courts[0].id
        selectedVenueId.value = store.courts[0].venue?.id || store.courts[0].venue_id || null
      }
      await fetchAvailability()
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể tải sân được yêu cầu.')
    } finally {
      loading.value = false
    }
  }

  function selectDate(isoDate: string) {
    date.value = isoDate
    fetchAvailability()
  }

  function goToToday() {
    selectDate(getLocalDateString(0))
  }

  function goToPrevDay() {
    selectDate(shiftDay(date.value, -1))
  }

  function goToNextDay() {
    selectDate(shiftDay(date.value, 1))
  }

  function selectCourt(courtId: number) {
    selectedCourtId.value = courtId
    router.replace({ params: { id: String(courtId) } })
    fetchAvailability()
  }

  function selectVenue(venueId: number | null) {
    selectedVenueId.value = venueId
    const courtsInVenue = filteredCourts.value
    if (
      courtsInVenue.length > 0 &&
      courtsInVenue[0] &&
      !courtsInVenue.some((c: Court) => c.id === selectedCourtId.value)
    ) {
      selectCourt(courtsInVenue[0].id)
    }
  }

  function toggleSlot(slot: AvailabilitySlot) {
    if (loading.value || !court.value || !slot.available) return
    store.toggle(slot)
  }

  function clearSelectedSlots() {
    store.selectedSlots = []
  }

  // The first free run of `count` hours, two 30-minute times per hour, within the time filter.
  function quickSelectAvailableHours(count: number = 1) {
    if (loading.value || !court.value) return
    clearSelectedSlots()
    const times = filteredSlots.value
    for (let i = 0; i + count * 2 <= times.length; i++) {
      const run = store.freeRun(times[i]!, times[i + count * 2 - 1]!)
      if (run?.length === count * 2) {
        store.selectedSlots = run
        return
      }
    }
  }

  // expectedTotal is the quote total shown, or the new price confirmed after a PRICE_CHANGED; it is
  // null until a signed-in customer's quote is in.
  async function proceedToBooking(expectedTotal: number | null) {
    if (
      loading.value ||
      !court.value ||
      store.selectedCourt !== court.value.id ||
      store.selectedSlots.length === 0
    )
      return
    if (!auth.user) {
      router.push({ name: 'login', query: { redirect: route.fullPath } })
      return
    }
    if (expectedTotal === null) return

    checkoutLoading.value = true
    error.value = ''
    try {
      const reservation = await store.checkoutReservation(expectedTotal)
      router.push({ name: 'reservations', params: { code: reservation.code } })
    } catch (cause) {
      const again = (total: number) => proceedToBooking(total)
      if (!store.catchPriceChange(cause, again) && !store.catchVoucherInvalid(cause, again)) {
        error.value = bookingErrorMessage(
          cause,
          'Không thể khởi tạo đơn đặt sân. Vui lòng thử lại.',
        )
      }
    } finally {
      checkoutLoading.value = false
    }
  }

  const resendingEmail = ref(false)

  async function resendVerificationEmail() {
    resendingEmail.value = true
    try {
      const res = await authApi.resendVerification()
      notice.value =
        res?.message || 'Đã gửi email xác thực thành công! Vui lòng kiểm tra hộp thư của bạn.'
      error.value = ''
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể gửi email xác thực. Vui lòng thử lại sau.')
    } finally {
      resendingEmail.value = false
    }
  }

  onMounted(() => {
    init()
  })

  return {
    store,
    auth,
    date,
    activeDateLabel,
    selectedCourtId,
    selectedVenueId,
    timeFilter,
    court,
    directOwnerPayments,
    venuesList,
    filteredCourts,
    filteredSlots,
    availableSlotsCount,
    totalSlotsCount,
    carouselDays,
    totalHours,
    error,
    notice,
    loading,
    checkoutLoading,
    resendingEmail,
    selectDate,
    goToToday,
    goToPrevDay,
    goToNextDay,
    selectCourt,
    selectVenue,
    toggleSlot,
    // The summary shows one interval, so removing it clears the selection.
    removeSlot: clearSelectedSlots,
    clearSelectedSlots,
    quickSelectAvailableHours,
    proceedToBooking,
    resendVerificationEmail,
    fetchAvailability,
  }
}
