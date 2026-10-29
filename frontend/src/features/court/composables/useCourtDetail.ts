import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { errorMessage } from '@/core/utils/errorMessage'
import { courtApi } from '../api/courtApi'
import type { Court } from '../types/court'

export function useCourtDetail() {
  const route = useRoute()
  const bookingStore = useBookingStore()

  const isGalleryOpen = ref(false)
  const loading = ref(false)
  const error = ref('')
  const court = ref<Court | null>(null)

  async function loadCourt() {
    const key = String(route.params.id || '')
    // Show the cached list entry at once; never another court when this id is missing.
    court.value = bookingStore.courts.find((c) => String(c.id) === key) ?? null
    loading.value = true
    error.value = ''
    try {
      court.value = await courtApi.getById(key)
    } catch (cause) {
      court.value = null
      error.value = errorMessage(cause, 'Không thể tải sân được yêu cầu.')
    } finally {
      loading.value = false
    }
  }

  // No invented price: a court without a price shows none.
  const basePrice = computed(() => court.value?.from_price ?? null)

  watch(() => route.params.id, loadCourt, { immediate: true })

  return {
    loading,
    error,
    court,
    isGalleryOpen,
    basePrice,
    loadCourt,
  }
}
