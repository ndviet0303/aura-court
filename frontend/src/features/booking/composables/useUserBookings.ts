import { ref, computed } from 'vue'
import { bookingApi } from '../api/bookingApi'
import type { V2Reservation } from '../types/v2Reservation'
import { errorMessage } from '@/core/utils/errorMessage'
import { displayCode, displayStatus } from '../utils/reservation'

export type BookingTab = 'all' | 'upcoming' | 'completed' | 'cancelled'

export function useUserBookings() {
  const bookings = ref<V2Reservation[]>([])
  const activeTab = ref<BookingTab>('all')
  const searchQuery = ref('')
  const error = ref('')
  const notice = ref('')
  const loading = ref(true)

  // Review modal state
  const showReviewModal = ref(false)
  const reviewingBooking = ref<V2Reservation | null>(null)
  const reviewRating = ref(5)
  const reviewBody = ref('')
  const submittingReview = ref(false)

  // QR Check-in modal state
  const showQrModal = ref(false)
  const qrBooking = ref<V2Reservation | null>(null)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const result = await bookingApi.getMyReservations()
      bookings.value = result.data || []
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể tải danh sách đặt sân.')
    } finally {
      loading.value = false
    }
  }

  // Statuses are read through the display rule, so a legacy mirror buckets by its booking status.
  function isCompleted(b: V2Reservation): boolean {
    const status = displayStatus(b)
    if (status === 'completed') return true
    if (status === 'confirmed' && b.slots && b.slots.length > 0) {
      return b.slots.every(
        (slot) => new Date(slot.ends_at ?? slot.starts_at).getTime() < Date.now(),
      )
    }
    return false
  }

  function isUpcoming(b: V2Reservation): boolean {
    const status = displayStatus(b)
    if (status === 'held') return true
    if (status === 'confirmed' || status === 'in_use') return !isCompleted(b)
    return false
  }

  function isCancelled(b: V2Reservation): boolean {
    return ['cancelled', 'expired', 'refunded'].includes(displayStatus(b))
  }

  const filteredBookings = computed(() => {
    let list = bookings.value

    if (activeTab.value === 'upcoming') {
      list = list.filter(isUpcoming)
    } else if (activeTab.value === 'completed') {
      list = list.filter(isCompleted)
    } else if (activeTab.value === 'cancelled') {
      list = list.filter(isCancelled)
    }

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.trim().toLowerCase()
      list = list.filter((b) => {
        const code = displayCode(b).toLowerCase()
        const courtName = (b.court?.name || '').toLowerCase()
        const venueName = (b.court?.venue?.name || '').toLowerCase()
        return code.includes(q) || courtName.includes(q) || venueName.includes(q)
      })
    }

    return list
  })

  const upcomingCount = computed(() => bookings.value.filter(isUpcoming).length)
  const completedCount = computed(() => bookings.value.filter(isCompleted).length)
  const cancelledCount = computed(() => bookings.value.filter(isCancelled).length)
  const totalCount = computed(() => bookings.value.length)

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  // QR Modal Handlers
  function openQr(b: V2Reservation) {
    qrBooking.value = b
    showQrModal.value = true
  }

  function closeQr() {
    showQrModal.value = false
    qrBooking.value = null
  }

  // Review Modal Handlers
  function openReview(b: V2Reservation) {
    reviewingBooking.value = b
    reviewRating.value = b.review?.rating || 5
    reviewBody.value = b.review?.body || ''
    showReviewModal.value = true
  }

  function closeReview() {
    showReviewModal.value = false
    reviewingBooking.value = null
  }

  async function submitReview() {
    if (!reviewingBooking.value) return
    submittingReview.value = true
    try {
      await bookingApi.submitReview(reviewingBooking.value.code, {
        rating: reviewRating.value,
        body: reviewBody.value.trim() || undefined,
      })
      showNotice('Cảm ơn bạn đã gửi đánh giá trải nghiệm sân đấu!')
      closeReview()
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể gửi đánh giá lúc này.')
    } finally {
      submittingReview.value = false
    }
  }

  return {
    bookings,
    activeTab,
    searchQuery,
    error,
    notice,
    loading,
    filteredBookings,
    totalCount,
    upcomingCount,
    completedCount,
    cancelledCount,
    showReviewModal,
    reviewingBooking,
    reviewRating,
    reviewBody,
    submittingReview,
    showQrModal,
    qrBooking,
    load,
    showNotice,
    openQr,
    closeQr,
    openReview,
    closeReview,
    submitReview,
  }
}
