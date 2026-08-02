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
  async function loadCourts() {
    try { courts.value = await api.get<Court[]>('/courts') }
    catch {
      courts.value = [
        { id: 1, name: 'Zenith Arena', description: 'Sân tiêu chuẩn · Quận 1', image_url: '/aura/court-4.jpg' },
        { id: 2, name: 'Lakeside Pavilion', description: 'Không gian thoáng · Quận 2', image_url: '/aura/court-5.jpg' },
        { id: 3, name: 'Skyline Courts', description: 'Sân cao cấp · Bình Thạnh', image_url: '/aura/court-6.jpg' },
      ]
    }
  }
  async function loadAvailability(courtId: number, date: string) {
    selectedCourt.value = courtId
    try { slots.value = await api.get<AvailabilitySlot[]>(`/availability?court_id=${courtId}&date=${date}`) }
    catch {
      const base = new Date(`${date}T06:00:00+07:00`)
      slots.value = Array.from({ length: 17 }, (_, index) => { const start = new Date(base.getTime() + index * 60 * 60 * 1000); return { startsAt: start.toISOString(), endsAt: new Date(start.getTime() + 3600000).toISOString(), price: index >= 11 ? 180000 : 120000, available: ![2, 8, 14].includes(index) } })
    }
    selectedSlots.value = []
  }
  function toggle(slot: AvailabilitySlot) { if (!slot.available) return; const index = selectedSlots.value.findIndex((item) => item.startsAt === slot.startsAt); if (index >= 0) selectedSlots.value.splice(index, 1); else selectedSlots.value.push(slot); selectedSlots.value.sort((a, b) => a.startsAt.localeCompare(b.startsAt)) }
  async function createBooking() { const result = await api.post<{ booking: Booking; payment: PaymentInfo }>('/bookings', { court_id: selectedCourt.value, slots: selectedSlots.value.map((slot) => slot.startsAt) }); current.value = result.booking; payment.value = result.payment; return result }
  return { courts, slots, selectedCourt, selectedSlots, current, payment, loadCourts, loadAvailability, toggle, createBooking }
})
