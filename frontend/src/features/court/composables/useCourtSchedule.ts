import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import type { Court, AvailabilitySlot } from '../types/court'

export function useCourtSchedule() {
  const route = useRoute()
  const router = useRouter()
  const store = useBookingStore()

  const date = ref(new Date().toISOString().slice(0, 10))
  const selectedCourtId = ref<number>(Number(route.params.id) || 1)
  const error = ref('')
  const notice = ref('')
  const loading = ref(false)

  const court = computed<Court | null>(() => {
    return (
      store.courts.find((c: Court) => c.id === selectedCourtId.value) ||
      store.courts[0] ||
      null
    )
  })

  const carouselDays = computed(() => {
    const days = []
    const base = new Date()
    for (let i = 0; i < 14; i++) {
      const d = new Date(base)
      d.setDate(base.getDate() + i)
      const iso = d.toISOString().slice(0, 10)
      const dayNum = d.getDate()
      const dayName = d.toLocaleDateString('vi-VN', { weekday: 'short' })
      days.push({ iso, dayNum, dayName })
    }
    return days
  })

  const totalPrice = computed(() => {
    return store.selectedSlots.reduce((sum: number, slot: AvailabilitySlot) => sum + slot.price, 0)
  })

  async function fetchAvailability() {
    if (court.value) {
      try {
        await store.loadAvailability(court.value.id, date.value)
      } catch {
        error.value = store.error
      }
    }
  }

  async function init() {
    loading.value = true
    try {
      if (store.courts.length === 0) {
        await store.loadCourts()
      }
      await fetchAvailability()
    } catch {
      // ignore
    } finally {
      loading.value = false
    }
  }

  function selectDate(isoDate: string) {
    date.value = isoDate
    fetchAvailability()
  }

  function selectCourt(courtId: number) {
    selectedCourtId.value = courtId
    fetchAvailability()
  }

  function toggleSlot(slot: AvailabilitySlot) {
    if (!slot.available) return
    store.toggle(slot)
  }

  function proceedToBooking() {
    if (!court.value || store.selectedSlots.length === 0) return
    router.push(`/booking?court=${court.value.id}`)
  }

  onMounted(() => {
    init()
  })

  return {
    store,
    date,
    selectedCourtId,
    court,
    carouselDays,
    totalPrice,
    error,
    notice,
    loading,
    selectDate,
    selectCourt,
    toggleSlot,
    proceedToBooking,
    fetchAvailability,
  }
}
