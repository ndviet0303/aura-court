import { ref } from 'vue'
import { courtApi, type CourtQueryParams } from '../api/courtApi'
import type { Court } from '../types/court'
import { errorMessage } from '@/core/utils/errorMessage'

export function useCourtList() {
  const courts = ref<Court[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function loadCourts(params?: CourtQueryParams) {
    loading.value = true
    error.value = null
    try {
      courts.value = await courtApi.list(params)
      return courts.value
    } catch (e) {
      courts.value = []
      error.value = errorMessage(e, 'Không thể tải danh sách sân. Vui lòng thử lại.')
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    courts,
    loading,
    error,
    loadCourts,
  }
}
