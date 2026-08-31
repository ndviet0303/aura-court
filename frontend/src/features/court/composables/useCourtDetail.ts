import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { courtApi } from '../api/courtApi'
import type { Court } from '../types/court'

export function useCourtDetail() {
  const route = useRoute()
  const bookingStore = useBookingStore()

  const selectedDuration = ref<number>(1.0)
  const isGalleryOpen = ref(false)
  const loading = ref(false)

  const slugOrId = computed(() => String(route.params.slug || route.params.id || ''))
  const fetchedCourt = ref<Court | null>(null)

  async function loadCourt() {
    if (bookingStore.courts.length === 0) {
      loading.value = true
      try {
        await bookingStore.loadCourts()
      } catch {
        // Handled by store
      } finally {
        loading.value = false
      }
    }
  }

  const court = computed<Court | null>(() => {
    const key = slugOrId.value
    if (!key) return bookingStore.courts[0] || null
    return (
      fetchedCourt.value ||
      bookingStore.courts.find((c) => c.slug === key || String(c.id) === key) ||
      bookingStore.courts[0] ||
      null
    )
  })

  const basePrice = computed(() => {
    if (court.value?.price_slots && court.value.price_slots.length > 0) {
      return court.value.price_slots[0]?.price || 120000
    }
    return 120000
  })

  const estimatedTotal = computed(() => {
    return basePrice.value * selectedDuration.value
  })

  const durationOptions = [
    { value: 1.0, label: '1.0h' },
    { value: 1.5, label: '1.5h' },
    { value: 2.0, label: '2.0h' },
    { value: 3.0, label: '3.0h' },
  ]

  onMounted(() => {
    loadCourt()
  })

  return {
    loading,
    court,
    selectedDuration,
    isGalleryOpen,
    basePrice,
    estimatedTotal,
    durationOptions,
    loadCourt,
  }
}
