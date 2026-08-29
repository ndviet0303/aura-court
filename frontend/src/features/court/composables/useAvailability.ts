import { ref } from 'vue'
import { courtApi } from '../api/courtApi'
import type { AvailabilitySlot } from '../types/court'
import { errorMessage } from '@/core/utils/errorMessage'

export function useAvailability() {
  const slots = ref<AvailabilitySlot[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function loadAvailability(courtId: number, date: string) {
    loading.value = true
    error.value = null
    try {
      slots.value = await courtApi.getAvailability(courtId, date)
      return slots.value
    } catch (e) {
      slots.value = []
      error.value = errorMessage(e, 'Không thể tải lịch sân. Vui lòng thử lại.')
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    slots,
    loading,
    error,
    loadAvailability,
  }
}
