import { ref, computed } from 'vue'
import { api } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'

export interface SettlementItem {
  id: number
  period_end: string
  gross_amount: number
  platform_fee: number
  gateway_fee: number
  net_amount: number
  status: string
}

export interface PayoutItem {
  id: number
  reference: string
  created_at: string
  completed_at?: string | null
  amount: number
  status: 'paid' | 'processing' | 'pending' | 'rejected'
  beneficiary?: {
    bank_code: string
    account_number: string
    account_holder: string
  }
}

export interface BeneficiaryInfo {
  id: number
  bank_code: string
  bank_name: string
  account_number: string
  account_holder: string
  tax_id: string
  is_verified: boolean
}

export function useAdminPayouts() {
  const loading = ref(false)
  const notice = ref('')
  const error = ref('')

  const settlements = ref<SettlementItem[]>([])
  const payouts = ref<PayoutItem[]>([])
  const beneficiary = ref<BeneficiaryInfo>({
    id: 0,
    bank_code: '',
    bank_name: '',
    account_number: '',
    account_holder: '',
    tax_id: '',
    is_verified: false,
  })

  const showCreateModal = ref(false)
  const showEditBeneficiaryModal = ref(false)

  const metrics = computed(() => {
    const gross = settlements.value.reduce((sum, s) => sum + (s.gross_amount || 0), 0)
    const fee = settlements.value.reduce((sum, s) => sum + (s.platform_fee || 0) + (s.gateway_fee || 0), 0)
    const net = settlements.value.reduce((sum, s) => sum + (s.net_amount || 0), 0)
    return { gross, fee, net }
  })

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [settleRes, payoutRes, benRes] = await Promise.all([
        api.get<{ data: SettlementItem[] }>('/finance/settlements'),
        api.get<{ data: PayoutItem[] }>('/finance/payouts'),
        api.get<BeneficiaryInfo[]>('/finance/beneficiaries'),
      ])
      if (settleRes?.data) settlements.value = settleRes.data
      if (payoutRes?.data) payouts.value = payoutRes.data
      if (Array.isArray(benRes) && benRes.length > 0 && benRes[0]) {
        beneficiary.value = {
          ...benRes[0],
          bank_name: benRes[0].bank_code === 'VCB' ? 'Vietcombank' : benRes[0].bank_code,
          is_verified: benRes[0].is_verified ?? true,
        }
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu quyết toán và rút tiền.')
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

  async function createPayout(amount: number) {
    loading.value = true
    try {
      const res = await api.post<PayoutItem>('/finance/payouts', {
        amount,
        beneficiary_id: beneficiary.value.id || undefined,
      })
      if (res) {
        payouts.value.unshift(res)
      }
      showCreateModal.value = false
      showNotice(`Đã tạo lệnh rút tiền ${amount.toLocaleString('vi-VN')} ₫ thành công!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tạo lệnh rút tiền.')
    } finally {
      loading.value = false
    }
  }

  async function updateBeneficiary(info: { bank_code: string; account_number: string; account_holder: string }) {
    loading.value = true
    try {
      const res = await api.post<BeneficiaryInfo>('/finance/beneficiaries', info)
      if (res) {
        beneficiary.value = {
          ...beneficiary.value,
          ...res,
        }
      }
      showEditBeneficiaryModal.value = false
      showNotice('Cập nhật tài khoản thụ hưởng thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật tài khoản thụ hưởng.')
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    notice,
    error,
    settlements,
    payouts,
    beneficiary,
    metrics,
    showCreateModal,
    showEditBeneficiaryModal,
    loadData,
    showNotice,
    createPayout,
    updateBeneficiary,
  }
}
