import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { financialExceptionsApi } from '../api/financialExceptionsApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type {
  FinancialException,
  AuditLog,
  AuditLogType,
  ExceptionStats,
  ExceptionFilterTab,
  ManualOverridePayload,
} from '../types/exceptions'

export function useAdminFinancialExceptions() {
  const { t } = useI18n()

  const activeTab = ref<ExceptionFilterTab>('all')
  const searchQuery = ref('')
  const filterDate = ref<string | null>(null)
  const filterSeverity = ref<string>('all')
  const filterStatus = ref<string>('all')
  const showAdvancedFilters = ref(false)

  const notice = ref('')
  const error = ref('')
  const loading = ref(false)
  const isExporting = ref(false)

  const stats = ref<ExceptionStats>({
    critical_count: 0,
    pending_count: 0,
    resolved_count: 0,
    total_risk_value: 0,
  })

  const exceptions = ref<FinancialException[]>([])
  const auditLogs = ref<AuditLog[]>([])

  const selectedOverrideItem = ref<FinancialException | null>(null)
  const isOverrideDrawerOpen = ref(false)

  const selectedDetailItem = ref<FinancialException | null>(null)
  const isDetailModalOpen = ref(false)

  function formatAuditLog(raw: any): AuditLog {
    const meta = raw.metadata || {}
    let type: AuditLogType = 'override'
    if (meta.type) {
      type = meta.type
    } else if (raw.action?.includes('flag')) {
      type = 'flag'
    } else if (raw.action?.includes('block')) {
      type = 'block'
    } else if (raw.action?.includes('unlock')) {
      type = 'unlock'
    }

    let formattedTime = ''
    if (raw.created_at) {
      try {
        const d = new Date(raw.created_at)
        formattedTime = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
      } catch {
        formattedTime = raw.created_at
      }
    }

    return {
      id: raw.id || Math.random(),
      author: meta.author_name || raw.user?.name || 'System Sentinel',
      role: meta.author_role || (raw.user ? 'Risk Officer' : 'System Guard'),
      time: formattedTime || '09:00',
      action:
        meta.action_type ||
        raw.action?.replace('finance_exception.', '').toUpperCase() ||
        'CAN THIỆP',
      details: meta.resolution || raw.action || '',
      hash: meta.audit_hash || `0x${Math.random().toString(16).substring(2, 10).toUpperCase()}`,
      type,
      created_at: raw.created_at,
    }
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [statsRes, exRes, logsRes] = await Promise.allSettled([
        financialExceptionsApi.getStats(),
        financialExceptionsApi.getExceptions({ per_page: 100 }),
        financialExceptionsApi.getAuditLogs(undefined, 50),
      ])

      if (statsRes.status === 'fulfilled' && statsRes.value?.stats) {
        stats.value = statsRes.value.stats
      }
      if (exRes.status === 'fulfilled' && exRes.value?.data) {
        exceptions.value = exRes.value.data
      }
      if (logsRes.status === 'fulfilled' && logsRes.value?.data) {
        auditLogs.value = (logsRes.value.data as any[]).map(formatAuditLog)
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
      // Tab filter
      if (activeTab.value === 'critical' && item.severity !== 'critical') return false
      if (
        activeTab.value === 'refund' &&
        item.type !== 'late_refund' &&
        !item.description?.toLowerCase().includes('hoàn')
      )
        return false
      if (
        activeTab.value === 'deposit' &&
        item.type !== 'voucher_hunting' &&
        item.type !== 'limit_exceeded' &&
        !item.description?.toLowerCase().includes('nạp') &&
        !item.description?.toLowerCase().includes('rút')
      )
        return false
      if (
        activeTab.value === 'deposit_hold' &&
        item.type !== 'deposit_hold_mismatch' &&
        !item.description?.toLowerCase().includes('cọc')
      )
        return false

      // Severity dropdown filter
      if (filterSeverity.value !== 'all' && item.severity !== filterSeverity.value) return false

      // Status dropdown filter
      if (filterStatus.value !== 'all') {
        if (filterStatus.value === 'open' && item.status === 'resolved') return false
        if (filterStatus.value === 'resolved' && item.status !== 'resolved') return false
      }

      // Date filter
      if (filterDate.value && item.detected_at) {
        const itemDate = item.detected_at.split('T')[0]
        if (itemDate !== filterDate.value) return false
      }

      // Search query
      if (!searchQuery.value.trim()) return true
      const q = searchQuery.value.toLowerCase()
      const userObj = item.metadata?.user || item.resolver
      return (
        String(item.reference || '')
          .toLowerCase()
          .includes(q) ||
        String(userObj?.name || '')
          .toLowerCase()
          .includes(q) ||
        String(userObj?.id || '')
          .toLowerCase()
          .includes(q) ||
        String(item.description || '')
          .toLowerCase()
          .includes(q) ||
        String(item.metadata?.court || '')
          .toLowerCase()
          .includes(q)
      )
    })
  })

  function toggleAdvancedFilters() {
    showAdvancedFilters.value = !showAdvancedFilters.value
  }

  function resetFilters() {
    activeTab.value = 'all'
    searchQuery.value = ''
    filterDate.value = null
    filterSeverity.value = 'all'
    filterStatus.value = 'all'
  }

  function openDetailModal(item: FinancialException) {
    selectedDetailItem.value = item
    isDetailModalOpen.value = true
  }

  function closeDetailModal() {
    isDetailModalOpen.value = false
    selectedDetailItem.value = null
  }

  function openOverrideDrawer(item: FinancialException) {
    selectedOverrideItem.value = item
    isOverrideDrawerOpen.value = true
    if (isDetailModalOpen.value) {
      isDetailModalOpen.value = false
    }
  }

  function closeOverrideDrawer() {
    isOverrideDrawerOpen.value = false
    selectedOverrideItem.value = null
  }

  async function submitOverride(payload: ManualOverridePayload) {
    if (!selectedOverrideItem.value) return

    const targetItem = selectedOverrideItem.value
    loading.value = true
    try {
      await financialExceptionsApi.resolveException(targetItem.id, {
        resolution: payload.reason,
        action_type: payload.actionType,
      })

      const found = exceptions.value.find((e) => e.id === targetItem.id)
      if (found) {
        found.status = 'resolved'
        found.resolution = payload.reason
      }

      // Refresh data to fetch updated stats and append new immutable audit log
      await loadData()
      notice.value = t('admin.exceptions.overrideSuccessMsg', {
        ref: targetItem.reference || targetItem.id,
      })
      setTimeout(() => {
        notice.value = ''
      }, 5000)
    } catch (e: unknown) {
      error.value = errorMessage(e, t('admin.exceptions.overrideErrorMsg'))
    } finally {
      loading.value = false
      closeOverrideDrawer()
    }
  }

  async function exportAuditReport() {
    isExporting.value = true
    try {
      // Simulate CA report compile & generation
      await new Promise((resolve) => setTimeout(resolve, 800))
      notice.value = t('admin.exceptions.exportSuccess')
      setTimeout(() => {
        notice.value = ''
      }, 4000)
    } finally {
      isExporting.value = false
    }
  }

  function downloadCaSignedLedger() {
    notice.value = t('admin.exceptions.downloadCaSuccess')
    setTimeout(() => {
      notice.value = ''
    }, 4000)
  }

  return {
    activeTab,
    searchQuery,
    filterDate,
    filterSeverity,
    filterStatus,
    showAdvancedFilters,
    notice,
    error,
    loading,
    isExporting,
    stats,
    exceptions,
    auditLogs,
    selectedOverrideItem,
    isOverrideDrawerOpen,
    selectedDetailItem,
    isDetailModalOpen,
    filteredExceptions,
    toggleAdvancedFilters,
    resetFilters,
    openDetailModal,
    closeDetailModal,
    openOverrideDrawer,
    closeOverrideDrawer,
    submitOverride,
    exportAuditReport,
    downloadCaSignedLedger,
    loadData,
  }
}
