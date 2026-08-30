import { computed, onMounted, ref } from 'vue'
import { adminKycApi } from '../api/adminKycApi'
import type { HostKyc, KycStats, KycStatus } from '../types/kyc'

export function useAdminKyc() {
  const activeTab = ref<KycStatus | 'all'>('pending')
  const searchQuery = ref('')
  const loading = ref(false)
  const isSubmitting = ref(false)
  const notice = ref<string | null>(null)

  const stats = ref<KycStats>({
    pendingCount: 12,
    aiConfidenceRate: 96.4,
    avgProcessingTimeMinutes: 14.2,
    flaggedCount: 2,
    approvedCount: 148,
    needsSupplementCount: 3,
  })

  const kycList = ref<HostKyc[]>([])
  const selectedKyc = ref<HostKyc | null>(null)

  // Underwriter form state
  const selectedRejectReason = ref('')
  const customRejectReason = ref('')
  const reviewerNotes = ref('')
  const checklist = ref({
    repIdentityMatched: true,
    businessLicenseValid: true,
    bankAccountMatched: true,
    brandNameDiscrepancyAccepted: false,
    facilityInspected: true,
  })

  async function fetchStats() {
    try {
      const res = await adminKycApi.getStats()
      if (res.stats) {
        stats.value = res.stats
      }
    } catch {
      // ignore
    }
  }

  async function fetchQueue() {
    loading.value = true
    try {
      const res = await adminKycApi.getQueue({
        status: activeTab.value,
        search: searchQuery.value,
      })
      kycList.value = res.data
      const firstItem = res.data[0]
      if (!selectedKyc.value && firstItem) {
        selectKyc(firstItem)
      } else if (selectedKyc.value) {
        const found = res.data.find((k) => k.id === selectedKyc.value?.id)
        if (found) {
          selectKyc(found)
        } else if (firstItem) {
          selectKyc(firstItem)
        }
      }
    } finally {
      loading.value = false
    }
  }

  function selectKyc(kyc: HostKyc) {
    selectedKyc.value = kyc
    reviewerNotes.value = kyc.reviewerNotes || ''
    selectedRejectReason.value = ''
    customRejectReason.value = ''
    if (kyc.checklistState) {
      checklist.value = {
        repIdentityMatched: kyc.checklistState.repIdentityMatched ?? true,
        businessLicenseValid: kyc.checklistState.businessLicenseValid ?? true,
        bankAccountMatched: kyc.checklistState.bankAccountMatched ?? true,
        brandNameDiscrepancyAccepted: kyc.checklistState.brandNameDiscrepancyAccepted ?? false,
        facilityInspected: kyc.checklistState.facilityInspected ?? true,
      }
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) {
        notice.value = null
      }
    }, 4500)
  }

  async function handleApprove() {
    if (!selectedKyc.value) return
    isSubmitting.value = true
    try {
      const res = await adminKycApi.reviewKyc(selectedKyc.value.id, {
        decision: 'approve',
        reviewerNotes: reviewerNotes.value,
        checklistState: checklist.value,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'approved'
      await fetchQueue()
      await fetchStats()
    } finally {
      isSubmitting.value = false
    }
  }

  async function handleReject() {
    if (!selectedKyc.value) return
    const reason = selectedRejectReason.value || customRejectReason.value || 'Hồ sơ không đáp ứng tiêu chuẩn thẩm định.'
    isSubmitting.value = true
    try {
      const res = await adminKycApi.reviewKyc(selectedKyc.value.id, {
        decision: 'reject',
        rejectionReason: reason,
        reviewerNotes: reviewerNotes.value,
        checklistState: checklist.value,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'rejected'
      await fetchQueue()
      await fetchStats()
    } finally {
      isSubmitting.value = false
    }
  }

  async function handleRequestSupplement() {
    if (!selectedKyc.value) return
    const reason = selectedRejectReason.value || customRejectReason.value || 'Vui lòng bổ sung giấy tờ xác thực rõ nét hơn.'
    isSubmitting.value = true
    try {
      const res = await adminKycApi.reviewKyc(selectedKyc.value.id, {
        decision: 'request_supplement',
        rejectionReason: reason,
        reviewerNotes: reviewerNotes.value,
        checklistState: checklist.value,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'needs_supplement'
      await fetchQueue()
      await fetchStats()
    } finally {
      isSubmitting.value = false
    }
  }

  const filteredQueue = computed(() => {
    let list = kycList.value
    if (activeTab.value !== 'all') {
      list = list.filter((item) => item.status === activeTab.value)
    }
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter(
        (item) =>
          item.kycCode.toLowerCase().includes(q) ||
          item.businessName.toLowerCase().includes(q) ||
          item.representativeName.toLowerCase().includes(q) ||
          item.taxCode.includes(q)
      )
    }
    return list
  })

  onMounted(() => {
    fetchStats()
    fetchQueue()
  })

  return {
    activeTab,
    searchQuery,
    loading,
    isSubmitting,
    notice,
    stats,
    kycList,
    selectedKyc,
    filteredQueue,
    selectedRejectReason,
    customRejectReason,
    reviewerNotes,
    checklist,
    selectKyc,
    fetchQueue,
    handleApprove,
    handleReject,
    handleRequestSupplement,
  }
}
