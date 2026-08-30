import { ref, computed, onMounted } from 'vue'
import { disputeApi } from '../api/disputeApi'
import type {
  DisputeCase,
  DisputeStats,
  DisputeStatus,
  ArbitrateDisputePayload,
} from '../types/dispute'

export function useAdminDisputes() {
  const activeTab = ref<DisputeStatus | 'all'>('all')
  const searchQuery = ref('')
  const loading = ref(false)
  const isSubmitting = ref(false)
  const notice = ref<string | null>(null)
  const isDrawerOpen = ref(false)
  const activeEvidenceTab = ref<'logs' | 'chat' | 'map'>('logs')

  const stats = ref<DisputeStats>({
    pending_count: 0,
    investigatingCount: 0,
    awaitingReplyCount: 0,
    resolvedCount: 0,
    criticalSlaCount: 0,
    avgResolutionTimeMinutes: 0,
    consensusRate: 100,
  })

  const disputes = ref<DisputeCase[]>([])
  const selectedDispute = ref<DisputeCase | null>(null)

  const filteredDisputes = computed(() => {
    let list = disputes.value

    if (activeTab.value !== 'all') {
      list = list.filter((item) => item.status === activeTab.value)
    }

    if (searchQuery.value.trim()) {
      const kw = searchQuery.value.toLowerCase().trim()
      list = list.filter(
        (item) =>
          item.disputeCode.toLowerCase().includes(kw) ||
          item.title.toLowerCase().includes(kw) ||
          item.complainant?.name?.toLowerCase().includes(kw) ||
          item.respondent?.name?.toLowerCase().includes(kw) ||
          item.facility?.name?.toLowerCase().includes(kw)
      )
    }

    return list
  })

  async function loadData() {
    loading.value = true
    try {
      const [list, statsData] = await Promise.all([
        disputeApi.getDisputes(),
        disputeApi.getStats(),
      ])
      disputes.value = list
      stats.value = statsData
      if (!selectedDispute.value && list.length > 0) {
        selectedDispute.value = list[0] || null
      }
    } catch (e: unknown) {
      notice.value = e instanceof Error ? e.message : 'Không thể tải danh sách tranh chấp.'
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

  async function submitArbitration(payload: ArbitrateDisputePayload) {
    if (!selectedDispute.value) return
    isSubmitting.value = true
    notice.value = null

    try {
      const res = await disputeApi.arbitrateDispute(selectedDispute.value.id, payload)
      notice.value = res.message

      // Update in-place
      const idx = disputes.value.findIndex((d) => d.id === selectedDispute.value?.id)
      if (idx !== -1) {
        disputes.value[idx] = res.data
      }
      selectedDispute.value = res.data
      closeDrawer()

      setTimeout(() => {
        notice.value = null
      }, 5000)
    } catch (e: unknown) {
      notice.value = e instanceof Error ? e.message : 'Có lỗi xảy ra khi phán quyết vụ việc.'
    } finally {
      isSubmitting.value = false
    }
  }

  async function handleDismiss() {
    if (!selectedDispute.value) return
    await submitArbitration({
      decision: 'dismiss',
      notes: 'Bác bỏ khiếu nại do không đủ căn cứ bằng chứng theo quy định.',
    })
  }

  function handleRelocateCourt() {
    notice.value = 'Đã gửi lệnh điều chuyển tự động cho cơ sở thể thao.'
    setTimeout(() => {
      notice.value = null
    }, 4000)
  }

  onMounted(() => {
    loadData()
  })

  return {
    activeTab,
    searchQuery,
    loading,
    isSubmitting,
    notice,
    isDrawerOpen,
    activeEvidenceTab,
    stats,
    disputes,
    selectedDispute,
    filteredDisputes,
    selectDispute,
    openDrawer,
    closeDrawer,
    submitArbitration,
    handleDismiss,
    handleRelocateCourt,
    loadData,
  }
}
