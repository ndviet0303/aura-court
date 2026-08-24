import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { AvailabilitySlot, Booking, Court, PaymentInfo } from '@/types'

const paymentStorageKey = 'aura-court-payment'

export const useBookingStore = defineStore('booking', () => {
  const courts = ref<Court[]>([])
  const slots = ref<AvailabilitySlot[]>([])
  const selectedCourt = ref<number | null>(null)
  const selectedSlots = ref<AvailabilitySlot[]>([])
  const current = ref<Booking | null>(null)
  const payment = ref<PaymentInfo | null>(null)
  const error = ref('')
  const courtsLoading = ref(false)
  const slotsLoading = ref(false)
  const loading = computed(() => courtsLoading.value || slotsLoading.value)

  async function loadCourts() {
    error.value = ''
    courtsLoading.value = true
    try {
      courts.value = await api.get<Court[]>('/courts')
    } catch (cause) {
      courts.value = []
      error.value = 'Không thể tải danh sách sân. Vui lòng thử lại.'
      throw cause
    } finally {
      courtsLoading.value = false
    }
  }

  async function loadAvailability(courtId: number, date: string) {
    selectedCourt.value = courtId
    error.value = ''
    slotsLoading.value = true
    try {
      slots.value = await api.get<AvailabilitySlot[]>(`/availability?court_id=${courtId}&date=${date}`)
    } catch (cause) {
      slots.value = []
      error.value = 'Không thể tải lịch sân. Vui lòng thử lại.'
      throw cause
    } finally {
      slotsLoading.value = false
      selectedSlots.value = []
    }
  }

  function toggle(slot: AvailabilitySlot) {
    if (!slot.available) return
    const index = selectedSlots.value.findIndex((item) => item.startsAt === slot.startsAt)
    if (index >= 0) selectedSlots.value.splice(index, 1)
    else selectedSlots.value.push(slot)
    selectedSlots.value.sort((a, b) => a.startsAt.localeCompare(b.startsAt))
  }

  function persistPayment() {
    if (payment.value) sessionStorage.setItem(paymentStorageKey, JSON.stringify(payment.value))
  }

  function restorePayment() {
    if (payment.value) return payment.value
    try {
      const saved = sessionStorage.getItem(paymentStorageKey)
      if (saved) payment.value = JSON.parse(saved) as PaymentInfo
    } catch { sessionStorage.removeItem(paymentStorageKey) }
    return payment.value
  }

  async function loadPayment(code: string) {
    const result = await api.get<{ booking?: Booking; payment: PaymentInfo; status: string }>('/bookings/' + code + '/payment')
    payment.value = result.payment
    if (result.booking) {
      current.value = result.booking
    }
    persistPayment()
    return result
  }

  function clearPayment() {
    payment.value = null
    sessionStorage.removeItem(paymentStorageKey)
  }

  async function createBooking(couponCode?: string) {
    const result = await api.post<{ booking: Booking; payment: PaymentInfo }>('/bookings', { court_id: selectedCourt.value, slots: selectedSlots.value.map((slot) => slot.startsAt), coupon_code: couponCode || undefined })
    current.value = result.booking
    payment.value = result.payment
    persistPayment()
    return result
  }

  async function refreshPaymentStatus() {
    restorePayment()
    if (!payment.value) return null
    return api.get<{ status: string; payment: unknown; expiresAt: string }>(`/bookings/${payment.value.code}/payment-status`)
  }

  async function stripePay(cardDetails: { cardNumber?: string; cardExpiry?: string; cardCvc?: string; cardName?: string } = {}) {
    restorePayment()
    const code = payment.value?.code || current.value?.code
    if (!code) throw new Error('Chưa có phiên thanh toán.')
    const result = await api.post<{ status: string; booking: Booking }>(`/bookings/${code}/stripe-pay`, {
      card_number: cardDetails.cardNumber,
      card_expiry: cardDetails.cardExpiry,
      card_cvc: cardDetails.cardCvc,
      card_name: cardDetails.cardName,
    })
    if (result.booking) current.value = result.booking
    return result
  }

  async function demoPay() {
    restorePayment()
    const code = payment.value?.code || current.value?.code
    if (!code) throw new Error('Chưa có phiên thanh toán.')
    const result = await api.post<{ status: string; booking: Booking }>(`/bookings/${code}/demo-pay`)
    if (result.booking) current.value = result.booking
    return result
  }

  async function applyCoupon(couponCode?: string) {
    restorePayment()
    const code = payment.value?.code || current.value?.code
    if (!code) throw new Error('Chưa có phiên thanh toán.')
    const result = await api.post<{ booking: Booking; payment: PaymentInfo; status: string }>(`/bookings/${code}/coupon`, {
      coupon_code: couponCode || null,
    })
    current.value = result.booking
    payment.value = result.payment
    persistPayment()
    return result
  }

  return { courts, slots, selectedCourt, selectedSlots, current, payment, error, loading, courtsLoading, slotsLoading, loadCourts, loadAvailability, toggle, createBooking, applyCoupon, restorePayment, loadPayment, clearPayment, refreshPaymentStatus, demoPay, stripePay }
})
