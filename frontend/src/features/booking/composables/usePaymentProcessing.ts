import { ref, computed } from 'vue'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Booking } from '../types/booking'
import type { Court } from '@/features/court/types/court'

export function usePaymentProcessing() {
  const store = useBookingStore()
  const auth = useAuthStore()

  const status = ref('pending_payment')
  const error = ref('')
  const copyNotice = ref('')
  const selectedMethod = ref<'qr' | 'card'>('qr')
  const showSuccessModal = ref(false)

  // Coupon
  const couponCode = ref('')
  const couponLoading = ref(false)
  const couponError = ref('')
  const couponSuccess = ref('')

  // Stripe Card Form
  const cardNumber = ref('')
  const cardExpiry = ref('')
  const cardCvc = ref('')
  const cardName = ref('')
  const isCardProcessing = ref(false)

  const remainingSeconds = ref(900) // 15 mins default
  let timer: number | undefined
  let countdownTimer: number | undefined

  const payment = computed(() => store.payment)
  const booking = computed<Booking | null>(() => store.current)

  const court = computed<Court | null>(() => {
    if (booking.value?.court) return booking.value.court
    if (store.selectedCourt) {
      return store.courts.find((c: Court) => c.id === store.selectedCourt) || null
    }
    return null
  })

  const playerDetails = computed(() => ({
    name: auth.user?.name || 'Alexander Mitchell',
    email: auth.user?.email || 'a.mitchell@aura.com',
    phone: auth.user?.phone || '+84 (0) 90 123 4567',
  }))

  const countdownFormatted = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60)
    const s = remainingSeconds.value % 60
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
  })

  async function checkPaymentStatus() {
    try {
      const res = await store.refreshPaymentStatus()
      if (res?.status === 'confirmed' || res?.status === 'completed') {
        status.value = 'confirmed'
        showSuccessModal.value = true
        clearInterval(timer)
      }
    } catch {
      // polling error ignored
    }
  }

  async function applyCoupon() {
    if (!couponCode.value.trim()) return
    couponLoading.value = true
    couponError.value = ''
    couponSuccess.value = ''

    try {
      await store.applyCoupon(couponCode.value.trim())
      couponSuccess.value = 'Áp dụng mã giảm giá thành công!'
    } catch (e) {
      couponError.value = errorMessage(e, 'Mã giảm giá không hợp lệ hoặc đã hết hạn.')
    } finally {
      couponLoading.value = false
    }
  }

  async function handleStripePay() {
    isCardProcessing.value = true
    error.value = ''
    try {
      await store.stripePay({
        cardNumber: cardNumber.value,
        cardExpiry: cardExpiry.value,
        cardCvc: cardCvc.value,
        cardName: cardName.value,
      })
      status.value = 'confirmed'
      showSuccessModal.value = true
    } catch (e) {
      error.value = errorMessage(e, 'Thanh toán thẻ thất bại. Vui lòng thử lại.')
    } finally {
      isCardProcessing.value = false
    }
  }

  function copyToClipboard(text: string, label: string) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(() => {
        copyNotice.value = `Đã sao chép ${label}`
        setTimeout(() => {
          copyNotice.value = ''
        }, 2500)
      })
    }
  }

  function startTimers() {
    store.restorePayment()
    if (payment.value?.expiresAt) {
      const exp = new Date(payment.value.expiresAt).getTime()
      remainingSeconds.value = Math.max(0, Math.floor((exp - Date.now()) / 1000))
    }

    countdownTimer = window.setInterval(() => {
      if (remainingSeconds.value > 0) {
        remainingSeconds.value--
      } else {
        clearInterval(countdownTimer)
      }
    }, 1000)

    timer = window.setInterval(checkPaymentStatus, 4000)
  }

  function stopTimers() {
    clearInterval(timer)
    clearInterval(countdownTimer)
  }

  return {
    payment,
    booking,
    court,
    playerDetails,
    status,
    error,
    copyNotice,
    selectedMethod,
    showSuccessModal,
    couponCode,
    couponLoading,
    couponError,
    couponSuccess,
    cardNumber,
    cardExpiry,
    cardCvc,
    cardName,
    isCardProcessing,
    remainingSeconds,
    countdownFormatted,
    applyCoupon,
    handleStripePay,
    copyToClipboard,
    startTimers,
    stopTimers,
  }
}
