import { ref, computed, onMounted } from 'vue'
import { financialExceptionsApi } from '../api/financialExceptionsApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type {
  FinancialException,
  AuditLog,
  ExceptionStats,
  ExceptionFilterTab,
} from '../types/exceptions'

export function useAdminFinancialExceptions() {
  const activeTab = ref<ExceptionFilterTab>('all')
  const searchQuery = ref('')
  const notice = ref('')
  const error = ref('')
  const loading = ref(false)

  const stats = ref<ExceptionStats>({
    criticalCount: 0,
    pending_count: 0,
    resolvedCount: 0,
    totalRiskValue: 0,
  })

  const exceptions = ref<FinancialException[]>([])
  const auditLogs = ref<AuditLog[]>([])

  const selectedItem = ref<FinancialException | null>(null)
  const isDrawerOpen = ref(false)

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [statsRes, exRes, logsRes] = await Promise.allSettled([
        financialExceptionsApi.getStats(),
        financialExceptionsApi.getExceptions(),
        financialExceptionsApi.getAuditLogs(),
      ])

      if (statsRes.status === 'fulfilled' && statsRes.value?.stats) {
        stats.value = statsRes.value.stats
      }
      if (exRes.status === 'fulfilled' && exRes.value?.data) {
        exceptions.value = exRes.value.data
      }
      if (logsRes.status === 'fulfilled' && logsRes.value?.data) {
        auditLogs.value = logsRes.value.data
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu ngoại lệ tài chính.')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadData()
  })

  const filteredExceptions = computed(() => {
    return exceptions.value.filter((item) => {
      if (activeTab.value === 'critical' && item.severity !== 'critical') return false
      if (activeTab.value === 'refund' && !item.description?.includes('Hoàn tiền')) return false
      if (activeTab.value === 'deposit' && !item.description?.includes('Nạp') && !item.description?.includes('Rút')) return false
      if (activeTab.value === 'deposit_hold' && !item.description?.includes('cọc')) return false

      if (!searchQuery.value.trim()) return true
      const q = searchQuery.value.toLowerCase()
      return (
        item.reference?.toLowerCase().includes(q) ||
        item.resolver?.name?.toLowerCase().includes(q) ||
        item.resolver?.email?.toLowerCase().includes(q) ||
        item.description?.toLowerCase().includes(q)
      )
    })
  })

  function openOverrideDrawer(item: FinancialException) {
    selectedItem.value = item
    isDrawerOpen.value = true
  }

  function closeDrawer() {
    isDrawerOpen.value = false
    selectedItem.value = null
  }

  async function submitOverride(payload: { actionType: string; reason: string }) {
    if (!selectedItem.value) return

    const targetItem = selectedItem.value
    loading.value = true
    try {
      await financialExceptionsApi.resolveException(targetItem.id, {
        resolution: payload.reason,
        action_type: payload.actionType,
      })

      const found = exceptions.value.find((e) => e.id === targetItem.id)
      if (found) {
        found.status = 'resolved'
      }

      await loadData()
      notice.value = `Đã can thiệp và giải quyết ngoại lệ cho giao dịch ${targetItem.reference || targetItem.id} thành công.`
      setTimeout(() => {
        notice.value = ''
      }, 4000)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể xử lý ngoại lệ.')
    } finally {
      loading.value = false
      closeDrawer()
    }
  }

  return {
    activeTab,
    searchQuery,
    notice,
    error,
    loading,
    stats,
    exceptions,
    auditLogs,
    selectedItem,
    isDrawerOpen,
    filteredExceptions,
    openOverrideDrawer,
    closeDrawer,
    submitOverride,
    loadData,
  }
}
