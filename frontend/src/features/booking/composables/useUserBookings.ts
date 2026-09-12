import { ref, computed } from 'vue'
import { bookingApi } from '../api/bookingApi'
import type { Booking } from '../types/booking'
import { errorMessage } from '@/core/utils/errorMessage'

export type BookingTab = 'all' | 'upcoming' | 'completed' | 'cancelled'

export function useUserBookings() {
  const bookings = ref<Booking[]>([])
  const activeTab = ref<BookingTab>('all')
  const searchQuery = ref('')
  const error = ref('')
  const notice = ref('')
  const loading = ref(true)

  // Review modal state
  const showReviewModal = ref(false)
  const reviewingBooking = ref<Booking | null>(null)
  const reviewRating = ref(5)
  const reviewBody = ref('')
  const submittingReview = ref(false)

  // QR Check-in modal state
  const showQrModal = ref(false)
  const qrBooking = ref<Booking | null>(null)


  async function load() {
    loading.value = true
    error.value = ''
    try {
      const result = await bookingApi.list()
      bookings.value = result.data || []
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể tải danh sách đặt sân.')
    } finally {
      loading.value = false
    }
  }

  function isCompleted(b: Booking): boolean {
    if (b.status === 'completed') return true
    if (b.status === 'confirmed' && b.slots && b.slots.length > 0) {
      return b.slots.every((slot) => new Date(slot.ends_at).getTime() < Date.now())
    }
    return false
  }

  function isUpcoming(b: Booking): boolean {
    if (b.status === 'pending_payment') return true
    if (b.status === 'confirmed') {
      return !isCompleted(b)
    }
    return false
  }

  function isCancelled(b: Booking): boolean {
    return ['cancelled', 'expired', 'refunded'].includes(b.status)
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
        const code = (b.code || '').toLowerCase()
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
  function openQr(b: Booking) {
    qrBooking.value = b
    showQrModal.value = true
  }

  function closeQr() {
    showQrModal.value = false
    qrBooking.value = null
  }


  // Review Modal Handlers
  function openReview(b: Booking) {
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
