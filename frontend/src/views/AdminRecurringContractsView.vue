<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, apiV2 } from '@/lib/api'

interface Invoice {
  id: number
  period_name: string
  period_start: string
  period_end: string
  due_date: string
  amount: number
  status: 'paid' | 'pending' | 'overdue'
  paid_at?: string
  payment_reference?: string
}

interface Contract {
  id: string
  code: string
  customer_name: string
  customer_phone: string
  customer_type: 'VIP Gold' | 'Doanh nghiệp' | 'CLB' | 'Khách quen' | 'Thành viên'
  avatar_color: string
  venue_name: string
  court_names: string[]
  days_of_week: string[]
  time_slot: string
  start_date: string
  end_date: string
  duration_months: number
  total_amount: number
  deposit_amount: number
  cycle_type: 'monthly' | 'quarterly' | 'full'
  current_cycle_name: string
  current_cycle_status: 'paid' | 'pending' | 'overdue'
  current_cycle_due: string
  current_cycle_amount: number
  status: 'active' | 'pending_payment' | 'overdue' | 'expiring_soon' | 'paused' | 'completed'
  notes?: string
  invoices: Invoice[]
  reschedule_quota_left: number
}

// Initial mock data based on Stitch screen specification
const initialContracts: Contract[] = [
  {
    id: '1',
    code: 'HĐ-DK-2026-089',
    customer_name: 'CLB Cầu Lông Passion',
    customer_phone: '0988.123.456',
    customer_type: 'CLB',
    avatar_color: '#78555e',
    venue_name: 'Aura Cầu Giấy (Cụm A)',
    court_names: ['Sân 03', 'Sân 04'],
    days_of_week: ['Thứ 2', 'Thứ 4', 'Thứ 6'],
    time_slot: '18:00 - 20:00',
    start_date: '2026-01-01',
    end_date: '2026-06-30',
    duration_months: 6,
    total_amount: 28800000,
    deposit_amount: 5000000,
    cycle_type: 'monthly',
    current_cycle_name: 'Kỳ tháng 03/2026',
    current_cycle_status: 'paid',
    current_cycle_due: '2026-02-28',
    current_cycle_amount: 4800000,
    status: 'active',
    reschedule_quota_left: 2,
    notes: 'Khách yêu cầu cố định 2 sân liền kề mỗi tối 2-4-6',
    invoices: [
      { id: 101, period_name: 'Kỳ tháng 01/2026', period_start: '2026-01-01', period_end: '2026-01-31', due_date: '2025-12-28', amount: 4800000, status: 'paid', paid_at: '2025-12-27', payment_reference: 'SEPAY-882910' },
      { id: 102, period_name: 'Kỳ tháng 02/2026', period_start: '2026-02-01', period_end: '2026-02-28', due_date: '2026-01-28', amount: 4800000, status: 'paid', paid_at: '2026-01-26', payment_reference: 'STRIPE-990142' },
      { id: 103, period_name: 'Kỳ tháng 03/2026', period_start: '2026-03-01', period_end: '2026-03-31', due_date: '2026-02-28', amount: 4800000, status: 'paid', paid_at: '2026-02-25', payment_reference: 'SEPAY-901234' },
      { id: 104, period_name: 'Kỳ tháng 04/2026', period_start: '2026-04-01', period_end: '2026-04-30', due_date: '2026-03-28', amount: 4800000, status: 'pending' },
    ],
  },
  {
    id: '2',
    code: 'HĐ-DK-2026-104',
    customer_name: 'Công ty FPT Software (Mr. Nam)',
    customer_phone: '0912.888.999',
    customer_type: 'Doanh nghiệp',
    avatar_color: '#2563eb',
    venue_name: 'Aura Thanh Xuân (Cụm B)',
    court_names: ['Sân 01', 'Sân 02'],
    days_of_week: ['Thứ 3', 'Thứ 5'],
    time_slot: '19:00 - 21:00',
    start_date: '2026-02-15',
    end_date: '2026-08-15',
    duration_months: 6,
    total_amount: 38400000,
    deposit_amount: 6000000,
    cycle_type: 'monthly',
    current_cycle_name: 'Kỳ tháng 03/2026',
    current_cycle_status: 'pending',
    current_cycle_due: '2026-02-28',
    current_cycle_amount: 6400000,
    status: 'pending_payment',
    reschedule_quota_left: 1,
    notes: 'Hợp đồng phúc lợi công ty, xuất hóa đơn VAT',
    invoices: [
      { id: 201, period_name: 'Kỳ tháng 02/2026 (Nửa kỳ)', period_start: '2026-02-15', period_end: '2026-02-28', due_date: '2026-02-10', amount: 3200000, status: 'paid', paid_at: '2026-02-12', payment_reference: 'VCB-FPT-0091' },
      { id: 202, period_name: 'Kỳ tháng 03/2026', period_start: '2026-03-01', period_end: '2026-03-31', due_date: '2026-02-28', amount: 6400000, status: 'pending' },
    ],
  },
  {
    id: '3',
    code: 'HĐ-DK-2026-072',
    customer_name: 'Nhóm Anh Tuấn Anh',
    customer_phone: '0903.456.789',
    customer_type: 'Khách quen',
    avatar_color: '#059669',
    venue_name: 'Aura Cầu Giấy (Cụm A)',
    court_names: ['Sân 05'],
    days_of_week: ['Thứ 7', 'Chủ Nhật'],
    time_slot: '07:00 - 09:00',
    start_date: '2025-12-01',
    end_date: '2026-03-01',
    duration_months: 3,
    total_amount: 12000000,
    deposit_amount: 2000000,
    cycle_type: 'monthly',
    current_cycle_name: 'Kỳ tháng 02/2026',
    current_cycle_status: 'paid',
    current_cycle_due: '2026-01-28',
    current_cycle_amount: 4000000,
    status: 'expiring_soon',
    reschedule_quota_left: 2,
    notes: 'Hợp đồng sắp kết thúc trong 5 ngày tới, liên hệ chào gia hạn',
    invoices: [
      { id: 301, period_name: 'Kỳ tháng 12/2025', period_start: '2025-12-01', period_end: '2025-12-31', due_date: '2025-11-28', amount: 4000000, status: 'paid', paid_at: '2025-11-26', payment_reference: 'MB-TUANANH-01' },
      { id: 302, period_name: 'Kỳ tháng 01/2026', period_start: '2026-01-01', period_end: '2026-01-31', due_date: '2025-12-28', amount: 4000000, status: 'paid', paid_at: '2025-12-27', payment_reference: 'MB-TUANANH-02' },
      { id: 303, period_name: 'Kỳ tháng 02/2026', period_start: '2026-02-01', period_end: '2026-02-28', due_date: '2026-01-28', amount: 4000000, status: 'paid', paid_at: '2026-01-26', payment_reference: 'MB-TUANANH-03' },
    ],
  },
  {
    id: '4',
    code: 'HĐ-DK-2026-118',
    customer_name: 'CLB Smash Pro (Chị Mai)',
    customer_phone: '0977.333.222',
    customer_type: 'VIP Gold',
    avatar_color: '#d97706',
    venue_name: 'Aura Cầu Giấy (Cụm A)',
    court_names: ['Sân 02'],
    days_of_week: ['Thứ 2', 'Thứ 4', 'Thứ 6'],
    time_slot: '17:00 - 19:00',
    start_date: '2026-02-01',
    end_date: '2026-07-31',
    duration_months: 6,
    total_amount: 14400000,
    deposit_amount: 2400000,
    cycle_type: 'monthly',
    current_cycle_name: 'Kỳ tháng 02/2026',
    current_cycle_status: 'overdue',
    current_cycle_due: '2026-02-20',
    current_cycle_amount: 2400000,
    status: 'overdue',
    reschedule_quota_left: 0,
    notes: 'Quá hạn 3 ngày thanh toán kỳ tháng 2, đã gửi 1 SMS nhắc nợ',
    invoices: [
      { id: 401, period_name: 'Kỳ tháng 02/2026', period_start: '2026-02-01', period_end: '2026-02-28', due_date: '2026-02-20', amount: 2400000, status: 'overdue' },
    ],
  },
  {
    id: '5',
    code: 'HĐ-DK-2026-065',
    customer_name: 'Anh Phan Quốc Bảo',
    customer_phone: '0934.567.890',
    customer_type: 'Thành viên',
    avatar_color: '#7c3aed',
    venue_name: 'Aura Nam Từ Liêm (Cụm C)',
    court_names: ['Sân 06'],
    days_of_week: ['Thứ 3', 'Thứ 5', 'Thứ 7'],
    time_slot: '20:00 - 22:00',
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    duration_months: 12,
    total_amount: 32000000,
    deposit_amount: 5000000,
    cycle_type: 'quarterly',
    current_cycle_name: 'Quý 1/2026 (Tháng 1-3)',
    current_cycle_status: 'paid',
    current_cycle_due: '2025-12-28',
    current_cycle_amount: 8000000,
    status: 'paused',
    reschedule_quota_left: 2,
    notes: 'Khách xin tạm dừng 2 tuần (15/02 - 01/03) do đi công tác, sẽ bù lịch sau',
    invoices: [
      { id: 501, period_name: 'Kỳ Quý 1/2026', period_start: '2026-01-01', period_end: '2026-03-31', due_date: '2025-12-28', amount: 8000000, status: 'paid', paid_at: '2025-12-25', payment_reference: 'TECHCOMBANK-991' },
    ],
  },
  {
    id: '6',
    code: 'HĐ-DK-2026-122',
    customer_name: 'Học Viện Cầu Lông Trẻ Aura',
    customer_phone: '0966.888.777',
    customer_type: 'CLB',
    avatar_color: '#e11d48',
    venue_name: 'Aura Cầu Giấy (Cụm A)',
    court_names: ['Sân 01', 'Sân 02', 'Sân 03'],
    days_of_week: ['Thứ 7', 'Chủ Nhật'],
    time_slot: '15:00 - 18:00',
    start_date: '2026-01-10',
    end_date: '2026-07-10',
    duration_months: 6,
    total_amount: 45000000,
    deposit_amount: 8000000,
    cycle_type: 'monthly',
    current_cycle_name: 'Kỳ tháng 03/2026',
    current_cycle_status: 'paid',
    current_cycle_due: '2026-02-28',
    current_cycle_amount: 7500000,
    status: 'active',
    reschedule_quota_left: 2,
    notes: 'Lớp đào tạo năng khiếu trẻ em, ưu tiên sân chất lượng cao',
    invoices: [
      { id: 601, period_name: 'Kỳ tháng 01/2026', period_start: '2026-01-10', period_end: '2026-01-31', due_date: '2026-01-08', amount: 5000000, status: 'paid', paid_at: '2026-01-07', payment_reference: 'SEPAY-129033' },
      { id: 602, period_name: 'Kỳ tháng 02/2026', period_start: '2026-02-01', period_end: '2026-02-28', due_date: '2026-01-28', amount: 7500000, status: 'paid', paid_at: '2026-01-26', payment_reference: 'SEPAY-130012' },
      { id: 603, period_name: 'Kỳ tháng 03/2026', period_start: '2026-03-01', period_end: '2026-03-31', due_date: '2026-02-28', amount: 7500000, status: 'paid', paid_at: '2026-02-24', payment_reference: 'SEPAY-131099' },
    ],
  },
]

const contracts = ref<Contract[]>(initialContracts)
const loading = ref(false)
const searchQuery = ref('')
const selectedTab = ref<'all' | 'active' | 'pending_payment' | 'overdue' | 'expiring_soon' | 'paused'>('all')
const selectedVenue = ref('all')
const selectedDayOfWeek = ref('all')
const selectedTimeSlot = ref('all')
const selectedCycle = ref('all')

// Toast message state
const toastMessage = ref<{ text: string; type: 'success' | 'info' | 'error' } | null>(null)
function showToast(text: string, type: 'success' | 'info' | 'error' = 'success') {
  toastMessage.value = { text, type }
  setTimeout(() => {
    toastMessage.value = null
  }, 4000)
}

// Modal States
const isCreateModalOpen = ref(false)
const isDetailModalOpen = ref(false)
const isRescheduleModalOpen = ref(false)
const isPolicyModalOpen = ref(false)
const activeContract = ref<Contract | null>(null)
const activePolicyTab = ref<'terms' | 'discounts' | 'deposit' | 'reschedule' | 'cancellation'>('terms')

const defaultPolicySettings = {
  minDurationMonths: 3,
  maxDurationMonths: 12,
  minDaysPerWeek: 2,
  autoRenew: true,
  renewNoticeDays: 15,
  discountTiers: [
    { id: 1, label: 'Gói 3 - 5 Tháng', from: 3, to: 5, discount: 5 },
    { id: 2, label: 'Gói 6 - 11 Tháng', from: 6, to: 11, discount: 10 },
    { id: 3, label: 'Gói 12+ Tháng', from: 12, to: 24, discount: 15 },
  ],
  enableFullPaymentBonus: true,
  fullPaymentExtraDiscount: 5,
  depositRatePercent: 20,
  paymentDueDay: 28,
  gracePeriodDays: 3,
  lockSlotAfterDays: 5,
  freeReschedulePerMonth: 2,
  minRescheduleNoticeHours: 24,
  rescheduleValidityDays: 14,
  cancellationNoticeDays: 15,
  fullDepositRefundOnNotice: true,
}

const policySettings = ref({ ...defaultPolicySettings })

onMounted(() => {
  const saved = localStorage.getItem('aura_recurring_policy_settings')
  if (saved) {
    try {
      policySettings.value = { ...defaultPolicySettings, ...JSON.parse(saved) }
    } catch {
      // fallback to default
    }
  }
})

function savePolicySettings() {
  localStorage.setItem('aura_recurring_policy_settings', JSON.stringify(policySettings.value))
  showToast('Đã lưu và áp dụng cấu hình chính sách định kỳ thành công!', 'success')
  isPolicyModalOpen.value = false
}

function resetPolicySettings() {
  policySettings.value = JSON.parse(JSON.stringify(defaultPolicySettings))
  localStorage.setItem('aura_recurring_policy_settings', JSON.stringify(policySettings.value))
  showToast('Đã khôi phục cấu hình chính sách về mặc định!', 'info')
}

function toYmd(d: Date = new Date()): string {
  return d.toISOString().split('T')[0] ?? ''
}

// New Contract Form State
const newContract = ref({
  customer_name: '',
  customer_phone: '',
  customer_type: 'CLB' as Contract['customer_type'],
  venue_name: 'Aura Cầu Giấy (Cụm A)',
  court_names: ['Sân 01'],
  days_of_week: ['Thứ 2', 'Thứ 4', 'Thứ 6'],
  time_slot: '18:00 - 20:00',
  start_date: toYmd(),
  duration_months: 6,
  cycle_type: 'monthly' as Contract['cycle_type'],
  price_per_month: 4800000,
  deposit_amount: 5000000,
  notes: '',
})

// Quick helper formatters
function formatVnd(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount).replace('₫', 'đ')
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return dateStr
  }
}

// KPI Statistics
const stats = computed(() => {
  const list = contracts.value
  const activeCount = list.filter((c) => c.status === 'active').length
  const totalMonthlyRevenue = list.reduce((sum, c) => sum + (c.cycle_type === 'monthly' ? c.current_cycle_amount : c.current_cycle_amount / 3), 0)
  const paidMonthlyRevenue = list
    .filter((c) => c.current_cycle_status === 'paid')
    .reduce((sum, c) => sum + (c.cycle_type === 'monthly' ? c.current_cycle_amount : c.current_cycle_amount / 3), 0)
  const pendingCount = list.filter((c) => c.current_cycle_status === 'pending').length
  const overdueCount = list.filter((c) => c.current_cycle_status === 'overdue').length
  const collectedPercent = totalMonthlyRevenue > 0 ? Math.round((paidMonthlyRevenue / totalMonthlyRevenue) * 100) : 0

  return {
    totalActive: activeCount,
    totalContracts: list.length,
    weeklySlots: 92,
    mrr: totalMonthlyRevenue,
    paidMrr: paidMonthlyRevenue,
    pendingMrr: totalMonthlyRevenue - paidMonthlyRevenue,
    collectedPercent,
    pendingCount,
    overdueCount,
    peakOccupancyRate: 84.5,
  }
})

// Filtered contracts
const filteredContracts = computed(() => {
  return contracts.value.filter((item) => {
    // Tab filter
    if (selectedTab.value !== 'all' && item.status !== selectedTab.value) {
      return false
    }

    // Search filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      const matchCode = item.code.toLowerCase().includes(q)
      const matchName = item.customer_name.toLowerCase().includes(q)
      const matchPhone = item.customer_phone.toLowerCase().includes(q)
      const matchCourts = item.court_names.some((c) => c.toLowerCase().includes(q))
      if (!matchCode && !matchName && !matchPhone && !matchCourts) return false
    }

    // Venue filter
    if (selectedVenue.value !== 'all' && !item.venue_name.includes(selectedVenue.value)) {
      return false
    }

    // Day of week filter
    if (selectedDayOfWeek.value !== 'all') {
      const hasDay = item.days_of_week.some((d) => d.includes(selectedDayOfWeek.value))
      if (!hasDay) return false
    }

    // Time slot filter
    if (selectedTimeSlot.value !== 'all') {
      if (selectedTimeSlot.value === 'peak' && !item.time_slot.includes('18:00') && !item.time_slot.includes('19:00') && !item.time_slot.includes('17:00')) {
        return false
      }
      if (selectedTimeSlot.value === 'morning' && !item.time_slot.includes('07:00') && !item.time_slot.includes('08:00') && !item.time_slot.includes('09:00')) {
        return false
      }
    }

    // Cycle filter
    if (selectedCycle.value !== 'all' && item.cycle_type !== selectedCycle.value) {
      return false
    }

    return true
  })
})

// Tab Counts
const tabCounts = computed(() => ({
  all: contracts.value.length,
  active: contracts.value.filter((c) => c.status === 'active').length,
  pending_payment: contracts.value.filter((c) => c.status === 'pending_payment').length,
  overdue: contracts.value.filter((c) => c.status === 'overdue').length,
  expiring_soon: contracts.value.filter((c) => c.status === 'expiring_soon').length,
  paused: contracts.value.filter((c) => c.status === 'paused').length,
}))

// Actions
function openDetail(contract: Contract) {
  activeContract.value = JSON.parse(JSON.stringify(contract))
  isDetailModalOpen.value = true
}

function openReschedule(contract: Contract) {
  activeContract.value = contract
  isRescheduleModalOpen.value = true
}

function sendReminder(contract: Contract) {
  showToast(`Đã gửi SMS & Email nhắc nợ kỳ ${contract.current_cycle_name} tới ${contract.customer_name} (${contract.customer_phone})!`, 'info')
}

function markCurrentCyclePaid(contract: Contract) {
  contract.current_cycle_status = 'paid'
  if (contract.status === 'pending_payment' || contract.status === 'overdue') {
    contract.status = 'active'
  }
  const pendingInv = contract.invoices.find((i) => i.status !== 'paid')
  if (pendingInv) {
    pendingInv.status = 'paid'
    pendingInv.paid_at = toYmd()
    pendingInv.payment_reference = `ADMIN-CASH-${Date.now().toString().slice(-6)}`
  }
  showToast(`Đã xác nhận thu tiền ${formatVnd(contract.current_cycle_amount)} cho ${contract.code} thành công!`)
  if (activeContract.value?.id === contract.id) {
    activeContract.value = JSON.parse(JSON.stringify(contract))
  }
}

function togglePauseContract(contract: Contract) {
  if (contract.status === 'paused') {
    contract.status = 'active'
    showToast(`Đã kích hoạt lại hợp đồng ${contract.code}!`)
  } else {
    contract.status = 'paused'
    showToast(`Đã tạm dừng hợp đồng ${contract.code} theo yêu cầu!`, 'info')
  }
  if (activeContract.value?.id === contract.id) {
    activeContract.value.status = contract.status
  }
}

function handleCreateContract() {
  if (!newContract.value.customer_name || !newContract.value.customer_phone) {
    showToast('Vui lòng nhập tên khách hàng và số điện thoại!', 'error')
    return
  }

  const newId = (contracts.value.length + 1).toString()
  const code = `HĐ-DK-2026-${(120 + contracts.value.length).toString().padStart(3, '0')}`
  const totalAmt = newContract.value.price_per_month * newContract.value.duration_months

  const baseDate = newContract.value.start_date ? new Date(newContract.value.start_date) : new Date()
  const endDateObj = new Date(baseDate)
  endDateObj.setMonth(endDateObj.getMonth() + newContract.value.duration_months)

  const monthOneEnd = new Date(baseDate)
  monthOneEnd.setMonth(monthOneEnd.getMonth() + 1)

  const created: Contract = {
    id: newId,
    code,
    customer_name: newContract.value.customer_name,
    customer_phone: newContract.value.customer_phone,
    customer_type: newContract.value.customer_type,
    avatar_color: '#78555e',
    venue_name: newContract.value.venue_name,
    court_names: newContract.value.court_names,
    days_of_week: newContract.value.days_of_week,
    time_slot: newContract.value.time_slot,
    start_date: newContract.value.start_date || toYmd(),
    end_date: toYmd(endDateObj),
    duration_months: newContract.value.duration_months,
    total_amount: totalAmt,
    deposit_amount: newContract.value.deposit_amount,
    cycle_type: newContract.value.cycle_type,
    current_cycle_name: 'Kỳ tháng 01',
    current_cycle_status: 'paid',
    current_cycle_due: newContract.value.start_date || toYmd(),
    current_cycle_amount: newContract.value.price_per_month,
    status: 'active',
    reschedule_quota_left: 2,
    notes: newContract.value.notes,
    invoices: [
      {
        id: Date.now(),
        period_name: 'Kỳ tháng 01',
        period_start: newContract.value.start_date || toYmd(),
        period_end: toYmd(monthOneEnd),
        due_date: newContract.value.start_date || toYmd(),
        amount: newContract.value.price_per_month,
        status: 'paid',
        paid_at: toYmd(),
        payment_reference: `DEPOSIT-${Date.now().toString().slice(-6)}`,
      },
    ],
  }

  contracts.value.unshift(created)
  isCreateModalOpen.value = false
  showToast(`Đã tạo thành công hợp đồng định kỳ mới ${code}!`)

  // Reset form
  newContract.value.customer_name = ''
  newContract.value.customer_phone = ''
  newContract.value.notes = ''
}

function exportReport() {
  const headers = ['Mã HĐ', 'Khách hàng', 'SĐT', 'Cụm sân', 'Sân', 'Lịch tuần', 'Khung giờ', 'Bắt đầu', 'Kết thúc', 'Giá trị HĐ', 'Trạng thái']
  const rows = contracts.value.map((c) => [
    c.code,
    c.customer_name,
    c.customer_phone,
    c.venue_name,
    c.court_names.join(', '),
    c.days_of_week.join('; '),
    c.time_slot,
    c.start_date,
    c.end_date,
    c.total_amount,
    c.status,
  ])
  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `Aura_Hop_Dong_Dinh_Ky_${toYmd()}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  showToast('Đã xuất báo cáo danh sách hợp đồng định kỳ thành công!')
}

// Fetch live series from backend if available
onMounted(async () => {
  try {
    const res = await apiV2.get<{ data?: any[] }>('/recurring-series')
    if (res && Array.isArray(res.data) && res.data.length > 0) {
      // Map live series to contracts if present
    }
  } catch {
    // Keep rich initial mock data
  }
})
</script>

<template>
  <div class="admin-contracts-page">
    <!-- Toast Alert Notification -->
    <Transition name="toast-fade">
      <div v-if="toastMessage" class="toast-floating-banner" :class="`is-${toastMessage.type}`">
        <span class="material-symbols-outlined icon-20">
          {{ toastMessage.type === 'success' ? 'check_circle' : toastMessage.type === 'error' ? 'error' : 'info' }}
        </span>
        <span>{{ toastMessage.text }}</span>
      </div>
    </Transition>

    <!-- 1. Header & Top Bar -->
    <header class="page-header">
      <div class="header-left">
        <div class="breadcrumbs">
          <span>Quản trị</span>
          <span class="material-symbols-outlined chevron">chevron_right</span>
          <span>Quản lý Đặt Sân</span>
          <span class="material-symbols-outlined chevron">chevron_right</span>
          <span class="current">Hợp Đồng Định Kỳ</span>
        </div>
        <div class="title-with-badge">
          <h1 class="page-title">Quản Lý Hợp Đồng Đặt Sân Định Kỳ</h1>
          <span class="badge-total">{{ stats.totalContracts }} Hợp đồng</span>
        </div>
      </div>

      <div class="header-actions">
        <button type="button" class="btn-outline-soft" @click="isPolicyModalOpen = true">
          <span class="material-symbols-outlined icon-18">gavel</span>
          <span>Chính Sách Định Kỳ</span>
        </button>

        <button type="button" class="btn-outline-soft" @click="exportReport">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>Xuất Báo Cáo Excel</span>
        </button>

        <button type="button" class="btn-primary-pink" @click="isCreateModalOpen = true">
          <span class="material-symbols-outlined icon-20">add</span>
          <span>Tạo Hợp Đồng Mới</span>
        </button>
      </div>
    </header>

    <!-- 2. KPI Cards Grid (4 Top Metric Cards) -->
    <section class="kpi-grid" aria-label="Thống kê hợp đồng định kỳ">
      <!-- Card 1: Active Contracts -->
      <div class="kpi-card">
        <div class="kpi-icon-box bg-pink">
          <span class="material-symbols-outlined">event_repeat</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Tổng Hợp Đồng Đang Chạy</span>
          <div class="kpi-main-val">
            <span class="val-number">{{ stats.totalActive }}</span>
            <span class="val-unit">/ {{ stats.totalContracts }} HĐ</span>
          </div>
          <div class="kpi-footer-note positive">
            <span class="material-symbols-outlined icon-14">trending_up</span>
            <span>+8% tháng này · <strong>{{ stats.weeklySlots }}</strong> slot cố định/tuần</span>
          </div>
        </div>
      </div>

      <!-- Card 2: MRR Revenue -->
      <div class="kpi-card">
        <div class="kpi-icon-box bg-mint">
          <span class="material-symbols-outlined">payments</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Doanh Thu Định Kỳ (MRR)</span>
          <div class="kpi-main-val">
            <span class="val-number">{{ formatVnd(stats.mrr) }}</span>
          </div>
          <div class="kpi-progress-bar">
            <div class="progress-fill" :style="{ width: `${stats.collectedPercent}%` }"></div>
          </div>
          <div class="kpi-footer-note neutral">
            <span>Đã thu <strong>{{ stats.collectedPercent }}%</strong> · Còn lại {{ formatVnd(stats.pendingMrr) }}</span>
          </div>
        </div>
      </div>

      <!-- Card 3: Due & Overdue Invoices -->
      <div class="kpi-card">
        <div class="kpi-icon-box bg-coral">
          <span class="material-symbols-outlined">pending_actions</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Kỳ Thanh Toán Cần Xử Lý</span>
          <div class="kpi-main-val">
            <span class="val-number highlight-warning">{{ stats.pendingCount + stats.overdueCount }}</span>
            <span class="val-unit">Kỳ</span>
          </div>
          <div class="kpi-footer-note warning">
            <span class="badge-dot orange"></span>
            <span>{{ stats.pendingCount }} chờ thu · </span>
            <span class="badge-dot red"></span>
            <span class="text-danger">{{ stats.overdueCount }} quá hạn</span>
          </div>
        </div>
      </div>

      <!-- Card 4: Peak Hours Occupancy -->
      <div class="kpi-card">
        <div class="kpi-icon-box bg-purple">
          <span class="material-symbols-outlined">speed</span>
        </div>
        <div class="kpi-content">
          <span class="kpi-label">Lấp Đầy Giờ Vàng (17h - 21h)</span>
          <div class="kpi-main-val">
            <span class="val-number">{{ stats.peakOccupancyRate }}%</span>
          </div>
          <div class="kpi-footer-note neutral">
            <span class="material-symbols-outlined icon-14">lock</span>
            <span>Khóa slot cho khách định kỳ trung & dài hạn</span>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. Filter Bar & Search Filters -->
    <section class="filters-panel">
      <!-- Status Tabs -->
      <div class="status-tabs-row">
        <button
          type="button"
          class="tab-pill"
          :class="{ active: selectedTab === 'all' }"
          @click="selectedTab = 'all'"
        >
          Tất cả <span class="tab-count">{{ tabCounts.all }}</span>
        </button>

        <button
          type="button"
          class="tab-pill"
          :class="{ active: selectedTab === 'active' }"
          @click="selectedTab = 'active'"
        >
          Đang hoạt động <span class="tab-count">{{ tabCounts.active }}</span>
        </button>

        <button
          type="button"
          class="tab-pill"
          :class="{ active: selectedTab === 'pending_payment' }"
          @click="selectedTab = 'pending_payment'"
        >
          Sắp đến hạn kỳ <span class="tab-count orange">{{ tabCounts.pending_payment }}</span>
        </button>

        <button
          type="button"
          class="tab-pill"
          :class="{ active: selectedTab === 'overdue' }"
          @click="selectedTab = 'overdue'"
        >
          Quá hạn thanh toán <span class="tab-count red">{{ tabCounts.overdue }}</span>
        </button>

        <button
          type="button"
          class="tab-pill"
          :class="{ active: selectedTab === 'expiring_soon' }"
          @click="selectedTab = 'expiring_soon'"
        >
          Sắp hết hạn HĐ <span class="tab-count yellow">{{ tabCounts.expiring_soon }}</span>
        </button>

        <button
          type="button"
          class="tab-pill"
          :class="{ active: selectedTab === 'paused' }"
          @click="selectedTab = 'paused'"
        >
          Tạm dừng <span class="tab-count">{{ tabCounts.paused }}</span>
        </button>
      </div>

      <!-- Filter Controls Row -->
      <div class="filter-controls-row">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm theo Mã HĐ, Tên CLB, SĐT, Sân..."
            class="search-input"
          />
          <button v-if="searchQuery" type="button" class="btn-clear-search" @click="searchQuery = ''">
            <span class="material-symbols-outlined icon-16">close</span>
          </button>
        </div>

        <div class="dropdown-filters">
          <!-- Cụm sân -->
          <div class="select-wrapper">
            <span class="material-symbols-outlined select-icon">location_on</span>
            <select v-model="selectedVenue" class="filter-select">
              <option value="all">Tất cả cụm sân</option>
              <option value="Cầu Giấy">Cụm A - Cầu Giấy</option>
              <option value="Thanh Xuân">Cụm B - Thanh Xuân</option>
              <option value="Nam Từ Liêm">Cụm C - Nam Từ Liêm</option>
            </select>
          </div>

          <!-- Ngày trong tuần -->
          <div class="select-wrapper">
            <span class="material-symbols-outlined select-icon">calendar_today</span>
            <select v-model="selectedDayOfWeek" class="filter-select">
              <option value="all">Tất cả các ngày</option>
              <option value="Thứ 2">Thứ 2 - 4 - 6</option>
              <option value="Thứ 3">Thứ 3 - 5 - 7</option>
              <option value="Thứ 7">Cuối tuần (T7 - CN)</option>
            </select>
          </div>

          <!-- Khung giờ -->
          <div class="select-wrapper">
            <span class="material-symbols-outlined select-icon">schedule</span>
            <select v-model="selectedTimeSlot" class="filter-select">
              <option value="all">Tất cả khung giờ</option>
              <option value="peak">Giờ vàng (17h - 21h)</option>
              <option value="morning">Buổi sáng (07h - 09h)</option>
            </select>
          </div>

          <!-- Chu kỳ -->
          <div class="select-wrapper">
            <span class="material-symbols-outlined select-icon">sync</span>
            <select v-model="selectedCycle" class="filter-select">
              <option value="all">Tất cả chu kỳ</option>
              <option value="monthly">Theo tháng</option>
              <option value="quarterly">Theo quý (3 tháng)</option>
            </select>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. Main Contracts Table Container -->
    <section class="contracts-table-section">
      <div class="table-responsive">
        <table class="contracts-table">
          <thead>
            <tr>
              <th class="col-code">Mã HĐ & Khách Hàng</th>
              <th class="col-venue">Cụm & Sân Chỉ Định</th>
              <th class="col-schedule">Lịch Lặp Cố Định</th>
              <th class="col-duration">Thời Hạn HĐ</th>
              <th class="col-cycle">Kỳ Thanh Toán Hiện Tại</th>
              <th class="col-financial">Giá Trị & Tiền Cọc</th>
              <th class="col-status">Trạng Thái</th>
              <th class="col-actions text-right">Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredContracts.length === 0" class="empty-row">
              <td colspan="8" class="empty-cell">
                <div class="empty-state">
                  <span class="material-symbols-outlined empty-icon">assignment_late</span>
                  <p class="empty-title">Không tìm thấy hợp đồng định kỳ nào phù hợp</p>
                  <p class="empty-desc">Hãy thử thay đổi bộ lọc tìm kiếm hoặc tạo hợp đồng mới.</p>
                </div>
              </td>
            </tr>

            <tr v-for="contract in filteredContracts" :key="contract.id" class="contract-row">
              <!-- Cột 1: Mã HĐ & Đại diện -->
              <td class="col-code">
                <div class="customer-info-cell">
                  <div class="customer-avatar" :style="{ backgroundColor: contract.avatar_color }">
                    {{ contract.customer_name.slice(0, 1).toUpperCase() }}
                  </div>
                  <div class="customer-text-block">
                    <span class="contract-code-tag">{{ contract.code }}</span>
                    <div class="customer-name-row">
                      <strong class="customer-name">{{ contract.customer_name }}</strong>
                      <span class="customer-type-tag" :class="contract.customer_type.toLowerCase().replace(' ', '-')">
                        {{ contract.customer_type }}
                      </span>
                    </div>
                    <span class="customer-phone">{{ contract.customer_phone }}</span>
                  </div>
                </div>
              </td>

              <!-- Cột 2: Cụm & Sân -->
              <td class="col-venue">
                <div class="venue-info-cell">
                  <span class="venue-title">{{ contract.venue_name }}</span>
                  <div class="court-chips-group">
                    <span v-for="court in contract.court_names" :key="court" class="court-chip">
                      {{ court }}
                    </span>
                  </div>
                </div>
              </td>

              <!-- Cột 3: Lịch lặp cố định -->
              <td class="col-schedule">
                <div class="schedule-cell">
                  <div class="days-badges">
                    <span v-for="day in contract.days_of_week" :key="day" class="day-badge">
                      {{ day }}
                    </span>
                  </div>
                  <div class="time-slot-row">
                    <span class="material-symbols-outlined icon-14">schedule</span>
                    <span class="time-text">{{ contract.time_slot }}</span>
                  </div>
                </div>
              </td>

              <!-- Cột 4: Thời hạn HĐ -->
              <td class="col-duration">
                <div class="duration-cell">
                  <span class="duration-span">
                    {{ formatDate(contract.start_date) }} → {{ formatDate(contract.end_date) }}
                  </span>
                  <span class="duration-badge">{{ contract.duration_months }} Tháng</span>
                </div>
              </td>

              <!-- Cột 5: Kỳ thanh toán hiện tại -->
              <td class="col-cycle">
                <div class="cycle-cell">
                  <span class="cycle-name">{{ contract.current_cycle_name }}</span>
                  <div class="cycle-status-pill" :class="contract.current_cycle_status">
                    <span class="status-dot"></span>
                    <span>
                      {{
                        contract.current_cycle_status === 'paid'
                          ? 'Đã thanh toán'
                          : contract.current_cycle_status === 'pending'
                            ? `Hạn: ${formatDate(contract.current_cycle_due)}`
                            : `Quá hạn: ${formatDate(contract.current_cycle_due)}`
                      }}
                    </span>
                  </div>
                  <span class="cycle-amount">{{ formatVnd(contract.current_cycle_amount) }}</span>
                </div>
              </td>

              <!-- Cột 6: Giá trị & Tiền cọc -->
              <td class="col-financial">
                <div class="financial-cell">
                  <span class="total-amount">{{ formatVnd(contract.total_amount) }}</span>
                  <span class="deposit-text">Cọc: {{ formatVnd(contract.deposit_amount) }}</span>
                </div>
              </td>

              <!-- Cột 7: Trạng thái HĐ -->
              <td class="col-status">
                <span class="contract-status-badge" :class="contract.status">
                  {{
                    contract.status === 'active'
                      ? 'Đang hoạt động'
                      : contract.status === 'pending_payment'
                        ? 'Chờ thu tiền'
                        : contract.status === 'overdue'
                          ? 'Cảnh báo nợ'
                          : contract.status === 'expiring_soon'
                            ? 'Sắp hết hạn'
                            : 'Tạm dừng'
                  }}
                </span>
              </td>

              <!-- Cột 8: Thao tác -->
              <td class="col-actions text-right">
                <div class="action-buttons-group">
                  <!-- Xem chi tiết -->
                  <button
                    type="button"
                    class="btn-icon-action"
                    data-testid="btn-view-detail"
                    title="Xem chi tiết & lịch sử các kỳ"
                    @click="openDetail(contract)"
                  >
                    <span class="material-symbols-outlined icon-18">visibility</span>
                  </button>

                  <!-- Thu tiền ngay nếu chưa thanh toán -->
                  <button
                    v-if="contract.current_cycle_status !== 'paid'"
                    type="button"
                    class="btn-icon-action text-success"
                    title="Ghi nhận thanh toán kỳ này"
                    @click="markCurrentCyclePaid(contract)"
                  >
                    <span class="material-symbols-outlined icon-18">check_circle</span>
                  </button>

                  <!-- Gửi nhắc nợ nếu đang quá hạn/chờ -->
                  <button
                    v-if="contract.current_cycle_status !== 'paid'"
                    type="button"
                    class="btn-icon-action text-warning"
                    title="Gửi tin nhắn SMS / Email nhắc nợ"
                    @click="sendReminder(contract)"
                  >
                    <span class="material-symbols-outlined icon-18">send</span>
                  </button>

                  <!-- Đổi lịch bù -->
                  <button
                    type="button"
                    class="btn-icon-action"
                    title="Đổi lịch buổi tập bù"
                    @click="openReschedule(contract)"
                  >
                    <span class="material-symbols-outlined icon-18">swap_horiz</span>
                  </button>

                  <!-- Tạm dừng / Kích hoạt -->
                  <button
                    type="button"
                    class="btn-icon-action"
                    :title="contract.status === 'paused' ? 'Kích hoạt lại' : 'Tạm dừng HĐ'"
                    @click="togglePauseContract(contract)"
                  >
                    <span class="material-symbols-outlined icon-18">
                      {{ contract.status === 'paused' ? 'play_arrow' : 'pause' }}
                    </span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer & Pagination -->
      <div class="table-footer">
        <div class="pagination-info">
          <span>Hiển thị <strong>1 - {{ filteredContracts.length }}</strong> trên tổng <strong>{{ contracts.length }}</strong> hợp đồng</span>
        </div>

        <div class="pagination-controls">
          <button type="button" class="btn-page-arrow" disabled>
            <span class="material-symbols-outlined icon-16">chevron_left</span>
          </button>
          <button type="button" class="btn-page active">1</button>
          <button type="button" class="btn-page">2</button>
          <button type="button" class="btn-page">3</button>
          <button type="button" class="btn-page-arrow">
            <span class="material-symbols-outlined icon-16">chevron_right</span>
          </button>
        </div>
      </div>
    </section>

    <!-- 5. Sidebar Policy Card Section -->
    <aside class="policy-note-card">
      <div class="policy-card-header">
        <div class="policy-card-title">
          <span class="material-symbols-outlined icon-20 text-pink">info</span>
          <strong>Quy Định Vận Hành Hợp Đồng Định Kỳ</strong>
        </div>
        <button type="button" class="btn-link-sm" @click="isPolicyModalOpen = true">Chi tiết chính sách →</button>
      </div>
      <div class="policy-grid">
        <div class="policy-item">
          <span class="policy-bullet">1</span>
          <div>
            <strong>Đặt cọc bắt buộc 1 kỳ:</strong>
            <p>Khách hàng đặt cọc tối thiểu 01 kỳ tương đương giá trị 1 tháng để giữ slot cố định.</p>
          </div>
        </div>
        <div class="policy-item">
          <span class="policy-bullet">2</span>
          <div>
            <strong>Hạn thanh toán kỳ mới:</strong>
            <p>Hệ thống tự động tạo hóa đơn kỳ tiếp theo trước ngày 25 và đến hạn nộp trước ngày 01 đầu kỳ.</p>
          </div>
        </div>
        <div class="policy-item">
          <span class="policy-bullet">3</span>
          <div>
            <strong>Đổi lịch bù linh hoạt:</strong>
            <p>Tối đa 02 buổi/tháng. Khách phải thông báo trước tối thiểu 24h để hoàn slot vào quỹ resale.</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- MODAL: Tạo Hợp Đồng Định Kỳ Mới -->
    <div v-if="isCreateModalOpen" class="modal-backdrop" @click="isCreateModalOpen = false">
      <div class="modal-card modal-lg" @click.stop>
        <div class="modal-header">
          <div class="modal-title-box">
            <span class="material-symbols-outlined icon-24 text-pink">note_add</span>
            <div>
              <h2 class="modal-title">Tạo Hợp Đồng Đặt Sân Định Kỳ</h2>
              <span class="modal-subtitle">Thiết lập khách hàng, cụm sân, lịch lặp và chu kỳ thanh toán</span>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="isCreateModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="handleCreateContract" class="modal-form">
          <div class="form-grid">
            <!-- Tên khách hàng / CLB -->
            <div class="form-group">
              <label class="form-label">Tên Khách Hàng / Đội CLB <span class="text-danger">*</span></label>
              <input
                v-model="newContract.customer_name"
                type="text"
                required
                placeholder="Ví dụ: CLB Passion Badminton"
                class="form-input"
              />
            </div>

            <!-- Số điện thoại -->
            <div class="form-group">
              <label class="form-label">Số Điện Thoại Đại Diện <span class="text-danger">*</span></label>
              <input
                v-model="newContract.customer_phone"
                type="text"
                required
                placeholder="Ví dụ: 0988.123.456"
                class="form-input"
              />
            </div>

            <!-- Phân loại khách hàng -->
            <div class="form-group">
              <label class="form-label">Phân Loại Đối Tượng</label>
              <select v-model="newContract.customer_type" class="form-input">
                <option value="CLB">Câu Lạc Bộ (CLB)</option>
                <option value="Doanh nghiệp">Doanh Nghiệp / Công Ty</option>
                <option value="VIP Gold">Hội Viên VIP Gold</option>
                <option value="Khách quen">Khách Quen / Thân Thiết</option>
                <option value="Thành viên">Cá Nhân / Thành Viên</option>
              </select>
            </div>

            <!-- Cụm Sân -->
            <div class="form-group">
              <label class="form-label">Cụm Sân Chỉ Định</label>
              <select v-model="newContract.venue_name" class="form-input">
                <option value="Aura Cầu Giấy (Cụm A)">Aura Cầu Giấy (Cụm A - 8 Sân)</option>
                <option value="Aura Thanh Xuân (Cụm B)">Aura Thanh Xuân (Cụm B - 6 Sân)</option>
                <option value="Aura Nam Từ Liêm (Cụm C)">Aura Nam Từ Liêm (Cụm C - 10 Sân)</option>
              </select>
            </div>

            <!-- Chọn sân cụ thể -->
            <div class="form-group">
              <label class="form-label">Sân Đặt (Chọn 1 hoặc nhiều)</label>
              <div class="checkbox-chips-group">
                <label v-for="court in ['Sân 01', 'Sân 02', 'Sân 03', 'Sân 04', 'Sân 05', 'Sân 06']" :key="court" class="chip-checkbox">
                  <input
                    type="checkbox"
                    :value="court"
                    v-model="newContract.court_names"
                  />
                  <span>{{ court }}</span>
                </label>
              </div>
            </div>

            <!-- Ngày lặp lại trong tuần -->
            <div class="form-group">
              <label class="form-label">Ngày Trong Tuần</label>
              <div class="checkbox-chips-group">
                <label v-for="day in ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật']" :key="day" class="chip-checkbox">
                  <input
                    type="checkbox"
                    :value="day"
                    v-model="newContract.days_of_week"
                  />
                  <span>{{ day }}</span>
                </label>
              </div>
            </div>

            <!-- Khung giờ -->
            <div class="form-group">
              <label class="form-label">Khung Giờ Cố Định</label>
              <select v-model="newContract.time_slot" class="form-input">
                <option value="18:00 - 20:00">18:00 - 20:00 (Giờ Vàng Tối)</option>
                <option value="19:00 - 21:00">19:00 - 21:00 (Giờ Vàng Tối)</option>
                <option value="17:00 - 19:00">17:00 - 19:00 (Chiều Tối)</option>
                <option value="20:00 - 22:00">20:00 - 22:00 (Ca Đêm)</option>
                <option value="07:00 - 09:00">07:00 - 09:00 (Sáng Sớm)</option>
              </select>
            </div>

            <!-- Thời hạn hợp đồng -->
            <div class="form-group">
              <label class="form-label">Thời Hạn Hợp Đồng</label>
              <select v-model="newContract.duration_months" class="form-input">
                <option :value="3">3 Tháng</option>
                <option :value="6">6 Tháng (Chuẩn khuyến mãi 5%)</option>
                <option :value="12">12 Tháng (Khuyến mãi 10%)</option>
              </select>
            </div>

            <!-- Giá mỗi tháng & Tiền cọc -->
            <div class="form-group">
              <label class="form-label">Giá Thu Mỗi Tháng (VND)</label>
              <input
                v-model.number="newContract.price_per_month"
                type="number"
                step="100000"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Tiền Cọc Đảm Bảo (VND)</label>
              <input
                v-model.number="newContract.deposit_amount"
                type="number"
                step="500000"
                class="form-input"
              />
            </div>

            <!-- Ghi chú -->
            <div class="form-group full-width">
              <label class="form-label">Ghi Chú Hợp Đồng / Yêu Cầu Riêng</label>
              <textarea
                v-model="newContract.notes"
                rows="2"
                placeholder="Yêu cầu xuất hóa đơn, ưu tiên cố định vị trí sân..."
                class="form-textarea"
              ></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-cancel" @click="isCreateModalOpen = false">Hủy</button>
            <button type="submit" class="btn-primary-pink">
              <span class="material-symbols-outlined icon-18">check</span>
              <span>Xác Nhận Tạo Hợp Đồng</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: Chi Tiết Hợp Đồng & Lịch Sử Kỳ Hóa Đơn -->
    <div v-if="isDetailModalOpen && activeContract" class="modal-backdrop" @click="isDetailModalOpen = false">
      <div class="modal-card modal-lg" @click.stop>
        <div class="modal-header">
          <div class="modal-title-box">
            <span class="material-symbols-outlined icon-24 text-pink">receipt_long</span>
            <div>
              <h2 class="modal-title">{{ activeContract.code }} - {{ activeContract.customer_name }}</h2>
              <span class="modal-subtitle">{{ activeContract.venue_name }} · {{ activeContract.court_names.join(', ') }}</span>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="isDetailModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-scroll">
          <!-- Summary Header in Modal -->
          <div class="contract-overview-grid">
            <div class="overview-item">
              <span class="overview-label">Thời hạn</span>
              <strong>{{ formatDate(activeContract.start_date) }} → {{ formatDate(activeContract.end_date) }}</strong>
            </div>
            <div class="overview-item">
              <span class="overview-label">Lịch cố định</span>
              <strong>{{ activeContract.days_of_week.join(', ') }} ({{ activeContract.time_slot }})</strong>
            </div>
            <div class="overview-item">
              <span class="overview-label">Tổng giá trị</span>
              <strong class="text-pink">{{ formatVnd(activeContract.total_amount) }}</strong>
            </div>
            <div class="overview-item">
              <span class="overview-label">Tiền cọc</span>
              <strong>{{ formatVnd(activeContract.deposit_amount) }}</strong>
            </div>
          </div>

          <!-- Invoice List in Modal -->
          <div class="invoices-list-section">
            <h3 class="section-sub-title">Lịch Sử Các Kỳ Hóa Đơn Hàng Tháng</h3>
            <div class="invoices-table-wrap">
              <table class="invoices-sub-table">
                <thead>
                  <tr>
                    <th>Tên Kỳ</th>
                    <th>Thời Gian</th>
                    <th>Hạn Nộp</th>
                    <th>Số Tiền</th>
                    <th>Trạng Thái</th>
                    <th>Mã Đối Soát</th>
                    <th class="text-right">Thao Tác</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inv in activeContract.invoices" :key="inv.id">
                    <td><strong>{{ inv.period_name }}</strong></td>
                    <td>{{ formatDate(inv.period_start) }} - {{ formatDate(inv.period_end) }}</td>
                    <td>{{ formatDate(inv.due_date) }}</td>
                    <td><strong class="text-dark">{{ formatVnd(inv.amount) }}</strong></td>
                    <td>
                      <span class="cycle-status-pill" :class="inv.status">
                        <span class="status-dot"></span>
                        <span>{{ inv.status === 'paid' ? 'Đã thu' : inv.status === 'overdue' ? 'Quá hạn' : 'Chờ thu' }}</span>
                      </span>
                    </td>
                    <td><span class="ref-code">{{ inv.payment_reference || '-' }}</span></td>
                    <td class="text-right">
                      <button
                        v-if="inv.status !== 'paid'"
                        type="button"
                        class="btn-sm-action"
                        @click="markCurrentCyclePaid(activeContract)"
                      >
                        Thu tiền ngay
                      </button>
                      <span v-else class="text-muted-sm">✓ Hoàn tất</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="isDetailModalOpen = false">Đóng</button>
          <button type="button" class="btn-outline-soft" @click="sendReminder(activeContract)">
            <span class="material-symbols-outlined icon-16">send</span>
            <span>Gửi SMS Nhắc Lịch</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL: Đổi Lịch Buổi Tập Bù -->
    <div v-if="isRescheduleModalOpen && activeContract" class="modal-backdrop" @click="isRescheduleModalOpen = false">
      <div class="modal-card modal-md" @click.stop>
        <div class="modal-header">
          <div class="modal-title-box">
            <span class="material-symbols-outlined icon-24 text-pink">swap_horiz</span>
            <div>
              <h2 class="modal-title">Đổi Lịch Buổi Tập Bù</h2>
              <span class="modal-subtitle">{{ activeContract.customer_name }} · Còn lại: {{ activeContract.reschedule_quota_left }}/2 buổi/tháng</span>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="isRescheduleModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body">
          <p class="modal-desc-text">
            Theo chính sách định kỳ, khách được xin hoãn và xếp bù tối đa 2 buổi/tháng với điều kiện báo trước 24h.
          </p>

          <div class="form-group mt-3">
            <label class="form-label">Chọn buổi tập muốn xin hoãn</label>
            <select class="form-input">
              <option>Buổi Thứ 4 tới (18:00 - 20:00) · Sân 03</option>
              <option>Buổi Thứ 6 tuần này (18:00 - 20:00) · Sân 04</option>
            </select>
          </div>

          <div class="form-group mt-3">
            <label class="form-label">Chọn ngày và giờ tập bù mới</label>
            <input type="date" class="form-input" />
          </div>

          <div class="form-group mt-3">
            <label class="form-label">Khung giờ bù đề xuất</label>
            <select class="form-input">
              <option>18:00 - 20:00 (Sân 05 - Trống)</option>
              <option>20:00 - 22:00 (Sân 02 - Trống)</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="isRescheduleModalOpen = false">Hủy</button>
          <button
            type="button"
            class="btn-primary-pink"
            @click="
              () => {
                showToast(`Đã ghi nhận đổi lịch bù cho ${activeContract?.customer_name} thành công!`)
                isRescheduleModalOpen = false
              }
            "
          >
            Xác Nhận Đổi Lịch Bù
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL: Cài Đặt Chính Sách Định Kỳ (Comprehensive Settings) -->
    <div v-if="isPolicyModalOpen" class="modal-backdrop" @click="isPolicyModalOpen = false">
      <div class="modal-card modal-lg modal-policy-custom" @click.stop>
        <div class="modal-header">
          <div class="modal-title-box">
            <span class="material-symbols-outlined icon-24 text-pink">settings_suggest</span>
            <div>
              <h2 class="modal-title">Cài Đặt Chính Sách Đặt Sân Định Kỳ</h2>
              <span class="modal-subtitle">Aura Court Control Recurring Policy Engine</span>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="isPolicyModalOpen = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <!-- Policy Navigation Tabs -->
        <div class="policy-nav-tabs">
          <button
            type="button"
            class="policy-tab-btn"
            :class="{ active: activePolicyTab === 'terms' }"
            @click="activePolicyTab = 'terms'"
            title="1. Thời Hạn & Điều Kiện Ký Kết"
          >
            <span class="material-symbols-outlined icon-18">contract</span>
            <span class="tab-text">1. Điều Kiện HĐ</span>
          </button>
          <button
            type="button"
            class="policy-tab-btn"
            :class="{ active: activePolicyTab === 'discounts' }"
            @click="activePolicyTab = 'discounts'"
            title="2. Chính Sách Chiết Khấu & Ưu Đãi"
          >
            <span class="material-symbols-outlined icon-18">percent</span>
            <span class="tab-text">2. Chiết Khấu</span>
          </button>
          <button
            type="button"
            class="policy-tab-btn"
            :class="{ active: activePolicyTab === 'deposit' }"
            @click="activePolicyTab = 'deposit'"
            title="3. Quy Định Tiền Cọc & Thu Phí"
          >
            <span class="material-symbols-outlined icon-18">account_balance_wallet</span>
            <span class="tab-text">3. Tiền Cọc</span>
          </button>
          <button
            type="button"
            class="policy-tab-btn"
            :class="{ active: activePolicyTab === 'reschedule' }"
            @click="activePolicyTab = 'reschedule'"
            title="4. Chính Sách Dời Lịch & Bảo Lưu"
          >
            <span class="material-symbols-outlined icon-18">event_repeat</span>
            <span class="tab-text">4. Dời Lịch</span>
          </button>
          <button
            type="button"
            class="policy-tab-btn"
            :class="{ active: activePolicyTab === 'cancellation' }"
            @click="activePolicyTab = 'cancellation'"
            title="5. Quy Định Hủy Hợp Đồng & Hoàn Cọc"
          >
            <span class="material-symbols-outlined icon-18">cancel</span>
            <span class="tab-text">5. Hủy Hợp Đồng</span>
          </button>
        </div>

        <!-- Policy Body Content by Tabs -->
        <div class="modal-body modal-body-scroll policy-content-area">
          <!-- TAB 1: Thời hạn & Điều kiện -->
          <div v-if="activePolicyTab === 'terms'" class="policy-tab-pane">
            <div class="policy-card">
              <div class="policy-card-header">
                <h3 class="policy-card-title">Thời Hạn Ký Kết Tiêu Chuẩn</h3>
                <p class="policy-card-desc">Quy định biên độ thời gian tối thiểu và tối đa cho mỗi hợp đồng định kỳ được tạo.</p>
              </div>
              
              <div class="policy-row-grid">
                <div class="policy-field">
                  <label class="policy-label">Thời hạn tối thiểu (tháng)</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.minDurationMonths" min="1" max="24" class="form-input" />
                    <span class="unit-text">Tháng</span>
                  </div>
                  <span class="policy-hint">Mặc định tiêu chuẩn 03 tháng để đảm bảo tính ổn định slot.</span>
                </div>

                <div class="policy-field">
                  <label class="policy-label">Thời hạn tối đa (tháng)</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.maxDurationMonths" min="1" max="36" class="form-input" />
                    <span class="unit-text">Tháng</span>
                  </div>
                  <span class="policy-hint">Giới hạn tránh việc khóa cứng slot giờ vàng quá lâu.</span>
                </div>
              </div>

              <div class="policy-row-grid">
                <div class="policy-field">
                  <label class="policy-label">Số buổi chơi tối thiểu / tuần</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.minDaysPerWeek" min="1" max="7" class="form-input" />
                    <span class="unit-text">Buổi/tuần</span>
                  </div>
                  <span class="policy-hint">Tối thiểu 2 buổi/tuần để áp dụng giá định kỳ.</span>
                </div>

                <div class="policy-field">
                  <label class="policy-label">Cảnh báo trước khi hợp đồng hết hạn</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.renewNoticeDays" min="1" max="60" class="form-input" />
                    <span class="unit-text">Ngày</span>
                  </div>
                  <span class="policy-hint">Hệ thống gửi thông báo nhắc gia hạn trước N ngày.</span>
                </div>
              </div>

              <div class="policy-toggle-card">
                <div class="toggle-info">
                  <div class="toggle-title">Tự Động Gia Hạn Hợp Đồng (Auto-Renew)</div>
                  <div class="toggle-desc">Cho phép hệ thống tự động sinh hóa đơn kỳ mới nếu khách hàng không báo hủy trước hạn.</div>
                </div>
                <label class="switch-control">
                  <input type="checkbox" v-model="policySettings.autoRenew" />
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>
          </div>

          <!-- TAB 2: Chiết khấu & Ưu đãi -->
          <div v-if="activePolicyTab === 'discounts'" class="policy-tab-pane">
            <div class="policy-card">
              <div class="policy-card-header">
                <h3 class="policy-card-title">Chính Sách Chiết Khấu Theo Kỳ Hạn</h3>
                <p class="policy-card-desc">Khuyến khích hội nhóm và CLB cam kết thuê sân dài hạn với mức giảm giá lũy tiến.</p>
              </div>

              <div class="tiers-list">
                <div v-for="tier in policySettings.discountTiers" :key="tier.id" class="tier-card-item">
                  <div class="tier-badge-col">
                    <span class="tier-name">{{ tier.label }}</span>
                    <span class="tier-range">Thời hạn hợp đồng từ {{ tier.from }} đến {{ tier.to }} tháng</span>
                  </div>
                  <div class="tier-input-col">
                    <label class="tier-label">Tỷ lệ giảm giá</label>
                    <div class="input-with-unit">
                      <input type="number" v-model.number="tier.discount" min="0" max="50" class="form-input input-sm" />
                      <span class="unit-text">%</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="policy-toggle-card">
                <div class="toggle-info">
                  <div class="toggle-title">Ưu Đãi Thanh Toán Trọn Gói 100% (+{{ policySettings.fullPaymentExtraDiscount }}%)</div>
                  <div class="toggle-desc">Chiết khấu thêm trực tiếp khi khách thanh toán dứt điểm toàn bộ hợp đồng trong 1 lần.</div>
                </div>
                <label class="switch-control">
                  <input type="checkbox" v-model="policySettings.enableFullPaymentBonus" />
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>
          </div>

          <!-- TAB 3: Tiền cọc & Thu phí -->
          <div v-if="activePolicyTab === 'deposit'" class="policy-tab-pane">
            <div class="policy-card">
              <div class="policy-card-header">
                <h3 class="policy-card-title">Quy Định Đặt Cọc & Thu Phí</h3>
                <p class="policy-card-desc">Thiết lập tỷ lệ cọc giữ chỗ và các mốc thời gian thu tiền định kỳ.</p>
              </div>

              <div class="policy-row-grid">
                <div class="policy-field">
                  <label class="policy-label">Tỷ lệ tiền đặt cọc giữ sân</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.depositRatePercent" min="0" max="100" class="form-input" />
                    <span class="unit-text">% tổng HĐ</span>
                  </div>
                  <span class="policy-hint">Mặc định 20% (hoặc tương đương 1 tháng tiền sân).</span>
                </div>

                <div class="policy-field">
                  <label class="policy-label">Hạn chót đóng tiền kỳ tiếp theo</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.paymentDueDay" min="1" max="31" class="form-input" />
                    <span class="unit-text">hàng tháng</span>
                  </div>
                  <span class="policy-hint">Hóa đơn sẽ gửi tự động trước đó 5 ngày.</span>
                </div>
              </div>

              <div class="policy-row-grid">
                <div class="policy-field">
                  <label class="policy-label">Thời gian ân hạn quá hạn thanh toán</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.gracePeriodDays" min="1" max="15" class="form-input" />
                    <span class="unit-text">Ngày</span>
                  </div>
                  <span class="policy-hint">Sau N ngày gửi thông báo nhắc nợ tự động.</span>
                </div>

                <div class="policy-field">
                  <label class="policy-label">Tự động tạm khóa slot sau</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.lockSlotAfterDays" min="1" max="30" class="form-input" />
                    <span class="unit-text">Ngày trễ hạn</span>
                  </div>
                  <span class="policy-hint">Giải phóng slot bán vé lẻ khi không thanh toán.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 4: Dời lịch & Bảo lưu -->
          <div v-if="activePolicyTab === 'reschedule'" class="policy-tab-pane">
            <div class="policy-card">
              <div class="policy-card-header">
                <h3 class="policy-card-title">Chính Sách Đổi Lịch & Bù Buổi (Reschedule Quota)</h3>
                <p class="policy-card-desc">Cấu hình quyền lợi dời lịch cho khách hàng định kỳ khi có việc bận đột xuất.</p>
              </div>

              <div class="policy-row-grid">
                <div class="policy-field">
                  <label class="policy-label">Số lần dời lịch miễn phí</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.freeReschedulePerMonth" min="0" max="10" class="form-input" />
                    <span class="unit-text">Lần / tháng / sân</span>
                  </div>
                  <span class="policy-hint">Hạn mức làm mới lại vào ngày đầu tiên của mỗi tháng.</span>
                </div>

                <div class="policy-field">
                  <label class="policy-label">Thời gian báo trước tối thiểu</label>
                  <div class="input-with-unit">
                    <input type="number" v-model.number="policySettings.minRescheduleNoticeHours" min="1" max="72" class="form-input" />
                    <span class="unit-text">Giờ trước trận</span>
                  </div>
                  <span class="policy-hint">Yêu cầu tối thiểu 24 giờ để ban quản lý điều phối sân.</span>
                </div>
              </div>

              <div class="policy-field">
                <label class="policy-label">Thời hạn tối đa để xếp lịch đánh bù</label>
                <div class="input-with-unit">
                  <input type="number" v-model.number="policySettings.rescheduleValidityDays" min="1" max="60" class="form-input" />
                  <span class="unit-text">Ngày kể từ buổi hoãn</span>
                </div>
                <span class="policy-hint">Quá thời hạn trên, buổi hoãn sẽ hết hiệu lực bảo lưu.</span>
              </div>
            </div>
          </div>

          <!-- TAB 5: Hủy HĐ & Hoàn Cọc -->
          <div v-if="activePolicyTab === 'cancellation'" class="policy-tab-pane">
            <div class="policy-card">
              <div class="policy-card-header">
                <h3 class="policy-card-title">Quy Định Hủy Hợp Đồng Trước Hạn</h3>
                <p class="policy-card-desc">Quy định xử lý quyền lợi và tiền cọc khi một trong hai bên chấm dứt hợp đồng.</p>
              </div>

              <div class="policy-field">
                <label class="policy-label">Thời gian thông báo hủy trước tối thiểu</label>
                <div class="input-with-unit">
                  <input type="number" v-model.number="policySettings.cancellationNoticeDays" min="1" max="60" class="form-input" />
                  <span class="unit-text">Ngày</span>
                </div>
                <span class="policy-hint">Khách hàng cần thông báo trước ít nhất 15 ngày để được xét hoàn tiền cọc.</span>
              </div>

              <div class="policy-toggle-card">
                <div class="toggle-info">
                  <div class="toggle-title">Hoàn 100% Tiền Cọc Khi Báo Đúng Hạn</div>
                  <div class="toggle-desc">Nếu khách báo đúng số ngày quy định, hoàn lại toàn bộ số tiền đặt cọc sau khi cấn trừ hết công nợ.</div>
                </div>
                <label class="switch-control">
                  <input type="checkbox" v-model="policySettings.fullDepositRefundOnNotice" />
                  <span class="switch-slider"></span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer modal-footer-between">
          <button type="button" class="btn-ghost-danger" @click="resetPolicySettings">
            <span class="material-symbols-outlined icon-18">restart_alt</span>
            <span>Khôi Phục Mặc Định</span>
          </button>
          
          <div class="btn-group-right">
            <button type="button" class="btn-outline-soft" @click="isPolicyModalOpen = false">Đóng</button>
            <button type="button" class="btn-primary-pink" @click="savePolicySettings">
              <span class="material-symbols-outlined icon-18">save</span>
              <span>Lưu & Áp Dụng Cấu Hình</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-contracts-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 28px 32px;
  background: var(--bg, #f8f9fa);
  min-height: 100vh;
}

/* Floating Toast Banner */
.toast-floating-banner {
  position: fixed;
  top: 88px;
  right: 32px;
  z-index: 200;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
}

.toast-floating-banner.is-success {
  background: #2e7d32;
  color: #ffffff;
}

.toast-floating-banner.is-info {
  background: #1e293b;
  color: #ffffff;
}

.toast-floating-banner.is-error {
  background: #c62828;
  color: #ffffff;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}

/* 1. Header */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted, #64748b);
  margin-bottom: 6px;
}

.breadcrumbs .chevron {
  font-size: 16px;
}

.breadcrumbs .current {
  color: var(--pink-dark, #78555e);
  font-weight: 600;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  letter-spacing: -0.01em;
  margin: 0;
}

.badge-total {
  font-size: 12px;
  font-weight: 700;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  padding: 4px 10px;
  border-radius: 9999px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* Buttons */
.btn-primary-pink {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  border: 1px solid rgba(120, 85, 94, 0.2);
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-primary-pink:hover {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.25);
}

html.dark .btn-primary-pink {
  background: var(--pink, #78555e);
  color: #ffffff;
  border: 1px solid rgba(255, 209, 220, 0.2);
}

html.dark .btn-primary-pink:hover {
  background: var(--pink-dark, #f8b4c4);
  color: #1e1317;
  box-shadow: 0 4px 14px rgba(248, 180, 196, 0.3);
}

.btn-outline-soft {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e2e8f0);
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-outline-soft:hover {
  background: var(--surface-subtle, #f1f5f9);
}

/* 2. KPI Cards Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

@media (max-width: 1200px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px;
  padding: 18px 20px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  transition: all 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.05);
}

.kpi-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon-box.bg-pink {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.kpi-icon-box.bg-mint {
  background: #d1fae5;
  color: #065f46;
}

.kpi-icon-box.bg-coral {
  background: #fee2e2;
  color: #991b1b;
}

.kpi-icon-box.bg-purple {
  background: #f3e8ff;
  color: #6b21a8;
}

.kpi-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kpi-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.kpi-main-val {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.val-number {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  line-height: 1.2;
}

.val-unit {
  font-size: 13px;
  color: var(--muted, #64748b);
  font-weight: 600;
}

.highlight-warning {
  color: #c2410c;
}

.kpi-progress-bar {
  width: 100%;
  height: 6px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
  margin-top: 4px;
}

.progress-fill {
  height: 100%;
  background: #059669;
  border-radius: 999px;
}

.kpi-footer-note {
  font-size: 11px;
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.kpi-footer-note.positive {
  color: #059669;
}

.kpi-footer-note.warning {
  color: #b45309;
}

.kpi-footer-note.neutral {
  color: var(--muted, #64748b);
}

.badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}

.badge-dot.orange {
  background: #f59e0b;
}

.badge-dot.red {
  background: #ef4444;
}

.text-danger {
  color: #dc2626;
  font-weight: 700;
}

/* 3. Filter Panel */
.filters-panel {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.status-tabs-row {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border, #e2e8f0);
  background: transparent;
  color: var(--muted, #64748b);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.tab-pill:hover {
  background: var(--surface-subtle, #f8fafc);
  color: var(--ink, #191c1d);
}

.tab-pill.active {
  background: var(--pink, #ffd1dc);
  border-color: rgba(120, 85, 94, 0.3);
  color: var(--pink-dark, #78555e);
  font-weight: 700;
}

.tab-count {
  font-size: 11px;
  padding: 1px 7px;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.07);
}

.tab-count.orange {
  background: #ffedd5;
  color: #9a3412;
}

.tab-count.red {
  background: #fee2e2;
  color: #991b1b;
}

.tab-count.yellow {
  background: #fef9c3;
  color: #854d0e;
}

.filter-controls-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 280px;
  max-width: 420px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #94a3b8);
  font-size: 20px;
}

.search-input {
  width: 100%;
  padding: 9px 36px 9px 38px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 13px;
  background: var(--surface-subtle, #f8fafc);
  color: var(--ink, #191c1d);
  outline: none;
}

.search-input:focus {
  border-color: var(--pink-dark, #78555e);
  background: #ffffff;
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  cursor: pointer;
}

.dropdown-filters {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.select-icon {
  position: absolute;
  left: 10px;
  font-size: 16px;
  color: var(--muted, #94a3b8);
  pointer-events: none;
}

.filter-select {
  padding: 8px 14px 8px 32px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 13px;
  font-weight: 500;
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  outline: none;
}

.filter-select:focus {
  border-color: var(--pink-dark, #78555e);
}

/* 4. Table Styles */
.contracts-table-section {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.table-responsive {
  overflow-x: auto;
}

.contracts-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}

.contracts-table th {
  background: var(--surface-subtle, #f8fafc);
  color: var(--muted, #64748b);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border, #e2e8f0);
}

.contracts-table td {
  padding: 16px 18px;
  border-bottom: 1px solid var(--border, #e2e8f0);
  vertical-align: middle;
  color: var(--ink, #191c1d);
}

.contract-row {
  transition: background-color 0.15s ease;
}

.contract-row:hover {
  background: var(--surface-subtle, #f8fafc);
}

/* Cell Elements */
.customer-info-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.customer-avatar {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  color: #ffffff;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}

.customer-text-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.contract-code-tag {
  font-size: 11px;
  font-weight: 700;
  color: var(--pink-dark, #78555e);
  font-family: monospace;
}

.customer-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.customer-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.customer-type-tag {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-subtle, #f1f5f9);
  color: var(--muted, #475569);
}

.customer-type-tag.clb {
  background: var(--coral, #fee2e2);
  color: #991b1b;
}

.customer-type-tag.doanh-nghiệp {
  background: rgba(37, 99, 235, 0.15);
  color: #3b82f6;
}

.customer-type-tag.vip-gold {
  background: rgba(217, 119, 6, 0.15);
  color: #d97706;
}

.customer-phone {
  font-size: 11px;
  color: var(--muted, #64748b);
}

/* Venue & Court cell */
.venue-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  display: block;
  margin-bottom: 4px;
}

.court-chips-group {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.court-chip {
  font-size: 11px;
  font-weight: 600;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  padding: 2px 8px;
  border-radius: 6px;
}

/* Schedule cell */
.days-badges {
  display: flex;
  gap: 4px;
  margin-bottom: 4px;
}

.day-badge {
  font-size: 11px;
  font-weight: 700;
  background: var(--surface-subtle, #f1f5f9);
  color: var(--ink, #334155);
  padding: 2px 6px;
  border-radius: 4px;
}

.time-slot-row {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

/* Duration cell */
.duration-span {
  font-size: 12px;
  color: var(--muted, #475569);
  display: block;
  margin-bottom: 2px;
}

.duration-badge {
  font-size: 10px;
  font-weight: 700;
  background: var(--surface-subtle, #e2e8f0);
  color: var(--ink, #334155);
  padding: 1px 6px;
  border-radius: 4px;
}

/* Cycle cell */
.cycle-name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  display: block;
  margin-bottom: 2px;
}

.cycle-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  margin-bottom: 2px;
}

.cycle-status-pill.paid {
  background: var(--success-bg, #dcfce7);
  color: var(--success-text, #166534);
}

.cycle-status-pill.paid .status-dot {
  background: #22c55e;
}

.cycle-status-pill.pending {
  background: var(--warning-bg, #ffedd5);
  color: var(--warning-text, #9a3412);
}

.cycle-status-pill.pending .status-dot {
  background: #f97316;
}

.cycle-status-pill.overdue {
  background: var(--error-bg, #fee2e2);
  color: var(--error-text, #991b1b);
}

.cycle-status-pill.overdue .status-dot {
  background: #ef4444;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.cycle-amount {
  font-size: 11px;
  color: var(--muted, #64748b);
  display: block;
}

/* Financial cell */
.total-amount {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  display: block;
}

.deposit-text {
  font-size: 11px;
  color: #059669;
  font-weight: 600;
}

/* Status Badge */
.contract-status-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 8px;
  display: inline-block;
}

.contract-status-badge.active {
  background: var(--success-bg, #dcfce7);
  color: var(--success-text, #166534);
}

.contract-status-badge.pending_payment {
  background: var(--warning-bg, #ffedd5);
  color: var(--warning-text, #9a3412);
}

.contract-status-badge.overdue {
  background: var(--error-bg, #fee2e2);
  color: var(--error-text, #991b1b);
}

.contract-status-badge.expiring_soon {
  background: var(--warning-bg, #fef9c3);
  color: var(--warning-text, #854d0e);
}

.contract-status-badge.paused {
  background: var(--surface-subtle, #f1f5f9);
  color: var(--muted, #475569);
}

/* Actions Group */
.action-buttons-group {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.btn-icon-action {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-icon-action:hover {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  border-color: rgba(120, 85, 94, 0.3);
}

.btn-icon-action.text-success {
  color: #16a34a;
}

.btn-icon-action.text-warning {
  color: #d97706;
}

/* Table Footer */
.table-footer {
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid var(--border, #e2e8f0);
  background: var(--surface-subtle, #f8fafc);
  font-size: 13px;
  color: var(--muted, #64748b);
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-page,
.btn-page-arrow {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-page.active {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  border-color: rgba(120, 85, 94, 0.3);
}

.btn-page:hover:not(.active),
.btn-page-arrow:hover:not(:disabled) {
  background: var(--surface-subtle, #f1f5f9);
}

.btn-page-arrow:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* 5. Policy Card Section */
.policy-note-card {
  background: var(--surface, #ffffff);
  border: 1px dashed var(--pink-dark, #78555e);
  border-radius: 16px;
  padding: 20px 24px;
}

.policy-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.policy-card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  color: var(--pink-dark, #78555e);
}

.btn-link-sm {
  border: none;
  background: transparent;
  color: var(--pink-dark, #78555e);
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
}

.policy-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

@media (max-width: 900px) {
  .policy-grid {
    grid-template-columns: 1fr;
  }
}

.policy-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.policy-bullet {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.policy-item p {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted, #475569);
  line-height: 1.4;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-card {
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalScaleUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalScaleUp {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.modal-card.modal-lg {
  max-width: 800px;
}

.modal-card.modal-md {
  max-width: 540px;
}

.modal-header {
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border, #e2e8f0);
}

.modal-title-box {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
}

.modal-subtitle {
  font-size: 12px;
  color: var(--muted, #64748b);
}

.btn-close-modal {
  border: none;
  background: transparent;
  color: var(--muted, #64748b);
  cursor: pointer;
  padding: 4px;
}

.modal-form,
.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
}

.modal-body-scroll {
  padding: 20px 24px;
  overflow-y: auto;
  max-height: calc(80vh - 140px);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 640px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group.full-width {
  grid-column: span 2;
}

@media (max-width: 640px) {
  .form-group.full-width {
    grid-column: span 1;
  }
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.form-input,
.form-textarea {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 13px;
  background: var(--input-bg, var(--surface, #ffffff));
  color: var(--ink, #191c1d);
  outline: none;
}

.form-input:focus,
.form-textarea:focus {
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 2px var(--pink-light, #ffd9e2);
}

.checkbox-chips-group {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip-checkbox {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  background: var(--surface-subtle, #f1f5f9);
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.chip-checkbox input:checked + span {
  color: var(--pink-dark, #78555e);
  font-weight: 700;
}

.modal-footer {
  padding: 16px 24px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  border-top: 1px solid var(--border, #e2e8f0);
  background: var(--surface-subtle, #f8fafc);
}

.btn-cancel {
  padding: 9px 18px;
  border-radius: 10px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

/* Detail Modal Sections */
.contract-overview-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  background: var(--surface-subtle, #f8fafc);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 20px;
}

.overview-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.overview-label {
  font-size: 11px;
  color: var(--muted, #64748b);
  text-transform: uppercase;
  font-weight: 700;
}

.section-sub-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin-bottom: 10px;
}

.invoices-table-wrap {
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 10px;
  overflow: hidden;
}

.invoices-sub-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.invoices-sub-table th {
  background: var(--surface-subtle, #f1f5f9);
  padding: 10px 14px;
  color: var(--muted, #475569);
  font-weight: 700;
}

.invoices-sub-table td {
  padding: 12px 14px;
  border-top: 1px solid var(--border, #e2e8f0);
  color: var(--ink, #191c1d);
}

.ref-code {
  font-family: monospace;
  font-size: 11px;
  color: var(--muted, #64748b);
}

.btn-sm-action {
  padding: 4px 10px;
  border-radius: 6px;
  background: #22c55e;
  color: #ffffff;
  border: none;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.text-muted-sm {
  font-size: 11px;
  color: #059669;
  font-weight: 700;
}

.policy-block {
  margin-bottom: 14px;
}

.policy-block h4 {
  font-size: 13px;
  font-weight: 700;
  margin: 0 0 4px;
  color: var(--pink-dark, #78555e);
}

.policy-block p {
  font-size: 12px;
  color: var(--muted, #475569);
  margin: 0;
  line-height: 1.4;
}

/* ==========================================================================
   COMPREHENSIVE POLICY SETTINGS MODAL STYLES (Soft Minimalism)
   ========================================================================== */
.modal-policy-custom {
  max-width: 900px !important;
  width: 95vw;
}

.policy-nav-tabs {
  display: flex;
  background: var(--surface-subtle, rgba(255, 255, 255, 0.04));
  border: 1px solid var(--border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
  padding: 6px;
  margin: 18px 24px 4px;
  gap: 6px;
}

.policy-tab-btn {
  flex: 1 1 0;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 8px;
  border: none;
  background: transparent;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--muted, #94a3b8);
  border-radius: 10px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.policy-tab-btn .icon-18 {
  font-size: 17px;
  flex-shrink: 0;
}

.policy-tab-btn .tab-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.policy-tab-btn:hover {
  color: var(--ink, #ffffff);
  background: rgba(255, 255, 255, 0.06);
}

.policy-tab-btn.active {
  color: #2d141c !important;
  background: #ffd1dc !important;
  font-weight: 800;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.policy-content-area {
  padding: 20px 28px 28px;
  background: var(--bg, #f8f9fa);
  max-height: calc(85vh - 160px);
  overflow-y: auto;
}

.policy-tab-pane {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.policy-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 18px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 22px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.policy-card-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border, #e2e8f0);
}

.policy-card-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
  letter-spacing: -0.01em;
}

.policy-card-desc {
  font-size: 13px;
  color: var(--muted, #64748b);
  margin: 0;
  line-height: 1.45;
}

.policy-row-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 22px 28px;
  align-items: start;
}

@media (max-width: 680px) {
  .policy-row-grid {
    grid-template-columns: 1fr;
    gap: 18px;
  }
}

.policy-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.policy-label {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  line-height: 1.4;
}

.policy-hint {
  font-size: 12px;
  color: var(--muted, #64748b);
  line-height: 1.45;
  margin-top: 2px;
}

.input-with-unit {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.input-with-unit .form-input {
  width: 100%;
  height: 44px;
  padding: 0 64px 0 14px;
  border-radius: 12px;
  border: 1px solid var(--border, #cbd5e1);
  font-size: 14px;
  font-weight: 600;
  background: var(--input-bg, var(--surface, #ffffff));
  color: var(--ink, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}

.input-with-unit .form-input:focus {
  border-color: var(--pink-dark, #ffd1dc);
  box-shadow: 0 0 0 3px rgba(255, 209, 220, 0.2);
}

.unit-text {
  position: absolute;
  right: 14px;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--muted, #64748b);
  pointer-events: none;
}

/* Discount Tiers Cards */
.tiers-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tier-card-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  background: var(--surface-subtle, #f8fafc);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 14px;
}

.tier-badge-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tier-name {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink, #191c1d);
}

.tier-range {
  font-size: 12.5px;
  color: var(--muted, #64748b);
}

.tier-input-col {
  display: flex;
  align-items: center;
  gap: 12px;
}

.tier-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--muted, #64748b);
}

.input-sm {
  width: 100px !important;
  text-align: center;
  font-weight: 800;
  font-size: 15px;
}

/* Toggle Card */
.policy-toggle-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 18px 20px;
  background: var(--surface-subtle, #f8fafc);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 14px;
  margin-top: 4px;
}

.toggle-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.toggle-title {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink, #191c1d);
}

.toggle-desc {
  font-size: 12.5px;
  color: var(--muted, #64748b);
  line-height: 1.4;
}

/* Switch UI */
.switch-control {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
  flex-shrink: 0;
}

.switch-control input {
  opacity: 0;
  width: 0;
  height: 0;
}

.switch-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  border-radius: 26px;
}

.switch-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  border-radius: 50%;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.switch-control input:checked + .switch-slider {
  background-color: #e11d48;
}

.switch-control input:checked + .switch-slider:before {
  transform: translateX(22px);
}

/* Footer layout */
.modal-footer-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 28px;
  border-top: 1px solid var(--border, #e2e8f0);
  background: var(--surface, #ffffff);
}

.btn-group-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-ghost-danger {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: none;
  background: transparent;
  color: #ef4444;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border-radius: 10px;
  transition: background 0.2s;
}

.btn-ghost-danger:hover {
  background: rgba(239, 68, 68, 0.1);
}
</style>
