import { ref, computed } from 'vue'

export interface DenominationCount {
  value: number
  count: number
}

export interface ShiftCashDrop {
  id: string
  time: string
  amount: number
  reason: string
  created_by: string
  recipient?: string
}

export interface CashShiftItem {
  id: number
  code: string
  cashier_id: number
  cashier_name: string
  cashier_avatar?: string
  venue_name: string
  shift_name: string
  opened_at: string
  closed_at?: string | null
  status: 'open' | 'closed'
  opening_cash: number
  cash_sales: number
  qr_sales: number
  wallet_sales: number
  total_sales: number
  expected_cash: number
  declared_cash: number
  variance: number
  cash_drops_total: number
  cash_drops: ShiftCashDrop[]
  orders_count: number
  court_orders_count: number
  pos_orders_count: number
  note?: string
  variance_reason?: string
  reconciliation_status: 'matched' | 'exception_short' | 'exception_over' | 'pending_review' | 'approved' | 'rejected'
  reviewed_by?: string | null
  reviewed_at?: string | null
  review_note?: string | null
}

export function useAdminShiftReconciliation() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const searchQuery = ref('')
  const statusFilter = ref<'all' | 'open' | 'matched' | 'exception' | 'approved'>('all')

  const activeShift = ref<CashShiftItem>({
    id: 89,
    code: 'SH-2026-089',
    cashier_id: 1,
    cashier_name: 'Nguyễn Văn A',
    venue_name: 'Aura Central Arena - Quầy Thu Ngân 01',
    shift_name: 'Ca Sáng (06:00 - 14:00)',
    opened_at: '2026-08-30 06:00:00',
    closed_at: null,
    status: 'open',
    opening_cash: 1500000,
    cash_sales: 4250000,
    qr_sales: 12800000,
    wallet_sales: 3500000,
    total_sales: 20550000,
    expected_cash: 5550000,
    declared_cash: 5500000,
    variance: -50000,
    cash_drops_total: 200000,
    cash_drops: [
      {
        id: 'CD-01',
        time: '08:30',
        amount: 200000,
        reason: 'Mua đá lạnh & túi rác quầy F&B',
        created_by: 'Nguyễn Văn A',
        recipient: 'Tạp hóa Minh Tâm',
      },
    ],
    orders_count: 42,
    court_orders_count: 28,
    pos_orders_count: 14,
    note: 'Két có đủ tiền mệnh giá nhỏ 10k, 20k, 50k phục vụ trả tiền lẻ cho khách đầu ngày.',
    variance_reason: 'Khách thanh toán đơn POS F&B thiếu 50k tiền mặt, hẹn trả bù ca chiều khi vào sân thi đấu.',
    reconciliation_status: 'exception_short',
  })

  const shiftHistory = ref<CashShiftItem[]>([
    {
      id: 88,
      code: 'SH-2026-088',
      cashier_id: 2,
      cashier_name: 'Trần Thị B',
      venue_name: 'Aura Central Arena - Quầy 02',
      shift_name: 'Ca Tối (14:00 - 22:00)',
      opened_at: '2026-08-29 14:00:00',
      closed_at: '2026-08-29 22:15:00',
      status: 'closed',
      opening_cash: 2000000,
      cash_sales: 8500000,
      qr_sales: 24600000,
      wallet_sales: 6200000,
      total_sales: 39300000,
      expected_cash: 10500000,
      declared_cash: 10500000,
      variance: 0,
      cash_drops_total: 0,
      cash_drops: [],
      orders_count: 68,
      court_orders_count: 45,
      pos_orders_count: 23,
      reconciliation_status: 'matched',
      reviewed_by: 'Admin Elena',
      reviewed_at: '2026-08-29 22:30:00',
    },
    {
      id: 87,
      code: 'SH-2026-087',
      cashier_id: 3,
      cashier_name: 'Lê Văn C',
      venue_name: 'Westside Courts',
      shift_name: 'Ca Sáng (06:00 - 14:00)',
      opened_at: '2026-08-29 06:00:00',
      closed_at: '2026-08-29 14:05:00',
      status: 'closed',
      opening_cash: 1500000,
      cash_sales: 3800000,
      qr_sales: 9400000,
      wallet_sales: 2100000,
      total_sales: 15300000,
      expected_cash: 5300000,
      declared_cash: 5320000,
      variance: 20000,
      cash_drops_total: 0,
      cash_drops: [],
      orders_count: 32,
      court_orders_count: 20,
      pos_orders_count: 12,
      variance_reason: 'Khách tip thêm 20k tiền thối.',
      reconciliation_status: 'approved',
      reviewed_by: 'Admin Elena',
      reviewed_at: '2026-08-29 15:00:00',
    },
  ])

  const denominations = ref<DenominationCount[]>([
    { value: 500000, count: 6 },
    { value: 200000, count: 8 },
    { value: 100000, count: 7 },
    { value: 50000, count: 3 },
    { value: 20000, count: 4 },
    { value: 10000, count: 6 },
    { value: 5000, count: 2 },
    { value: 2000, count: 0 },
    { value: 1000, count: 0 },
  ])

  const totalDenominationCash = computed(() => {
    return denominations.value.reduce((sum, item) => sum + item.value * item.count, 0)
  })

  const filteredHistory = computed(() => {
    return shiftHistory.value.filter((shift) => {
      const matchStatus =
        statusFilter.value === 'all' ||
        (statusFilter.value === 'open' && shift.status === 'open') ||
        (statusFilter.value === 'matched' && shift.reconciliation_status === 'matched') ||
        (statusFilter.value === 'exception' && (shift.reconciliation_status === 'exception_short' || shift.reconciliation_status === 'exception_over')) ||
        (statusFilter.value === 'approved' && shift.reconciliation_status === 'approved')

      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        shift.code.toLowerCase().includes(q) ||
        shift.cashier_name.toLowerCase().includes(q) ||
        shift.venue_name.toLowerCase().includes(q)

      return matchStatus && matchSearch
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function addCashDrop(payload: { amount: number; reason: string; recipient?: string }) {
    const drop: ShiftCashDrop = {
      id: `CD-0${activeShift.value.cash_drops.length + 1}`,
      time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      amount: payload.amount,
      reason: payload.reason,
      created_by: activeShift.value.cashier_name,
      recipient: payload.recipient,
    }
    activeShift.value.cash_drops.push(drop)
    activeShift.value.cash_drops_total += payload.amount
    activeShift.value.expected_cash -= payload.amount
    activeShift.value.variance = activeShift.value.declared_cash - activeShift.value.expected_cash
    showNotice(`Đã ghi nhận rút tiền két ${payload.amount.toLocaleString('vi-VN')} ₫`)
  }

  return {
    loading,
    saving,
    notice,
    error,
    searchQuery,
    statusFilter,
    activeShift,
    shiftHistory,
    denominations,
    totalDenominationCash,
    filteredHistory,
    showNotice,
    addCashDrop,
  }
}
