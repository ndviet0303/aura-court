import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { courtApi } from '../api/courtApi'
import type { Court, CourtReview } from '../types/court'

export function useCourtDetail() {
  const route = useRoute()
  const bookingStore = useBookingStore()

  const isGalleryOpen = ref(false)
  const loading = ref(false)
  const reviewsLoading = ref(false)
  const reviews = ref<CourtReview[]>([])

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

  async function loadReviews() {
    if (!court.value?.id) return
    reviewsLoading.value = true
    try {
      const res = await courtApi.getReviews(court.value.id)
      const data = (res as any)?.data?.data || (res as any)?.data || []
      reviews.value = Array.isArray(data) ? data : []
    } catch {
      reviews.value = []
    } finally {
      reviewsLoading.value = false
    }
  }

  watch(
    () => court.value?.id,
    (id) => {
      if (id) {
        loadReviews()
      }
    },
    { immediate: true },
  )

  // No invented price: a court without a price shows none.
  const basePrice = computed(() => court.value?.from_price ?? null)

  onMounted(() => {
    loadCourt()
  })

  return {
    loading,
    court,
    reviews,
    reviewsLoading,
    isGalleryOpen,
    basePrice,
    loadCourt,
    loadReviews,
  }
}
