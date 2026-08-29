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

  const rules = ref<PriceRuleItem[]>([
    {
      id: 1,
      name: 'Tết Nguyên Đán 2025',
      type: 'holiday',
      priority: 1,
      price: 156000,
      adjustment_type: 'percentage_add',
      adjustment_value: 30,
      is_active: true,
      version: 3,
    },
    {
      id: 2,
      name: 'Giờ vàng tối',
      type: 'peak',
      priority: 2,
      price: 180000,
      adjustment_type: 'fixed',
      adjustment_value: 180000,
      starts_at: '17:00',
      ends_at: '21:00',
      weekdays: [1, 2, 3, 4, 5],
      is_active: true,
      version: 1,
    },
    {
      id: 3,
      name: 'Khuyến mãi giờ trưa thấp điểm',
      type: 'surge',
      priority: 3,
      price: 90000,
      adjustment_type: 'percentage_discount',
      adjustment_value: 25,
      starts_at: '11:00',
      ends_at: '14:00',
      weekdays: [1, 2, 3, 4, 5],
      is_active: true,
      version: 1,
    },
  ])

  async function loadRules() {
    loading.value = true
    try {
      const res = await adminApi.getPricingCalendar<{ rules: PriceRuleItem[] }>()
      if (res && res.rules) {
        rules.value = res.rules
      }
    } catch (e) {
      // fallback to mock rules
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

  function saveRule(payload: any) {
    if (editingRule.value) {
      Object.assign(editingRule.value, payload)
      showNotice(`Đã cập nhật quy tắc "${payload.name}"`)
    } else {
      rules.value.push({
        id: Date.now(),
        ...payload,
        is_active: true,
        version: 1,
      })
      showNotice(`Đã thêm quy tắc mới "${payload.name}"`)
    }
    showRuleModal.value = false
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
