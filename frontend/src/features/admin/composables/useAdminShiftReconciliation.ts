import { ref, computed, onMounted } from 'vue'
import { posApi } from '@/features/pos/api/posApi'
import { adminApi } from '@/features/admin/api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Venue } from '@/features/court/types/court'

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
  cashier_email?: string
  venue_id?: number
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
  note?: string
  reconciliation_status: 'matched' | 'exception' | 'approved' | 'rejected' | string
  reviewed_by?: string | null
  reviewed_at?: string | null
  review_note?: string | null
}

const defaultShift: CashShiftItem = {
  id: 0,
  code: 'SH-NONE',
  cashier_id: 0,
  cashier_name: '',
  venue_name: '',
  shift_name: '',
  opened_at: '',
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
  reconciliation_status: 'matched',
}

export function useAdminShiftReconciliation() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const venuesList = ref<Venue[]>([])
  const activeShift = ref<CashShiftItem>({ ...defaultShift })
  const shiftHistory = ref<CashShiftItem[]>([])

  // Filters
  const searchQuery = ref('')
  const statusFilter = ref<'all' | 'open' | 'matched' | 'exception' | 'approved'>('all')
  const selectedVenueId = ref<number | 'all'>('all')
  const selectedDate = ref<string | null>(null)

  // Modals
  const showOpenShiftModal = ref(false)
  const showCloseShiftModal = ref(false)
  const showDenominationModal = ref(false)
  const showCashDropsModal = ref(false)
  const showReconciliationDetailModal = ref(false)
  const selectedShiftForReview = ref<CashShiftItem | null>(null)

  // Banknotes counter
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
    return denominations.value.reduce((sum, item) => sum + item.value * (item.count || 0), 0)
  })

  const hasActiveShift = computed(() => {
    return activeShift.value.id > 0 && activeShift.value.status === 'open'
  })

  function mapPosShift(raw: any, defaultVenueName = 'Aura Badminton Arena'): CashShiftItem {
    const rawVariance =
      raw.variance ??
      (raw.declared_cash != null && raw.expected_cash != null
        ? raw.declared_cash - raw.expected_cash
        : 0)
    const reconStatus =
      raw.reconciliation?.status ||
      (raw.status === 'open' ? 'open' : rawVariance === 0 ? 'matched' : 'exception')

    const vName = raw.venue?.name || venuesList.value[0]?.name || defaultVenueName
    const cashierName =
      raw.cashier?.name ||
      raw.user?.name ||
      (raw.cashier_id === 1 || raw.cashier_id === 3
        ? 'Aura Administrator'
        : `Thu ngân #${raw.cashier_id || 1}`)

    return {
      id: raw.id,
      code: `SH-${String(raw.id).padStart(4, '0')}`,
      cashier_id: raw.cashier_id || raw.user_id || 1,
      cashier_name: cashierName,
      cashier_email: raw.cashier?.email || raw.user?.email,
      venue_id: raw.venue_id || venuesList.value[0]?.id || 1,
      venue_name:
        typeof vName === 'object' ? vName.vi || vName.en || Object.values(vName)[0] : vName,
      shift_name:
        raw.name ||
        (raw.opened_at
          ? `Ca trực ngày ${new Date(raw.opened_at).toLocaleDateString('vi-VN')}`
          : 'Ca bán hàng'),
      opened_at: raw.opened_at || raw.created_at || new Date().toISOString(),
      closed_at: raw.closed_at || null,
      status: raw.status || (raw.closed_at ? 'closed' : 'open'),
      opening_cash: Number(raw.opening_cash || raw.initial_cash || 0),
      cash_sales: Number(raw.cash_sales || 0),
      qr_sales: Number(raw.qr_sales || 0),
      wallet_sales: Number(raw.wallet_sales || 0),
      total_sales: Number(
        raw.total_sales ||
          Number(raw.cash_sales || 0) + Number(raw.qr_sales || 0) + Number(raw.wallet_sales || 0),
      ),
      expected_cash: Number(
        raw.expected_cash || Number(raw.opening_cash || 0) + Number(raw.cash_sales || 0),
      ),
      declared_cash: Number(
        raw.declared_cash ??
          (raw.status === 'open' ? Number(raw.opening_cash || 0) + Number(raw.cash_sales || 0) : 0),
      ),
      variance: Number(rawVariance || 0),
      cash_drops_total: Number(raw.cash_drops_total || 0),
      cash_drops: raw.cash_drops || [],
      orders_count: Number(raw.orders_count || 0),
      note: raw.note || raw.reconciliation?.note,
      reconciliation_status: reconStatus,
      reviewed_by: raw.reconciliation?.reviewer?.name || raw.reviewed_by,
      reviewed_at: raw.reconciliation?.reviewed_at || raw.reviewed_at,
      review_note: raw.reconciliation?.note || raw.review_note,
    }
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [venuesRes, activeRes, historyRes] = await Promise.allSettled([
        adminApi.getVenues(),
        posApi.getActiveShift(),
        posApi.getShifts(),
      ])

      if (venuesRes.status === 'rejected') {
        error.value = errorMessage(venuesRes.reason, 'Không thể tải danh sách cơ sở.')
      } else if (venuesRes.status === 'fulfilled' && Array.isArray(venuesRes.value)) {
        venuesList.value = venuesRes.value
      }

      const defaultVenue = venuesList.value[0]?.name
        ? typeof venuesList.value[0].name === 'object'
          ? (venuesList.value[0].name as any).vi || Object.values(venuesList.value[0].name)[0]
          : venuesList.value[0].name
        : 'Aura Badminton Arena'

      if (activeRes.status === 'fulfilled' && activeRes.value) {
        activeShift.value = mapPosShift(activeRes.value, defaultVenue as string)
      } else {
        activeShift.value = { ...defaultShift }
      }

      if (historyRes.status === 'fulfilled' && Array.isArray(historyRes.value)) {
        shiftHistory.value = historyRes.value.map((item) =>
          mapPosShift(item, defaultVenue as string),
        )
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu đối soát ca làm việc.')
    } finally {
      loading.value = false
    }
  }

  const filteredHistory = computed(() => {
    return shiftHistory.value.filter((shift) => {
      // Status filter
      const matchStatus =
        statusFilter.value === 'all' ||
        (statusFilter.value === 'open' && shift.status === 'open') ||
        (statusFilter.value === 'matched' && shift.reconciliation_status === 'matched') ||
        (statusFilter.value === 'exception' &&
          (shift.reconciliation_status === 'exception' || shift.variance !== 0)) ||
        (statusFilter.value === 'approved' && shift.reconciliation_status === 'approved')

      // Venue filter
      const matchVenue = selectedVenueId.value === 'all' || shift.venue_id === selectedVenueId.value

      // Date filter
      const matchDate =
        !selectedDate.value || (shift.opened_at && shift.opened_at.startsWith(selectedDate.value))

      // Search query
      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        shift.code.toLowerCase().includes(q) ||
        shift.cashier_name.toLowerCase().includes(q) ||
        shift.venue_name.toLowerCase().includes(q) ||
        (shift.note && shift.note.toLowerCase().includes(q))

      return matchStatus && matchVenue && matchDate && matchSearch
    })
  })

  // KPI Metrics
  const activeShiftsCount = computed(() => {
    return hasActiveShift.value ? 1 : 0
  })

  const todayCashCollected = computed(() => {
    const active = hasActiveShift.value ? activeShift.value.cash_sales : 0
    const historyToday = shiftHistory.value
      .filter(
        (s) =>
          s.status === 'closed' &&
          s.closed_at &&
          s.closed_at.startsWith(new Date().toISOString().slice(0, 10)),
      )
      .reduce((sum, s) => sum + s.cash_sales, 0)
    return active + historyToday
  })

  const todayDigitalRevenue = computed(() => {
    const active = hasActiveShift.value
      ? activeShift.value.qr_sales + activeShift.value.wallet_sales
      : 0
    const historyToday = shiftHistory.value
      .filter(
        (s) =>
          s.status === 'closed' &&
          s.closed_at &&
          s.closed_at.startsWith(new Date().toISOString().slice(0, 10)),
      )
      .reduce((sum, s) => sum + s.qr_sales + s.wallet_sales, 0)
    return active + historyToday
  })

  const totalVarianceDiscrepancy = computed(() => {
    return shiftHistory.value.reduce((sum, s) => sum + Math.abs(s.variance || 0), 0)
  })

  const matchRatePercent = computed<number | null>(() => {
    const closedShifts = shiftHistory.value.filter((s) => s.status === 'closed')
    if (closedShifts.length === 0) return null
    const matchedCount = closedShifts.filter(
      (s) =>
        s.reconciliation_status === 'matched' ||
        s.reconciliation_status === 'approved' ||
        s.variance === 0,
    ).length
    return Math.round((matchedCount / closedShifts.length) * 100)
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function incrementDenom(val: number, delta: number) {
    const item = denominations.value.find((d) => d.value === val)
    if (item) {
      item.count = Math.max(0, item.count + delta)
    }
  }

  function resetDenominations() {
    denominations.value.forEach((d) => (d.count = 0))
  }

  async function openNewShift(openingCash: number) {
    saving.value = true
    error.value = ''
    try {
      const res = await posApi.openShift({ opening_cash: openingCash })
      showOpenShiftModal.value = false
      showNotice('Mở ca làm việc thành công!')
      await loadData()
      return res
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể mở ca làm việc mới.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function closeCurrentShift(declaredCash: number, note?: string) {
    if (!activeShift.value.id) return
    saving.value = true
    error.value = ''
    try {
      const res = await posApi.closeShift(activeShift.value.id, {
        declared_cash: declaredCash,
        note,
      })
      showCloseShiftModal.value = false
      showNotice('Đã đóng ca và lưu biên bản đối soát thành công!')
      await loadData()
      return res
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể đóng ca làm việc.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function reviewShiftReconciliation(shiftId: number, status: string, note?: string) {
    saving.value = true
    error.value = ''
    try {
      const res = await posApi.reviewShift(shiftId, { status, note })
      showReconciliationDetailModal.value = false
      showNotice('Đã lưu quyết định duyệt đối soát thành công!')
      await loadData()
      return res
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lưu duyệt đối soát.')
      throw e
    } finally {
      saving.value = false
    }
  }

  function addCashDrop(payload: { amount: number; reason: string; recipient?: string }) {
    const drop: ShiftCashDrop = {
      id: `CD-0${activeShift.value.cash_drops.length + 1}`,
      time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      amount: payload.amount,
      reason: payload.reason,
      created_by: activeShift.value.cashier_name || 'Thu ngân',
      recipient: payload.recipient,
    }
    activeShift.value.cash_drops.push(drop)
    activeShift.value.cash_drops_total += payload.amount
    activeShift.value.expected_cash -= payload.amount
    activeShift.value.variance = activeShift.value.declared_cash - activeShift.value.expected_cash
    showNotice(`Đã ghi nhận rút tiền két ${payload.amount.toLocaleString('vi-VN')} ₫`)
  }

  function applyCountedCashToDeclared() {
    activeShift.value.declared_cash = totalDenominationCash.value
    activeShift.value.variance = activeShift.value.declared_cash - activeShift.value.expected_cash
    showDenominationModal.value = false
    showNotice(
      `Đã cập nhật số tiền đếm ${totalDenominationCash.value.toLocaleString('vi-VN')} ₫ vào tiền két!`,
    )
  }

  function openReviewModal(shift: CashShiftItem) {
    selectedShiftForReview.value = shift
    showReconciliationDetailModal.value = true
  }

  onMounted(() => {
    loadData()
  })

  return {
    loading,
    saving,
    notice,
    error,
    venuesList,
    activeShift,
    shiftHistory,
    hasActiveShift,
    searchQuery,
    statusFilter,
    selectedVenueId,
    selectedDate,
    showOpenShiftModal,
    showCloseShiftModal,
    showDenominationModal,
    showCashDropsModal,
    showReconciliationDetailModal,
    selectedShiftForReview,
    denominations,
    totalDenominationCash,
    filteredHistory,
    activeShiftsCount,
    todayCashCollected,
    todayDigitalRevenue,
    totalVarianceDiscrepancy,
    matchRatePercent,
    loadData,
    showNotice,
    incrementDenom,
    resetDenominations,
    openNewShift,
    closeCurrentShift,
    reviewShiftReconciliation,
    addCashDrop,
    applyCountedCashToDeclared,
    openReviewModal,
  }
}
