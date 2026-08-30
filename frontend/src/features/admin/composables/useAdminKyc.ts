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
    pending_count: 0,
    ai_confidence_rate: 0,
    avg_processing_time_minutes: 0,
    flagged_count: 0,
    approved_count: 0,
    needs_supplement_count: 0,
  })

  const kycList = ref<HostKyc[]>([])
  const selectedKyc = ref<HostKyc | null>(null)

  // Underwriter form state
  const selectedRejectReason = ref('')
  const customRejectReason = ref('')
  const reviewer_notes = ref('')
  const checklist = ref({
    rep_identity_matched: true,
    business_license_valid: true,
    bank_account_matched: true,
    brand_name_discrepancy_accepted: false,
    facility_inspected: true,
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
    } catch (err: any) {
      showNotice(err.message || 'Không thể tải danh sách KYC');
    } finally {
      loading.value = false
    }
  }

  function selectKyc(kyc: HostKyc) {
    selectedKyc.value = kyc
    reviewer_notes.value = kyc.reviewer_notes || ''
    selectedRejectReason.value = ''
    customRejectReason.value = ''
    if (kyc.checklist_state) {
      checklist.value = {
        rep_identity_matched: kyc.checklist_state.rep_identity_matched ?? true,
        business_license_valid: kyc.checklist_state.business_license_valid ?? true,
        bank_account_matched: kyc.checklist_state.bank_account_matched ?? true,
        brand_name_discrepancy_accepted: kyc.checklist_state.brand_name_discrepancy_accepted ?? false,
        facility_inspected: kyc.checklist_state.facility_inspected ?? true,
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
        reviewer_notes: reviewer_notes.value,
        checklist_state: checklist.value,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'approved'
      await fetchQueue()
      await fetchStats()
    } catch (err: any) {
      showNotice(err.message || 'Có lỗi xảy ra, vui lòng thử lại');
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
        rejection_reason: reason,
        reviewer_notes: reviewer_notes.value,
        checklist_state: checklist.value,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'rejected'
      await fetchQueue()
      await fetchStats()
    } catch (err: any) {
      showNotice(err.message || 'Có lỗi xảy ra, vui lòng thử lại');
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
        rejection_reason: reason,
        reviewer_notes: reviewer_notes.value,
        checklist_state: checklist.value,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'needs_supplement'
      await fetchQueue()
      await fetchStats()
    } catch (err: any) {
      showNotice(err.message || 'Có lỗi xảy ra, vui lòng thử lại');
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
          item.kyc_code.toLowerCase().includes(q) ||
          item.business_name.toLowerCase().includes(q) ||
          item.representative_name.toLowerCase().includes(q) ||
          item.tax_code.includes(q)
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
    reviewer_notes,
    checklist,
    selectKyc,
    fetchQueue,
    handleApprove,
    handleReject,
    handleRequestSupplement,
  }
}
