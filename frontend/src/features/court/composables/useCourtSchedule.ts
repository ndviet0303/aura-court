import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/features/auth/api/authApi'
import { bookingApi } from '@/features/booking/api/bookingApi'
import type { V2PriceQuote } from '@/features/booking/types/v2Reservation'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, AvailabilitySlot, Venue } from '../types/court'

export type ScheduleTimeFilter = 'all' | 'morning' | 'afternoon' | 'evening' | 'peak'

export function useCourtSchedule() {
  const route = useRoute()
  const router = useRouter()
  const store = useBookingStore()
  const auth = useAuthStore()

  // Local Date helper
  const getLocalDateString = (offsetDays = 0): string => {
    const d = new Date()
    d.setDate(d.getDate() + offsetDays)
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const date = ref(getLocalDateString(0))
  const slugOrId = computed(() => String(route.params.slug || route.params.id || ''))
  const selectedCourtId = ref<number | null>(null)
  const selectedVenueId = ref<number | null>(null)
  const timeFilter = ref<ScheduleTimeFilter>('all')
  const error = ref('')
  const notice = ref('')
  const loading = ref(false)
  const checkoutLoading = ref(false)
  const quoteLoading = ref(false)
  const activeQuote = ref<V2PriceQuote | null>(null)

  const court = computed<Court | null>(() => {
    const key = slugOrId.value
    if (selectedCourtId.value) {
      const foundById = store.courts.find((c: Court) => c.id === selectedCourtId.value)
      if (foundById) return foundById
    }
    if (key) {
      const foundByKey = store.courts.find((c: Court) => c.slug === key || String(c.id) === key)
      if (foundByKey) return foundByKey
    }
    return store.courts[0] || null
  })

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
    return store.courts.filter((c: Court) => c.venue?.id === selectedVenueId.value || c.venue_id === selectedVenueId.value)
  })

  const carouselDays = computed(() => {
    const days = []
    const todayStr = new Date().toISOString().slice(0, 10)
    const base = new Date()
    for (let i = 0; i < 14; i++) {
      const d = new Date(base)
      d.setDate(base.getDate() + i)
      const iso = d.toISOString().slice(0, 10)
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
      const d = new Date(date.value)
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
        })
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

  const totalPrice = computed(() => {
    return store.selectedSlots.reduce((sum: number, slot: AvailabilitySlot) => sum + slot.price, 0)
  })

  const totalHours = computed(() => store.selectedSlots.length)

  async function fetchAvailability() {
    if (!court.value) return
    loading.value = true
    error.value = ''
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
    try {
      if (store.courts.length === 0) {
        await store.loadCourts()
      }
      const key = slugOrId.value
      if (key) {
        const found = store.courts.find((c: Court) => c.slug === key || String(c.id) === key)
        if (found) {
          selectedCourtId.value = found.id
          if (found.venue?.id) {
            selectedVenueId.value = found.venue.id
          }
        }
      } else if (store.courts.length > 0 && store.courts[0]) {
        selectedCourtId.value = store.courts[0].id
      }
      await fetchAvailability()
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể khởi tạo danh sách sân.')
    } finally {
      loading.value = false
    }
  }

  function selectDate(isoDate: string) {
    date.value = isoDate
    fetchAvailability()
  }

  function goToToday() {
    const todayIso = new Date().toISOString().slice(0, 10)
    selectDate(todayIso)
  }

  function goToPrevDay() {
    const d = new Date(date.value)
    d.setDate(d.getDate() - 1)
    selectDate(d.toISOString().slice(0, 10))
  }

  function goToNextDay() {
    const d = new Date(date.value)
    d.setDate(d.getDate() + 1)
    selectDate(d.toISOString().slice(0, 10))
  }

  function selectCourt(courtId: number) {
    selectedCourtId.value = courtId
    const found = store.courts.find((c: Court) => c.id === courtId)
    if (found?.slug) {
      router.replace({ params: { slug: found.slug } })
    }
    fetchAvailability()
  }

  function selectVenue(venueId: number | null) {
    selectedVenueId.value = venueId
    const courtsInVenue = filteredCourts.value
    if (courtsInVenue.length > 0 && courtsInVenue[0] && !courtsInVenue.some((c: Court) => c.id === selectedCourtId.value)) {
      selectCourt(courtsInVenue[0].id)
    }
  }

  function toggleSlot(slot: AvailabilitySlot) {
    if (!slot.available) return
    store.toggle(slot)
  }

  function removeSlot(slot: AvailabilitySlot) {
    store.toggle(slot)
  }

  function clearSelectedSlots() {
    store.selectedSlots = []
  }

  function quickSelectAvailableHours(count: number = 1) {
    clearSelectedSlots()
    const available = filteredSlots.value.filter((s) => s.available)
    for (let i = 0; i < Math.min(count, available.length); i++) {
      const slot = available[i]
      if (slot) {
        store.toggle(slot)
      }
    }
  }

  async function proceedToBooking(loyaltyPayload?: any) {
    if (!court.value || store.selectedSlots.length === 0) return
    if (!auth.user) {
      router.push({ name: 'login', query: { redirect: route.fullPath } })
      return
    }

    checkoutLoading.value = true
    error.value = ''
    try {
      const result = await store.createBooking(
        undefined,
        activeQuote.value?.quote_token,
        loyaltyPayload?.loyalty_points,
        loyaltyPayload?.loyalty_policy_version,
        loyaltyPayload?.idempotency_key,
      )
      if (result?.booking?.code) {
        router.push(`/payment/${result.booking.code}`)
      } else {
        router.push('/booking')
      }
    } catch (cause) {
      error.value = errorMessage(cause, 'Không thể khởi tạo đơn đặt sân. Vui lòng thử lại.')
    } finally {
      checkoutLoading.value = false
    }
  }

  const resendingEmail = ref(false)

  async function resendVerificationEmail() {
    resendingEmail.value = true
    try {
      const res = await authApi.resendVerification()
      notice.value = res?.message || 'Đã gửi email xác thực thành công! Vui lòng kiểm tra hộp thư của bạn.'
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
    venuesList,
    filteredCourts,
    filteredSlots,
    availableSlotsCount,
    totalSlotsCount,
    carouselDays,
    totalPrice,
    totalHours,
    activeQuote,
    quoteLoading,
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
    removeSlot,
    clearSelectedSlots,
    quickSelectAvailableHours,
    proceedToBooking,
    resendVerificationEmail,
    fetchAvailability,
  }
}
