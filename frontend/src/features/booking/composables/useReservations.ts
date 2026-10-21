import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { bookingApi } from '../api/bookingApi'
import type { Court } from '@/features/court/types/court'
import type { Reservation, PriceQuote } from '../types/reservation'
import { errorMessage } from '@/core/utils/errorMessage'
import { i18n, formatCurrency, getCurrentLocale } from '@/i18n'
import {
  amountPayable,
  bookingErrorMessage,
  displayCode,
  displayStatus,
  loyaltyFields,
  type LoyaltySelection,
} from '../utils/reservation'
import { venueClock } from '@/core/utils/venueTime'
import { usePriceChange } from './usePriceChange'

const t = (key: string, params?: Record<string, unknown>) => i18n.global.t(key, params || {})

// Display statuses per list tab; a used order (checked in, finished, no-show) stays under confirmed.
const TAB_STATUSES = {
  held: ['held'],
  confirmed: ['confirmed', 'in_use', 'completed', 'no_show'],
  cancelled: ['cancelled', 'expired'],
}

export function useReservations() {
  const route = useRoute()
  const courts = ref<Court[]>([])
  const reservations = ref<Reservation[]>([])
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

  const activeQuote = ref<PriceQuote | null>(null)
  // A PRICE_CHANGED on hold or checkout, shown until the customer confirms it or prices again.
  const price = usePriceChange()
  const quoteExpiresInSeconds = ref<number>(0)
  let quoteTimerInterval: number | NodeJS.Timeout | undefined = undefined

  const loading = ref(false)
  const calculatingQuote = ref(false)
  const holdingReservation = ref(false)
  const confirmingCode = ref<string | null>(null)

  const activeTab = ref<'all' | 'held' | 'confirmed' | 'cancelled'>('all')
  const searchQuery = ref('')
  const notice = ref('')
  const error = ref('')

  const selectedReservationForDetails = ref<Reservation | null>(null)
  const selectedReservationForQr = ref<Reservation | null>(null)
  const selectedReservationForPayment = ref<Reservation | null>(null)

  const money = (value: number) => formatCurrency(value)

  const formatDate = (value: string) => {
    if (!value) return ''
    try {
      const loc = getCurrentLocale() === 'en' ? 'en-US' : 'vi-VN'
      return new Date(value).toLocaleString(loc, {
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
    const start = venueClock(startsAt) || '00:00'
    if (endsAt) return `${start} - ${venueClock(endsAt) || '00:00'}`
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
        quoteTimerInterval = undefined
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
        bookingApi.getMyReservations(),
      ])

      if (courtsRes.status === 'fulfilled' && Array.isArray(courtsRes.value)) {
        courts.value = courtsRes.value
        if (!selectedCourtId.value && courtsRes.value.length > 0 && courtsRes.value[0]?.id) {
          selectedCourtId.value = courtsRes.value[0].id
        }
      }

      if (resRes.status === 'fulfilled') reservations.value = resRes.value?.data ?? []
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('reservations.messages.loadError'))
    } finally {
      loading.value = false
    }
  }

  async function calculateQuote() {
    if (!selectedCourtId.value || !selectedDate.value || !selectedTimeSlot.value) {
      error.value = t('reservations.messages.selectCourtAndTime')
      return
    }

    calculatingQuote.value = true
    error.value = ''
    notice.value = ''
    price.clear()

    try {
      const slotIso = new Date(`${selectedDate.value}T${selectedTimeSlot.value}:00`).toISOString()
      const response = await bookingApi.getPriceQuote({
        court_id: Number(selectedCourtId.value),
        slots: [slotIso],
      })

      if (response && response.quote) {
        activeQuote.value = response.quote
        startQuoteCountdown(response.quote.expires_at)
        notice.value = t('reservations.messages.quoteSuccess')
      }
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('reservations.messages.quoteError'))
    } finally {
      calculatingQuote.value = false
    }
  }

  // A hold spends no points, so the amount agreed is the quote total; agreedTotal is a confirmed new price.
  async function holdReservation(agreedTotal?: number) {
    if (!activeQuote.value) return
    holdingReservation.value = true
    error.value = ''
    notice.value = ''

    try {
      await bookingApi.holdReservation({
        quote_token: activeQuote.value.quote_token,
        expected_total: agreedTotal ?? activeQuote.value.total_amount,
      })
      activeQuote.value = null
      clearInterval(quoteTimerInterval)
      notice.value = t('reservations.messages.holdSuccess')
      await loadData()
      activeTab.value = 'held'
    } catch (cause: unknown) {
      if (!price.caught(cause, holdReservation)) {
        error.value = bookingErrorMessage(cause, t('reservations.messages.holdError'))
      }
    } finally {
      holdingReservation.value = false
    }
  }

  // Sends the amount payable shown (quote total less the points discount). A resubmission at a confirmed
  // new price (agreedTotal) has another body, so it goes under a fresh Idempotency-Key.
  async function checkoutReservation(loyaltyPayload?: LoyaltySelection, agreedTotal?: number) {
    if (!activeQuote.value) return
    holdingReservation.value = true
    error.value = ''
    notice.value = ''

    try {
      const { reservation } = await bookingApi.checkoutReservation(
        {
          quote_token: activeQuote.value.quote_token,
          court_id: selectedCourtId.value ?? undefined,
          expected_total:
            agreedTotal ?? amountPayable(activeQuote.value.total_amount, loyaltyPayload),
          ...loyaltyFields(loyaltyPayload),
        },
        agreedTotal === undefined ? loyaltyPayload?.idempotency_key : undefined,
      )
      const code = reservation.code
      activeQuote.value = null
      clearInterval(quoteTimerInterval)
      await loadData()
      if (needsPayment(reservation)) {
        notice.value = t('reservations.messages.checkoutPending', { code })
        activeTab.value = 'held'
        openPayment(reservation)
      } else {
        notice.value = t('reservations.messages.checkoutSuccess', { code })
        activeTab.value = 'confirmed'
      }
    } catch (cause: unknown) {
      const again = (total: number) => checkoutReservation(loyaltyPayload, total)
      if (!price.caught(cause, again)) {
        error.value = bookingErrorMessage(cause, t('reservations.messages.checkoutError'))
      }
    } finally {
      holdingReservation.value = false
    }
  }

  async function confirmReservation(reservation: Reservation) {
    confirmingCode.value = reservation.code
    error.value = ''
    notice.value = ''

    try {
      await bookingApi.confirmReservation(reservation.code)
      notice.value = t('reservations.messages.confirmSuccess', { code: displayCode(reservation) })
      await loadData()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('reservations.messages.confirmError'))
    } finally {
      confirmingCode.value = null
    }
  }

  const filteredReservations = computed(() => {
    let list = reservations.value

    const tab = activeTab.value
    if (tab !== 'all') list = list.filter((r) => TAB_STATUSES[tab].includes(displayStatus(r)))

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter(
        (r) =>
          displayCode(r).toLowerCase().includes(q) ||
          r.court?.name?.toLowerCase().includes(q) ||
          r.court?.venue?.name?.toLowerCase().includes(q),
      )
    }

    return list
  })

  const totalCount = computed(() => reservations.value.length)
  const countBy = (...statuses: string[]) =>
    reservations.value.filter((r) => statuses.includes(displayStatus(r))).length
  const heldCount = computed(() => countBy(...TAB_STATUSES.held))
  const confirmedCount = computed(() => countBy(...TAB_STATUSES.confirmed))
  const cancelledCount = computed(() => countBy(...TAB_STATUSES.cancelled))

  const activeHeldReservation = computed(() => reservations.value.find((r) => r.status === 'held'))

  const selectedCourt = computed(() => courts.value.find((c) => c.id === selectedCourtId.value))

  // The list row shows at once; the detail API adds the payment result and check-in times.
  async function openDetails(reservation: Reservation) {
    selectedReservationForDetails.value = reservation
    try {
      const detail = await bookingApi.getReservation(reservation.code)
      if (selectedReservationForDetails.value?.code === reservation.code) {
        selectedReservationForDetails.value = detail.reservation
      }
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('reservations.messages.loadError'))
    }
  }

  function closeDetails() {
    selectedReservationForDetails.value = null
  }

  function openQr(reservation: Reservation) {
    selectedReservationForQr.value = reservation
  }

  function closeQr() {
    selectedReservationForQr.value = null
  }

  function openPayment(reservation: Reservation) {
    selectedReservationForPayment.value = reservation
  }

  function closePayment() {
    selectedReservationForPayment.value = null
  }

  async function paymentCancelled(reservation: Reservation) {
    closePayment()
    closeDetails()
    notice.value = t('reservations.cancelPayment.done', { code: displayCode(reservation) })
    await loadData()
  }

  function needsPayment(r: Reservation) {
    return r.status === 'held' && r.payment_model === 'direct_owner_v1'
  }

  // Deep link /reservations/:code opens the reservation, plus its payment when still payable.
  async function openFromRoute() {
    const code = route.params.code
    if (typeof code !== 'string' || !code) return
    try {
      const { reservation } = await bookingApi.getReservation(code)
      selectedReservationForDetails.value = reservation
      if (needsPayment(reservation)) openPayment(reservation)
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('reservations.messages.loadError'))
    }
  }

  onMounted(async () => {
    await loadData()
    await openFromRoute()
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
    priceChange: price.priceChange,
    confirmPriceChange: price.confirm,
    quoteExpiresInSeconds,
    formattedQuoteRemaining,
    loading,
    calculatingQuote,
    holdingReservation,
    confirmingCode,
    activeTab,
    searchQuery,
    notice,
    error,
    selectedReservationForDetails,
    selectedReservationForQr,
    selectedReservationForPayment,
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
    checkoutReservation,
    confirmReservation,
    openDetails,
    closeDetails,
    openQr,
    closeQr,
    openPayment,
    closePayment,
    paymentCancelled,
  }
}
