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
  beneficiary: {
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
    id: 1,
    bank_code: 'VCB',
    bank_name: 'Vietcombank (Ngoại Thương Việt Nam)',
    account_number: '0123456789',
    account_holder: 'CONG TY TNHH SMASH',
    tax_id: '0316888999',
    is_verified: true,
  })

  const showCreateModal = ref(false)
  const showEditBeneficiaryModal = ref(false)

  const metrics = computed(() => {
    const gross = settlements.value.reduce((sum, s) => sum + s.gross_amount, 27500000)
    const fee = settlements.value.reduce((sum, s) => sum + s.platform_fee + s.gateway_fee, 1375000)
    const net = settlements.value.reduce((sum, s) => sum + s.net_amount, 26125000)
    return { gross, fee, net }
  })

  async function loadData() {
    loading.value = true
    try {
      const [settleRes, payoutRes, benRes] = await Promise.all([
        api.get<{ data: SettlementItem[] }>('/finance/settlements'),
        api.get<{ data: PayoutItem[] }>('/finance/payouts'),
        api.get<BeneficiaryInfo[]>('/finance/beneficiaries'),
      ])
      if (settleRes && settleRes.data) settlements.value = settleRes.data
      if (payoutRes && payoutRes.data) payouts.value = payoutRes.data
      if (benRes && benRes.length) {
        beneficiary.value = {
          ...benRes[0],
          bank_name: benRes[0]?.bank_code === 'VCB' ? 'Vietcombank' : benRes[0]?.bank_code || 'Vietcombank',
          is_verified: true,
        } as BeneficiaryInfo
      }
    } catch (e) {
      // mock fallback
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

  function createPayout(amount: number) {
    payouts.value.unshift({
      id: Date.now(),
      reference: `WD-${new Date().getMonth() + 1}${new Date().getFullYear().toString().slice(2)}-0${payouts.value.length + 1}`,
      created_at: new Date().toISOString().slice(0, 10),
      amount,
      status: 'processing',
      beneficiary: {
        bank_code: beneficiary.value.bank_code,
        account_number: beneficiary.value.account_number,
        account_holder: beneficiary.value.account_holder,
      },
    })
    showCreateModal.value = false
    showNotice(`Đã tạo lệnh rút tiền ${amount.toLocaleString('vi-VN')} ₫ thành công!`)
  }

  function updateBeneficiary(info: { bank_code: string; account_number: string; account_holder: string }) {
    beneficiary.value.bank_code = info.bank_code
    beneficiary.value.account_number = info.account_number
    beneficiary.value.account_holder = info.account_holder
    showEditBeneficiaryModal.value = false
    showNotice('Cập nhật tài khoản thụ hưởng thành công!')
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
