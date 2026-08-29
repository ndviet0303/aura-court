<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { posApi } from '@/features/pos/api/posApi'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

// Interfaces
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

// State
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const errorMessage = ref('')
const liveClock = ref(new Date().toLocaleTimeString('vi-VN'))
let clockInterval: any = null

// Filter & Search State
const searchQuery = ref('')
const statusFilter = ref<'all' | 'open' | 'matched' | 'exception' | 'approved'>('all')

// Current Active Shift
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

// Denomination Counter State
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

const showDenominationPanel = ref(false)
const manualActualCash = ref<number>(5500000)
const liveVarianceReason = ref(activeShift.value.variance_reason || '')

// Modals
const showOpenShiftModal = ref(false)
const showCloseShiftModal = ref(false)
const showCashDropModal = ref(false)
const showHandoverPrintModal = ref(false)
const showShiftDetailModal = ref(false)
const showReviewModal = ref(false)
const selectedShift = ref<CashShiftItem | null>(null)

// Forms
const openShiftForm = ref({
  cashier_name: 'Nguyễn Văn A',
  venue_name: 'Aura Central Arena - Quầy Thu Ngân 01',
  shift_name: 'Ca Chiều (14:00 - 22:00)',
  opening_cash: 1500000,
  note: 'Két tiền lẻ tiêu chuẩn 1.500.000 ₫',
})

const closeShiftForm = ref({
  declared_cash: 5500000,
  variance_reason: 'Khách nợ 50k hẹn trả vào ca chiều.',
  handover_to: 'Trần Thị B (Nhân viên ca chiều)',
  manager_witness: 'Lê Quản Lý',
  note: '',
})

const cashDropForm = ref({
  amount: 200000,
  reason: 'Mua đá lạnh & đồ dùng vệ sinh sân',
  recipient: 'Đại lý Minh Tâm',
  receipt_code: 'HD-8891',
})

const reviewForm = ref({
  status: 'approved' as 'approved' | 'exception_short' | 'rejected',
  note: 'Đã xác nhận chênh lệch 50.000 ₫ do khách nợ đơn POS. Cho phép bàn giao ca.',
})

// Shift History List
const shiftHistory = ref<CashShiftItem[]>([
  {
    id: 88,
    code: 'SH-2026-088',
    cashier_id: 2,
    cashier_name: 'Trần Thị B',
    venue_name: 'Aura Central Arena - Quầy 01',
    shift_name: 'Ca Tối (22:00 - 06:00)',
    opened_at: '2026-08-29 22:00:00',
    closed_at: '2026-08-30 06:00:00',
    status: 'closed',
    opening_cash: 1500000,
    cash_sales: 4700000,
    qr_sales: 13800000,
    wallet_sales: 2800000,
    total_sales: 21300000,
    expected_cash: 6200000,
    declared_cash: 6200000,
    variance: 0,
    cash_drops_total: 0,
    cash_drops: [],
    orders_count: 36,
    court_orders_count: 24,
    pos_orders_count: 12,
    note: 'Giao ca thuận lợi, đủ biên lai POS và VietQR.',
    variance_reason: '',
    reconciliation_status: 'matched',
    reviewed_by: 'Lê Quản Lý',
    reviewed_at: '2026-08-30 06:15:00',
    review_note: 'Khớp 100% không phát sinh lệch két.',
  },
  {
    id: 87,
    code: 'SH-2026-087',
    cashier_id: 3,
    cashier_name: 'Lê Văn C',
    venue_name: 'Aura Central Arena - Quầy 01',
    shift_name: 'Ca Chiều (14:00 - 22:00)',
    opened_at: '2026-08-29 14:00:00',
    closed_at: '2026-08-29 22:00:00',
    status: 'closed',
    opening_cash: 1500000,
    cash_sales: 2600000,
    qr_sales: 9600000,
    wallet_sales: 1500000,
    total_sales: 13700000,
    expected_cash: 4100000,
    declared_cash: 4080000,
    variance: -20000,
    cash_drops_total: 0,
    cash_drops: [],
    orders_count: 29,
    court_orders_count: 20,
    pos_orders_count: 9,
    note: 'Đông khách giờ cao điểm 18h-20h.',
    variance_reason: 'Thối nhầm 20k cho khách sân 4 đơn nước.',
    reconciliation_status: 'approved',
    reviewed_by: 'Lê Quản Lý',
    reviewed_at: '2026-08-29 22:20:00',
    review_note: 'Đã nhắc nhở kiểm đếm tiền cẩn thận.',
  },
  {
    id: 86,
    code: 'SH-2026-086',
    cashier_id: 1,
    cashier_name: 'Nguyễn Văn A',
    venue_name: 'Aura Central Arena - Quầy 01',
    shift_name: 'Ca Sáng (06:00 - 14:00)',
    opened_at: '2026-08-29 06:00:00',
    closed_at: '2026-08-29 14:00:00',
    status: 'closed',
    opening_cash: 1500000,
    cash_sales: 3800000,
    qr_sales: 11200000,
    wallet_sales: 3200000,
    total_sales: 18200000,
    expected_cash: 5300000,
    declared_cash: 5300000,
    variance: 0,
    cash_drops_total: 0,
    cash_drops: [],
    orders_count: 38,
    court_orders_count: 26,
    pos_orders_count: 12,
    note: 'Ca sáng hoạt động ổn định.',
    reconciliation_status: 'matched',
    reviewed_by: 'Lê Quản Lý',
    reviewed_at: '2026-08-29 14:10:00',
  },
])

// Helpers
function money(val: number): string {
  return (val || 0).toLocaleString('vi-VN') + ' ₫'
}

const calculatedDenominationCash = computed(() => {
  return denominations.value.reduce((acc, item) => acc + item.value * item.count, 0)
})

const currentExpectedCash = computed(() => {
  if (!activeShift.value) return 0
  return (
    activeShift.value.opening_cash +
    activeShift.value.cash_sales -
    activeShift.value.cash_drops_total
  )
})

const liveVariance = computed(() => {
  return manualActualCash.value - currentExpectedCash.value
})

const cashlessRatio = computed(() => {
  const nonCash = (activeShift.value?.qr_sales || 0) + (activeShift.value?.wallet_sales || 0)
  const total = activeShift.value?.total_sales || 1
  return Math.min(100, Math.round((nonCash / total) * 100))
})

const filteredHistory = computed(() => {
  return shiftHistory.value.filter((item) => {
    const q = searchQuery.value.toLowerCase().trim()
    const matchesSearch =
      !q ||
      item.code.toLowerCase().includes(q) ||
      item.cashier_name.toLowerCase().includes(q) ||
      item.shift_name.toLowerCase().includes(q)

    let matchesStatus = true
    if (statusFilter.value === 'open') matchesStatus = item.status === 'open'
    else if (statusFilter.value === 'matched') matchesStatus = item.reconciliation_status === 'matched'
    else if (statusFilter.value === 'exception')
      matchesStatus =
        item.reconciliation_status === 'exception_short' ||
        item.reconciliation_status === 'exception_over'
    else if (statusFilter.value === 'approved') matchesStatus = item.reconciliation_status === 'approved'

    return matchesSearch && matchesStatus
  })
})

// Actions
function applyDenominationTotal() {
  manualActualCash.value = calculatedDenominationCash.value
  showDenominationPanel.value = false
  notice.value = `Đã áp dụng tổng tiền kiểm đếm: ${money(manualActualCash.value)}`
  setTimeout(() => (notice.value = ''), 4000)
}

function adjustDenom(denom: DenominationCount, delta: number) {
  denom.count = Math.max(0, denom.count + delta)
}

async function handleOpenShift() {
  saving.value = true
  try {
    try {
      await posApi.openShift({ opening_cash: openShiftForm.value.opening_cash })
    } catch {}

    const newShift: CashShiftItem = {
      id: Date.now(),
      code: `SH-2026-0${shiftHistory.value.length + 90}`,
      cashier_id: auth.user?.id || 1,
      cashier_name: auth.user?.name || openShiftForm.value.cashier_name,
      venue_name: openShiftForm.value.venue_name,
      shift_name: openShiftForm.value.shift_name,
      opened_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
      closed_at: null,
      status: 'open',
      opening_cash: openShiftForm.value.opening_cash,
      cash_sales: 0,
      qr_sales: 0,
      wallet_sales: 0,
      total_sales: 0,
      expected_cash: openShiftForm.value.opening_cash,
      declared_cash: openShiftForm.value.opening_cash,
      variance: 0,
      cash_drops_total: 0,
      cash_drops: [],
      orders_count: 0,
      court_orders_count: 0,
      pos_orders_count: 0,
      note: openShiftForm.value.note,
      reconciliation_status: 'matched',
    }

    activeShift.value = newShift
    manualActualCash.value = newShift.opening_cash
    showOpenShiftModal.value = false
    notice.value = `Đã mở ca mới (${newShift.shift_name}) thành công!`
  } catch (err: any) {
    errorMessage.value = err.message || 'Lỗi mở ca trực'
  } finally {
    saving.value = false
    setTimeout(() => {
      notice.value = ''
      errorMessage.value = ''
    }, 4000)
  }
}

async function handleCloseShift() {
  if (!activeShift.value) return
  saving.value = true
  try {
    const finalDeclared = closeShiftForm.value.declared_cash
    const variance = finalDeclared - currentExpectedCash.value

    try {
      await posApi.closeShift(activeShift.value.id, {
        declared_cash: finalDeclared,
        note: closeShiftForm.value.note + (closeShiftForm.value.variance_reason ? ` | Lý do: ${closeShiftForm.value.variance_reason}` : ''),
      })
    } catch {}

    const closedItem: CashShiftItem = {
      ...activeShift.value,
      status: 'closed',
      closed_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
      declared_cash: finalDeclared,
      variance: variance,
      variance_reason: closeShiftForm.value.variance_reason,
      reconciliation_status:
        variance === 0
          ? 'matched'
          : variance < 0
          ? 'exception_short'
          : 'exception_over',
      note: closeShiftForm.value.note,
    }

    shiftHistory.value.unshift(closedItem)
    activeShift.value = closedItem
    showCloseShiftModal.value = false
    notice.value = `Đã đóng ca thành công! Tiền khai báo: ${money(finalDeclared)}`
  } catch (err: any) {
    errorMessage.value = err.message || 'Lỗi đóng ca'
  } finally {
    saving.value = false
    setTimeout(() => {
      notice.value = ''
      errorMessage.value = ''
    }, 5000)
  }
}

function handleAddCashDrop() {
  if (cashDropForm.value.amount <= 0) return
  const drop: ShiftCashDrop = {
    id: `CD-${String(activeShift.value.cash_drops.length + 1).padStart(2, '0')}`,
    time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
    amount: cashDropForm.value.amount,
    reason: cashDropForm.value.reason,
    created_by: auth.user?.name || 'Nguyễn Văn A',
    recipient: cashDropForm.value.recipient,
  }

  activeShift.value.cash_drops.push(drop)
  activeShift.value.cash_drops_total += drop.amount
  activeShift.value.expected_cash = currentExpectedCash.value

  showCashDropModal.value = false
  notice.value = `Đã ghi nhận phiếu chi két ${money(drop.amount)}`
  setTimeout(() => (notice.value = ''), 4000)
}

function openReviewShift(shift: CashShiftItem) {
  selectedShift.value = shift
  reviewForm.value.status = shift.reconciliation_status === 'matched' ? 'approved' : 'approved'
  reviewForm.value.note = shift.review_note || 'Đã kiểm tra chứng từ và biên lai hợp lệ.'
  showReviewModal.value = true
}

function handleSaveReview() {
  if (!selectedShift.value) return
  selectedShift.value.reconciliation_status = reviewForm.value.status
  selectedShift.value.reviewed_by = auth.user?.name || 'Lê Quản Lý'
  selectedShift.value.reviewed_at = new Date().toISOString().replace('T', ' ').substring(0, 19)
  selectedShift.value.review_note = reviewForm.value.note
  showReviewModal.value = false
  notice.value = `Quản lý đã ký duyệt đối soát ca ${selectedShift.value.code}!`
  setTimeout(() => (notice.value = ''), 4000)
}

function openShiftDetail(shift: CashShiftItem) {
  selectedShift.value = shift
  showShiftDetailModal.value = true
}

function printReport(shift?: CashShiftItem) {
  selectedShift.value = shift || activeShift.value
  showHandoverPrintModal.value = true
}

function executeBrowserPrint() {
  window.print()
}

onMounted(() => {
  clockInterval = setInterval(() => {
    liveClock.value = new Date().toLocaleTimeString('vi-VN')
  }, 1000)
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
})
</script>

<template>
  <div class="shift-management-view">
    <!-- Notice Toast -->
    <div v-if="notice" class="toast-notice">
      <span class="material-symbols-outlined text-pink">check_circle</span>
      <span>{{ notice }}</span>
    </div>

    <div v-if="errorMessage" class="toast-error">
      <span class="material-symbols-outlined">error</span>
      <span>{{ errorMessage }}</span>
    </div>

    <!-- Top Breadcrumb & Status Bar -->
    <div class="top-nav-bar">
      <div class="top-nav-inner">
        <div class="breadcrumb-trail">
          <span>AURA CONTROL</span>
          <span class="material-symbols-outlined nav-arrow">chevron_right</span>
          <span>BÁN LẺ & THU NGÂN</span>
          <span class="material-symbols-outlined nav-arrow">chevron_right</span>
          <span class="trail-active">QUẢN LÝ CA & ĐỐI SOÁT</span>
        </div>

        <div class="top-status-pills">
          <div class="status-chip system-time">
            <span class="material-symbols-outlined icon-16">schedule</span>
            <span>Thời gian hệ thống: <strong>{{ liveClock }}</strong></span>
          </div>
          <div class="status-chip venue-chip">
            <span class="material-symbols-outlined icon-16">storefront</span>
            <span>{{ activeShift.venue_name }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Container -->
    <div class="main-page-container">
      <!-- HERO HEADER SECTION -->
      <section class="shift-hero-header">
        <div class="hero-title-meta">
          <div class="hero-badges-row">
            <span
              class="active-status-badge"
              :class="activeShift.status === 'open' ? 'status-open' : 'status-closed'"
            >
              <span class="pulse-indicator" :class="{ 'pulse-active': activeShift.status === 'open' }"></span>
              {{ activeShift.status === 'open' ? 'Ca Đang Hoạt Động' : 'Đã Đóng Ca' }}
            </span>
            <span class="shift-code-badge">Mã ca: #{{ activeShift.code }}</span>
          </div>

          <h1 class="hero-page-title">Quản lý Ca & Đối Soát Tiền Mặt</h1>

          <div class="hero-info-line">
            <span>Nhân viên trực: <strong>{{ activeShift.cashier_name }}</strong></span>
            <span class="bullet">•</span>
            <span>Bắt đầu: <strong>{{ activeShift.opened_at }}</strong></span>
            <span class="bullet">•</span>
            <span>Ca làm việc: <strong>{{ activeShift.shift_name }}</strong></span>
          </div>
        </div>

        <!-- Action CTAs -->
        <div class="hero-action-buttons">
          <button type="button" class="btn-action btn-outline" @click="showCashDropModal = true">
            <span class="material-symbols-outlined">payments</span>
            <span>Phiếu Chi / Rút Két</span>
          </button>

          <button type="button" class="btn-action btn-outline" @click="printReport(activeShift)">
            <span class="material-symbols-outlined">print</span>
            <span>In Báo Cáo</span>
          </button>

          <button
            v-if="activeShift.status === 'open'"
            type="button"
            class="btn-action btn-primary"
            @click="showCloseShiftModal = true"
          >
            <span class="material-symbols-outlined">lock</span>
            <span>Đóng Ca & Quyết Toán</span>
          </button>

          <button
            v-else
            type="button"
            class="btn-action btn-primary"
            @click="showOpenShiftModal = true"
          >
            <span class="material-symbols-outlined">add_circle</span>
            <span>Mở Ca Mới</span>
          </button>
        </div>
      </section>

      <!-- 12-COLUMN DASHBOARD GRID -->
      <div class="reconciliation-dashboard-grid">
        <!-- LEFT COLUMN: Opening balance + Cash Reconciliation (8 Cols) -->
        <div class="left-reconciliation-col">
          <!-- CARD 1: OPENING BALANCE -->
          <div class="surface-card opening-balance-card">
            <div class="card-header-row">
              <div class="card-title-group">
                <div class="icon-avatar pink-avatar">
                  <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
                <div>
                  <h2 class="card-main-title">Tiền Đầu Ca (Opening Balance)</h2>
                  <p class="card-sub-desc">Số tiền két ban đầu được bàn giao và xác nhận khi mở ca</p>
                </div>
              </div>

              <span class="status-pill declared-pill">
                <span class="material-symbols-outlined icon-14">check</span>
                Đã Khai Báo
              </span>
            </div>

            <div class="opening-cash-display-box">
              <div class="cash-value-block">
                <span class="label-tiny">SỐ TIỀN TRONG KÉT ĐẦU CA</span>
                <div class="large-currency-text">{{ money(activeShift.opening_cash) }}</div>
                <p class="cash-note-text">{{ activeShift.note || 'Két tiền lẻ tiêu chuẩn' }}</p>
              </div>

              <div class="cash-action-block">
                <button type="button" class="btn-action-small" @click="showOpenShiftModal = true">
                  <span class="material-symbols-outlined icon-16">edit</span>
                  <span>Chỉnh sửa ca</span>
                </button>
              </div>
            </div>
          </div>

          <!-- CARD 2: RECONCILIATION & ACTUAL COUNT -->
          <div class="surface-card reconciliation-card">
            <div class="card-ambient-glow"></div>

            <div class="card-header-row relative-z">
              <div>
                <h2 class="card-main-title">Đối Soát Tiền Mặt (Reconciliation)</h2>
                <p class="card-sub-desc">So sánh doanh thu ghi nhận trên hệ thống và số tiền mặt thực tế trong két</p>
              </div>

              <button
                type="button"
                class="btn-toggle-denom"
                @click="showDenominationPanel = !showDenominationPanel"
              >
                <span class="material-symbols-outlined icon-18">calculate</span>
                <span>{{ showDenominationPanel ? 'Ẩn Bảng Mệnh Giá' : 'Đếm Mệnh Giá Tiền' }}</span>
              </button>
            </div>

            <!-- Denomination Counter Drawer -->
            <div v-if="showDenominationPanel" class="denomination-calculator-panel">
              <div class="panel-head">
                <div class="panel-head-title">
                  <span class="material-symbols-outlined">pin</span>
                  <span>Bảng Kê Mệnh Giá Tiền Mặt Thực Tế</span>
                </div>
                <div class="panel-head-total">
                  Tổng đếm: <strong>{{ money(calculatedDenominationCash) }}</strong>
                </div>
              </div>

              <div class="denomination-grid">
                <div v-for="d in denominations" :key="d.value" class="denom-box">
                  <span class="denom-label">{{ money(d.value) }}</span>
                  <div class="stepper-row">
                    <button type="button" class="btn-step" @click="adjustDenom(d, -1)">-</button>
                    <input v-model.number="d.count" type="number" min="0" class="step-input" />
                    <button type="button" class="btn-step plus" @click="adjustDenom(d, 1)">+</button>
                  </div>
                  <span class="denom-subtotal">{{ money(d.value * d.count) }}</span>
                </div>
              </div>

              <div class="panel-actions">
                <button type="button" class="btn-apply-denom" @click="applyDenominationTotal">
                  Áp Dụng Vào Số Thực Tế ({{ money(calculatedDenominationCash) }})
                </button>
              </div>
            </div>

            <!-- Two-Column Calculation Area -->
            <div class="reconciliation-split-grid relative-z">
              <!-- Column 1: System Recorded Revenue -->
              <div class="system-revenue-box">
                <div class="box-section-title">
                  <span class="material-symbols-outlined icon-16">monitoring</span>
                  <span>Doanh thu Hệ thống (Ghi nhận)</span>
                </div>

                <div class="revenue-line-items">
                  <div class="rev-row">
                    <span class="rev-label">
                      <span class="material-symbols-outlined icon-16 color-cash">payments</span>
                      Thu tiền mặt (POS/Sân)
                    </span>
                    <span class="rev-val">{{ money(activeShift.cash_sales) }}</span>
                  </div>

                  <div class="rev-row">
                    <span class="rev-label">
                      <span class="material-symbols-outlined icon-16 color-qr">qr_code_2</span>
                      Chuyển khoản / VietQR
                    </span>
                    <span class="rev-val">{{ money(activeShift.qr_sales) }}</span>
                  </div>

                  <div class="rev-row">
                    <span class="rev-label">
                      <span class="material-symbols-outlined icon-16 color-wallet">wallet</span>
                      Ví nội bộ Aura Club
                    </span>
                    <span class="rev-val">{{ money(activeShift.wallet_sales) }}</span>
                  </div>

                  <div v-if="activeShift.cash_drops_total > 0" class="rev-row rev-expense">
                    <span class="rev-label">
                      <span class="material-symbols-outlined icon-16">output</span>
                      Phiếu chi / Rút két
                    </span>
                    <span class="rev-val">- {{ money(activeShift.cash_drops_total) }}</span>
                  </div>
                </div>

                <div class="expected-total-banner">
                  <div class="banner-title-box">
                    <span class="banner-title">Tổng tiền mặt kỳ vọng</span>
                    <span class="banner-sub">(Két đầu ca + Thu mặt - Chi két)</span>
                  </div>
                  <div class="banner-amount">{{ money(currentExpectedCash) }}</div>
                </div>
                <p class="expected-footnote">* Đã bao gồm tiền đầu ca ({{ money(activeShift.opening_cash) }}).</p>
              </div>

              <!-- Column 2: Actual Count & Variance -->
              <div class="actual-count-box">
                <div class="box-section-title">
                  <span class="material-symbols-outlined icon-16">fact_check</span>
                  <span>Tiền mặt thực tế (Actual Count)</span>
                </div>

                <div class="actual-input-wrapper">
                  <label class="input-label-sm">Nhập số tiền kiểm đếm trong két (VND)</label>
                  <div class="input-with-suffix">
                    <input
                      v-model.number="manualActualCash"
                      type="number"
                      step="10000"
                      class="actual-currency-input"
                      placeholder="Nhập số tiền..."
                    />
                    <span class="input-suffix">VND</span>
                  </div>
                </div>

                <!-- Variance Display Box -->
                <div
                  class="variance-result-card"
                  :class="{
                    'var-balanced': liveVariance === 0,
                    'var-deficit': liveVariance < 0,
                    'var-surplus': liveVariance > 0,
                  }"
                >
                  <span class="var-badge-title">
                    {{ liveVariance === 0 ? 'Khớp 100% (Balanced)' : liveVariance < 0 ? 'Chênh Lệch Thiếu (Deficit)' : 'Chênh Lệch Thừa (Surplus)' }}
                  </span>
                  <div class="var-amount-display">
                    {{ liveVariance > 0 ? '+' : '' }}{{ money(liveVariance) }}
                  </div>
                  <p class="var-desc-text">
                    {{
                      liveVariance === 0
                        ? 'Số tiền thực tế trùng khớp hoàn toàn với doanh thu hệ thống.'
                        : liveVariance < 0
                        ? 'Két tiền thực tế thiếu hụt so với doanh thu ghi nhận.'
                        : 'Két tiền thực tế dôi dư so với doanh thu ghi nhận.'
                    }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Variance Notes -->
            <div class="variance-notes-section relative-z">
              <div class="notes-header-row">
                <label class="notes-label">Biên bản giải trình & ghi nhận chênh lệch</label>
                <div class="quick-tags-list">
                  <button
                    type="button"
                    class="quick-tag-btn"
                    @click="liveVarianceReason = 'Khách thanh toán thiếu tiền mặt, hẹn trả bù sau.'"
                  >
                    + Khách hẹn bù
                  </button>
                  <button
                    type="button"
                    class="quick-tag-btn"
                    @click="liveVarianceReason = 'Trả nhầm tiền thối cho khách giờ cao điểm.'"
                  >
                    + Thối nhầm tiền
                  </button>
                  <button
                    type="button"
                    class="quick-tag-btn"
                    @click="liveVarianceReason = 'Chi tiền mua đá lạnh và dụng cụ vệ sinh khẩn cấp.'"
                  >
                    + Chi khẩn cấp
                  </button>
                </div>
              </div>

              <textarea
                v-model="liveVarianceReason"
                rows="3"
                class="notes-textarea"
                placeholder="Nhập lý do chi tiết về khoản chênh lệch hoặc ghi chú bàn giao ca..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Payment Breakdown Visuals & KPIs (4 Cols) -->
        <div class="right-stats-col">
          <!-- CARD 1: PAYMENT METHOD VISUAL -->
          <div class="surface-card payment-method-card">
            <div class="card-header-row">
              <span class="card-mini-title">CƠ CẤU THANH TOÁN</span>
              <span class="badge-growth">+12% Non-cash</span>
            </div>

            <div class="circular-radar-wrapper">
              <svg class="radar-svg" viewBox="0 0 100 100">
                <circle class="radar-circle-outer" cx="50" cy="50" r="44"></circle>
                <circle class="radar-circle-inner" cx="50" cy="50" r="34"></circle>
              </svg>
              <div class="radar-inner-text">
                <span class="radar-percentage">{{ cashlessRatio }}%</span>
                <span class="radar-sub">Không tiền mặt</span>
              </div>
            </div>

            <h3 class="breakdown-title">Tỷ lệ Chuyển Khoản & Ví</h3>
            <p class="breakdown-desc">
              Giao dịch qua VietQR và Thẻ Hội Viên chiếm đa số, giúp việc kiểm đếm két an toàn và nhanh chóng.
            </p>

            <div class="progress-bars-stack">
              <div class="prog-item">
                <div class="prog-info">
                  <span>Chuyển khoản VietQR</span>
                  <strong>{{ money(activeShift.qr_sales) }} (62%)</strong>
                </div>
                <div class="prog-track">
                  <div class="prog-fill fill-blue" style="width: 62%"></div>
                </div>
              </div>

              <div class="prog-item">
                <div class="prog-info">
                  <span>Ví Thẻ Hội Viên</span>
                  <strong>{{ money(activeShift.wallet_sales) }} (17%)</strong>
                </div>
                <div class="prog-track">
                  <div class="prog-fill fill-purple" style="width: 17%"></div>
                </div>
              </div>

              <div class="prog-item">
                <div class="prog-info">
                  <span>Tiền mặt tại két</span>
                  <strong>{{ money(activeShift.cash_sales) }} (21%)</strong>
                </div>
                <div class="prog-track">
                  <div class="prog-fill fill-green" style="width: 21%"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- CARD 2: QUICK KPIS -->
          <div class="surface-card pink-kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">THỐNG KÊ CA TRỰC</span>
              <span class="material-symbols-outlined icon-20">bolt</span>
            </div>

            <div class="kpi-grid">
              <div class="kpi-box">
                <span class="kpi-label">Đơn Đặt Sân</span>
                <div class="kpi-num">{{ activeShift.court_orders_count }}</div>
              </div>

              <div class="kpi-box">
                <span class="kpi-label">Đơn Bán Lẻ POS</span>
                <div class="kpi-num">{{ activeShift.pos_orders_count }}</div>
              </div>

              <div class="kpi-box">
                <span class="kpi-label">Sân Trống Hiện Tại</span>
                <div class="kpi-num">2 / 10</div>
              </div>

              <div class="kpi-box">
                <span class="kpi-label">Tổng Doanh Thu</span>
                <div class="kpi-num-money">{{ money(activeShift.total_sales) }}</div>
              </div>
            </div>
          </div>

          <!-- CARD 3: CASH DROPS -->
          <div class="surface-card cash-drops-card">
            <div class="card-header-row">
              <div class="card-mini-title flex-title">
                <span class="material-symbols-outlined icon-18 color-danger">receipt_long</span>
                <span>Phiếu Chi / Rút Két</span>
              </div>
              <button type="button" class="btn-text-link" @click="showCashDropModal = true">
                + Thêm
              </button>
            </div>

            <div v-if="activeShift.cash_drops && activeShift.cash_drops.length > 0" class="drops-list">
              <div v-for="drop in activeShift.cash_drops" :key="drop.id" class="drop-row">
                <div class="drop-meta">
                  <span class="drop-reason">{{ drop.reason }} <small>({{ drop.time }})</small></span>
                  <span class="drop-recipient">Người nhận: {{ drop.recipient || 'Nội bộ' }}</span>
                </div>
                <span class="drop-amount">- {{ money(drop.amount) }}</span>
              </div>
            </div>

            <div v-else class="empty-drops-box">
              Chưa có khoản chi hoặc rút két nào trong ca này.
            </div>
          </div>
        </div>
      </div>

      <!-- SHIFT HISTORY & AUDIT LOG TABLE -->
      <section class="surface-card shift-history-section">
        <div class="history-header-row">
          <div>
            <h2 class="card-main-title">Lịch Sử Giao Ca & Nhật Ký Đối Soát</h2>
            <p class="card-sub-desc">Toàn bộ hồ sơ giao ca, đối soát tiền mặt và chữ ký xác nhận của quản lý cơ sở</p>
          </div>

          <div class="history-filter-controls">
            <div class="search-input-box">
              <span class="material-symbols-outlined search-icon">search</span>
              <input
                v-model="searchQuery"
                type="text"
                class="search-text-field"
                placeholder="Tìm nhân viên, mã ca..."
              />
            </div>

            <select v-model="statusFilter" class="filter-select-field">
              <option value="all">Tất cả trạng thái</option>
              <option value="matched">Khớp 100%</option>
              <option value="exception">Có chênh lệch</option>
              <option value="approved">Quản lý đã duyệt</option>
            </select>
          </div>
        </div>

        <div class="table-scroll-container">
          <table class="audit-log-table">
            <thead>
              <tr>
                <th>THỜI GIAN & CA</th>
                <th>NHÂN VIÊN TRỰC</th>
                <th>DOANH THU TỔNG</th>
                <th>TIỀN MẶT ĐẦU → CUỐI</th>
                <th>CHÊNH LỆCH</th>
                <th>ĐỐI SOÁT</th>
                <th class="text-right">THAO TÁC</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="shift in filteredHistory" :key="shift.id">
                <td>
                  <div class="cell-primary-text">{{ shift.opened_at.split(' ')[0] }}</div>
                  <div class="cell-sub-text">{{ shift.shift_name }}</div>
                  <span class="cell-code-pill">#{{ shift.code }}</span>
                </td>

                <td>
                  <div class="cashier-cell">
                    <div class="cashier-avatar-small">{{ shift.cashier_name.slice(0, 1) }}</div>
                    <div>
                      <div class="cell-primary-text">{{ shift.cashier_name }}</div>
                      <div class="cell-sub-text">{{ shift.venue_name.split('-')[0] }}</div>
                    </div>
                  </div>
                </td>

                <td>
                  <div class="cell-primary-bold">{{ money(shift.total_sales) }}</div>
                  <div class="cell-sub-text">
                    Mặt: {{ money(shift.cash_sales) }} • QR: {{ money(shift.qr_sales) }}
                  </div>
                </td>

                <td>
                  <div class="cell-primary-text">
                    {{ money(shift.opening_cash) }} → <strong class="color-primary">{{ money(shift.declared_cash) }}</strong>
                  </div>
                  <div class="cell-sub-text">Kỳ vọng: {{ money(shift.expected_cash) }}</div>
                </td>

                <td>
                  <div
                    class="cell-variance-badge"
                    :class="{
                      'var-green': shift.variance === 0,
                      'var-red': shift.variance < 0,
                      'var-blue': shift.variance > 0,
                    }"
                  >
                    {{ shift.variance > 0 ? '+' : '' }}{{ money(shift.variance) }}
                  </div>
                  <div v-if="shift.variance_reason" class="variance-snippet" :title="shift.variance_reason">
                    {{ shift.variance_reason }}
                  </div>
                </td>

                <td>
                  <span v-if="shift.reconciliation_status === 'matched'" class="status-badge badge-matched">
                    <span class="material-symbols-outlined icon-14">check_circle</span>
                    Khớp 100%
                  </span>
                  <span v-else-if="shift.reconciliation_status === 'approved'" class="status-badge badge-approved">
                    <span class="material-symbols-outlined icon-14">verified</span>
                    Đã duyệt
                  </span>
                  <span v-else-if="shift.reconciliation_status === 'exception_short'" class="status-badge badge-deficit">
                    <span class="material-symbols-outlined icon-14">error</span>
                    Thiếu tiền
                  </span>
                  <span v-else class="status-badge badge-surplus">
                    <span class="material-symbols-outlined icon-14">warning</span>
                    Thừa tiền
                  </span>
                </td>

                <td class="text-right">
                  <div class="table-actions-row">
                    <button type="button" class="btn-icon-table" title="Xem chi tiết" @click="openShiftDetail(shift)">
                      <span class="material-symbols-outlined icon-18">visibility</span>
                    </button>
                    <button type="button" class="btn-icon-table" title="In phiếu giao ca" @click="printReport(shift)">
                      <span class="material-symbols-outlined icon-18">print</span>
                    </button>
                    <button type="button" class="btn-icon-table btn-table-sign" title="Quản lý ký duyệt" @click="openReviewShift(shift)">
                      <span class="material-symbols-outlined icon-18">draw</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- MODAL 1: OPEN SHIFT MODAL -->
    <div v-if="showOpenShiftModal" class="modal-backdrop-custom" @click.self="showOpenShiftModal = false">
      <div class="modal-card-custom">
        <div class="modal-header-custom">
          <div class="modal-title-with-icon">
            <div class="icon-avatar pink-avatar">
              <span class="material-symbols-outlined">add_circle</span>
            </div>
            <div>
              <h3 class="modal-heading">Mở Ca Thu Ngân Mới</h3>
              <p class="modal-sub">Khai báo tiền mặt trong két và thông tin nhận ca</p>
            </div>
          </div>
          <button type="button" class="btn-close-custom" @click="showOpenShiftModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-custom">
          <div class="form-group-custom">
            <label class="form-label-custom">Cơ sở & Quầy thu ngân</label>
            <input v-model="openShiftForm.venue_name" type="text" class="form-input-custom" />
          </div>

          <div class="form-grid-2">
            <div class="form-group-custom">
              <label class="form-label-custom">Nhân viên nhận ca</label>
              <input v-model="openShiftForm.cashier_name" type="text" class="form-input-custom" />
            </div>
            <div class="form-group-custom">
              <label class="form-label-custom">Ca làm việc</label>
              <select v-model="openShiftForm.shift_name" class="form-input-custom">
                <option value="Ca Sáng (06:00 - 14:00)">Ca Sáng (06:00 - 14:00)</option>
                <option value="Ca Chiều (14:00 - 22:00)">Ca Chiều (14:00 - 22:00)</option>
                <option value="Ca Tối (22:00 - 06:00)">Ca Tối (22:00 - 06:00)</option>
              </select>
            </div>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Tiền mặt đầu ca (Opening Float Cash)</label>
            <input
              v-model.number="openShiftForm.opening_cash"
              type="number"
              step="50000"
              class="form-input-custom font-bold-custom"
            />
            <p class="form-hint-custom">Số tiền lẻ tiêu chuẩn dùng để thối khách đầu ca: 1.500.000 ₫</p>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Ghi chú tình trạng két</label>
            <textarea
              v-model="openShiftForm.note"
              rows="2"
              class="form-input-custom"
              placeholder="Ví dụ: Két tiền đầy đủ các mệnh giá 10k, 20k, 50k..."
            ></textarea>
          </div>
        </div>

        <div class="modal-footer-custom">
          <button type="button" class="btn-action btn-outline" @click="showOpenShiftModal = false">
            Hủy
          </button>
          <button type="button" class="btn-action btn-primary" :disabled="saving" @click="handleOpenShift">
            <span class="material-symbols-outlined icon-18">lock_open</span>
            <span>{{ saving ? 'Đang mở ca...' : 'Xác Nhận Mở Ca' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 2: CLOSE SHIFT MODAL -->
    <div v-if="showCloseShiftModal" class="modal-backdrop-custom" @click.self="showCloseShiftModal = false">
      <div class="modal-card-custom modal-lg-custom">
        <div class="modal-header-custom">
          <div class="modal-title-with-icon">
            <div class="icon-avatar pink-avatar">
              <span class="material-symbols-outlined">lock</span>
            </div>
            <div>
              <h3 class="modal-heading">Đóng Ca & Quyết Toán Tiền Mặt</h3>
              <p class="modal-sub">Kiểm đếm két tiền, đối soát và bàn giao ca tiếp theo</p>
            </div>
          </div>
          <button type="button" class="btn-close-custom" @click="showCloseShiftModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-custom">
          <!-- Summary Recap Box -->
          <div class="summary-recap-banner">
            <div class="recap-item">
              <span class="recap-label">Tiền Đầu Ca</span>
              <strong class="recap-val">{{ money(activeShift.opening_cash) }}</strong>
            </div>
            <div class="recap-item">
              <span class="recap-label">Thu Mặt Trong Ca</span>
              <strong class="recap-val">{{ money(activeShift.cash_sales) }}</strong>
            </div>
            <div class="recap-item">
              <span class="recap-label">Kỳ Vọng Trong Két</span>
              <strong class="recap-val color-primary">{{ money(currentExpectedCash) }}</strong>
            </div>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Số tiền mặt thực tế kiểm đếm (VND)</label>
            <input
              v-model.number="closeShiftForm.declared_cash"
              type="number"
              step="10000"
              class="form-input-custom large-input color-primary"
            />
          </div>

          <div
            class="variance-alert-banner"
            :class="{
              'banner-green': (closeShiftForm.declared_cash - currentExpectedCash) === 0,
              'banner-red': (closeShiftForm.declared_cash - currentExpectedCash) < 0,
              'banner-blue': (closeShiftForm.declared_cash - currentExpectedCash) > 0,
            }"
          >
            <strong>
              Chênh lệch: {{ (closeShiftForm.declared_cash - currentExpectedCash) > 0 ? '+' : '' }}{{ money(closeShiftForm.declared_cash - currentExpectedCash) }}
            </strong>
            <span>
              {{
                (closeShiftForm.declared_cash - currentExpectedCash) === 0
                  ? 'Khớp 100%'
                  : (closeShiftForm.declared_cash - currentExpectedCash) < 0
                  ? 'Thiếu tiền két'
                  : 'Thừa tiền két'
              }}
            </span>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Biên bản giải trình chênh lệch</label>
            <textarea
              v-model="closeShiftForm.variance_reason"
              rows="2"
              class="form-input-custom"
              placeholder="Ghi rõ lý do nếu có lệch két..."
            ></textarea>
          </div>

          <div class="form-grid-2">
            <div class="form-group-custom">
              <label class="form-label-custom">Bàn giao cho nhân viên</label>
              <input v-model="closeShiftForm.handover_to" type="text" class="form-input-custom" />
            </div>
            <div class="form-group-custom">
              <label class="form-label-custom">Quản lý / Người làm chứng</label>
              <input v-model="closeShiftForm.manager_witness" type="text" class="form-input-custom" />
            </div>
          </div>
        </div>

        <div class="modal-footer-custom">
          <button type="button" class="btn-action btn-outline" @click="showCloseShiftModal = false">
            Hủy
          </button>
          <button type="button" class="btn-action btn-primary" :disabled="saving" @click="handleCloseShift">
            <span class="material-symbols-outlined icon-18">done_all</span>
            <span>{{ saving ? 'Đang quyết toán...' : 'Hoàn Tất Đóng Ca' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 3: CASH DROP MODAL -->
    <div v-if="showCashDropModal" class="modal-backdrop-custom" @click.self="showCashDropModal = false">
      <div class="modal-card-custom">
        <div class="modal-header-custom">
          <div class="modal-title-with-icon">
            <div class="icon-avatar pink-avatar">
              <span class="material-symbols-outlined">payments</span>
            </div>
            <div>
              <h3 class="modal-heading">Phiếu Chi / Rút Tiền Két</h3>
              <p class="modal-sub">Ghi nhận khoản chi phát sinh từ két tiền mặt</p>
            </div>
          </div>
          <button type="button" class="btn-close-custom" @click="showCashDropModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-custom">
          <div class="form-group-custom">
            <label class="form-label-custom">Số tiền chi từ két (VND)</label>
            <input
              v-model.number="cashDropForm.amount"
              type="number"
              step="10000"
              class="form-input-custom color-danger font-bold-custom"
            />
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Lý do chi tiền</label>
            <input
              v-model="cashDropForm.reason"
              type="text"
              class="form-input-custom"
              placeholder="Mua đá lạnh, túi đựng vợt, vệ sinh..."
            />
          </div>

          <div class="form-grid-2">
            <div class="form-group-custom">
              <label class="form-label-custom">Người nhận tiền</label>
              <input v-model="cashDropForm.recipient" type="text" class="form-input-custom" />
            </div>
            <div class="form-group-custom">
              <label class="form-label-custom">Số hóa đơn / Chứng từ</label>
              <input v-model="cashDropForm.receipt_code" type="text" class="form-input-custom" />
            </div>
          </div>
        </div>

        <div class="modal-footer-custom">
          <button type="button" class="btn-action btn-outline" @click="showCashDropModal = false">
            Hủy
          </button>
          <button type="button" class="btn-action btn-primary" @click="handleAddCashDrop">
            <span class="material-symbols-outlined icon-18">save</span>
            <span>Lưu Phiếu Chi</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 4: MANAGER REVIEW & SIGN-OFF -->
    <div v-if="showReviewModal && selectedShift" class="modal-backdrop-custom" @click.self="showReviewModal = false">
      <div class="modal-card-custom">
        <div class="modal-header-custom">
          <div class="modal-title-with-icon">
            <div class="icon-avatar pink-avatar">
              <span class="material-symbols-outlined">draw</span>
            </div>
            <div>
              <h3 class="modal-heading">Quản Lý Ký Duyệt Đối Soát Ca</h3>
              <p class="modal-sub">Mã ca: #{{ selectedShift.code }} • Nhân viên: {{ selectedShift.cashier_name }}</p>
            </div>
          </div>
          <button type="button" class="btn-close-custom" @click="showReviewModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-custom">
          <div class="review-recap-box">
            <div class="recap-line">
              <span>Doanh thu tổng:</span>
              <strong>{{ money(selectedShift.total_sales) }}</strong>
            </div>
            <div class="recap-line">
              <span>Tiền mặt khai báo:</span>
              <strong class="color-primary">{{ money(selectedShift.declared_cash) }}</strong>
            </div>
            <div class="recap-line">
              <span>Chênh lệch:</span>
              <strong :class="selectedShift.variance === 0 ? 'color-success' : 'color-danger'">
                {{ selectedShift.variance > 0 ? '+' : '' }}{{ money(selectedShift.variance) }}
              </strong>
            </div>
            <p v-if="selectedShift.variance_reason" class="recap-reason">
              Giải trình thu ngân: "{{ selectedShift.variance_reason }}"
            </p>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Kết luận duyệt của quản lý</label>
            <div class="form-grid-2">
              <button
                type="button"
                class="btn-review-option"
                :class="{ selected: reviewForm.status === 'approved' }"
                @click="reviewForm.status = 'approved'"
              >
                <span class="material-symbols-outlined icon-16">verified</span>
                Phê Duyệt Khớp
              </button>

              <button
                type="button"
                class="btn-review-option option-warning"
                :class="{ selected: reviewForm.status === 'exception_short' }"
                @click="reviewForm.status = 'exception_short'"
              >
                <span class="material-symbols-outlined icon-16">warning</span>
                Duyệt Có Lệch Két
              </button>
            </div>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Ghi chú xác nhận của Quản lý cơ sở</label>
            <textarea
              v-model="reviewForm.note"
              rows="3"
              class="form-input-custom"
              placeholder="Đã đối chiếu hóa đơn POS và giao dịch VietQR..."
            ></textarea>
          </div>
        </div>

        <div class="modal-footer-custom">
          <button type="button" class="btn-action btn-outline" @click="showReviewModal = false">
            Hủy
          </button>
          <button type="button" class="btn-action btn-primary" @click="handleSaveReview">
            <span class="material-symbols-outlined icon-18">check_circle</span>
            <span>Lưu & Ký Xác Nhận</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 5: PRINTABLE SLIP -->
    <div v-if="showHandoverPrintModal && selectedShift" class="modal-backdrop-custom" @click.self="showHandoverPrintModal = false">
      <div class="modal-card-custom modal-print-card">
        <div class="modal-header-custom no-print">
          <div class="modal-title-with-icon">
            <span class="material-symbols-outlined color-primary">receipt</span>
            <h3 class="modal-heading">Phiếu Bàn Giao Ca & Đối Soát</h3>
          </div>
          <div class="modal-header-actions">
            <button type="button" class="btn-action btn-primary btn-sm" @click="executeBrowserPrint">
              <span class="material-symbols-outlined icon-16">print</span>
              In Ngay
            </button>
            <button type="button" class="btn-close-custom" @click="showHandoverPrintModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
        </div>

        <!-- Printable Document Canvas -->
        <div class="printable-slip">
          <div class="slip-header">
            <h4>AURA COURT CONTROL</h4>
            <p class="slip-venue">{{ selectedShift.venue_name }}</p>
            <p class="slip-doc-type">BIÊN BẢN GIAO CA & ĐỐI SOÁT KÉT TIỀN</p>
            <p class="slip-code">MÃ CA: #{{ selectedShift.code }}</p>
          </div>

          <div class="slip-meta-grid">
            <div class="slip-row"><span>Nhân viên trực:</span> <strong>{{ selectedShift.cashier_name }}</strong></div>
            <div class="slip-row"><span>Ca làm việc:</span> <span>{{ selectedShift.shift_name }}</span></div>
            <div class="slip-row"><span>Thời gian mở:</span> <span>{{ selectedShift.opened_at }}</span></div>
            <div class="slip-row"><span>Thời gian đóng:</span> <span>{{ selectedShift.closed_at || 'Đang mở' }}</span></div>
          </div>

          <div class="slip-divider"></div>

          <div class="slip-financial-breakdown">
            <div class="slip-row"><span>1. Tiền két đầu ca:</span> <span>{{ money(selectedShift.opening_cash) }}</span></div>
            <div class="slip-row"><span>2. Thu tiền mặt POS:</span> <span>{{ money(selectedShift.cash_sales) }}</span></div>
            <div class="slip-row"><span>3. Chuyển khoản VietQR:</span> <span>{{ money(selectedShift.qr_sales) }}</span></div>
            <div class="slip-row"><span>4. Ví thẻ hội viên:</span> <span>{{ money(selectedShift.wallet_sales) }}</span></div>
            <div v-if="selectedShift.cash_drops_total > 0" class="slip-row color-danger">
              <span>5. Đã chi từ két:</span> <span>- {{ money(selectedShift.cash_drops_total) }}</span>
            </div>
          </div>

          <div class="slip-divider"></div>

          <div class="slip-summary-block">
            <div class="slip-row bold-row"><span>Tổng doanh thu ca:</span> <strong>{{ money(selectedShift.total_sales) }}</strong></div>
            <div class="slip-row"><span>Tiền mặt kỳ vọng:</span> <span>{{ money(selectedShift.expected_cash) }}</span></div>
            <div class="slip-row bold-row"><span>Tiền mặt thực tế:</span> <span>{{ money(selectedShift.declared_cash) }}</span></div>
            <div class="slip-row bold-row" :class="selectedShift.variance === 0 ? 'color-success' : 'color-danger'">
              <span>Chênh lệch (+/-):</span> <span>{{ selectedShift.variance > 0 ? '+' : '' }}{{ money(selectedShift.variance) }}</span>
            </div>
          </div>

          <div v-if="selectedShift.variance_reason" class="slip-reason-box">
            <strong>Lý do giải trình:</strong> {{ selectedShift.variance_reason }}
          </div>

          <!-- Signatures -->
          <div class="slip-signatures-grid">
            <div class="sig-col">
              <p class="sig-title">Người giao ca</p>
              <p class="sig-sub">(Ký & ghi rõ họ tên)</p>
              <div class="sig-space"></div>
              <p class="sig-name">{{ selectedShift.cashier_name }}</p>
            </div>
            <div class="sig-col">
              <p class="sig-title">Quản lý cơ sở</p>
              <p class="sig-sub">(Ký xác nhận)</p>
              <div class="sig-space"></div>
              <p class="sig-name">{{ selectedShift.reviewed_by || 'Lê Quản Lý' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 6: DETAILED VIEW MODAL -->
    <div v-if="showShiftDetailModal && selectedShift" class="modal-backdrop-custom" @click.self="showShiftDetailModal = false">
      <div class="modal-card-custom modal-lg-custom">
        <div class="modal-header-custom">
          <div class="modal-title-with-icon">
            <div class="icon-avatar pink-avatar">
              <span class="material-symbols-outlined">info</span>
            </div>
            <div>
              <h3 class="modal-heading">Chi Tiết Ca Trực #{{ selectedShift.code }}</h3>
              <p class="modal-sub">{{ selectedShift.shift_name }} • {{ selectedShift.opened_at }}</p>
            </div>
          </div>
          <button type="button" class="btn-close-custom" @click="showShiftDetailModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-custom">
          <div class="details-stat-grid">
            <div class="details-stat-box">
              <span class="stat-label-tiny">Tiền Đầu Ca</span>
              <strong class="stat-main-val">{{ money(selectedShift.opening_cash) }}</strong>
            </div>
            <div class="details-stat-box">
              <span class="stat-label-tiny">Doanh Thu Tổng</span>
              <strong class="stat-main-val color-primary">{{ money(selectedShift.total_sales) }}</strong>
            </div>
            <div class="details-stat-box">
              <span class="stat-label-tiny">Khai Báo Cuối Ca</span>
              <strong class="stat-main-val">{{ money(selectedShift.declared_cash) }}</strong>
            </div>
            <div class="details-stat-box">
              <span class="stat-label-tiny">Chênh Lệch</span>
              <strong class="stat-main-val" :class="selectedShift.variance === 0 ? 'color-success' : 'color-danger'">
                {{ selectedShift.variance > 0 ? '+' : '' }}{{ money(selectedShift.variance) }}
              </strong>
            </div>
          </div>

          <div class="details-note-box">
            <h4 class="note-box-title">Ghi chú ca & Giải trình chênh lệch:</h4>
            <p class="note-box-content">
              {{ selectedShift.variance_reason || selectedShift.note || 'Không có ghi chú phát sinh.' }}
            </p>
          </div>

          <div v-if="selectedShift.reviewed_by" class="reviewed-confirmation-box">
            <div class="rev-conf-head">
              <span class="material-symbols-outlined icon-16">verified</span>
              <span>Quản lý đã ký duyệt: {{ selectedShift.reviewed_by }} ({{ selectedShift.reviewed_at }})</span>
            </div>
            <p class="rev-conf-body">{{ selectedShift.review_note || 'Đã kiểm tra và phê duyệt đối soát.' }}</p>
          </div>
        </div>

        <div class="modal-footer-custom">
          <button type="button" class="btn-action btn-primary" @click="showShiftDetailModal = false">
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped CSS variables & Design Tokens for Aura Soft Minimalism */
.shift-management-view {
  --primary: #78555e;
  --primary-container: #ffd1dc;
  --on-primary: #ffffff;
  --on-primary-container: #7a5761;
  --surface: #f8f9fa;
  --surface-card: #ffffff;
  --surface-low: #f3f4f5;
  --surface-high: #edeeef;
  --surface-highest: #e1e3e4;
  --outline: #817476;
  --outline-variant: #d3c3c5;
  --ink: #191c1d;
  --ink-muted: #5d5f5f;
  --success: #059669;
  --success-bg: #ecfdf5;
  --danger: #ba1a1a;
  --danger-bg: #ffdad6;
  --danger-ink: #93000a;
  --blue: #2563eb;
  --purple: #9333ea;

  min-height: 100%;
  width: 100%;
  background: var(--surface);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  box-sizing: border-box;
  overflow-x: hidden;
}

/* Toast Notifications */
.toast-notice,
.toast-error {
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  font-size: 14px;
  font-weight: 600;
  animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.toast-notice {
  background: #191c1d;
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.toast-error {
  background: var(--danger);
  color: #ffffff;
}

.text-pink {
  color: var(--primary-container);
}

/* Top Navigation Bar */
.top-nav-bar {
  position: sticky;
  top: 0;
  z-index: 40;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--outline-variant);
  padding: 12px 32px;
}

.top-nav-inner {
  max-width: 1360px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.breadcrumb-trail {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: var(--ink-muted);
}

.nav-arrow {
  font-size: 14px;
  color: var(--outline);
}

.trail-active {
  color: var(--primary);
}

.top-status-pills {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
}

.system-time {
  background: var(--surface-low);
  border: 1px solid var(--surface-highest);
  color: var(--ink-muted);
}

.venue-chip {
  background: rgba(255, 209, 220, 0.4);
  color: var(--primary);
}

/* Main Container */
.main-page-container {
  max-width: 1360px;
  margin: 0 auto;
  padding: 28px 32px 64px;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* Hero Section */
.shift-hero-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
  padding-bottom: 8px;
}

.hero-title-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.hero-badges-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.active-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status-open {
  background: var(--primary-container);
  color: var(--primary);
}

.status-closed {
  background: var(--surface-highest);
  color: var(--ink-muted);
}

.pulse-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--primary);
}

.pulse-active {
  animation: pulseDot 1.8s infinite ease-in-out;
}

.shift-code-badge {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-muted);
}

.hero-page-title {
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
  margin: 0;
  line-height: 1.2;
}

.hero-info-line {
  font-size: 14px;
  color: var(--ink-muted);
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.hero-info-line strong {
  color: var(--ink);
}

.bullet {
  color: var(--outline-variant);
}

.hero-action-buttons {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.25);
}

.btn-primary:hover {
  background: #63444c;
  transform: translateY(-1px);
}

.btn-outline {
  background: #ffffff;
  border: 1px solid var(--outline-variant);
  color: var(--ink);
}

.btn-outline:hover {
  background: var(--surface-low);
  border-color: var(--outline);
}

/* 12-Column Dashboard Grid */
.reconciliation-dashboard-grid {
  display: grid;
  grid-template-columns: 8fr 4fr;
  gap: 28px;
}

@media (max-width: 1024px) {
  .reconciliation-dashboard-grid {
    grid-template-columns: 1fr;
  }
}

.left-reconciliation-col,
.right-stats-col {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Surface Cards */
.surface-card {
  background: var(--surface-card);
  border-radius: 24px;
  border: 1px solid var(--outline-variant);
  padding: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  position: relative;
  overflow: hidden;
}

.card-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.card-title-group {
  display: flex;
  align-items: center;
  gap: 14px;
}

.icon-avatar {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.pink-avatar {
  background: var(--primary-container);
  color: var(--primary);
}

.card-main-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 2px;
}

.card-sub-desc {
  font-size: 12px;
  color: var(--ink-muted);
  margin: 0;
}

.card-mini-title {
  font-size: 12px;
  font-weight: 800;
  color: var(--ink-muted);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.flex-title {
  display: flex;
  align-items: center;
  gap: 6px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 12px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.declared-pill {
  background: var(--surface-low);
  border: 1px solid var(--surface-highest);
  color: var(--ink-muted);
}

/* Opening Cash Card */
.opening-cash-display-box {
  background: var(--surface);
  border: 1px solid var(--surface-high);
  border-radius: 18px;
  padding: 18px 22px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.label-tiny {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: var(--ink-muted);
  text-transform: uppercase;
  margin-bottom: 4px;
  display: block;
}

.large-currency-text {
  font-size: 32px;
  font-weight: 900;
  color: var(--ink);
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.cash-note-text {
  font-size: 12px;
  color: var(--ink-muted);
  margin: 6px 0 0;
  font-style: italic;
}

.btn-action-small {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid var(--outline-variant);
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-action-small:hover {
  background: var(--surface-high);
}

/* Reconciliation Card */
.reconciliation-card {
  position: relative;
}

.card-ambient-glow {
  position: absolute;
  top: -80px;
  right: -80px;
  width: 260px;
  height: 260px;
  border-radius: 50%;
  background: var(--primary-container);
  filter: blur(60px);
  opacity: 0.45;
  pointer-events: none;
}

.relative-z {
  position: relative;
  z-index: 2;
}

.btn-toggle-denom {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 12px;
  background: rgba(255, 209, 220, 0.5);
  color: var(--primary);
  border: none;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-toggle-denom:hover {
  background: var(--primary-container);
}

/* Denomination Calculator Panel */
.denomination-calculator-panel {
  background: var(--surface-low);
  border: 1px solid var(--outline-variant);
  border-radius: 18px;
  padding: 18px;
  margin-bottom: 24px;
  position: relative;
  z-index: 2;
  animation: fadeIn 0.2s ease-out;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.panel-head-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.panel-head-total {
  font-size: 13px;
  color: var(--primary);
}

.denomination-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 10px;
}

.denom-box {
  background: #ffffff;
  border: 1px solid var(--surface-highest);
  border-radius: 12px;
  padding: 10px 8px;
  text-align: center;
}

.denom-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--ink-muted);
  margin-bottom: 6px;
}

.stepper-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.btn-step {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  background: var(--surface-high);
  border: none;
  font-weight: 800;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-step.plus {
  background: var(--primary-container);
  color: var(--primary);
}

.step-input {
  width: 36px;
  text-align: center;
  font-size: 12px;
  font-weight: 700;
  border: none;
  border-bottom: 1.5px solid var(--primary);
  background: transparent;
  outline: none;
}

.denom-subtotal {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--primary);
  margin-top: 6px;
}

.panel-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.btn-apply-denom {
  padding: 8px 16px;
  border-radius: 10px;
  background: var(--primary);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

/* Two-Column Reconciliation Layout */
.reconciliation-split-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

@media (max-width: 768px) {
  .reconciliation-split-grid {
    grid-template-columns: 1fr;
  }
}

.system-revenue-box,
.actual-count-box {
  background: var(--surface);
  border: 1px solid var(--surface-high);
  border-radius: 18px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.actual-count-box {
  background: #ffffff;
  border-color: var(--outline-variant);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.box-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--ink-muted);
  margin-bottom: 16px;
}

.revenue-line-items {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rev-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 0;
  border-bottom: 1px solid rgba(225, 227, 228, 0.6);
  font-size: 13px;
}

.rev-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink-muted);
}

.rev-val {
  font-weight: 700;
  color: var(--ink);
}

.color-cash {
  color: var(--success);
}
.color-qr {
  color: var(--blue);
}
.color-wallet {
  color: var(--purple);
}
.color-danger {
  color: var(--danger);
}
.color-primary {
  color: var(--primary);
}
.color-success {
  color: var(--success);
}

.rev-expense {
  color: var(--danger);
}
.rev-expense .rev-val {
  color: var(--danger);
}

.expected-total-banner {
  margin-top: 18px;
  background: #ffffff;
  border: 1px solid var(--outline-variant);
  border-radius: 14px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.banner-title-box {
  display: flex;
  flex-direction: column;
}

.banner-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.banner-sub {
  font-size: 10px;
  color: var(--outline);
}

.banner-amount {
  font-size: 20px;
  font-weight: 800;
  color: var(--primary);
}

.expected-footnote {
  font-size: 11px;
  color: var(--ink-muted);
  margin: 6px 0 0;
  font-style: italic;
}

/* Actual Count Box */
.actual-input-wrapper {
  margin-bottom: 18px;
}

.input-label-sm {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-muted);
  margin-bottom: 6px;
}

.input-with-suffix {
  position: relative;
  display: flex;
  align-items: center;
}

.actual-currency-input {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--primary);
  padding: 8px 36px 8px 0;
  font-size: 26px;
  font-weight: 800;
  color: var(--primary);
  outline: none;
  box-sizing: border-box;
}

.input-suffix {
  position: absolute;
  right: 0;
  font-size: 12px;
  font-weight: 700;
  color: var(--ink-muted);
}

/* Variance Box */
.variance-result-card {
  padding: 16px;
  border-radius: 16px;
  border: 1px solid transparent;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.var-balanced {
  background: var(--success-bg);
  border-color: #6ee7b7;
  color: #065f46;
}

.var-deficit {
  background: rgba(255, 218, 214, 0.5);
  border-color: var(--danger);
  color: var(--danger);
}

.var-surplus {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #1e40af;
}

.var-badge-title {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.var-amount-display {
  font-size: 24px;
  font-weight: 900;
  letter-spacing: -0.02em;
}

.var-desc-text {
  font-size: 11px;
  margin: 6px 0 0;
  font-weight: 500;
  opacity: 0.9;
}

/* Variance Notes Section */
.variance-notes-section {
  padding-top: 18px;
  border-top: 1px solid var(--surface-highest);
}

.notes-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.notes-label {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--ink-muted);
}

.quick-tags-list {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.quick-tag-btn {
  padding: 4px 10px;
  border-radius: 9999px;
  background: var(--surface-low);
  border: 1px solid var(--surface-highest);
  font-size: 11px;
  font-weight: 600;
  color: var(--ink-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.quick-tag-btn:hover {
  background: var(--primary-container);
  color: var(--primary);
  border-color: var(--primary-container);
}

.notes-textarea {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--outline-variant);
  border-radius: 14px;
  padding: 12px 14px;
  font-size: 13px;
  color: var(--ink);
  outline: none;
  resize: vertical;
  box-sizing: border-box;
  font-family: inherit;
  transition: border-color 0.2s ease;
}

.notes-textarea:focus {
  border-color: var(--primary);
  background: #ffffff;
}

/* Right Stats Cards */
.payment-method-card {
  text-align: center;
}

.badge-growth {
  font-size: 11px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 9999px;
  background: var(--success-bg);
  color: var(--success);
}

.circular-radar-wrapper {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 12px auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.radar-svg {
  width: 100%;
  height: 100%;
  animation: spinRadar 24s linear infinite;
}

.radar-circle-outer {
  fill: none;
  stroke: var(--primary);
  stroke-width: 2;
  stroke-dasharray: 8 6;
  opacity: 0.25;
}

.radar-circle-inner {
  fill: none;
  stroke: var(--primary-container);
  stroke-width: 4;
  stroke-dasharray: 14 8;
  opacity: 0.5;
}

.radar-inner-text {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.radar-percentage {
  font-size: 24px;
  font-weight: 900;
  color: var(--primary);
  line-height: 1;
}

.radar-sub {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--ink-muted);
  margin-top: 2px;
}

.breakdown-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin: 4px 0 2px;
}

.breakdown-desc {
  font-size: 12px;
  color: var(--ink-muted);
  margin: 0 0 16px;
  line-height: 1.4;
}

.progress-bars-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
  text-align: left;
}

.prog-info {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 4px;
}

.prog-info strong {
  color: var(--primary);
}

.prog-track {
  width: 100%;
  height: 6px;
  border-radius: 9999px;
  background: var(--surface-low);
  overflow: hidden;
}

.prog-fill {
  height: 100%;
  border-radius: 9999px;
}

.fill-blue {
  background: var(--blue);
}
.fill-purple {
  background: var(--purple);
}
.fill-green {
  background: var(--success);
}

/* Pink KPI Card */
.pink-kpi-card {
  background: var(--primary-container);
  border-color: var(--primary-container);
  color: var(--on-primary-container);
}

.kpi-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.kpi-title {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.kpi-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.kpi-box {
  background: rgba(255, 255, 255, 0.65);
  backdrop-filter: blur(4px);
  border-radius: 14px;
  padding: 12px;
}

.kpi-label {
  font-size: 11px;
  font-weight: 600;
  color: #5e3e47;
  display: block;
}

.kpi-num {
  font-size: 22px;
  font-weight: 900;
  color: var(--ink);
  margin-top: 4px;
}

.kpi-num-money {
  font-size: 15px;
  font-weight: 900;
  color: var(--ink);
  margin-top: 4px;
}

/* Cash Drops Card */
.cash-drops-card {
  padding: 20px;
}

.btn-text-link {
  background: none;
  border: none;
  font-size: 12px;
  font-weight: 700;
  color: var(--primary);
  cursor: pointer;
}

.btn-text-link:hover {
  text-decoration: underline;
}

.drops-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.drop-row {
  background: var(--surface);
  border: 1px solid var(--surface-high);
  border-radius: 12px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}

.drop-meta {
  display: flex;
  flex-direction: column;
}

.drop-reason {
  font-weight: 700;
  color: var(--ink);
}

.drop-reason small {
  color: var(--ink-muted);
  font-weight: normal;
}

.drop-recipient {
  font-size: 10px;
  color: var(--ink-muted);
}

.drop-amount {
  font-weight: 800;
  color: var(--danger);
}

.empty-drops-box {
  padding: 20px;
  text-align: center;
  font-size: 12px;
  color: var(--ink-muted);
  background: var(--surface);
  border-radius: 14px;
  border: 1px dashed var(--outline-variant);
}

/* Shift History Table Section */
.shift-history-section {
  padding: 28px;
}

.history-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.history-filter-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.search-input-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  font-size: 16px;
  color: var(--ink-muted);
}

.search-text-field {
  background: var(--surface);
  border: 1px solid var(--outline-variant);
  border-radius: 10px;
  padding: 8px 12px 8px 32px;
  font-size: 12px;
  color: var(--ink);
  outline: none;
  width: 200px;
  transition: border-color 0.2s ease;
}

.search-text-field:focus {
  border-color: var(--primary);
  background: #ffffff;
}

.filter-select-field {
  background: var(--surface);
  border: 1px solid var(--outline-variant);
  border-radius: 10px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  outline: none;
}

.table-scroll-container {
  overflow-x: auto;
  border: 1px solid var(--surface-highest);
  border-radius: 16px;
}

.audit-log-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
  text-align: left;
}

.audit-log-table th {
  background: var(--surface);
  padding: 12px 16px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: var(--ink-muted);
  border-bottom: 1px solid var(--surface-highest);
}

.audit-log-table td {
  padding: 14px 16px;
  border-bottom: 1px solid rgba(225, 227, 228, 0.6);
  white-space: nowrap;
}

.audit-log-table tr:hover {
  background: rgba(248, 249, 250, 0.8);
}

.cell-primary-text {
  font-weight: 700;
  color: var(--ink);
}

.cell-primary-bold {
  font-weight: 800;
  font-size: 13px;
  color: var(--ink);
}

.cell-sub-text {
  font-size: 11px;
  color: var(--ink-muted);
  margin-top: 2px;
}

.cell-code-pill {
  font-size: 10px;
  font-family: monospace;
  color: var(--primary);
  font-weight: 700;
}

.cashier-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cashier-avatar-small {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--primary-container);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 11px;
}

.cell-variance-badge {
  font-weight: 800;
}

.var-green {
  color: var(--success);
}
.var-red {
  color: var(--danger);
}
.var-blue {
  color: var(--blue);
}

.variance-snippet {
  font-size: 10px;
  color: var(--ink-muted);
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.badge-matched {
  background: var(--success-bg);
  color: var(--success);
}

.badge-approved {
  background: #eff6ff;
  color: var(--blue);
}

.badge-deficit {
  background: var(--danger-bg);
  color: var(--danger);
}

.badge-surplus {
  background: #fef3c7;
  color: #92400e;
}

.table-actions-row {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.btn-icon-table {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--surface-low);
  border: 1px solid var(--surface-highest);
  color: var(--ink-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-icon-table:hover {
  background: var(--surface-high);
  color: var(--ink);
}

.btn-table-sign {
  background: rgba(255, 209, 220, 0.6);
  color: var(--primary);
  border-color: var(--primary-container);
}

.btn-table-sign:hover {
  background: var(--primary-container);
}

.text-right {
  text-align: right;
}

/* Modals */
.modal-backdrop-custom {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: fadeIn 0.2s ease-out;
}

.modal-card-custom {
  background: #ffffff;
  border-radius: 24px;
  border: 1px solid var(--outline-variant);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-width: 520px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
}

.modal-lg-custom {
  max-width: 620px;
}

.modal-print-card {
  max-width: 560px;
}

.modal-header-custom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--surface-highest);
}

.modal-title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-heading {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.modal-sub {
  font-size: 12px;
  color: var(--ink-muted);
  margin: 2px 0 0;
}

.btn-close-custom {
  background: none;
  border: none;
  color: var(--ink-muted);
  cursor: pointer;
  padding: 4px;
}

.btn-close-custom:hover {
  color: var(--ink);
}

.modal-body-custom {
  padding: 20px 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group-custom {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label-custom {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.form-input-custom {
  background: var(--surface);
  border: 1px solid var(--outline-variant);
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 13px;
  color: var(--ink);
  outline: none;
  font-family: inherit;
  box-sizing: border-box;
}

.form-input-custom:focus {
  border-color: var(--primary);
  background: #ffffff;
}

.font-bold-custom {
  font-weight: 700;
}

.large-input {
  font-size: 20px;
  font-weight: 800;
}

.form-hint-custom {
  font-size: 11px;
  color: var(--ink-muted);
  margin: 2px 0 0;
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.modal-footer-custom {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--surface-highest);
  background: var(--surface);
}

/* Close Shift Specific Recap */
.summary-recap-banner {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 8px;
  background: var(--surface-low);
  border: 1px solid var(--surface-highest);
  border-radius: 14px;
  padding: 12px;
  text-align: center;
}

.recap-label {
  font-size: 10px;
  font-weight: 600;
  color: var(--ink-muted);
  display: block;
}

.recap-val {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink);
}

.variance-alert-banner {
  padding: 12px 16px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
}

.banner-green {
  background: var(--success-bg);
  color: var(--success);
}
.banner-red {
  background: var(--danger-bg);
  color: var(--danger);
}
.banner-blue {
  background: #eff6ff;
  color: var(--blue);
}

/* Review Options */
.review-recap-box {
  background: var(--surface);
  border: 1px solid var(--surface-highest);
  border-radius: 14px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
}

.recap-line {
  display: flex;
  justify-content: space-between;
}

.recap-reason {
  font-size: 11px;
  color: var(--ink-muted);
  font-style: italic;
  margin: 6px 0 0;
  padding-top: 6px;
  border-top: 1px solid var(--surface-highest);
}

.btn-review-option {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid var(--outline-variant);
  background: #ffffff;
  font-size: 12px;
  font-weight: 700;
  color: var(--ink-muted);
  cursor: pointer;
}

.btn-review-option.selected {
  background: var(--success-bg);
  border-color: var(--success);
  color: var(--success);
}

.btn-review-option.option-warning.selected {
  background: var(--danger-bg);
  border-color: var(--danger);
  color: var(--danger);
}

/* Printable Slip */
.printable-slip {
  padding: 24px;
  background: #fafafa;
  border: 1.5px dashed var(--outline);
  border-radius: 16px;
  font-family: monospace;
  font-size: 12px;
}

.slip-header {
  text-align: center;
  margin-bottom: 16px;
}

.slip-header h4 {
  font-size: 16px;
  font-weight: 800;
  margin: 0;
}

.slip-venue,
.slip-doc-type {
  font-size: 11px;
  color: var(--ink-muted);
  margin: 2px 0;
}

.slip-code {
  font-size: 11px;
  font-weight: 800;
  color: var(--primary);
  margin: 4px 0 0;
}

.slip-divider {
  border-bottom: 1px dashed var(--outline);
  margin: 12px 0;
}

.slip-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.bold-row {
  font-weight: 800;
}

.slip-reason-box {
  background: #ffffff;
  border: 1px solid var(--surface-highest);
  padding: 8px;
  border-radius: 8px;
  font-size: 10px;
  margin-top: 10px;
}

.slip-signatures-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 20px;
  padding-top: 14px;
  border-top: 1px dashed var(--outline);
  text-align: center;
}

.sig-title {
  font-weight: 800;
  font-size: 11px;
  margin: 0;
}

.sig-sub {
  font-size: 9px;
  color: var(--ink-muted);
  margin: 2px 0 0;
}

.sig-space {
  height: 40px;
}

.sig-name {
  font-weight: 800;
  font-size: 11px;
  margin: 0;
}

/* Detail Modal Specs */
.details-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  background: var(--surface-low);
  border: 1px solid var(--surface-highest);
  border-radius: 16px;
  padding: 14px;
  text-align: center;
}

.stat-label-tiny {
  font-size: 10px;
  font-weight: 600;
  color: var(--ink-muted);
  display: block;
}

.stat-main-val {
  font-size: 14px;
  font-weight: 800;
  color: var(--ink);
}

.details-note-box {
  background: var(--surface);
  border: 1px solid var(--surface-highest);
  border-radius: 14px;
  padding: 14px;
}

.note-box-title {
  font-size: 12px;
  font-weight: 700;
  margin: 0 0 6px;
}

.note-box-content {
  font-size: 12px;
  color: var(--ink-muted);
  margin: 0;
}

.reviewed-confirmation-box {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 14px;
  padding: 12px 14px;
  color: #1e3a8a;
}

.rev-conf-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 800;
}

.rev-conf-body {
  font-size: 11px;
  margin: 4px 0 0;
}

/* Icon Utilities */
.icon-14 {
  font-size: 14px;
}
.icon-16 {
  font-size: 16px;
}
.icon-18 {
  font-size: 18px;
}
.icon-20 {
  font-size: 20px;
}

/* Keyframes */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes pulseDot {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.3);
    opacity: 0.6;
  }
}

@keyframes spinRadar {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Print Overrides */
@media print {
  body * {
    visibility: hidden !important;
  }
  .printable-slip,
  .printable-slip * {
    visibility: visible !important;
  }
  .printable-slip {
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    border: none !important;
    background: #ffffff !important;
  }
  .no-print {
    display: none !important;
  }
}
</style>
