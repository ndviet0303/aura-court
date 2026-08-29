<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { financeApi } from '../api/financeApi'
import { useAuthStore } from '@/stores/auth'
import { formatCurrency } from '@/i18n'

const { t } = useI18n()
const auth = useAuthStore()

// Types
export interface SettlementRecord {
  id: number
  date: string
  period_start?: string
  period_end?: string
  gross_amount: number
  platform_fee: number // 3%
  gateway_fee: number // 2%
  net_amount: number
  status: 'settled' | 'pending' | 'processing' | 'disputed'
  notes?: string
}

export interface PayoutRecord {
  id: number
  code: string
  created_at: string
  completed_at?: string | null
  amount: number
  status: 'draft' | 'pending_approval' | 'approved' | 'submitted' | 'processing' | 'paid' | 'failed' | 'rejected'
  beneficiary_name: string
  beneficiary_bank: string
  beneficiary_account: string
  current_step: number // 1: Tao lenh, 2: Duyet, 3: Gui yeu cau, 4: Dang chi tra, 5: Hoan tat
  step_description?: string
  tx_hash?: string
  reference?: string
}

export interface BeneficiaryAccount {
  id: number
  provider?: string
  bank_code: string
  bank_name: string
  account_number: string
  account_holder: string
  tax_id?: string
  is_verified: boolean
  updated_at?: string
}

export interface FinanceExceptionItem {
  id: number
  code: string
  type: 'discrepancy' | 'gateway_timeout' | 'chargeback' | 'bank_mismatch'
  description: string
  amount: number
  status: 'unresolved' | 'resolved'
  created_at: string
  resolution?: string
}

// State
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const error = ref('')

// Filters
const timeFilter = ref<'today' | 'week' | 'month' | 'all'>('month')
const searchQuery = ref('')
const statusFilter = ref<'all' | 'settled' | 'pending' | 'processing'>('all')

// Selected / Active Payout for timeline inspect
const activePayoutId = ref<number | null>(null)

// Modals
const showCreatePayoutModal = ref(false)
const showEditBeneficiaryModal = ref(false)
const showSnapshotModal = ref(false)
const showPayoutDetailModal = ref(false)
const selectedPayoutDetail = ref<PayoutRecord | null>(null)

// Forms
const payoutForm = ref({
  amount: 25000000,
  settlement_id: 1,
  beneficiary_id: 1,
  note: '',
})

const beneficiaryForm = ref({
  bank_code: 'VCB',
  bank_name: 'Vietcombank (VCB)',
  account_number: '0123456789',
  account_holder: 'CONG TY TNHH SMASH',
  tax_id: '0316888999',
})

const snapshotForm = ref({
  period_start: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  period_end: new Date().toISOString().split('T')[0],
  note: '',
})

// Bank list for selection
const popularBanks = [
  { code: 'VCB', name: 'Vietcombank (VCB - Ngân hàng Ngoại Thương)' },
  { code: 'TCB', name: 'Techcombank (TCB - Kỹ Thương Việt Nam)' },
  { code: 'MB', name: 'MB Bank (Ngân hàng Quân Đội)' },
  { code: 'ACB', name: 'ACB (Ngân hàng Á Châu)' },
  { code: 'BIDV', name: 'BIDV (Đầu tư và Phát triển Việt Nam)' },
  { code: 'CTG', name: 'VietinBank (Công Thương Việt Nam)' },
  { code: 'VPB', name: 'VPBank (Việt Nam Thịnh Vượng)' },
  { code: 'TPB', name: 'TPBank (Tiên Phong)' },
]

// Mock & Real Data Holders
const beneficiary = ref<BeneficiaryAccount>({
  id: 1,
  provider: 'payos',
  bank_code: 'VCB',
  bank_name: 'Vietcombank (VCB)',
  account_number: '0123456789',
  account_holder: 'Cong Ty TNHH Smash',
  tax_id: '0316888999',
  is_verified: true,
  updated_at: '15/10/2023',
})

const settlementRecords = ref<SettlementRecord[]>([
  {
    id: 1,
    date: '15/10/2023',
    gross_amount: 12500000,
    platform_fee: 375000,
    gateway_fee: 250000,
    net_amount: 11875000,
    status: 'settled',
    notes: 'Kỳ đối soát ngày 15/10 hoàn tất tự động D+2',
  },
  {
    id: 2,
    date: '16/10/2023',
    gross_amount: 15000000,
    platform_fee: 450000,
    gateway_fee: 300000,
    net_amount: 14250000,
    status: 'settled',
    notes: 'Kỳ đối soát ngày 16/10 đã chốt số liệu',
  },
  {
    id: 3,
    date: '17/10/2023',
    gross_amount: 18000000,
    platform_fee: 540000,
    gateway_fee: 360000,
    net_amount: 17100000,
    status: 'pending',
    notes: 'Đang đợi chu kỳ hoàn tất D+2 (Dự kiến: 19/10/2023)',
  },
  {
    id: 4,
    date: '18/10/2023',
    gross_amount: 21500000,
    platform_fee: 645000,
    gateway_fee: 430000,
    net_amount: 20425000,
    status: 'processing',
    notes: 'Các giao dịch trực tuyến phát sinh trong ngày',
  },
])

const payouts = ref<PayoutRecord[]>([
  {
    id: 1,
    code: 'WD-0923-01',
    created_at: '01/10/2023',
    completed_at: '02/10/2023',
    amount: 50000000,
    status: 'paid',
    beneficiary_name: 'Cong Ty TNHH Smash',
    beneficiary_bank: 'Vietcombank (VCB)',
    beneficiary_account: '0123456789',
    current_step: 5,
    step_description: 'Đã giải ngân thành công vào tài khoản thụ hưởng',
    tx_hash: 'TX9928172648',
  },
  {
    id: 2,
    code: 'WD-1023-01',
    created_at: '18/10/2023',
    completed_at: null,
    amount: 25000000,
    status: 'processing',
    beneficiary_name: 'Cong Ty TNHH Smash',
    beneficiary_bank: 'Vietcombank (VCB)',
    beneficiary_account: '0123456789',
    current_step: 4,
    step_description: 'Đang thực hiện lệnh chi trả liên ngân hàng tự động 24/7',
    tx_hash: 'TX1029384756',
  },
])

const exceptions = ref<FinanceExceptionItem[]>([
  {
    id: 1,
    code: 'EXC-8821',
    type: 'discrepancy',
    description: 'Chênh lệch 200.000 ₫ giữa cổng thanh toán và giao dịch ca sáng 14/10',
    amount: 200000,
    status: 'unresolved',
    created_at: '14/10/2023 11:30',
  },
])

// Computed Metrics
const grossTotal = computed(() => {
  return settlementRecords.value.reduce((acc, curr) => acc + curr.gross_amount, 0)
})

const feeTotal = computed(() => {
  return settlementRecords.value.reduce((acc, curr) => acc + curr.platform_fee + curr.gateway_fee, 0)
})

const netTotal = computed(() => {
  return settlementRecords.value.reduce((acc, curr) => acc + curr.net_amount, 0)
})

const availableWithdrawBalance = computed(() => {
  // Only settled records minus already withdrawn amount
  const settledSum = settlementRecords.value
    .filter((s) => s.status === 'settled')
    .reduce((acc, s) => acc + s.net_amount, 0)
  const processingPayouts = payouts.value
    .filter((p) => p.status === 'processing' || p.status === 'pending_approval')
    .reduce((acc, p) => acc + p.amount, 0)
  return Math.max(0, settledSum - processingPayouts)
})

const filteredSettlements = computed(() => {
  let list = settlementRecords.value
  if (statusFilter.value !== 'all') {
    list = list.filter((s) => s.status === statusFilter.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter((s) => s.date.includes(q) || s.notes?.toLowerCase().includes(q))
  }
  return list
})

// Highlighted Active Payout for Stepper Display
const currentInspectedPayout = computed(() => {
  if (activePayoutId.value) {
    return payouts.value.find((p) => p.id === activePayoutId.value) || payouts.value[0]
  }
  return payouts.value.find((p) => p.status === 'processing') || payouts.value[0] || null
})

// Stepper Progress % Calculation
const stepperProgressPercent = computed(() => {
  if (!currentInspectedPayout.value) return 0
  const step = currentInspectedPayout.value.current_step
  // 5 steps: 1 (0%), 2 (25%), 3 (50%), 4 (75%), 5 (100%)
  return Math.max(0, Math.min(100, (step - 1) * 25))
})

// API Loaders & Action Handlers
async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [settlementRes, payoutRes, beneficiaryRes, exceptionsRes] = await Promise.allSettled([
      financeApi.getSettlements(),
      financeApi.getPayouts(),
      financeApi.getBeneficiaries(),
      financeApi.getExceptions(),
    ])

    if (settlementRes.status === 'fulfilled' && settlementRes.value?.data?.length) {
      // Map API settlements
      settlementRecords.value = settlementRes.value.data.map((item: any, idx: number) => ({
        id: item.id || idx + 1,
        date: item.period_end ? new Date(item.period_end).toLocaleDateString('vi-VN') : `Kỳ #${item.id}`,
        gross_amount: item.gross_amount || 0,
        platform_fee: item.platform_fee || Math.round((item.gross_amount || 0) * 0.03),
        gateway_fee: item.gateway_fee || Math.round((item.gross_amount || 0) * 0.02),
        net_amount: item.net_amount || (item.gross_amount ? item.gross_amount * 0.95 : 0),
        status: item.status || 'settled',
        notes: item.notes || `Đối soát kỳ ${item.id}`,
      }))
    }

    if (payoutRes.status === 'fulfilled' && payoutRes.value?.data?.length) {
      payouts.value = payoutRes.value.data.map((p: any) => ({
        id: p.id,
        code: p.reference || `WD-${String(p.id).padStart(4, '0')}`,
        created_at: p.created_at ? new Date(p.created_at).toLocaleDateString('vi-VN') : '—',
        completed_at: p.completed_at ? new Date(p.completed_at).toLocaleDateString('vi-VN') : null,
        amount: p.amount || 0,
        status: p.status || 'processing',
        beneficiary_name: p.beneficiary?.account_holder || beneficiary.value.account_holder,
        beneficiary_bank: p.beneficiary?.bank_code || beneficiary.value.bank_name,
        beneficiary_account: p.beneficiary?.account_number || beneficiary.value.account_number,
        current_step: p.status === 'paid' ? 5 : p.status === 'processing' || p.status === 'submitted' ? 4 : p.status === 'approved' ? 2 : 1,
        step_description: p.status === 'paid' ? 'Đã hoàn tất thanh toán' : 'Đang xử lý chi trả',
      }))
    }

    if (beneficiaryRes.status === 'fulfilled' && beneficiaryRes.value?.length) {
      const b = beneficiaryRes.value[0]
      if (b) {
        const matchingBank = popularBanks.find((item) => item.code === b.bank_code)
        const fullBankName = matchingBank ? matchingBank.name : (b.bank_code || 'Vietcombank (VCB)')
        beneficiary.value = {
          id: b.id,
          provider: b.provider || 'payos',
          bank_code: b.bank_code || 'VCB',
          bank_name: fullBankName,
          account_number: b.account_number,
          account_holder: b.account_holder || 'Tài khoản thụ hưởng',
          tax_id: b.tax_id || '',
          is_verified: true,
          updated_at: new Date().toLocaleDateString('vi-VN'),
        }
        beneficiaryForm.value = {
          bank_code: b.bank_code || 'VCB',
          bank_name: fullBankName,
          account_number: b.account_number || '',
          account_holder: b.account_holder || '',
          tax_id: b.tax_id || '',
        }
      }
    }

    if (exceptionsRes.status === 'fulfilled' && exceptionsRes.value?.data?.length) {
      exceptions.value = exceptionsRes.value.data.map((e: any) => ({
        id: e.id,
        code: `EXC-${e.id}`,
        type: e.type || 'discrepancy',
        description: e.description || e.reason || 'Sai lệch số liệu',
        amount: e.amount || 0,
        status: e.status || 'unresolved',
        created_at: e.created_at ? new Date(e.created_at).toLocaleString('vi-VN') : '—',
        resolution: e.resolution,
      }))
    }
  } catch (err) {
    // Graceful fallback to mock data on error / unauthenticated mock
    console.info('Using local financial mock data:', err)
  } finally {
    loading.value = false
  }
}

async function handleCreatePayout() {
  if (payoutForm.value.amount < 500000) {
    error.value = 'Hạn mức rút tiền tối thiểu là 500.000 ₫.'
    return
  }
  if (payoutForm.value.amount > availableWithdrawBalance.value && availableWithdrawBalance.value > 0) {
    error.value = `Số tiền rút vượt quá số dư khả dụng (${formatCurrency(availableWithdrawBalance.value)}).`
    return
  }

  saving.value = true
  error.value = ''
  try {
    await financeApi.createPayout({
      settlement_id: payoutForm.value.settlement_id || 1,
      beneficiary_id: beneficiary.value.id || 1,
      amount: payoutForm.value.amount,
      note: payoutForm.value.note,
    })
    notice.value = `Đã tạo lệnh rút tiền ${formatCurrency(payoutForm.value.amount)} thành công.`
  } catch {
    // Demo simulation
    const newId = payouts.value.length + 1
    const newCode = `WD-${new Date().getMonth() + 1}${String(new Date().getFullYear()).slice(2)}-0${newId}`
    payouts.value.unshift({
      id: newId,
      code: newCode,
      created_at: new Date().toLocaleDateString('vi-VN'),
      completed_at: null,
      amount: payoutForm.value.amount,
      status: 'processing',
      beneficiary_name: beneficiary.value.account_holder,
      beneficiary_bank: beneficiary.value.bank_name,
      beneficiary_account: beneficiary.value.account_number,
      current_step: 3,
      step_description: 'Đã gửi yêu cầu rút tiền tới ngân hàng',
    })
    activePayoutId.value = newId
    notice.value = `[Demo] Đã gửi lệnh rút tiền ${formatCurrency(payoutForm.value.amount)} thành công.`
  } finally {
    saving.value = false
    showCreatePayoutModal.value = false
  }
}

async function handleUpdateBeneficiary() {
  if (!beneficiaryForm.value.account_number || !beneficiaryForm.value.account_holder) {
    error.value = 'Vui lòng nhập đầy đủ số tài khoản và tên chủ tài khoản.'
    return
  }

  saving.value = true
  error.value = ''
  try {
    const selectedBank = popularBanks.find((b) => b.code === beneficiaryForm.value.bank_code)
    const bankName = selectedBank ? selectedBank.name.split(' (')[0] : beneficiaryForm.value.bank_code

    await financeApi.createBeneficiary({
      bank_code: beneficiaryForm.value.bank_code,
      account_number: beneficiaryForm.value.account_number,
      account_holder: beneficiaryForm.value.account_holder.toUpperCase(),
      tax_id: beneficiaryForm.value.tax_id,
    })

    beneficiary.value = {
      ...beneficiary.value,
      bank_code: beneficiaryForm.value.bank_code,
      bank_name: `${bankName} (${beneficiaryForm.value.bank_code})`,
      account_number: beneficiaryForm.value.account_number,
      account_holder: beneficiaryForm.value.account_holder.toUpperCase(),
      tax_id: beneficiaryForm.value.tax_id,
      updated_at: new Date().toLocaleDateString('vi-VN'),
    }
    notice.value = 'Đã cập nhật thông tin tài khoản thụ hưởng thành công.'
  } catch {
    // Demo fallback
    const selectedBank = popularBanks.find((b) => b.code === beneficiaryForm.value.bank_code)
    const bankName = selectedBank ? selectedBank.name.split(' (')[0] : beneficiaryForm.value.bank_code
    beneficiary.value = {
      ...beneficiary.value,
      bank_code: beneficiaryForm.value.bank_code,
      bank_name: `${bankName} (${beneficiaryForm.value.bank_code})`,
      account_number: beneficiaryForm.value.account_number,
      account_holder: beneficiaryForm.value.account_holder.toUpperCase(),
      tax_id: beneficiaryForm.value.tax_id,
      updated_at: new Date().toLocaleDateString('vi-VN'),
    }
    notice.value = '[Demo] Cập nhật tài khoản thụ hưởng thành công.'
  } finally {
    saving.value = false
    showEditBeneficiaryModal.value = false
  }
}

async function handleCreateSnapshot() {
  saving.value = true
  error.value = ''
  try {
    await financeApi.snapshotSettlement({
      period_start: snapshotForm.value.period_start,
      period_end: snapshotForm.value.period_end,
    })
    notice.value = 'Đã chốt kỳ đối soát D+2 thành công.'
    await loadData()
  } catch {
    // Demo fallback
    const newSettlement: SettlementRecord = {
      id: settlementRecords.value.length + 1,
      date: new Date(snapshotForm.value.period_end || Date.now()).toLocaleDateString('vi-VN'),
      gross_amount: 16500000,
      platform_fee: 495000,
      gateway_fee: 330000,
      net_amount: 15675000,
      status: 'settled',
      notes: `Kỳ đối soát thủ công ${snapshotForm.value.period_start} đến ${snapshotForm.value.period_end}`,
    }
    settlementRecords.value.unshift(newSettlement)
    notice.value = '[Demo] Đã chốt snapshot đối soát D+2 thành công.'
  } finally {
    saving.value = false
    showSnapshotModal.value = false
  }
}

async function handleApprovePayout(payout: PayoutRecord) {
  saving.value = true
  try {
    await financeApi.approvePayout(payout.id)
    payout.status = 'approved'
    payout.current_step = 3
    payout.step_description = 'Đã duyệt lệnh rút tiền, chuẩn bị chuyển khoản'
    notice.value = `Đã duyệt lệnh rút ${payout.code}.`
  } catch {
    payout.status = 'approved'
    payout.current_step = 3
    payout.step_description = 'Đã duyệt lệnh rút tiền, chuẩn bị chuyển khoản'
    notice.value = `[Demo] Đã duyệt lệnh rút ${payout.code}.`
  } finally {
    saving.value = false
  }
}

async function handleSyncPayoutStatus(payout: PayoutRecord) {
  saving.value = true
  try {
    await financeApi.queryPayoutStatus(payout.id)
    payout.status = 'paid'
    payout.current_step = 5
    payout.completed_at = new Date().toLocaleDateString('vi-VN')
    payout.step_description = 'Đã hoàn tất thanh toán thành công'
    notice.value = `Đồng bộ trạng thái ${payout.code}: Hoàn tất chi trả.`
  } catch {
    payout.status = 'paid'
    payout.current_step = 5
    payout.completed_at = new Date().toLocaleDateString('vi-VN')
    payout.step_description = 'Đã hoàn tất thanh toán thành công'
    notice.value = `[Demo] Đồng bộ trạng thái ${payout.code}: Hoàn tất chi trả.`
  } finally {
    saving.value = false
  }
}

function openPayoutDetail(payout: PayoutRecord) {
  selectedPayoutDetail.value = payout
  activePayoutId.value = payout.id
  showPayoutDetailModal.value = true
}

function selectQuickAmount(val: number) {
  payoutForm.value.amount = val
}

function setFullWithdrawal() {
  payoutForm.value.amount = availableWithdrawBalance.value || 43225000
}

function selectInspectedPayout(p: PayoutRecord) {
  activePayoutId.value = p.id
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="aura-payouts-container">
    <!-- Notifications & Toast -->
    <transition name="fade">
      <div v-if="notice" class="aura-toast toast-success">
        <span class="material-symbols-outlined icon-20">check_circle</span>
        <span class="toast-text">{{ notice }}</span>
        <button type="button" class="toast-close-btn" @click="notice = ''">
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="aura-toast toast-error">
        <span class="material-symbols-outlined icon-20">error</span>
        <span class="toast-text">{{ error }}</span>
        <button type="button" class="toast-close-btn" @click="error = ''">
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>
    </transition>

    <!-- Header Section -->
    <header class="payouts-header">
      <div class="header-titles">
        <h1 class="page-title">Báo cáo Doanh thu & Rút tiền</h1>
        <p class="page-subtitle">Quản lý doanh thu, đối soát và lịch sử rút tiền</p>
      </div>

      <div class="header-actions">
        <button
          type="button"
          class="btn-secondary-action"
          :disabled="loading"
          title="Làm mới số liệu đối soát"
          @click="loadData"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-anim': loading }">refresh</span>
          <span>Làm mới</span>
        </button>

        <button
          type="button"
          class="btn-secondary-action"
          title="Chốt đối soát D+2 thủ công"
          @click="showSnapshotModal = true"
        >
          <span class="material-symbols-outlined icon-18">add_chart</span>
          <span>Chốt đối soát</span>
        </button>

        <button
          type="button"
          class="btn-primary-action"
          title="Tạo lệnh rút tiền mới"
          @click="showCreatePayoutModal = true"
        >
          <span class="material-symbols-outlined icon-20">add</span>
          <span>Tạo lệnh rút tiền</span>
        </button>
      </div>
    </header>

    <!-- Main 12-Column Grid Layout -->
    <div class="payouts-grid">
      <!-- Left Column (8 cols): Settlement Overview & Payouts History -->
      <div class="grid-left-col">
        <!-- 1. Settlement Overview Card (D+2) -->
        <section class="aura-card settlement-card">
          <div class="card-glow-bg"></div>

          <div class="card-header-row">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary icon-22">payments</span>
              <h2 class="card-title">Tổng quan thanh toán (D+2)</h2>
            </div>

            <div class="flex items-center gap-2">
              <select
                v-model="timeFilter"
                class="time-select-dropdown"
                aria-label="Chọn khoảng thời gian đối soát"
              >
                <option value="today">Hôm nay</option>
                <option value="week">Tuần này</option>
                <option value="month">Tháng này</option>
                <option value="all">Tất cả</option>
              </select>
            </div>
          </div>

          <!-- 3 KPI Metric Cards -->
          <div class="metrics-grid-row">
            <!-- Gross Revenue -->
            <div class="metric-box gross-box">
              <span class="metric-label">TỔNG DOANH THU</span>
              <span class="metric-val text-ink">{{ formatCurrency(grossTotal || 45500000) }}</span>
              <span class="metric-sub text-muted">Doanh thu gộp đặt sân</span>
            </div>

            <!-- Fees & Commission -->
            <div class="metric-box fee-box">
              <span class="metric-label text-error">PHÍ & HOA HỒNG</span>
              <span class="metric-val text-error">-{{ formatCurrency(feeTotal || 2275000) }}</span>
              <span class="metric-sub text-muted">3% Platform + 2% Cổng TT</span>
            </div>

            <!-- Net Settled Revenue -->
            <div class="metric-box net-box">
              <span class="metric-label text-primary">THỰC NHẬN</span>
              <span class="metric-val text-primary">{{ formatCurrency(netTotal || 43225000) }}</span>
              <span class="metric-sub text-primary font-medium">Khả dụng rút: {{ formatCurrency(availableWithdrawBalance || 43225000) }}</span>
            </div>
          </div>

          <!-- Daily Settlement Table -->
          <div class="table-container">
            <table class="aura-data-table">
              <thead>
                <tr>
                  <th>Ngày giao dịch</th>
                  <th class="text-right">Doanh thu gộp</th>
                  <th class="text-right">Hoa hồng (3%)</th>
                  <th class="text-right">Phí cổng TT (2%)</th>
                  <th class="text-right">Thực nhận</th>
                  <th class="text-center">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="s in filteredSettlements"
                  :key="s.id"
                  class="table-data-row"
                >
                  <td class="cell-date font-medium text-ink">
                    <span class="hover:text-primary transition-colors cursor-pointer">{{ s.date }}</span>
                  </td>
                  <td class="text-right font-medium text-ink">
                    {{ formatCurrency(s.gross_amount) }}
                  </td>
                  <td class="text-right text-muted">
                    -{{ formatCurrency(s.platform_fee) }}
                  </td>
                  <td class="text-right text-muted">
                    -{{ formatCurrency(s.gateway_fee) }}
                  </td>
                  <td class="text-right font-bold text-primary">
                    {{ formatCurrency(s.net_amount) }}
                  </td>
                  <td class="text-center">
                    <span
                      class="status-pill"
                      :class="`pill-${s.status}`"
                    >
                      <span v-if="s.status === 'processing'" class="dot-pulse-mini"></span>
                      {{ s.status === 'settled' ? 'Đã đối soát' : s.status === 'pending' ? 'Chờ đối soát' : 'Đang xử lý' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 2. Payout History & Interactive Stepper -->
        <section class="aura-card payout-history-card">
          <div class="card-header-row">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary icon-22">account_balance_wallet</span>
              <h2 class="card-title">Lịch sử rút tiền</h2>
            </div>

            <span v-if="currentInspectedPayout" class="text-xs font-mono text-muted">
              Đang xem tiến trình: <strong class="text-primary font-bold">{{ currentInspectedPayout.code }}</strong>
            </span>
          </div>

          <!-- Interactive 5-step Visual Stepper -->
          <div class="stepper-wrapper">
            <div class="stepper-track-bg">
              <div
                class="stepper-track-fill"
                :style="{ width: `${stepperProgressPercent}%` }"
              ></div>
            </div>

            <div class="stepper-nodes">
              <!-- Step 1: Tạo lệnh -->
              <div
                class="step-node"
                :class="{ active: (currentInspectedPayout?.current_step || 1) >= 1 }"
                title="Lệnh rút tiền được tạo trong hệ thống"
              >
                <div class="step-circle">
                  <span class="material-symbols-outlined icon-16">draft</span>
                </div>
                <span class="step-label">Tạo lệnh</span>
              </div>

              <!-- Step 2: Duyệt -->
              <div
                class="step-node"
                :class="{ active: (currentInspectedPayout?.current_step || 1) >= 2 }"
                title="Quản trị viên hoặc hệ thống kiểm duyệt giao dịch"
              >
                <div class="step-circle">
                  <span class="material-symbols-outlined icon-16">fact_check</span>
                </div>
                <span class="step-label">Duyệt</span>
              </div>

              <!-- Step 3: Gửi yêu cầu -->
              <div
                class="step-node"
                :class="{ active: (currentInspectedPayout?.current_step || 1) >= 3 }"
                title="Lệnh giải ngân chuyển tiếp qua cổng PayOS / Ngân hàng"
              >
                <div class="step-circle">
                  <span class="material-symbols-outlined icon-16">send</span>
                </div>
                <span class="step-label">Gửi yêu cầu</span>
              </div>

              <!-- Step 4: Đang chi trả -->
              <div
                class="step-node"
                :class="{
                  active: (currentInspectedPayout?.current_step || 1) >= 4,
                  current: (currentInspectedPayout?.current_step || 1) === 4
                }"
                title="Hệ thống đang thực hiện chuyển khoản 24/7"
              >
                <div class="step-circle">
                  <span class="material-symbols-outlined icon-16">payments</span>
                </div>
                <span class="step-label">Đang chi trả</span>
              </div>

              <!-- Step 5: Hoàn tất -->
              <div
                class="step-node"
                :class="{
                  active: (currentInspectedPayout?.current_step || 1) >= 5,
                  completed: (currentInspectedPayout?.current_step || 1) >= 5
                }"
                title="Tiền đã về tài khoản ngân hàng thụ hưởng"
              >
                <div class="step-circle">
                  <span class="material-symbols-outlined icon-16">check_circle</span>
                </div>
                <span class="step-label">Hoàn tất</span>
              </div>
            </div>
          </div>

          <!-- Payout Records Table -->
          <div class="table-container mt-6">
            <table class="aura-data-table">
              <thead>
                <tr>
                  <th>Mã GD</th>
                  <th>Ngày tạo</th>
                  <th>Ngày hoàn tất</th>
                  <th class="text-right">Số tiền</th>
                  <th class="text-center">Trạng thái</th>
                  <th class="text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in payouts"
                  :key="p.id"
                  class="table-data-row cursor-pointer"
                  :class="{ 'row-selected': activePayoutId === p.id }"
                  @click="selectInspectedPayout(p)"
                >
                  <td class="font-mono text-sm text-primary font-bold">
                    {{ p.code }}
                  </td>
                  <td class="text-ink">
                    {{ p.created_at }}
                  </td>
                  <td class="text-muted">
                    {{ p.completed_at || '—' }}
                  </td>
                  <td class="text-right font-bold text-ink">
                    {{ formatCurrency(p.amount) }}
                  </td>
                  <td class="text-center">
                    <span
                      class="status-pill"
                      :class="p.status === 'paid' ? 'pill-settled' : 'pill-processing'"
                    >
                      <span v-if="p.status === 'processing'" class="dot-pulse-mini"></span>
                      {{ p.status === 'paid' ? 'Hoàn tất' : 'Đang xử lý' }}
                    </span>
                  </td>
                  <td class="text-right" @click.stop>
                    <div class="flex items-center justify-end gap-1">
                      <button
                        v-if="p.status === 'pending_approval'"
                        type="button"
                        class="btn-row-action btn-approve"
                        title="Duyệt lệnh rút tiền này"
                        @click="handleApprovePayout(p)"
                      >
                        <span class="material-symbols-outlined icon-14">check</span>
                        <span>Duyệt</span>
                      </button>

                      <button
                        v-if="p.status === 'processing'"
                        type="button"
                        class="btn-row-action btn-sync"
                        title="Đồng bộ trạng thái từ cổng thanh toán"
                        @click="handleSyncPayoutStatus(p)"
                      >
                        <span class="material-symbols-outlined icon-14">sync</span>
                        <span>Đồng bộ</span>
                      </button>

                      <button
                        type="button"
                        class="btn-row-action btn-view"
                        title="Xem chi tiết"
                        @click="openPayoutDetail(p)"
                      >
                        <span class="material-symbols-outlined icon-14">info</span>
                        <span>Chi tiết</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Right Column (4 cols): Beneficiary, Policy, Guide Banner & Exceptions -->
      <div class="grid-right-col">
        <!-- 1. Beneficiary Account Card -->
        <section class="aura-card beneficiary-card">
          <div class="card-header-row">
            <h3 class="card-title text-base">Tài khoản thụ hưởng</h3>
            <span class="kyc-badge">
              <span class="material-symbols-outlined icon-14">verified</span>
              <span>KYC VERIFIED</span>
            </span>
          </div>

          <div class="beneficiary-info-box">
            <div class="info-row">
              <span class="info-label">Ngân hàng</span>
              <span class="info-value">{{ beneficiary.bank_name }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Tên tài khoản</span>
              <span class="info-value uppercase">{{ beneficiary.account_holder }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Số tài khoản</span>
              <span class="info-value font-mono tracking-wider">{{ beneficiary.account_number }}</span>
            </div>
            <div v-if="beneficiary.tax_id" class="info-row">
              <span class="info-label">Mã số thuế</span>
              <span class="info-value font-mono">{{ beneficiary.tax_id }}</span>
            </div>
          </div>

          <button
            type="button"
            class="btn-update-beneficiary"
            title="Cập nhật tài khoản ngân hàng thụ hưởng"
            @click="showEditBeneficiaryModal = true"
          >
            <span class="material-symbols-outlined icon-16">edit</span>
            <span>Cập nhật thông tin</span>
          </button>
        </section>

        <!-- 2. Withdrawal Policy Card -->
        <section class="aura-card policy-card">
          <div class="policy-header">
            <span class="material-symbols-outlined text-primary icon-20">info</span>
            <h4 class="policy-title">Chính sách rút tiền</h4>
          </div>

          <ul class="policy-list">
            <li>Hạn mức rút tối thiểu: <strong>500.000 ₫</strong></li>
            <li>Thời gian xử lý: <strong>1-2 ngày làm việc</strong> (Hỗ trợ 24/7 liên ngân hàng).</li>
            <li>Doanh thu khả dụng là doanh thu đã qua chu kỳ đối soát tự động <strong>(D+2)</strong>.</li>
            <li>Phí rút tiền về tài khoản ngân hàng: <strong>Miễn phí</strong>.</li>
          </ul>
        </section>

        <!-- 3. Cash Flow Guide Banner -->
        <div class="guide-banner-card">
          <div class="guide-banner-bg"></div>
          <div class="guide-banner-overlay"></div>
          <div class="guide-banner-content">
            <span class="guide-eyebrow">HƯỚNG DẪN</span>
            <span class="guide-heading">Tối ưu hóa dòng tiền & Đối soát tự động</span>
            <p class="guide-desc">Khám phá cách thức tự động hóa doanh thu và phân tách dòng tiền ca trực.</p>
          </div>
        </div>

        <!-- 4. Reconciliation Exceptions (If any) -->
        <section v-if="exceptions.length" class="aura-card exceptions-card">
          <div class="card-header-row">
            <div class="flex items-center gap-1">
              <span class="material-symbols-outlined text-error icon-18">warning</span>
              <h4 class="card-title text-sm text-error">Sai lệch đối soát ({{ exceptions.length }})</h4>
            </div>
          </div>

          <div class="exceptions-list">
            <div v-for="exc in exceptions" :key="exc.id" class="exception-item">
              <div class="flex justify-between items-start">
                <span class="font-mono text-xs font-bold text-error">{{ exc.code }}</span>
                <span class="text-xs font-bold text-error">{{ formatCurrency(exc.amount) }}</span>
              </div>
              <p class="text-xs text-muted mt-1">{{ exc.description }}</p>
              <div class="flex justify-between items-center mt-2 text-[11px] text-muted">
                <span>{{ exc.created_at }}</span>
                <span class="font-semibold text-primary cursor-pointer hover:underline">Xử lý ngay →</span>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- MODAL 1: Create Payout -->
    <div v-if="showCreatePayoutModal" class="aura-modal-backdrop" @click.self="showCreatePayoutModal = false">
      <div class="aura-modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-2">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined text-primary icon-20">account_balance_wallet</span>
            </div>
            <div>
              <h3 class="modal-title">Tạo Lệnh Rút Tiền</h3>
              <p class="modal-subtitle">Rút doanh thu khả dụng về tài khoản thụ hưởng đã xác thực</p>
            </div>
          </div>
          <button type="button" class="btn-modal-close" @click="showCreatePayoutModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="handleCreatePayout">
          <!-- Balance Summary Banner -->
          <div class="payout-balance-banner">
            <span class="text-xs text-muted">Số dư khả dụng có thể rút (D+2):</span>
            <span class="font-bold text-lg text-primary">{{ formatCurrency(availableWithdrawBalance || 43225000) }}</span>
          </div>

          <!-- Amount Input -->
          <div class="form-group">
            <label class="form-label">Số tiền cần rút (₫) *</label>
            <div class="amount-input-wrap">
              <input
                v-model.number="payoutForm.amount"
                type="number"
                min="500000"
                step="100000"
                class="form-input text-lg font-bold"
                required
                placeholder="VD: 25000000"
              />
              <span class="amount-suffix">VND</span>
            </div>

            <!-- Quick Amount Chips -->
            <div class="quick-amounts-row">
              <button type="button" class="quick-chip" @click="selectQuickAmount(5000000)">5.000.000 ₫</button>
              <button type="button" class="quick-chip" @click="selectQuickAmount(10000000)">10.000.000 ₫</button>
              <button type="button" class="quick-chip" @click="selectQuickAmount(20000000)">20.000.000 ₫</button>
              <button type="button" class="quick-chip chip-all" @click="setFullWithdrawal">Rút tất cả</button>
            </div>
          </div>

          <!-- Beneficiary Target Box -->
          <div class="form-group">
            <label class="form-label">Tài khoản nhận tiền</label>
            <div class="beneficiary-preview-box">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-green icon-18">verified</span>
                <span class="font-bold text-sm text-ink">{{ beneficiary.bank_name }}</span>
              </div>
              <div class="text-xs text-muted mt-1">
                <span>STK: <strong class="font-mono text-ink">{{ beneficiary.account_number }}</strong></span>
                <span class="mx-1">·</span>
                <span>Chủ TK: <strong class="uppercase text-ink">{{ beneficiary.account_holder }}</strong></span>
              </div>
            </div>
          </div>

          <!-- Note -->
          <div class="form-group">
            <label class="form-label">Ghi chú (Tùy chọn)</label>
            <input
              v-model="payoutForm.note"
              type="text"
              class="form-input"
              placeholder="VD: Rút doanh thu tuần 2 tháng 10"
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn-modal-cancel"
              @click="showCreatePayoutModal = false"
            >
              Hủy
            </button>
            <button
              type="submit"
              class="btn-modal-submit"
              :disabled="saving || payoutForm.amount < 500000"
            >
              <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim">progress_activity</span>
              <span>Xác nhận rút tiền</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: Edit Beneficiary -->
    <div v-if="showEditBeneficiaryModal" class="aura-modal-backdrop" @click.self="showEditBeneficiaryModal = false">
      <div class="aura-modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-2">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined text-primary icon-20">account_balance</span>
            </div>
            <div>
              <h3 class="modal-title">Cập Nhật Tài Khoản Thụ Hưởng</h3>
              <p class="modal-subtitle">Thông tin ngân hàng nhận tiền tự động khi giải ngân</p>
            </div>
          </div>
          <button type="button" class="btn-modal-close" @click="showEditBeneficiaryModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="handleUpdateBeneficiary">
          <div class="form-group">
            <label class="form-label">Ngân hàng thụ hưởng *</label>
            <select v-model="beneficiaryForm.bank_code" class="form-input" required>
              <option v-for="bank in popularBanks" :key="bank.code" :value="bank.code">
                {{ bank.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Số tài khoản ngân hàng *</label>
            <input
              v-model="beneficiaryForm.account_number"
              type="text"
              class="form-input font-mono"
              required
              placeholder="VD: 0123456789"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Tên chủ tài khoản (In hoa không dấu) *</label>
            <input
              v-model="beneficiaryForm.account_holder"
              type="text"
              class="form-input uppercase"
              required
              placeholder="VD: CONG TY TNHH SMASH"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Mã số thuế / MST (Tùy chọn)</label>
            <input
              v-model="beneficiaryForm.tax_id"
              type="text"
              class="form-input font-mono"
              placeholder="VD: 0316888999"
            />
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn-modal-cancel"
              @click="showEditBeneficiaryModal = false"
            >
              Hủy
            </button>
            <button
              type="submit"
              class="btn-modal-submit"
              :disabled="saving"
            >
              <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim">progress_activity</span>
              <span>Lưu thông tin ngân hàng</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 3: Snapshot Settlement -->
    <div v-if="showSnapshotModal" class="aura-modal-backdrop" @click.self="showSnapshotModal = false">
      <div class="aura-modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-2">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined text-primary icon-20">add_chart</span>
            </div>
            <div>
              <h3 class="modal-title">Chốt Kỳ Đối Soát D+2</h3>
              <p class="modal-subtitle">Tổng hợp và khóa sổ các giao dịch thanh toán trong kỳ</p>
            </div>
          </div>
          <button type="button" class="btn-modal-close" @click="showSnapshotModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="modal-form" @submit.prevent="handleCreateSnapshot">
          <div class="form-group">
            <label class="form-label">Từ ngày</label>
            <input v-model="snapshotForm.period_start" type="date" class="form-input" required />
          </div>

          <div class="form-group">
            <label class="form-label">Đến ngày</label>
            <input v-model="snapshotForm.period_end" type="date" class="form-input" required />
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" @click="showSnapshotModal = false">
              Hủy
            </button>
            <button type="submit" class="btn-modal-submit" :disabled="saving">
              <span v-if="saving" class="material-symbols-outlined icon-16 spin-anim">progress_activity</span>
              <span>Tạo snapshot đối soát</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 4: Payout Detail Modal -->
    <div v-if="showPayoutDetailModal && selectedPayoutDetail" class="aura-modal-backdrop" @click.self="showPayoutDetailModal = false">
      <div class="aura-modal-card">
        <div class="modal-header">
          <div class="flex items-center gap-2">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined text-primary icon-20">receipt_long</span>
            </div>
            <div>
              <h3 class="modal-title">Chi Tiết Lệnh Rút: {{ selectedPayoutDetail.code }}</h3>
              <p class="modal-subtitle">Trạng thái xử lý và thông tin chuyển khoản</p>
            </div>
          </div>
          <button type="button" class="btn-modal-close" @click="showPayoutDetailModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="payout-detail-body space-y-3">
          <div class="info-row">
            <span class="info-label">Số tiền rút</span>
            <span class="info-value font-bold text-primary text-base">{{ formatCurrency(selectedPayoutDetail.amount) }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Trạng thái</span>
            <span class="status-pill" :class="selectedPayoutDetail.status === 'paid' ? 'pill-settled' : 'pill-processing'">
              {{ selectedPayoutDetail.status === 'paid' ? 'Hoàn tất' : 'Đang xử lý' }}
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Ngày tạo lệnh</span>
            <span class="info-value">{{ selectedPayoutDetail.created_at }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Ngày hoàn tất</span>
            <span class="info-value">{{ selectedPayoutDetail.completed_at || 'Đang xử lý' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Ngân hàng thụ hưởng</span>
            <span class="info-value">{{ selectedPayoutDetail.beneficiary_bank }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Tên thụ hưởng</span>
            <span class="info-value uppercase">{{ selectedPayoutDetail.beneficiary_name }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Số tài khoản</span>
            <span class="info-value font-mono">{{ selectedPayoutDetail.beneficiary_account }}</span>
          </div>
          <div v-if="selectedPayoutDetail.tx_hash" class="info-row">
            <span class="info-label">Mã giao dịch (TxID)</span>
            <span class="info-value font-mono text-xs">{{ selectedPayoutDetail.tx_hash }}</span>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-modal-submit w-full" @click="showPayoutDetailModal = false">
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.aura-payouts-container {
  padding: 24px 32px 48px;
  background: var(--bg, #f8f9fa);
  min-height: calc(100vh - 78px);
  color: var(--ink, #191c1d);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

:global(html.dark) .aura-payouts-container {
  background: #121316;
  color: #f1f3f5;
}

/* Toast Notifications */
.aura-toast {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.toast-success {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #c8e6c9;
}

:global(html.dark) .toast-success {
  background: #1b3320;
  color: #81c784;
  border-color: #2e5934;
}

.toast-error {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

:global(html.dark) .toast-error {
  background: #381a1d;
  color: #ef9a9a;
  border-color: #5c282d;
}

.toast-text {
  flex: 1;
}

.toast-close-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  color: inherit;
  opacity: 0.7;
}

.toast-close-btn:hover {
  opacity: 1;
}

/* Header */
.payouts-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink, #191c1d);
  line-height: 1.2;
  margin: 0;
}

:global(html.dark) .page-title {
  color: #ffffff;
}

.page-subtitle {
  font-size: 14px;
  color: var(--muted, #6c757d);
  margin-top: 6px;
}

:global(html.dark) .page-subtitle {
  color: #94a3b8;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-secondary-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e0e4ea);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-secondary-action:hover {
  background: var(--surface-subtle, #f3f4f5);
}

:global(html.dark) .btn-secondary-action {
  background: #1e2025;
  border-color: #353942;
  color: #e2e8f0;
}

:global(html.dark) .btn-secondary-action:hover {
  background: #2b2e36;
}

.btn-primary-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
  transition: all 0.15s ease;
}

.btn-primary-action:hover {
  background: #62434b;
  transform: translateY(-1px);
}

:global(html.dark) .btn-primary-action {
  background: #f8b4c4;
  color: #201317;
  font-weight: 800;
  box-shadow: 0 4px 14px rgba(248, 180, 196, 0.3);
}

:global(html.dark) .btn-primary-action:hover {
  background: #ffd1dc;
}

/* 12-Column Grid Layout */
.payouts-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .payouts-grid {
    grid-template-columns: repeat(12, 1fr);
  }

  .grid-left-col {
    grid-column: span 8 / span 8;
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .grid-right-col {
    grid-column: span 4 / span 4;
    display: flex;
    flex-direction: column;
    gap: 24px;
  }
}

/* Aura Card */
.aura-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  padding: 24px;
  position: relative;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .aura-card {
  background: #1a1c22;
  border-color: #2e323b;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}

.card-glow-bg {
  position: absolute;
  top: -80px;
  right: -80px;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: rgba(120, 85, 94, 0.04);
  filter: blur(50px);
  pointer-events: none;
}

:global(html.dark) .card-glow-bg {
  background: rgba(248, 180, 196, 0.05);
}

.card-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 10px;
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .card-title {
  color: #ffffff;
}

.time-select-dropdown {
  background: var(--surface-subtle, #f3f4f5);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 600;
  outline: none;
  cursor: pointer;
}

:global(html.dark) .time-select-dropdown {
  background: #252830;
  border-color: #353942;
  color: #e2e8f0;
}

/* 3 KPI Metrics Row */
.metrics-grid-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

@media (max-width: 640px) {
  .metrics-grid-row {
    grid-template-columns: 1fr;
  }
}

.metric-box {
  padding: 16px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  transition: transform 0.15s ease;
}

.gross-box {
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .gross-box {
  background: #22252c;
  border-color: #333742;
}

.fee-box {
  background: rgba(186, 26, 26, 0.05);
  border: 1px solid rgba(186, 26, 26, 0.12);
}

:global(html.dark) .fee-box {
  background: rgba(239, 83, 80, 0.08);
  border-color: rgba(239, 83, 80, 0.2);
}

.net-box {
  background: rgba(120, 85, 94, 0.08);
  border: 1px solid rgba(120, 85, 94, 0.2);
}

:global(html.dark) .net-box {
  background: rgba(248, 180, 196, 0.1);
  border-color: rgba(248, 180, 196, 0.25);
}

.metric-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: var(--muted, #6c757d);
  text-transform: uppercase;
}

.metric-val {
  font-size: 20px;
  font-weight: 800;
  line-height: 1.2;
}

.metric-sub {
  font-size: 11px;
  margin-top: 2px;
}

/* Tables */
.table-container {
  overflow-x: auto;
}

.aura-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.aura-data-table th {
  padding: 12px 14px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted, #6c757d);
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .aura-data-table th {
  color: #94a3b8;
  border-color: #2e323b;
}

.aura-data-table td {
  padding: 14px 14px;
  border-bottom: 1px solid var(--border, #f0f2f5);
  vertical-align: middle;
}

:global(html.dark) .aura-data-table td {
  border-color: #252830;
}

.table-data-row:hover {
  background: var(--surface-subtle, #f8f9fa);
}

:global(html.dark) .table-data-row:hover {
  background: #22252c;
}

.row-selected {
  background: rgba(120, 85, 94, 0.06) !important;
}

:global(html.dark) .row-selected {
  background: rgba(248, 180, 196, 0.08) !important;
}

/* Status Pills */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.pill-settled {
  background: #e8f5e9;
  color: #2e7d32;
}

:global(html.dark) .pill-settled {
  background: #1b3320;
  color: #81c784;
}

.pill-pending {
  background: #fff3e0;
  color: #e65100;
}

:global(html.dark) .pill-pending {
  background: #3b2812;
  color: #ffb74d;
}

.pill-processing {
  background: rgba(120, 85, 94, 0.12);
  color: var(--primary, #78555e);
}

:global(html.dark) .pill-processing {
  background: rgba(248, 180, 196, 0.18);
  color: #f8b4c4;
}

.dot-pulse-mini {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  animation: pulseAnim 1.4s infinite ease-in-out;
}

@keyframes pulseAnim {
  0%, 100% { opacity: 0.3; transform: scale(0.8); }
  50% { opacity: 1; transform: scale(1.2); }
}

/* Row Action Buttons */
.btn-row-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-row-action:hover {
  background: var(--surface-subtle, #f3f4f5);
}

:global(html.dark) .btn-row-action {
  background: #252830;
  border-color: #353942;
  color: #e2e8f0;
}

.btn-approve {
  background: #e8f5e9;
  color: #2e7d32;
  border-color: #c8e6c9;
}

.btn-sync {
  background: rgba(120, 85, 94, 0.08);
  color: var(--primary, #78555e);
  border-color: rgba(120, 85, 94, 0.2);
}

/* Visual Stepper */
.stepper-wrapper {
  position: relative;
  margin: 32px 12px 16px;
}

.stepper-track-bg {
  position: absolute;
  top: 15px;
  left: 10px;
  right: 10px;
  height: 4px;
  background: var(--surface-subtle, #e0e4ea);
  border-radius: 9999px;
  z-index: 1;
}

:global(html.dark) .stepper-track-bg {
  background: #2e323b;
}

.stepper-track-fill {
  height: 100%;
  background: var(--primary, #78555e);
  border-radius: 9999px;
  transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(html.dark) .stepper-track-fill {
  background: #f8b4c4;
}

.stepper-nodes {
  position: relative;
  display: flex;
  justify-content: space-between;
  z-index: 2;
}

.step-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.step-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--surface, #ffffff);
  border: 2px solid var(--border, #cbd5e1);
  color: var(--muted, #94a3b8);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

:global(html.dark) .step-circle {
  background: #1a1c22;
  border-color: #353942;
  color: #64748b;
}

.step-node.active .step-circle {
  background: var(--primary, #78555e);
  border-color: var(--primary, #78555e);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.25);
}

:global(html.dark) .step-node.active .step-circle {
  background: #f8b4c4;
  border-color: #f8b4c4;
  color: #201317;
  box-shadow: 0 2px 8px rgba(248, 180, 196, 0.35);
}

.step-node.current .step-circle {
  border-width: 3px;
  animation: pulseCircle 1.6s infinite ease-in-out;
}

@keyframes pulseCircle {
  0%, 100% { box-shadow: 0 0 0 0 rgba(120, 85, 94, 0.4); }
  50% { box-shadow: 0 0 0 6px rgba(120, 85, 94, 0); }
}

.step-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  white-space: nowrap;
}

.step-node.active .step-label {
  color: var(--ink, #191c1d);
  font-weight: 700;
}

:global(html.dark) .step-node.active .step-label {
  color: #ffffff;
}

/* Beneficiary Card */
.kyc-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 9999px;
  background: #e8f5e9;
  color: #2e7d32;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
}

:global(html.dark) .kyc-badge {
  background: #1b3320;
  color: #81c784;
}

.beneficiary-info-box {
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

:global(html.dark) .beneficiary-info-box {
  background: #22252c;
  border-color: #333742;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
}

.info-label {
  color: var(--muted, #6c757d);
  font-size: 12px;
}

:global(html.dark) .info-label {
  color: #94a3b8;
}

.info-value {
  font-weight: 600;
  color: var(--ink, #191c1d);
}

:global(html.dark) .info-value {
  color: #ffffff;
}

.btn-update-beneficiary {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #cbd5e1);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-update-beneficiary:hover {
  background: var(--surface-subtle, #f3f4f5);
}

:global(html.dark) .btn-update-beneficiary {
  background: #252830;
  border-color: #353942;
  color: #e2e8f0;
}

/* Policy Card */
.policy-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.policy-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .policy-title {
  color: #ffffff;
}

.policy-list {
  padding-left: 18px;
  margin: 0;
  font-size: 12px;
  color: var(--muted, #6c757d);
  line-height: 1.6;
}

:global(html.dark) .policy-list {
  color: #cbd5e1;
}

.policy-list strong {
  color: var(--ink, #191c1d);
}

:global(html.dark) .policy-list strong {
  color: #ffffff;
}

/* Guide Banner */
.guide-banner-card {
  height: 190px;
  border-radius: 16px;
  overflow: hidden;
  position: relative;
  border: 1px solid var(--border, #e0e4ea);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  cursor: pointer;
}

.guide-banner-bg {
  width: 100%;
  height: 100%;
  background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDQlJyueyPW5rwZ4bcvYNp9Rn8gDPUsTOM295qsvknN8LDBvTpbv7SNA3cR96_6Pt24k7EviuFO7s9a6obtci3giKnYvnPPBCx50W-v9MBRn8XUMiElINDsNjt8TpsTd8WnKXtrV0fos5EH23_7XRmT7bgJw7BiQdTV__CrZPOhBWpUwNrKchag4DD223Aue7Sq0AUGGxd90aR7iypA2Bol2Hbg6Jke2BXywF1Pb7Ip_GN7gIGxRKvw');
  background-size: cover;
  background-position: center;
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.guide-banner-card:hover .guide-banner-bg {
  transform: scale(1.06);
}

.guide-banner-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(25, 28, 29, 0.92) 0%, rgba(25, 28, 29, 0.4) 60%, transparent 100%);
}

.guide-banner-content {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 16px 20px;
  color: #ffffff;
}

.guide-eyebrow {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #f8b4c4;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.guide-heading {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.3;
}

.guide-desc {
  font-size: 11px;
  opacity: 0.85;
  margin-top: 4px;
}

/* Exceptions */
.exceptions-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.exception-item {
  padding: 12px;
  border-radius: 10px;
  background: rgba(186, 26, 26, 0.05);
  border: 1px solid rgba(186, 26, 26, 0.15);
}

/* Modals */
.aura-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.aura-modal-card {
  width: 100%;
  max-width: 500px;
  background: var(--surface, #ffffff);
  border-radius: 20px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
  padding: 24px;
  max-height: 90vh;
  overflow-y: auto;
  border: 1px solid var(--border, #e0e4ea);
  animation: modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

:global(html.dark) .aura-modal-card {
  background: #1e2025;
  border-color: #353942;
  color: #ffffff;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.modal-icon-badge {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(120, 85, 94, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .modal-icon-badge {
  background: rgba(248, 180, 196, 0.15);
}

.modal-title {
  font-size: 17px;
  font-weight: 800;
  margin: 0;
}

.modal-subtitle {
  font-size: 12px;
  color: var(--muted, #6c757d);
  margin-top: 2px;
}

:global(html.dark) .modal-subtitle {
  color: #94a3b8;
}

.btn-modal-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #6c757d);
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.payout-balance-banner {
  background: rgba(120, 85, 94, 0.08);
  border: 1px solid rgba(120, 85, 94, 0.18);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

:global(html.dark) .payout-balance-banner {
  background: rgba(248, 180, 196, 0.12);
  border-color: rgba(248, 180, 196, 0.25);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

:global(html.dark) .form-label {
  color: #e2e8f0;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  font-size: 14px;
  outline: none;
}

.form-input:focus {
  border-color: var(--primary, #78555e);
}

:global(html.dark) .form-input {
  background: #252830;
  border-color: #353942;
  color: #ffffff;
}

.amount-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.amount-suffix {
  position: absolute;
  right: 14px;
  font-size: 12px;
  font-weight: 700;
  color: var(--muted, #6c757d);
}

.quick-amounts-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 8px;
}

.quick-chip {
  padding: 5px 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-subtle, #f3f4f5);
  border: 1px solid var(--border, #e0e4ea);
  color: var(--ink, #191c1d);
  cursor: pointer;
}

.quick-chip:hover {
  background: rgba(120, 85, 94, 0.1);
  border-color: var(--primary, #78555e);
}

.chip-all {
  background: rgba(120, 85, 94, 0.12);
  color: var(--primary, #78555e);
  font-weight: 700;
}

:global(html.dark) .quick-chip {
  background: #252830;
  border-color: #353942;
  color: #e2e8f0;
}

.beneficiary-preview-box {
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 10px;
  padding: 12px;
}

:global(html.dark) .beneficiary-preview-box {
  background: #252830;
  border-color: #353942;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 8px;
}

.btn-modal-cancel {
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  background: transparent;
  border: 1px solid var(--border, #cbd5e1);
  color: var(--ink, #191c1d);
  cursor: pointer;
}

:global(html.dark) .btn-modal-cancel {
  color: #cbd5e1;
  border-color: #353942;
}

.btn-modal-submit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  background: var(--primary, #78555e);
  color: #ffffff;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
}

.btn-modal-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

:global(html.dark) .btn-modal-submit {
  background: #f8b4c4;
  color: #201317;
}

/* Utilities */
.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
