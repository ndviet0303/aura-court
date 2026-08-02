import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { AvailabilitySlot, Booking, Court, PaymentInfo } from '@/types'

export const useBookingStore = defineStore('booking', () => {
  const courts = ref<Court[]>([])
  const slots = ref<AvailabilitySlot[]>([])
  const selectedCourt = ref<number | null>(null)
  const selectedSlots = ref<AvailabilitySlot[]>([])
  const current = ref<Booking | null>(null)
  const payment = ref<PaymentInfo | null>(null)
  async function loadCourts() { courts.value = await api.get<Court[]>('/courts') }
  async function loadAvailability(courtId: number, date: string) { selectedCourt.value = courtId; slots.value = await api.get<AvailabilitySlot[]>(`/availability?court_id=${courtId}&date=${date}`); selectedSlots.value = [] }
  function toggle(slot: AvailabilitySlot) { if (!slot.available) return; const index = selectedSlots.value.findIndex((item) => item.startsAt === slot.startsAt); if (index >= 0) selectedSlots.value.splice(index, 1); else selectedSlots.value.push(slot); selectedSlots.value.sort((a, b) => a.startsAt.localeCompare(b.startsAt)) }
  async function createBooking() { const result = await api.post<{ booking: Booking; payment: PaymentInfo }>('/bookings', { court_id: selectedCourt.value, slots: selectedSlots.value.map((slot) => slot.startsAt) }); current.value = result.booking; payment.value = result.payment; return result }
  return { courts, slots, selectedCourt, selectedSlots, current, payment, loadCourts, loadAvailability, toggle, createBooking }
})
