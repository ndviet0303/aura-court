import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { bookingApi } from '../api/bookingApi'
import type { Booking, PaymentInfo } from '../types/booking'
import type { Court, AvailabilitySlot } from '@/features/court/types/court'

const paymentStorageKey = 'aura-court-payment'

export const useBookingStore = defineStore('booking', () => {
  const courts = ref<Court[]>([])
  const slots = ref<AvailabilitySlot[]>([])
  const selectedCourt = ref<number | null>(null)
  const selectedSlots = ref<AvailabilitySlot[]>([])
  const current = ref<Booking | null>(null)
  const payment = ref<PaymentInfo | null>(null)
  const walletBalance = ref<number>(0)
  const error = ref('')
  const courtsLoading = ref(false)
  const slotsLoading = ref(false)
  const loading = computed(() => courtsLoading.value || slotsLoading.value)

  async function loadCourts(params?: { sport_type?: string; venue_id?: number }) {
    error.value = ''
    courtsLoading.value = true
    try {
      courts.value = await bookingApi.getCourts(params)
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
      slots.value = await bookingApi.getAvailability(courtId, date)
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
    } catch {
      sessionStorage.removeItem(paymentStorageKey)
    }
    return payment.value
  }

  async function fetchWalletBalance() {
    try {
      const res = await bookingApi.getWalletBalance()
      if (typeof res?.balance === 'number') {
        walletBalance.value = res.balance
      }
      return walletBalance.value
    } catch {
      return walletBalance.value
    }
  }

  async function loadPayment(code: string) {
    const result = await bookingApi.getPayment(code)
    if (result.booking) {
      current.value = result.booking
    }
    if (result.wallet && typeof result.wallet.balance === 'number') {
      walletBalance.value = result.wallet.balance
    }
    return result
  }

  function clearPayment() {
    payment.value = null
    sessionStorage.removeItem(paymentStorageKey)
  }

  async function createBooking(couponCode?: string, quoteToken?: string) {
    const result = await bookingApi.create({
      court_id: selectedCourt.value,
      slots: selectedSlots.value.map((slot) => slot.startsAt),
      coupon_code: couponCode || undefined,
      quote_token: quoteToken || undefined,
    })
    current.value = result.booking
    return result
  }

  async function refreshPaymentStatus() {
    if (!current.value?.code) return null
    return bookingApi.getPaymentStatus(current.value.code)
  }

  async function applyCoupon(couponCode?: string) {
    const code = current.value?.code
    if (!code) throw new Error('Chưa có booking.')
    const result = await bookingApi.applyCoupon(code, couponCode)
    current.value = result.booking
    return result
  }

  return {
    courts,
    slots,
    selectedCourt,
    selectedSlots,
    current,
    payment,
    walletBalance,
    error,
    loading,
    courtsLoading,
    slotsLoading,
    loadCourts,
    loadAvailability,
    toggle,
    createBooking,
    applyCoupon,
    restorePayment,
    loadPayment,
    clearPayment,
    refreshPaymentStatus,
    fetchWalletBalance,
  }
})
