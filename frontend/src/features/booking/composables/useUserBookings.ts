import { ref, computed } from 'vue'
import { bookingApi } from '../api/bookingApi'
import type { Booking } from '../types/booking'
import { errorMessage } from '@/core/utils/errorMessage'

export function useUserBookings() {
  const bookings = ref<Booking[]>([])
  const activeTab = ref<'all' | 'upcoming' | 'completed' | 'cancelled'>('all')
  const error = ref('')
  const notice = ref('')
  const loading = ref(true)

  const showReviewModal = ref(false)
  const reviewingBooking = ref<Booking | null>(null)
  const reviewRating = ref(5)
  const reviewBody = ref('')

  async function load() {
    loading.value = true
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
    return (
      b.status === 'confirmed' &&
      b.slots.length > 0 &&
      b.slots.every((slot) => new Date(slot.ends_at).getTime() < Date.now())
    )
  }

  function isUpcoming(b: Booking): boolean {
    if (['pending_payment'].includes(b.status)) return true
    if (b.status === 'confirmed') {
      return !isCompleted(b)
    }
    return false
  }

  const filteredBookings = computed(() => {
    if (activeTab.value === 'upcoming') {
      return bookings.value.filter(isUpcoming)
    }
    if (activeTab.value === 'completed') {
      return bookings.value.filter(isCompleted)
    }
    if (activeTab.value === 'cancelled') {
      return bookings.value.filter((b) => b.status === 'cancelled')
    }
    return bookings.value
  })

  const upcomingCount = computed(() => bookings.value.filter(isUpcoming).length)
  const completedCount = computed(() => bookings.value.filter(isCompleted).length)
  const cancelledCount = computed(() => bookings.value.filter((b) => b.status === 'cancelled').length)

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function cancelBooking(booking: Booking) {
    if (!confirm('Bạn có chắc chắn muốn huỷ lịch đặt sân này?')) return
    try {
      await bookingApi.cancel(booking.code, 'User cancelled')
      showNotice('Huỷ lịch đặt sân thành công!')
      await load()
    } catch (cause: unknown) {
      error.value = errorMessage(cause, 'Không thể huỷ đơn đặt sân.')
    }
  }

  function openReview(b: Booking) {
    reviewingBooking.value = b
    reviewRating.value = 5
    reviewBody.value = ''
    showReviewModal.value = true
  }

  function submitReview() {
    showReviewModal.value = false
    showNotice('Cảm ơn bạn đã gửi đánh giá chất lượng sân đấu!')
  }

  return {
    bookings,
    activeTab,
    error,
    notice,
    loading,
    filteredBookings,
    upcomingCount,
    completedCount,
    cancelledCount,
    showReviewModal,
    reviewingBooking,
    reviewRating,
    reviewBody,
    load,
    showNotice,
    cancelBooking,
    openReview,
    submitReview,
  }
}
