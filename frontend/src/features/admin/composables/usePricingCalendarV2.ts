import { ref, computed } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface PriceRuleItem {
  id: number
  name: string
  type: 'holiday' | 'peak' | 'special_event' | 'bad_weather' | 'surge'
  priority: number
  price?: number
  adjustment_type: 'fixed' | 'percentage_add' | 'percentage_discount'
  adjustment_value: number
  starts_at?: string
  ends_at?: string
  weekdays?: number[]
  is_active: boolean
  version: number
}

export function usePricingCalendarV2() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const showSimulatorModal = ref(false)
  const showRuleModal = ref(false)
  const editingRule = ref<PriceRuleItem | null>(null)

  const rules = ref<PriceRuleItem[]>([])

  async function loadRules() {
    loading.value = true
    error.value = ''
    try {
      const res = await adminApi.getPricingCalendar<{ rules: PriceRuleItem[] }>()
      if (res && res.rules) {
        rules.value = res.rules
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách quy tắc giá.')
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function saveRule(payload: any) {
    saving.value = true
    try {
      if (editingRule.value) {
        await adminApi.updatePriceRule(editingRule.value.id, payload)
        showNotice(`Đã cập nhật quy tắc "${payload.name || editingRule.value.name}"`)
      } else {
        await adminApi.createPriceRule(payload)
        showNotice(`Đã thêm quy tắc mới "${payload.name}"`)
      }
      await loadRules()
      showRuleModal.value = false
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lưu quy tắc giá.')
    } finally {
      saving.value = false
    }
  }

  return {
    loading,
    saving,
    notice,
    error,
    showSimulatorModal,
    showRuleModal,
    editingRule,
    rules,
    loadRules,
    showNotice,
    saveRule,
  }
}
