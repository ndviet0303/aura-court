import { ref } from 'vue'
import { posApi } from '../api/posApi'
import type { PosShift } from '../types/pos'
import { errorMessage } from '@/core/utils/errorMessage'

export function usePosShift() {
  const activeShift = ref<PosShift | null>(null)
  const loading = ref(false)
  const error = ref('')

  async function checkActiveShift() {
    loading.value = true
    error.value = ''
    try {
      const shift = await posApi.getActiveShift()
      activeShift.value = shift
      return shift
    } catch {
      activeShift.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function openShift(openingCash: number) {
    loading.value = true
    error.value = ''
    try {
      const newShift = await posApi.openShift({
        opening_cash: openingCash,
      })
      activeShift.value = newShift
      return newShift
    } catch (e) {
      error.value = errorMessage(e, 'Không thể mở ca làm việc. Vui lòng thử lại.')
      throw e
    } finally {
      loading.value = false
    }
  }

  async function closeShift(shiftId: number, payload: { declared_cash: number; note?: string }) {
    loading.value = true
    error.value = ''
    try {
      const closed = await posApi.closeShift(shiftId, payload)
      activeShift.value = null
      return closed
    } catch (e) {
      error.value = errorMessage(e, 'Không thể kết ca. Vui lòng kiểm tra lại số liệu.')
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    activeShift,
    loading,
    error,
    checkActiveShift,
    openShift,
    closeShift,
  }
}
