import { ref, computed, onMounted } from 'vue'
import { posApi } from '@/features/pos/api/posApi'
import { errorMessage } from '@/core/utils/errorMessage'

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

const defaultShift: CashShiftItem = {
  id: 0,
  code: 'SH-NONE',
  cashier_id: 0,
  cashier_name: 'Chưa mở ca',
  venue_name: 'Cơ sở chính',
  shift_name: 'Chưa có ca làm việc',
  opened_at: new Date().toISOString(),
  closed_at: null,
  status: 'closed',
  opening_cash: 0,
  cash_sales: 0,
  qr_sales: 0,
  wallet_sales: 0,
  total_sales: 0,
  expected_cash: 0,
  declared_cash: 0,
  variance: 0,
  cash_drops_total: 0,
  cash_drops: [],
  orders_count: 0,
  court_orders_count: 0,
  pos_orders_count: 0,
  reconciliation_status: 'matched',
}

export function useAdminShiftReconciliation() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const searchQuery = ref('')
  const statusFilter = ref<'all' | 'open' | 'matched' | 'exception' | 'approved'>('all')

  const activeShift = ref<CashShiftItem>({ ...defaultShift })
  const shiftHistory = ref<CashShiftItem[]>([])

  const denominations = ref<DenominationCount[]>([
    { value: 500000, count: 0 },
    { value: 200000, count: 0 },
    { value: 100000, count: 0 },
    { value: 50000, count: 0 },
    { value: 20000, count: 0 },
    { value: 10000, count: 0 },
    { value: 5000, count: 0 },
    { value: 2000, count: 0 },
    { value: 1000, count: 0 },
  ])

  const totalDenominationCash = computed(() => {
    return denominations.value.reduce((sum, item) => sum + item.value * item.count, 0)
  })

  function mapPosShift(raw: any): CashShiftItem {
    return {
      id: raw.id,
      code: raw.code || `SH-${raw.id}`,
      cashier_id: raw.user_id || raw.cashier_id || 1,
      cashier_name: raw.user?.name || raw.cashier_name || 'Thu ngân',
      venue_name: raw.venue?.name || 'Aura Central Arena',
      shift_name: raw.name || 'Ca làm việc',
      opened_at: raw.opened_at || raw.created_at || new Date().toISOString(),
      closed_at: raw.closed_at || null,
      status: raw.status || (raw.closed_at ? 'closed' : 'open'),
      opening_cash: Number(raw.opening_cash || raw.initial_cash || 0),
      cash_sales: Number(raw.cash_sales || 0),
      qr_sales: Number(raw.qr_sales || 0),
      wallet_sales: Number(raw.wallet_sales || 0),
      total_sales: Number(raw.total_sales || 0),
      expected_cash: Number(raw.expected_cash || 0),
      declared_cash: Number(raw.declared_cash || 0),
      variance: Number(raw.variance || 0),
      cash_drops_total: Number(raw.cash_drops_total || 0),
      cash_drops: raw.cash_drops || [],
      orders_count: raw.orders_count || 0,
      court_orders_count: raw.court_orders_count || 0,
      pos_orders_count: raw.pos_orders_count || 0,
      note: raw.note,
      variance_reason: raw.variance_reason,
      reconciliation_status: raw.reconciliation_status || (raw.variance === 0 ? 'matched' : 'exception_short'),
      reviewed_by: raw.reviewed_by,
      reviewed_at: raw.reviewed_at,
    }
  }

  async function loadShifts() {
    loading.value = true
    error.value = ''
    try {
      const [activeRes, historyRes] = await Promise.allSettled([
        posApi.getActiveShift(),
        posApi.getShifts(),
      ])

      if (activeRes.status === 'fulfilled' && activeRes.value) {
        activeShift.value = mapPosShift(activeRes.value)
      }
      if (historyRes.status === 'fulfilled' && Array.isArray(historyRes.value)) {
        shiftHistory.value = historyRes.value.map(mapPosShift)
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu ca làm việc.')
    } finally {
      loading.value = false
    }
  }

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

  onMounted(() => {
    loadShifts()
  })

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
    loadShifts,
  }
}
