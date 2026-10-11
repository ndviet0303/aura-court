import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { financeApi } from '../api/financeApi'
import type { SettlementRecord, PayoutRecord, BeneficiaryAccount } from '../types/finance'
import { errorMessage } from '@/core/utils/errorMessage'

export type {
  SettlementRecord as SettlementItem,
  PayoutRecord as PayoutItem,
  BeneficiaryAccount as BeneficiaryInfo,
}

export function getProviderFee(s: SettlementRecord): number {
  if (s.provider_fee_amount !== undefined && s.provider_fee_amount !== null) {
    return Number(s.provider_fee_amount)
  }
  return Number(s.gateway_fee ?? 0)
}

export function getHistoricalCommission(s: SettlementRecord): number {
  if (s.commission_amount !== undefined && s.commission_amount !== null) {
    return Number(s.commission_amount)
  }
  return Number(s.platform_fee ?? 0)
}

export function getTotalDeduction(s: SettlementRecord): number {
  return getProviderFee(s) + getHistoricalCommission(s)
}

export function getNetPayable(s: SettlementRecord): number {
  if (s.tenant_payable !== undefined && s.tenant_payable !== null) {
    return Number(s.tenant_payable)
  }
  return Number(s.net_amount ?? (s.gross_amount || 0) - getTotalDeduction(s))
}

export function useAdminPayouts() {
  const { t } = useI18n()

  const loading = ref(false)
  const actionLoading = ref(false)
  const notice = ref('')
  const error = ref('')

  const activeTab = ref<'payouts' | 'settlements' | 'flow'>('payouts')

  const settlements = ref<SettlementRecord[]>([])
  const payouts = ref<PayoutRecord[]>([])
  const beneficiary = ref<BeneficiaryAccount>({
    id: 0,
    bank_code: '',
    bank_name: '',
    account_number: '',
    account_holder: '',
    tax_id: '',
    is_verified: false,
    status: 'pending',
  })

  // Modals state
  const showCreateModal = ref(false)
  const showEditBeneficiaryModal = ref(false)
  const showSnapshotModal = ref(false)
  const selectedSettlementForPayout = ref<SettlementRecord | null>(null)

  // Filter states
  const payoutSearch = ref('')
  const payoutStatusFilter = ref('all')
  const payoutDateFilter = ref('')

  const settlementSearch = ref('')
  const settlementStatusFilter = ref('all')
  const settlementDateFilter = ref('')

  // Metrics computation
  const metrics = computed(() => {
    const gross = settlements.value.reduce((sum, s) => sum + (s.gross_amount || 0), 0)
    const providerFee = settlements.value.reduce((sum, s) => sum + getProviderFee(s), 0)
    const commission = settlements.value.reduce((sum, s) => sum + getHistoricalCommission(s), 0)
    const fee = providerFee + commission
    const net = settlements.value.reduce((sum, s) => sum + getNetPayable(s), 0)
    const paid = payouts.value
      .filter((p) => p.status === 'paid')
      .reduce((sum, p) => sum + (p.amount || 0), 0)

    return { gross, fee, net, paid, providerFee, commission }
  })

  // Filtered lists
  const filteredPayouts = computed(() => {
    return payouts.value.filter((p) => {
      const q = payoutSearch.value.trim().toLowerCase()
      const refCode = (p.reference || p.code || `WD-${p.id}`).toLowerCase()
      const bankCode = (p.beneficiary?.bank_code || '').toLowerCase()
      const bankName = (p.beneficiary?.bank_name || '').toLowerCase()
      const accNumber = (p.beneficiary?.account_number || '').toLowerCase()
      const accHolder = (p.beneficiary?.account_holder || p.beneficiary_name || '').toLowerCase()

      const matchSearch =
        !q ||
        refCode.includes(q) ||
        bankCode.includes(q) ||
        bankName.includes(q) ||
        accNumber.includes(q) ||
        accHolder.includes(q) ||
        String(p.amount).includes(q)

      const matchStatus =
        payoutStatusFilter.value === 'all' || p.status === payoutStatusFilter.value

      const matchDate =
        !payoutDateFilter.value || (p.created_at && p.created_at.startsWith(payoutDateFilter.value))

      return matchSearch && matchStatus && matchDate
    })
  })

  const filteredSettlements = computed(() => {
    return settlements.value.filter((s) => {
      const q = settlementSearch.value.trim().toLowerCase()
      const period = `${s.period_start} ${s.period_end}`.toLowerCase()
      const matchSearch =
        !q ||
        period.includes(q) ||
        String(s.gross_amount).includes(q) ||
        String(s.tenant_payable).includes(q)

      const matchStatus =
        settlementStatusFilter.value === 'all' || s.status === settlementStatusFilter.value

      const matchDate =
        !settlementDateFilter.value ||
        s.period_start === settlementDateFilter.value ||
        s.period_end === settlementDateFilter.value

      return matchSearch && matchStatus && matchDate
    })
  })

  const eligibleSettlements = computed(() => {
    return settlements.value.filter((s) => s.status === 'eligible')
  })

  function showNoticeMessage(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function getBankNameByCode(code: string): string {
    const bankMap: Record<string, string> = {
      VCB: 'Vietcombank',
      TCB: 'Techcombank',
      MB: 'MBBank',
      MBBANK: 'MBBank',
      ACB: 'ACB',
      BIDV: 'BIDV',
      CTG: 'VietinBank',
      VPB: 'VPBank',
      TPB: 'TPBank',
      STB: 'Sacombank',
      HDB: 'HDBank',
      VIB: 'VIB',
      MSB: 'MSB',
    }
    return bankMap[code.toUpperCase()] || code
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [settleRes, payoutRes, benRes] = await Promise.all([
        financeApi.getSettlements(),
        financeApi.getPayouts(),
        financeApi.getBeneficiaries(),
      ])

      // Handle Paginated / Array responses
      if (settleRes && 'data' in settleRes && Array.isArray(settleRes.data)) {
        settlements.value = settleRes.data
      } else if (Array.isArray(settleRes)) {
        settlements.value = settleRes
      }

      if (payoutRes && 'data' in payoutRes && Array.isArray(payoutRes.data)) {
        payouts.value = payoutRes.data.map((p) => ({
          ...p,
          reference: p.reference || p.code || `WD-${String(p.id).padStart(6, '0')}`,
        }))
      } else if (Array.isArray(payoutRes)) {
        payouts.value = payoutRes.map((p) => ({
          ...p,
          reference: p.reference || p.code || `WD-${String(p.id).padStart(6, '0')}`,
        }))
      }

      if (Array.isArray(benRes) && benRes.length > 0 && benRes[0]) {
        const b = benRes[0]
        beneficiary.value = {
          ...b,
          bank_name: b.bank_name || getBankNameByCode(b.bank_code),
          is_verified: b.status === 'verified' || b.is_verified === true,
        }
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, t('admin.payouts.loadError'))
    } finally {
      loading.value = false
    }
  }

  function openCreateModal(settlement?: SettlementRecord) {
    selectedSettlementForPayout.value = settlement || eligibleSettlements.value[0] || null
    showCreateModal.value = true
  }

  async function createPayout(settlementId: number, beneficiaryId: number) {
    actionLoading.value = true
    error.value = ''
    try {
      const res = await financeApi.createPayout({
        settlement_id: settlementId,
        beneficiary_id: beneficiaryId,
      })
      const newPayout = 'payout' in res && res.payout ? res.payout : (res as PayoutRecord)
      if (newPayout) {
        newPayout.reference = newPayout.reference || `WD-${String(newPayout.id).padStart(6, '0')}`
        payouts.value.unshift(newPayout)
      }
      showCreateModal.value = false
      showNoticeMessage(
        t('admin.payouts.payoutCreateSuccess', {
          amount: (newPayout?.amount || 0).toLocaleString('vi-VN') + ' ₫',
        }),
      )
      await loadData()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tạo lệnh rút tiền.')
    } finally {
      actionLoading.value = false
    }
  }

  async function approvePayout(payoutId: number) {
    actionLoading.value = true
    error.value = ''
    try {
      const res = await financeApi.approvePayout(payoutId)
      const updated = 'payout' in res && res.payout ? res.payout : (res as PayoutRecord)
      const idx = payouts.value.findIndex((p) => p.id === payoutId)
      if (idx !== -1 && updated) {
        payouts.value[idx] = { ...payouts.value[idx], ...updated }
      }
      showNoticeMessage(t('admin.payouts.payoutApproveSuccess', { id: payoutId }))
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể phê duyệt lệnh rút tiền.')
    } finally {
      actionLoading.value = false
    }
  }

  async function submitPayout(payoutId: number) {
    actionLoading.value = true
    error.value = ''
    try {
      const res = await financeApi.submitPayout(payoutId)
      const updated = 'payout' in res && res.payout ? res.payout : (res as PayoutRecord)
      const idx = payouts.value.findIndex((p) => p.id === payoutId)
      if (idx !== -1 && updated) {
        payouts.value[idx] = { ...payouts.value[idx], ...updated }
      }
      showNoticeMessage(t('admin.payouts.payoutSubmitSuccess', { id: payoutId }))
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể gửi lệnh rút tiền sang cổng thanh toán.')
    } finally {
      actionLoading.value = false
    }
  }

  async function syncPayoutStatus(payoutId: number) {
    actionLoading.value = true
    error.value = ''
    try {
      const res = await financeApi.queryPayoutStatus(payoutId)
      const updated = 'payout' in res && res.payout ? res.payout : (res as PayoutRecord)
      const idx = payouts.value.findIndex((p) => p.id === payoutId)
      if (idx !== -1 && updated) {
        payouts.value[idx] = { ...payouts.value[idx], ...updated }
      }
      showNoticeMessage(t('admin.payouts.payoutSyncSuccess', { id: payoutId }))
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể đồng bộ trạng thái lệnh rút tiền.')
    } finally {
      actionLoading.value = false
    }
  }

  async function snapshotSettlement(periodStart: string, periodEnd: string) {
    actionLoading.value = true
    error.value = ''
    try {
      const res = await financeApi.snapshotSettlement({
        period_start: periodStart,
        period_end: periodEnd,
      })
      const newSettle =
        'settlement' in res && res.settlement ? res.settlement : (res as SettlementRecord)
      if (newSettle) {
        settlements.value.unshift(newSettle)
      }
      showSnapshotModal.value = false
      showNoticeMessage(t('admin.payouts.snapshotSuccess'))
      await loadData()
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tạo kỳ quyết toán mới.')
    } finally {
      actionLoading.value = false
    }
  }

  async function updateBeneficiary(info: {
    bank_code: string
    account_number: string
    account_holder: string
    tax_id?: string
  }) {
    actionLoading.value = true
    error.value = ''
    try {
      const res = await financeApi.storeBeneficiary({
        provider: 'payos',
        bank_code: info.bank_code,
        account_number: info.account_number,
        account_holder: info.account_holder,
        tax_id: info.tax_id,
      })
      const newBen =
        'beneficiary' in res && res.beneficiary ? res.beneficiary : (res as BeneficiaryAccount)
      if (newBen) {
        beneficiary.value = {
          ...beneficiary.value,
          ...newBen,
          bank_name: newBen.bank_name || getBankNameByCode(newBen.bank_code),
          is_verified: newBen.status === 'verified' || newBen.is_verified === true,
        }
      }
      showEditBeneficiaryModal.value = false
      showNoticeMessage(t('admin.payouts.beneficiaryUpdateSuccess'))
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật tài khoản thụ hưởng.')
    } finally {
      actionLoading.value = false
    }
  }

  return {
    loading,
    actionLoading,
    notice,
    error,
    activeTab,
    settlements,
    payouts,
    beneficiary,
    metrics,
    showCreateModal,
    showEditBeneficiaryModal,
    showSnapshotModal,
    selectedSettlementForPayout,
    payoutSearch,
    payoutStatusFilter,
    payoutDateFilter,
    settlementSearch,
    settlementStatusFilter,
    settlementDateFilter,
    filteredPayouts,
    filteredSettlements,
    eligibleSettlements,
    loadData,
    openCreateModal,
    createPayout,
    approvePayout,
    submitPayout,
    syncPayoutStatus,
    snapshotSettlement,
    updateBeneficiary,
    showNoticeMessage,
    getBankNameByCode,
  }
}
