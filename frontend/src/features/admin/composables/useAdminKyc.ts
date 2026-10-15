import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminKycApi } from '../api/adminKycApi'
import type {
  DocumentInspectPayload,
  HostKyc,
  KycAuditLog,
  KycBusinessType,
  KycStats,
  KycStatus,
} from '../types/kyc'

export function useAdminKyc() {
  const { t } = useI18n()

  const activeTab = ref<KycStatus | 'all'>('pending')
  const selectedBusinessType = ref<KycBusinessType | 'all'>('all')
  const selectedDate = ref('')
  const searchQuery = ref('')
  const loading = ref(false)
  const isSubmitting = ref(false)
  const isExporting = ref(false)
  const notice = ref<string | null>(null)
  const error = ref<string | null>(null)

  const stats = ref<KycStats>({
    pending_count: 0,
    ai_confidence_rate: 0,
    avg_processing_time_minutes: null,
    flagged_count: 0,
    approved_count: 0,
    needs_supplement_count: 0,
  })

  const kycList = ref<HostKyc[]>([])
  const selectedKyc = ref<HostKyc | null>(null)

  // Document inspect modal state
  const isInspectModalOpen = ref(false)
  const inspectData = ref<DocumentInspectPayload | null>(null)

  // History modal state
  const isHistoryModalOpen = ref(false)
  const auditLogs = ref<KycAuditLog[]>([])

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
      // stats error fallback
    }
  }

  async function fetchQueue() {
    loading.value = true
    error.value = null
    try {
      const res = await adminKycApi.getQueue({
        status: activeTab.value,
        business_type: selectedBusinessType.value,
        date: selectedDate.value,
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
      error.value = err.message || t('admin.kyc.loadError')
      showNotice(err.message || t('admin.kyc.loadError'))
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
        brand_name_discrepancy_accepted:
          kyc.checklist_state.brand_name_discrepancy_accepted ?? false,
        facility_inspected: kyc.checklist_state.facility_inspected ?? true,
      }
    }
  }

  async function refreshSelectedKyc(id: number) {
    try {
      const res = await adminKycApi.getDetail(id)
      if (res.data) {
        selectKyc(res.data)
      }
    } catch {
      await fetchQueue()
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

  function openInspectModal(payload: DocumentInspectPayload) {
    inspectData.value = payload
    isInspectModalOpen.value = true
  }

  function closeInspectModal() {
    isInspectModalOpen.value = false
    inspectData.value = null
  }

  function openHistoryModal() {
    if (!selectedKyc.value) return
    auditLogs.value = [
      {
        id: 1,
        kyc_id: selectedKyc.value.id,
        action: 'Hồ sơ khởi tạo & nộp thẩm định',
        reviewer_name: selectedKyc.value.representative_name,
        decision: 'pending',
        notes: 'Chủ sân hoàn tất nộp hồ sơ pháp lý CCCD & GPKD.',
        created_at: selectedKyc.value.submitted_at || '2026-08-30 08:30',
      },
      {
        id: 2,
        kyc_id: selectedKyc.value.id,
        action: 'Đối soát AI OCR & NAPAS tự động',
        reviewer_name: 'Aura OCR AI Engine',
        decision: selectedKyc.value.status === 'flagged' ? 'flagged' : 'pending',
        notes: `Điểm tin cậy OCR: ${selectedKyc.value.ai_ocr_score}%. Sinh trắc khuôn mặt: ${selectedKyc.value.face_match_score}%.`,
        created_at: selectedKyc.value.submitted_at || '2026-08-30 08:32',
      },
    ]

    if (selectedKyc.value.status === 'approved') {
      auditLogs.value.push({
        id: 3,
        kyc_id: selectedKyc.value.id,
        action: 'Phê duyệt & Kích hoạt Chủ sân',
        reviewer_name: selectedKyc.value.reviewer_name || 'Lê Thị Kim Oanh (Cấp 3)',
        decision: 'approved',
        notes: selectedKyc.value.reviewer_notes || 'Hồ sơ pháp lý hợp lệ. Đã kích hoạt chủ sân.',
        created_at: selectedKyc.value.reviewed_at || '2026-08-30 10:15',
      })
    }

    isHistoryModalOpen.value = true
  }

  function closeHistoryModal() {
    isHistoryModalOpen.value = false
  }

  function callHotline() {
    if (!selectedKyc.value) return
    const phone = selectedKyc.value.representative_phone || '0909 888 999'
    navigator.clipboard?.writeText?.(phone)
    showNotice(t('admin.kyc.copiedPhoneNotice', { phone }))
  }

  async function exportKycReport() {
    isExporting.value = true
    try {
      await new Promise((resolve) => setTimeout(resolve, 800))
      const content = [
        [
          'KYC_CODE',
          'BUSINESS_NAME',
          'TAX_CODE',
          'REP_NAME',
          'STATUS',
          'OCR_SCORE',
          'SUBMITTED_AT',
        ].join(','),
        ...filteredQueue.value.map((k) =>
          [
            k.kyc_code,
            `"${k.business_name}"`,
            k.tax_code,
            `"${k.representative_name}"`,
            k.status,
            `${k.ai_ocr_score}%`,
            k.submitted_at,
          ].join(','),
        ),
      ].join('\n')

      const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `Aura_KYC_Audit_Report_${new Date().toISOString().slice(0, 10)}.csv`
      a.click()
      URL.revokeObjectURL(url)
      showNotice(t('admin.kyc.exportSuccess'))
    } catch {
      showNotice('Không thể xuất báo cáo.')
    } finally {
      isExporting.value = false
    }
  }

  async function handleApprove() {
    if (!selectedKyc.value) return
    isSubmitting.value = true
    try {
      const res = await adminKycApi.reviewKyc(selectedKyc.value.id, {
        decision: 'approve',
        reviewer_notes: reviewer_notes.value,
        checklist_state: checklist.value,
        expected_version: selectedKyc.value.version,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'approved'
      await fetchQueue()
      await fetchStats()
    } catch (err: any) {
      if (err.status === 409 || err.message?.includes('409') || err.message?.includes('xung đột')) {
        showNotice('Xung đột xét duyệt: Hồ sơ đã được cập nhật phiên bản mới hoặc đã được xử lý.')
        await refreshSelectedKyc(selectedKyc.value.id)
      } else {
        showNotice(err.message || 'Có lỗi xảy ra, vui lòng thử lại')
      }
    } finally {
      isSubmitting.value = false
    }
  }

  // BR-15: revision and rejection carry the reviewer's own reason of 10–1000 characters.
  function reviewReason(): string | null {
    const reason = customRejectReason.value.trim()
    if (reason.length < 10 || reason.length > 1000) {
      showNotice('Vui lòng nhập lý do dài 10–1.000 ký tự trước khi từ chối hoặc yêu cầu bổ sung.')
      return null
    }
    return reason
  }

  async function handleReject() {
    if (!selectedKyc.value) return
    const reason = reviewReason()
    if (!reason) return
    isSubmitting.value = true
    try {
      const res = await adminKycApi.reviewKyc(selectedKyc.value.id, {
        decision: 'reject',
        rejection_reason: reason,
        reviewer_notes: reviewer_notes.value,
        checklist_state: checklist.value,
        expected_version: selectedKyc.value.version,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'rejected'
      await fetchQueue()
      await fetchStats()
    } catch (err: any) {
      if (err.status === 409 || err.message?.includes('409') || err.message?.includes('xung đột')) {
        showNotice('Xung đột xét duyệt: Hồ sơ đã được cập nhật phiên bản mới hoặc đã được xử lý.')
        await refreshSelectedKyc(selectedKyc.value.id)
      } else {
        showNotice(err.message || 'Có lỗi xảy ra, vui lòng thử lại')
      }
    } finally {
      isSubmitting.value = false
    }
  }

  async function handleRequestSupplement() {
    if (!selectedKyc.value) return
    const reason = reviewReason()
    if (!reason) return
    isSubmitting.value = true
    try {
      const res = await adminKycApi.reviewKyc(selectedKyc.value.id, {
        decision: 'request_supplement',
        rejection_reason: reason,
        reviewer_notes: reviewer_notes.value,
        checklist_state: checklist.value,
        expected_version: selectedKyc.value.version,
      })
      showNotice(res.message)
      selectedKyc.value.status = 'needs_supplement'
      await fetchQueue()
      await fetchStats()
    } catch (err: any) {
      if (err.status === 409 || err.message?.includes('409') || err.message?.includes('xung đột')) {
        showNotice('Xung đột xét duyệt: Hồ sơ đã được cập nhật phiên bản mới hoặc đã được xử lý.')
        await refreshSelectedKyc(selectedKyc.value.id)
      } else {
        showNotice(err.message || 'Có lỗi xảy ra, vui lòng thử lại')
      }
    } finally {
      isSubmitting.value = false
    }
  }

  async function inspectAuthorizedDocument(doc: {
    id?: number
    title: string
    type: string
    url?: string
  }) {
    if (doc.id && selectedKyc.value) {
      try {
        const res = await adminKycApi.getDocumentUrl(selectedKyc.value.id, doc.id)
        openInspectModal({
          title: doc.title,
          type: doc.type as any,
          imageUrl: res.url,
        })
        return
      } catch {
        // Fallback to url if available
      }
    }
    if (doc.url) {
      openInspectModal({
        title: doc.title,
        type: doc.type as any,
        imageUrl: doc.url,
      })
    }
  }

  const filteredQueue = computed(() => {
    let list = kycList.value
    if (activeTab.value !== 'all') {
      list = list.filter((item) => item.status === activeTab.value)
    }
    if (selectedBusinessType.value !== 'all') {
      list = list.filter((item) => item.business_type === selectedBusinessType.value)
    }
    if (selectedDate.value) {
      list = list.filter(
        (item) => item.submitted_at && item.submitted_at.startsWith(selectedDate.value),
      )
    }
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter(
        (item) =>
          item.kyc_code.toLowerCase().includes(q) ||
          item.business_name.toLowerCase().includes(q) ||
          item.representative_name.toLowerCase().includes(q) ||
          item.tax_code.includes(q),
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
    selectedBusinessType,
    selectedDate,
    searchQuery,
    loading,
    isSubmitting,
    isExporting,
    notice,
    error,
    stats,
    kycList,
    selectedKyc,
    filteredQueue,
    selectedRejectReason,
    customRejectReason,
    reviewer_notes,
    checklist,
    isInspectModalOpen,
    inspectData,
    isHistoryModalOpen,
    auditLogs,
    selectKyc,
    fetchQueue,
    fetchStats,
    openInspectModal,
    closeInspectModal,
    openHistoryModal,
    closeHistoryModal,
    callHotline,
    exportKycReport,
    handleApprove,
    handleReject,
    handleRequestSupplement,
    inspectAuthorizedDocument,
  }
}
