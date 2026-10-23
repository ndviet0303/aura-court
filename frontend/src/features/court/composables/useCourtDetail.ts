import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import type { Court } from '../types/court'

export function useCourtDetail() {
  const route = useRoute()
  const bookingStore = useBookingStore()

  const isGalleryOpen = ref(false)
  const loading = ref(false)

  const courtId = computed(() => String(route.params.id || ''))
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
    const key = courtId.value
    if (!key) return bookingStore.courts[0] || null
    return (
      fetchedCourt.value ||
      bookingStore.courts.find((c) => String(c.id) === key) ||
      bookingStore.courts[0] ||
      null
    )
  })

  // No invented price: a court without a price shows none.
  const basePrice = computed(() => court.value?.from_price ?? null)

  onMounted(() => {
    loadCourt()
  })

  return {
    loading,
    court,
    isGalleryOpen,
    basePrice,
    loadCourt,
  }
}
