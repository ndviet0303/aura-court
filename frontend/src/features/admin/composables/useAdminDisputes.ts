import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { disputeApi } from '../api/disputeApi'
import type {
  DisputeCase,
  DisputeStats,
  DisputeStatus,
  DisputeSeverity,
  DisputeType,
  ArbitrateDisputePayload,
} from '../types/dispute'

export function useAdminDisputes() {
  const { t } = useI18n()

  const activeTab = ref<DisputeStatus | 'all'>('all')
  const searchQuery = ref('')
  const filterSeverity = ref<DisputeSeverity | 'all'>('all')
  const filterDisputeType = ref<DisputeType | 'all'>('all')
  const filterDate = ref<string>('')
  const showAdvancedFilters = ref(false)

  const loading = ref(false)
  const isSubmitting = ref(false)
  const isExporting = ref(false)
  const notice = ref<string | null>(null)
  const error = ref<string | null>(null)
  const isDrawerOpen = ref(false)
  const activeEvidenceTab = ref<'logs' | 'chat' | 'map'>('logs')

  const stats = ref<DisputeStats>({
    pending_count: 0,
    investigatingCount: 0,
    awaitingReplyCount: 0,
    resolvedCount: 0,
    criticalSlaCount: 0,
    avgResolutionTimeMinutes: 18.5,
    consensusRate: 98.2,
  })

  const disputes = ref<DisputeCase[]>([])
  const selectedDispute = ref<DisputeCase | null>(null)

  const filteredDisputes = computed(() => {
    let list = disputes.value

    if (activeTab.value !== 'all') {
      list = list.filter((item) => item.status === activeTab.value)
    }

    if (filterSeverity.value !== 'all') {
      list = list.filter((item) => item.severity === filterSeverity.value)
    }

    if (filterDisputeType.value !== 'all') {
      list = list.filter((item) => item.disputeType === filterDisputeType.value)
    }

    if (filterDate.value) {
      list = list.filter((item) => {
        if (!item.created_at) return true
        return item.created_at.startsWith(filterDate.value)
      })
    }

    if (searchQuery.value.trim()) {
      const kw = searchQuery.value.toLowerCase().trim()
      list = list.filter(
        (item) =>
          item.disputeCode.toLowerCase().includes(kw) ||
          item.title.toLowerCase().includes(kw) ||
          item.complainant?.name?.toLowerCase().includes(kw) ||
          item.respondent?.name?.toLowerCase().includes(kw) ||
          item.facility?.name?.toLowerCase().includes(kw) ||
          (item.bookingId && String(item.bookingId).includes(kw)),
      )
    }

    return list
  })

  async function loadData() {
    loading.value = true
    error.value = null
    try {
      const [list, statsData] = await Promise.all([disputeApi.getDisputes(), disputeApi.getStats()])
      disputes.value = list
      stats.value = statsData

      if (!selectedDispute.value && list.length > 0) {
        selectedDispute.value = list[0] || null
      } else if (selectedDispute.value) {
        // Keep selected item synced with refreshed list
        const updated = list.find((d) => d.id === selectedDispute.value?.id)
        if (updated) selectedDispute.value = updated
      }
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : t('admin.disputes.noCasesFound')
    } finally {
      loading.value = false
    }
  }

  function selectDispute(item: DisputeCase) {
    selectedDispute.value = item
    activeEvidenceTab.value = 'logs'
  }

  function openDrawer() {
    isDrawerOpen.value = true
  }

  function closeDrawer() {
    isDrawerOpen.value = false
  }

  function toggleAdvancedFilters() {
    showAdvancedFilters.value = !showAdvancedFilters.value
  }

  function resetFilters() {
    activeTab.value = 'all'
    filterSeverity.value = 'all'
    filterDisputeType.value = 'all'
    filterDate.value = ''
    searchQuery.value = ''
  }

  async function submitArbitration(payload: ArbitrateDisputePayload) {
    if (!selectedDispute.value) return
    isSubmitting.value = true
    notice.value = null
    error.value = null

    try {
      const res = await disputeApi.arbitrateDispute(selectedDispute.value.id, payload)
      notice.value = res.message || t('admin.disputes.dismissConfirmNotice')

      // Update in-place
      const idx = disputes.value.findIndex((d) => d.id === selectedDispute.value?.id)
      if (idx !== -1) {
        disputes.value[idx] = res.data
      }
      selectedDispute.value = res.data
      closeDrawer()

      // Refresh stats
      disputeApi.getStats().then((st) => {
        stats.value = st
      })

      setTimeout(() => {
        notice.value = null
      }, 5000)
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Error submitting arbitration.'
    } finally {
      isSubmitting.value = false
    }
  }

  async function handleDismiss() {
    if (!selectedDispute.value) return
    await submitArbitration({
      decision: 'dismiss',
      notes: t('admin.disputes.dismissConfirmNotice'),
    })
  }

  function handleRelocateCourt() {
    notice.value = t('admin.disputes.relocateSuccessNotice')
    setTimeout(() => {
      notice.value = null
    }, 4500)
  }

  async function exportPdfReport() {
    isExporting.value = true
    try {
      await new Promise((r) => setTimeout(r, 900))
      // Simulate PDF file download trigger
      const dspCode = selectedDispute.value?.disputeCode || 'ALL'
      notice.value = `PDF Report Dossier [${dspCode}_Arbitration_Record.pdf] exported successfully.`
      setTimeout(() => {
        notice.value = null
      }, 4000)
    } finally {
      isExporting.value = false
    }
  }

  function handlePrint() {
    window.print()
  }

  onMounted(() => {
    loadData()
  })

  return {
    activeTab,
    searchQuery,
    filterSeverity,
    filterDisputeType,
    filterDate,
    showAdvancedFilters,
    loading,
    isSubmitting,
    isExporting,
    notice,
    error,
    isDrawerOpen,
    activeEvidenceTab,
    stats,
    disputes,
    selectedDispute,
    filteredDisputes,
    selectDispute,
    openDrawer,
    closeDrawer,
    toggleAdvancedFilters,
    resetFilters,
    submitArbitration,
    handleDismiss,
    handleRelocateCourt,
    exportPdfReport,
    handlePrint,
    loadData,
  }
}
