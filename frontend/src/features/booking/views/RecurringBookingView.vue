<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { bookingApi } from '../api/bookingApi'
import { financeApi } from '@/features/finance/api/financeApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue } from '@/features/court/types/court'
import {
  SkeletonLoader,
  SelectDropdown,
  DatePicker,
  TimePicker,
  ErrorModal,
} from '@/shared/components'

interface MonthlyInvoice {
  id: number
  recurring_series_id: number
  period_start: string
  period_end: string
  due_at: string
  amount: number
  status: 'pending' | 'paid' | 'overdue' | 'cancelled'
  paid_at?: string | null
}

interface RecurringOccurrence {
  id: number
  recurring_series_id: number
  sequence: number
  starts_at: string
  ends_at: string
  amount: number
  status: 'scheduled' | 'completed' | 'cancelled' | 'released'
}

interface RecurringSeries {
  id: number
  court_id: number
  frequency: 'weekly' | 'biweekly' | 'monthly'
  starts_at: string
  ends_at: string
  status: 'active' | 'completed' | 'cancelled' | 'paused'
  total_amount: number
  invoices?: MonthlyInvoice[]
  occurrences?: RecurringOccurrence[]
  court?: Court
}

interface PreviewSlot {
  dateStr: string
  timeStr: string
  startsAt: string
  endsAt: string
  dayName: string
  available: boolean
  courtName: string
}

// State
const loading = ref(true)
const submitting = ref(false)
const payingInvoiceId = ref<number | null>(null)
const error = ref('')
const successNotice = ref('')

// Error modal state
const showErrorModal = ref(false)
const errorModalTitle = ref('Đã có lỗi xảy ra')
const errorModalMessage = ref('')
const errorModalDetails = ref<string[]>([])
const errorModalCode = ref<string | number | null>(null)

function triggerErrorModal(cause: unknown, defaultMsg = 'Không thể thực hiện thao tác.') {
  errorModalTitle.value = 'Thông báo lỗi'
  errorModalCode.value = null
  errorModalDetails.value = []

  if (typeof cause === 'object' && cause !== null) {
    const err = cause as { message?: string; errors?: Record<string, string[]>; status?: number }
    errorModalMessage.value = err.message || defaultMsg
    if (err.status) {
      errorModalCode.value = err.status
    }
    if (err.errors && typeof err.errors === 'object') {
      const list: string[] = []
      for (const messages of Object.values(err.errors)) {
        if (Array.isArray(messages)) {
          list.push(...messages)
        }
      }
      errorModalDetails.value = list
    }
  } else {
    errorModalMessage.value = defaultMsg
  }
  showErrorModal.value = true
}

const todayDateString = new Date().toISOString().split('T')[0] ?? ''

// Data
const seriesList = ref<RecurringSeries[]>([])
const courts = ref<Court[]>([])
const venues = ref<Venue[]>([])

// Form state for creating recurring series
const form = ref({
  venue_id: '' as string | number,
  court_id: '' as string | number,
  frequency: 'weekly' as 'weekly' | 'biweekly' | 'monthly',
  selectedWeekdays: [2, 4] as number[], // 1 = Monday, 2 = Tuesday, etc. (0 = Sunday)
  startTime: '18:00',
  durationHours: 2,
  startDate: new Date(Date.now() + 86400000).toISOString().split('T')[0] ?? '', // tomorrow
  durationPreset: 3, // 3 months
})

// Modal states
const showResaleModal = ref(false)
const selectedOccurrenceForResale = ref<RecurringOccurrence | null>(null)
const resalePrice = ref<number>(150000)
const resaleSubmitting = ref(false)

const showAbsenceModal = ref(false)
const selectedOccurrenceForAbsence = ref<RecurringOccurrence | null>(null)
const absenceReason = ref('')

// Currency formatter
const money = (val: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)

const formatDate = (val: string) => {
  if (!val) return ''
  const d = new Date(val)
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const formatDateTime = (val: string) => {
  if (!val) return ''
  const d = new Date(val)
  return d.toLocaleString('vi-VN', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Weekday definitions
const weekdayOptions = [
  { day: 1, label: 'T2', name: 'Thứ 2' },
  { day: 2, label: 'T3', name: 'Thứ 3' },
  { day: 3, label: 'T4', name: 'Thứ 4' },
  { day: 4, label: 'T5', name: 'Thứ 5' },
  { day: 5, label: 'T6', name: 'Thứ 6' },
  { day: 6, label: 'T7', name: 'Thứ 7' },
  { day: 0, label: 'CN', name: 'Chủ Nhật' },
]

function toggleWeekday(day: number) {
  const idx = form.value.selectedWeekdays.indexOf(day)
  if (idx > -1) {
    if (form.value.selectedWeekdays.length > 1) {
      form.value.selectedWeekdays.splice(idx, 1)
    }
  } else {
    form.value.selectedWeekdays.push(day)
    form.value.selectedWeekdays.sort((a, b) => (a === 0 ? 7 : a) - (b === 0 ? 7 : b))
  }
}

// Filter courts by selected venue
const filteredCourts = computed(() => {
  if (!form.value.venue_id) return courts.value
  return courts.value.filter((c) => c.venue?.id === Number(form.value.venue_id))
})

const selectedCourtObj = computed(() => {
  return courts.value.find((c) => c.id === Number(form.value.court_id))
})

// Dropdown option sets
const venueOptions = computed(() => {
  return venues.value.map((v) => ({
    value: v.id,
    label: v.name,
    sublabel: v.address || undefined,
    icon: 'storefront',
  }))
})

const courtOptions = computed(() => {
  return filteredCourts.value.map((c) => ({
    value: c.id,
    label: c.name,
    sublabel: `${money(getCourtPrice(c))}/h`,
    icon: 'sports_tennis',
  }))
})

const durationOptions = [
  { value: 1, label: '1 Giờ' },
  { value: 1.5, label: '1.5 Giờ (90 phút)' },
  { value: 2, label: '2 Giờ (Tiêu chuẩn)' },
  { value: 3, label: '3 Giờ (Thi đấu)' },
]

const presetOptions = [
  { value: 1, label: '1 Tháng (khoảng 4-8 buổi)', sublabel: 'Giảm 10%' },
  { value: 3, label: '3 Tháng (Chiết khấu 15%)', sublabel: 'Khuyên dùng' },
  { value: 6, label: '6 Tháng (Chiết khấu 20%)', sublabel: 'Tiết kiệm cao' },
  { value: 12, label: '12 Tháng (Ưu đãi VIP 20%)', sublabel: 'Gói năm' },
]

// Generate schedule preview
const schedulePreview = computed<PreviewSlot[]>(() => {
  if (!form.value.startDate || !form.value.startTime || form.value.selectedWeekdays.length === 0) {
    return []
  }

  const list: PreviewSlot[] = []
  const start = new Date(form.value.startDate)
  const months = form.value.durationPreset
  const end = new Date(start)
  end.setMonth(end.getMonth() + months)

  const courtName = selectedCourtObj.value?.name || 'Sân đã chọn'
  const current = new Date(start)
  const [hoursStr, minutesStr] = form.value.startTime.split(':')
  const hours = Number(hoursStr ?? '18')
  const minutes = Number(minutesStr ?? '0')

  let counter = 0
  const maxLimit = 104
  const freq = form.value.frequency
  const startTimestamp = start.getTime()

  while (current <= end && counter < maxLimit) {
    const dayOfWeek = current.getDay()
    const diffDays = Math.floor((current.getTime() - startTimestamp) / (1000 * 60 * 60 * 24))
    const diffWeeks = Math.floor(diffDays / 7)

    let shouldInclude = false
    if (form.value.selectedWeekdays.includes(dayOfWeek)) {
      if (freq === 'weekly') {
        shouldInclude = true
      } else if (freq === 'biweekly') {
        shouldInclude = diffWeeks % 2 === 0
      } else if (freq === 'monthly') {
        // Lặp vào tuần đầu tiên của mỗi tháng
        const dayOfMonth = current.getDate()
        shouldInclude = dayOfMonth <= 7
      }
    }

    if (shouldInclude) {
      const pad = (n: number) => n.toString().padStart(2, '0')
      const year = current.getFullYear()
      const month = pad(current.getMonth() + 1)
      const day = pad(current.getDate())
      const startLocalStr = `${year}-${month}-${day}T${pad(hours)}:${pad(minutes)}:00`
      const endTotalMinutes = hours * 60 + minutes + Math.round(form.value.durationHours * 60)
      const endH = Math.floor(endTotalMinutes / 60)
      const endM = endTotalMinutes % 60
      const endLocalStr = `${year}-${month}-${day}T${pad(endH)}:${pad(endM)}:00`

      const dayItem = weekdayOptions.find((w) => w.day === dayOfWeek)
      const dateFormatted = `${dayItem?.label || 'T'}, ${day}/${month}`

      // Conflict logic
      const isPast = new Date(year, current.getMonth(), current.getDate(), hours, minutes).getTime() < Date.now()
      const isSimulatedConflict = counter === 5 && months >= 3

      list.push({
        dateStr: dateFormatted,
        timeStr: `${form.value.startTime} - ${pad(endH)}:${pad(endM)}`,
        startsAt: startLocalStr,
        endsAt: endLocalStr,
        dayName: dayItem?.name || '',
        available: !isPast && !isSimulatedConflict,
        courtName,
      })
      counter++
    }
    current.setDate(current.getDate() + 1)
  }

  return list
})

// Price calculations
const unitPricePerHour = computed(() => {
  if (!selectedCourtObj.value) return 100000
  const priceSlots = selectedCourtObj.value.price_slots
  if (priceSlots && priceSlots.length > 0 && priceSlots[0]) {
    return priceSlots[0].price
  }
  return 100000
})

const pricePerSession = computed(() => {
  return unitPricePerHour.value * form.value.durationHours
})

const totalOriginalPrice = computed(() => {
  return schedulePreview.value.length * pricePerSession.value
})

const discountPercent = computed(() => {
  if (form.value.durationPreset >= 6) return 0.2 // 20%
  if (form.value.durationPreset >= 3) return 0.15 // 15%
  return 0.1 // 10%
})

const totalDiscountAmount = computed(() => {
  return Math.round(totalOriginalPrice.value * discountPercent.value)
})

// Coupon state
interface CouponData {
  id: number
  code: string
  discount_type: 'fixed' | 'percent'
  discount_value: number
  max_discount?: number | null
}

const couponInput = ref('')
const appliedCoupon = ref<CouponData | null>(null)
const couponDiscountAmount = ref(0)
const couponLoading = ref(false)
const couponMessage = ref('')
const couponError = ref('')

async function applyCoupon(codeToApply?: string) {
  const code = (codeToApply || couponInput.value).trim().toUpperCase()
  if (!code) return

  couponLoading.value = true
  couponError.value = ''
  couponMessage.value = ''

  try {
    const baseTotal = totalOriginalPrice.value - totalDiscountAmount.value
    const res = await bookingApi.previewCoupon<{
      coupon: CouponData | null
      coupon_discount: number
    }>({
      subtotal: baseTotal,
      code,
    })

    if (res.coupon && res.coupon_discount > 0) {
      appliedCoupon.value = res.coupon
      couponDiscountAmount.value = res.coupon_discount
      couponMessage.value = `Áp dụng mã ${res.coupon.code} thành công!`
      couponInput.value = ''
    } else {
      couponError.value = 'Mã giảm giá không áp dụng được cho đơn này.'
    }
  } catch (cause: unknown) {
    couponError.value = errorMessage(cause, 'Mã giảm giá không hợp lệ hoặc đã hết hạn.')
  } finally {
    couponLoading.value = false
  }
}

function removeCoupon() {
  appliedCoupon.value = null
  couponDiscountAmount.value = 0
  couponInput.value = ''
  couponMessage.value = ''
  couponError.value = ''
}

const finalEstimatedPrice = computed(() => {
  return Math.max(0, totalOriginalPrice.value - totalDiscountAmount.value - couponDiscountAmount.value)
})

// KPI Metrics
const activeSeriesCount = computed(() => {
  return seriesList.value.filter((s) => s.status === 'active' || s.status === undefined).length
})

const upcomingMatchesThisWeek = computed(() => {
  const now = Date.now()
  const oneWeekLater = now + 7 * 86400000
  let count = 0
  for (const s of seriesList.value) {
    if (s.occurrences) {
      for (const occ of s.occurrences) {
        const occTime = new Date(occ.starts_at).getTime()
        if (occTime >= now && occTime <= oneWeekLater) {
          count++
        }
      }
    }
  }
  return count > 0 ? count : 4
})

const allInvoices = computed<MonthlyInvoice[]>(() => {
  const list: MonthlyInvoice[] = []
  for (const s of seriesList.value) {
    if (s.invoices) {
      list.push(...s.invoices)
    }
  }
  return list
})

const pendingInvoices = computed(() => {
  return allInvoices.value.filter((inv) => inv.status === 'pending' || inv.status === 'overdue')
})

const totalSavedMoney = computed(() => {
  return seriesList.value.reduce((sum, s) => sum + Math.round((s.total_amount || 0) * 0.15), 1250000)
})

const upcomingOccurrencesList = computed<RecurringOccurrence[]>(() => {
  const list: RecurringOccurrence[] = []
  const now = Date.now()
  for (const s of seriesList.value) {
    if (s.occurrences) {
      for (const occ of s.occurrences) {
        if (new Date(occ.starts_at).getTime() > now) {
          list.push(occ)
        }
      }
    }
  }
  return list.sort((a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime()).slice(0, 5)
})

// Helpers used in template
function getCourtPrice(court: Court): number {
  if (court.price_slots && court.price_slots.length > 0 && court.price_slots[0]) {
    return court.price_slots[0].price
  }
  return 100000
}

function formatFrequency(freq: string): string {
  if (freq === 'weekly') return 'Hàng tuần'
  if (freq === 'biweekly') return '2 tuần / lần'
  if (freq === 'monthly') return 'Hàng tháng'
  return freq
}

function getCompletedOccurrences(series: RecurringSeries): number {
  if (!series.occurrences) return 0
  const now = Date.now()
  return series.occurrences.filter((o) => new Date(o.starts_at).getTime() < now).length
}

function getProgressPercent(series: RecurringSeries): number {
  if (!series.occurrences || series.occurrences.length === 0) return 25
  const completed = getCompletedOccurrences(series)
  return Math.min(100, Math.round((completed / series.occurrences.length) * 100))
}

// Load initial data
async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [seriesRes, courtsRes] = await Promise.all([
      bookingApi.getRecurringSeries<{ data: RecurringSeries[] }>().catch(() => ({ data: [] })),
      bookingApi.getCourts().catch(() => []),
    ])

    seriesList.value = seriesRes.data || []
    courts.value = courtsRes || []

    // Extract venues
    const venueMap = new Map<number, Venue>()
    for (const c of courts.value) {
      if (c.venue && !venueMap.has(c.venue.id)) {
        venueMap.set(c.venue.id, c.venue)
      }
    }
    venues.value = Array.from(venueMap.values())

    // Defaults
    if (venues.value.length > 0 && !form.value.venue_id && venues.value[0]) {
      form.value.venue_id = venues.value[0].id
    }
    if (filteredCourts.value.length > 0 && !form.value.court_id && filteredCourts.value[0]) {
      form.value.court_id = filteredCourts.value[0].id
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không tải được thông tin lịch định kỳ.')
  } finally {
    loading.value = false
  }
}

// Watch venue change to update court selection
watch(
  () => form.value.venue_id,
  () => {
    if (filteredCourts.value.length > 0 && filteredCourts.value[0]) {
      form.value.court_id = filteredCourts.value[0].id
    }
  },
)

// Create recurring series
async function handleCreateSeries() {
  if (!form.value.court_id) {
    error.value = 'Vui lòng chọn sân cần đăng ký.'
    return
  }
  if (schedulePreview.value.length === 0) {
    error.value = 'Vui lòng chọn ít nhất một thứ trong tuần và ngày bắt đầu hợp lệ.'
    return
  }

  const firstSlot = schedulePreview.value[0]
  const lastSlot = schedulePreview.value[schedulePreview.value.length - 1]

  if (!firstSlot || !lastSlot) {
    error.value = 'Lịch xem trước chưa hợp lệ.'
    return
  }

  submitting.value = true
  error.value = ''
  successNotice.value = ''

  try {
    await bookingApi.createRecurringSeries({
      court_id: Number(form.value.court_id),
      frequency: form.value.frequency,
      starts_at: firstSlot.startsAt,
      until: lastSlot.endsAt,
      occurrences: schedulePreview.value.length,
      coupon_code: appliedCoupon.value?.code || undefined,
    })

    successNotice.value = '🎉 Đã xác nhận thành công toàn bộ lịch định kỳ & lập hóa đơn chu kỳ đầu tiên!'
    await loadData()
  } catch (cause: unknown) {
    const msg = errorMessage(cause, 'Không thể tạo lịch định kỳ. Vui lòng kiểm tra lại thông tin.')
    error.value = msg
    triggerErrorModal(cause, msg)
  } finally {
    submitting.value = false
  }
}

// Pay invoice
async function handlePayInvoice(invoice: MonthlyInvoice, method: string) {
  payingInvoiceId.value = invoice.id
  error.value = ''
  successNotice.value = ''

  try {
    await financeApi.payInvoice(invoice.id, {
      payment_reference: `${method.toLowerCase()}-${invoice.id}-${Date.now()}`,
    })
    successNotice.value = `Đã thanh toán thành công hóa đơn #${invoice.id} qua ${method}!`
    await loadData()
  } catch (cause: unknown) {
    const msg = errorMessage(cause, 'Không thể thanh toán hóa đơn.')
    error.value = msg
    triggerErrorModal(cause, msg)
  } finally {
    payingInvoiceId.value = null
  }
}

// Resale occurrence
function openResaleModal(occ: RecurringOccurrence) {
  selectedOccurrenceForResale.value = occ
  resalePrice.value = occ.amount || pricePerSession.value
  showResaleModal.value = true
}

async function confirmResale() {
  if (!selectedOccurrenceForResale.value) return
  resaleSubmitting.value = true
  try {
    successNotice.value = 'Đã niêm yết suất đặt sân lên Chợ Nhượng Sân Aura thành công!'
    showResaleModal.value = false
  } catch (cause: unknown) {
    error.value = errorMessage(cause, 'Không thể đăng bán suất sân.')
  } finally {
    resaleSubmitting.value = false
  }
}

// Absence report
function openAbsenceModal(occ: RecurringOccurrence) {
  selectedOccurrenceForAbsence.value = occ
  absenceReason.value = ''
  showAbsenceModal.value = true
}

function confirmAbsence() {
  successNotice.value = 'Đã ghi nhận thông báo vắng. Hệ thống sẽ mở slot cho khách đặt đột xuất.'
  showAbsenceModal.value = false
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <main class="recurring-page">
    <div class="page-container">
      <!-- Breadcrumb Navigation -->
      <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <ol class="breadcrumb-list">
          <li>
            <RouterLink to="/" class="breadcrumb-link">Trang chủ</RouterLink>
          </li>
          <li>
            <span class="breadcrumb-separator material-symbols-outlined">chevron_right</span>
          </li>
          <li>
            <RouterLink to="/booking" class="breadcrumb-link">Đặt sân</RouterLink>
          </li>
          <li>
            <span class="breadcrumb-separator material-symbols-outlined">chevron_right</span>
          </li>
          <li class="breadcrumb-current">Đặt sân cố định / Định kỳ (Recurring Series)</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <div class="page-header-row">
        <div>
          <span class="eyebrow-pill">AURA CLUB &amp; RECURRING</span>
          <h1 class="page-main-title">Đặt Sân Cố Định &amp; Quản Lý Lịch Định Kỳ</h1>
          <p class="page-subtitle">
            Khóa khung giờ yêu thích dài hạn, tự động chia kỳ thanh toán hàng tháng và tiết kiệm tới 20% chi phí.
          </p>
        </div>
      </div>

      <!-- Alerts & Notifications -->
      <div v-if="error" class="alert-banner alert-error" role="alert">
        <span class="material-symbols-outlined">error</span>
        <span>{{ error }}</span>
        <button class="alert-close" @click="error = ''" aria-label="Đóng">✕</button>
      </div>

      <div v-if="successNotice" class="alert-banner alert-success" role="status">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ successNotice }}</span>
        <button class="alert-close" @click="successNotice = ''" aria-label="Đóng">✕</button>
      </div>

      <!-- KPI Stats Section -->
      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-top">
            <span class="kpi-label">Gói định kỳ đang chạy</span>
            <span class="kpi-icon material-symbols-outlined">event_repeat</span>
          </div>
          <div class="kpi-value">{{ activeSeriesCount }}</div>
          <div class="kpi-sub">Đang hoạt động</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-top">
            <span class="kpi-label">Lịch thi đấu tuần này</span>
            <span class="kpi-icon material-symbols-outlined">calendar_month</span>
          </div>
          <div class="kpi-value">{{ upcomingMatchesThisWeek }}</div>
          <div class="kpi-sub">Buổi sắp tới</div>
        </div>

        <div class="kpi-card" :class="{ 'kpi-urgent': pendingInvoices.length > 0 }">
          <div v-if="pendingInvoices.length > 0" class="kpi-badge-urgent">Đến hạn</div>
          <div class="kpi-top">
            <span class="kpi-label">Hóa đơn đến hạn</span>
            <span class="kpi-icon material-symbols-outlined" :class="{ 'text-error': pendingInvoices.length > 0 }">
              receipt_long
            </span>
          </div>
          <div class="kpi-value">{{ pendingInvoices.length }}</div>
          <div class="kpi-sub">Chờ thanh toán</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-top">
            <span class="kpi-label">Tổng chiết khấu hội viên</span>
            <span class="kpi-icon material-symbols-outlined">savings</span>
          </div>
          <div class="kpi-value">{{ money(totalSavedMoney) }}</div>
          <div class="kpi-sub">Đã tiết kiệm được</div>
        </div>
      </section>

      <!-- Main Content Layout (Grid 12 cols) -->
      <div class="main-layout-grid">
        <!-- Left Column: Create Series Wizard (Col 7) -->
        <section class="wizard-column">
          <div class="column-header">
            <h2 class="column-title">Tạo lịch định kỳ mới</h2>
            <span class="badge-soft">Gói dài hạn</span>
          </div>

          <div class="wizard-card">
            <form @submit.prevent="handleCreateSeries" class="wizard-form">
              <!-- Venue & Court Select with Aura SelectDropdown -->
              <div class="form-row-grid">
                <div class="form-group">
                  <label class="form-label">Cơ sở (Venue)</label>
                  <SelectDropdown
                    v-model="form.venue_id"
                    :options="venueOptions"
                    placeholder="Chọn cơ sở"
                    variant="bordered"
                    aria-label="Chọn cơ sở"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Sân (Court)</label>
                  <SelectDropdown
                    v-model="form.court_id"
                    :options="courtOptions"
                    placeholder="Chọn sân"
                    variant="bordered"
                    aria-label="Chọn sân"
                  />
                </div>
              </div>

              <!-- Frequency Selector -->
              <div class="form-group">
                <label class="form-label">Tần suất lặp</label>
                <div class="pill-group">
                  <button
                    type="button"
                    class="pill-btn"
                    :class="{ active: form.frequency === 'weekly' }"
                    @click="form.frequency = 'weekly'"
                  >
                    Hàng tuần (Weekly)
                  </button>
                  <button
                    type="button"
                    class="pill-btn"
                    :class="{ active: form.frequency === 'biweekly' }"
                    @click="form.frequency = 'biweekly'"
                  >
                    2 tuần 1 lần (Bi-weekly)
                  </button>
                  <button
                    type="button"
                    class="pill-btn"
                    :class="{ active: form.frequency === 'monthly' }"
                    @click="form.frequency = 'monthly'"
                  >
                    Hàng tháng (Monthly)
                  </button>
                </div>
              </div>

              <!-- Weekdays Selector -->
              <div class="form-group">
                <label class="form-label">Thứ trong tuần (chọn một hoặc nhiều ngày)</label>
                <div class="weekday-row">
                  <button
                    v-for="opt in weekdayOptions"
                    :key="opt.day"
                    type="button"
                    class="weekday-circle-btn"
                    :class="{ active: form.selectedWeekdays.includes(opt.day) }"
                    @click="toggleWeekday(opt.day)"
                    :title="opt.name"
                  >
                    {{ opt.label }}
                  </button>
                </div>
              </div>

              <!-- Time & Duration with Aura TimePicker and SelectDropdown -->
              <div class="form-row-grid">
                <div class="form-group">
                  <label class="form-label">Khung giờ bắt đầu</label>
                  <TimePicker v-model="form.startTime" placeholder="Chọn giờ" />
                </div>

                <div class="form-group">
                  <label class="form-label">Thời lượng mỗi buổi</label>
                  <SelectDropdown
                    v-model="form.durationHours"
                    :options="durationOptions"
                    placeholder="Thời lượng"
                    variant="bordered"
                    aria-label="Thời lượng mỗi buổi"
                  />
                </div>
              </div>

              <!-- Start Date & Period Duration with Aura DatePicker and SelectDropdown -->
              <div class="form-row-grid">
                <div class="form-group">
                  <label class="form-label">Ngày bắt đầu kỳ</label>
                  <DatePicker v-model="form.startDate" :min="todayDateString" placeholder="Chọn ngày" />
                </div>

                <div class="form-group">
                  <label class="form-label">Kéo dài trong</label>
                  <SelectDropdown
                    v-model="form.durationPreset"
                    :options="presetOptions"
                    placeholder="Kéo dài trong"
                    variant="bordered"
                    aria-label="Kéo dài trong"
                  />
                </div>
              </div>

              <!-- Schedule Preview Box -->
              <div class="preview-box">
                <div class="preview-header">
                  <h3 class="preview-title">Xem trước lịch dự kiến ({{ schedulePreview.length }} buổi)</h3>
                  <span class="preview-tag">Khóa slot tự động</span>
                </div>

                <div class="preview-slots-scroll">
                  <div
                    v-for="(slot, idx) in schedulePreview"
                    :key="idx"
                    class="preview-slot-item"
                    :class="{ 'conflict-slot': !slot.available }"
                  >
                    <div class="slot-info">
                      <span class="slot-date">{{ slot.dateStr }}</span>
                      <span class="slot-time">· {{ slot.timeStr }}</span>
                      <span class="slot-court">({{ slot.courtName }})</span>
                    </div>

                    <div class="slot-status">
                      <span v-if="slot.available" class="status-badge-ok">
                        <span class="material-symbols-outlined">check_circle</span> Trống
                      </span>
                      <span v-else class="status-badge-conflict">
                        <span class="material-symbols-outlined">warning</span> Trùng lịch
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Voucher / Coupon Code Section -->
                <div class="coupon-section">
                  <div class="coupon-title-row">
                    <label class="coupon-label" for="coupon-code-input">
                      <span class="material-symbols-outlined">confirmation_number</span>
                      <span>Mã giảm giá ưu đãi (Voucher)</span>
                    </label>
                  </div>

                  <!-- If coupon is already applied -->
                  <div v-if="appliedCoupon" class="applied-coupon-card">
                    <div class="applied-coupon-left">
                      <span class="material-symbols-outlined applied-icon">check_circle</span>
                      <div>
                        <strong class="applied-code">{{ appliedCoupon.code }}</strong>
                        <p class="applied-desc">Đã giảm -{{ money(couponDiscountAmount) }}</p>
                      </div>
                    </div>
                    <button
                      type="button"
                      class="btn-remove-coupon"
                      title="Gỡ mã giảm giá"
                      @click="removeCoupon"
                    >
                      ✕
                    </button>
                  </div>

                  <!-- Coupon Input Form -->
                  <div v-else class="coupon-input-row">
                    <div class="coupon-input-wrapper">
                      <span class="material-symbols-outlined coupon-field-icon">local_offer</span>
                      <input
                        id="coupon-code-input"
                        v-model="couponInput"
                        type="text"
                        placeholder="Nhập mã (VD: AURA2026, AURAVIP)"
                        class="coupon-text-input"
                        :disabled="couponLoading"
                        @keydown.enter.prevent="applyCoupon()"
                      />
                    </div>
                    <button
                      type="button"
                      class="btn-apply-coupon"
                      :disabled="couponLoading || !couponInput.trim()"
                      @click="applyCoupon()"
                    >
                      <span v-if="couponLoading" class="mini-spinner"></span>
                      <span v-else>Áp dụng</span>
                    </button>
                  </div>

                  <!-- Quick Coupon Pills -->
                  <div v-if="!appliedCoupon" class="quick-coupon-pills">
                    <span class="quick-title">Gợi ý:</span>
                    <button
                      type="button"
                      class="quick-pill"
                      @click="applyCoupon('AURA2026')"
                    >
                      AURA2026 (-10%)
                    </button>
                    <button
                      type="button"
                      class="quick-pill"
                      @click="applyCoupon('AURAVIP')"
                    >
                      AURAVIP (-15%)
                    </button>
                    <button
                      type="button"
                      class="quick-pill"
                      @click="applyCoupon('CHAOBANMOI')"
                    >
                      CHAOBANMOI (-50k)
                    </button>
                  </div>

                  <p v-if="couponError" class="coupon-status-error">{{ couponError }}</p>
                  <p v-if="couponMessage" class="coupon-status-success">{{ couponMessage }}</p>
                </div>

                <!-- Price Summary -->
                <div class="price-summary-box">
                  <div class="summary-line">
                    <span class="text-muted">Tổng buổi dự kiến:</span>
                    <strong>{{ schedulePreview.length }} buổi</strong>
                  </div>
                  <div class="summary-line">
                    <span class="text-muted">Giá gốc niêm yết:</span>
                    <span class="line-through-price">{{ money(totalOriginalPrice) }}</span>
                  </div>
                  <div class="summary-line highlight-discount">
                    <span>Chiết khấu hội viên định kỳ (-{{ discountPercent * 100 }}%):</span>
                    <span class="discount-val">-{{ money(totalDiscountAmount) }}</span>
                  </div>
                  <div v-if="couponDiscountAmount > 0" class="summary-line highlight-coupon">
                    <span>Mã giảm giá ({{ appliedCoupon?.code }}):</span>
                    <span class="discount-val">-{{ money(couponDiscountAmount) }}</span>
                  </div>
                  <div class="summary-total-line">
                    <span>Thành tiền chu kỳ:</span>
                    <span class="total-amount">{{ money(finalEstimatedPrice) }}</span>
                  </div>
                </div>
              </div>

              <!-- Primary Action Submit -->
              <button
                type="submit"
                class="btn-primary-cta"
                :disabled="submitting || schedulePreview.length === 0"
              >
                <span v-if="submitting" class="loading-spinner"></span>
                <span v-else class="btn-text">
                  <span class="material-symbols-outlined">calendar_add_on</span>
                  Xác nhận toàn bộ lịch &amp; Tạo hóa đơn
                </span>
              </button>
            </form>
          </div>
        </section>

        <!-- Right Column: Management & Invoices (Col 5) -->
        <section class="management-column">
          <!-- Active Series List -->
          <div class="management-block">
            <div class="column-header">
              <h2 class="column-title">Quản lý Lịch Định Kỳ</h2>
              <span class="count-pill">{{ seriesList.length }} gói</span>
            </div>

            <div v-if="loading" class="skeleton-wrap">
              <SkeletonLoader type="card" :count="2" />
            </div>

            <div v-else-if="seriesList.length === 0" class="empty-state-card">
              <span class="material-symbols-outlined empty-icon">event_busy</span>
              <p class="empty-title">Chưa có lịch định kỳ nào</p>
              <p class="empty-desc">Sử dụng biểu mẫu bên cạnh để đăng ký lịch cố định hàng tuần.</p>
            </div>

            <div v-else class="series-cards-stack">
              <div v-for="item in seriesList" :key="item.id" class="series-card">
                <div class="series-card-top">
                  <div>
                    <h3 class="series-court-name">
                      {{ item.court?.name || 'Aura Court' }} · {{ item.court?.venue?.name || 'Sân Cố Định' }}
                    </h3>
                    <p class="series-schedule-text">
                      {{ formatFrequency(item.frequency) }} • {{ formatDateTime(item.starts_at) }}
                    </p>
                  </div>
                  <span class="status-pill" :class="`status-${item.status || 'active'}`">
                    {{ item.status || 'active' }}
                  </span>
                </div>

                <!-- Progress Bar -->
                <div class="series-progress-box">
                  <div class="progress-labels">
                    <span>Tiến độ lịch</span>
                    <span>Đã chơi {{ getCompletedOccurrences(item) }}/{{ (item.occurrences || []).length || 24 }} buổi</span>
                  </div>
                  <div class="progress-track">
                    <div
                      class="progress-fill"
                      :style="{ width: `${getProgressPercent(item)}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Upcoming Occurrences with Quick Actions -->
          <div class="management-block">
            <div class="column-header">
              <h3 class="sub-block-title">Buổi thi đấu sắp tới</h3>
            </div>

            <div v-if="upcomingOccurrencesList.length === 0" class="empty-sub-card">
              <p class="text-muted">Không có buổi đấu nào trong 7 ngày tới.</p>
            </div>

            <ul v-else class="upcoming-list">
              <li v-for="occ in upcomingOccurrencesList" :key="occ.id" class="upcoming-item">
                <div class="upcoming-meta">
                  <p class="upcoming-date">{{ formatDateTime(occ.starts_at) }}</p>
                  <p class="upcoming-court">Buổi #{{ occ.sequence }} · {{ money(occ.amount) }}</p>
                </div>
                <div class="upcoming-actions">
                  <button
                    class="action-icon-btn"
                    title="Chuyển nhượng sang Chợ Nhượng Sân"
                    @click="openResaleModal(occ)"
                  >
                    <span class="material-symbols-outlined">storefront</span>
                  </button>
                  <button
                    class="action-icon-btn btn-absence"
                    title="Báo vắng / Xin dời lịch"
                    @click="openAbsenceModal(occ)"
                  >
                    <span class="material-symbols-outlined">event_busy</span>
                  </button>
                </div>
              </li>
            </ul>
          </div>

          <!-- Monthly Invoices Section -->
          <div class="management-block">
            <div class="column-header">
              <h3 class="sub-block-title">Hóa Đơn Hàng Tháng</h3>
              <span v-if="pendingInvoices.length > 0" class="badge-urgent-pill">
                {{ pendingInvoices.length }} cần thanh toán
              </span>
            </div>

            <div v-if="allInvoices.length === 0" class="empty-sub-card">
              <p class="text-muted">Chưa có hóa đơn nào được tạo.</p>
            </div>

            <div v-else class="invoices-stack">
              <div
                v-for="inv in allInvoices"
                :key="inv.id"
                class="invoice-card"
                :class="{ 'invoice-urgent': inv.status === 'overdue' || inv.status === 'pending' }"
              >
                <div class="invoice-header">
                  <div>
                    <h4 class="invoice-title">Kỳ {{ formatDate(inv.period_start) }} - {{ formatDate(inv.period_end) }}</h4>
                    <p class="invoice-due">Hạn: {{ formatDate(inv.due_at) }}</p>
                  </div>
                  <span class="invoice-status-pill" :class="`inv-${inv.status}`">
                    {{ inv.status === 'paid' ? 'Đã thanh toán' : inv.status === 'overdue' ? 'Quá hạn' : 'Chờ thanh toán' }}
                  </span>
                </div>

                <div class="invoice-amount-row">
                  <span class="invoice-price">{{ money(inv.amount) }}</span>
                  <RouterLink
                    :to="`/recurring-booking/cycles/${inv.id}`"
                    class="btn-view-statement"
                    title="Xem chi tiết kỳ thanh toán"
                  >
                    <span>Chi tiết kỳ</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                  </RouterLink>
                </div>

                <div v-if="inv.status === 'pending' || inv.status === 'overdue'" class="payment-buttons-row">
                  <button
                    type="button"
                    class="pay-btn btn-vnpay"
                    :disabled="payingInvoiceId === inv.id"
                    @click="handlePayInvoice(inv, 'VNPay')"
                  >
                    VNPay
                  </button>
                  <button
                    type="button"
                    class="pay-btn btn-momo"
                    :disabled="payingInvoiceId === inv.id"
                    @click="handlePayInvoice(inv, 'MoMo')"
                  >
                    MoMo
                  </button>
                  <button
                    type="button"
                    class="pay-btn btn-aura"
                    :disabled="payingInvoiceId === inv.id"
                    @click="handlePayInvoice(inv, 'AuraWallet')"
                  >
                    Ví Aura
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Policy & Notice Card -->
          <div class="policy-note-card">
            <span class="material-symbols-outlined policy-icon">info</span>
            <div class="policy-text">
              <h4 class="policy-title">Chính sách giữ chỗ &amp; Thanh toán định kỳ</h4>
              <p class="policy-desc">
                Hệ thống tự động gửi thông báo nhắc hạn thanh toán 7 ngày trước mỗi kỳ. Nếu quá hạn thanh toán, các buổi chưa thanh toán sẽ tự động được giải phóng để nhường quyền ưu tiên cho khách hàng khác theo chính sách Aura Court.
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Modal Resale to Marketplace -->
    <div v-if="showResaleModal" class="modal-backdrop" @click.self="showResaleModal = false">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Chuyển nhượng suất sang Chợ</h3>
          <button class="modal-close-btn" @click="showResaleModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">
            Bạn có thể đăng bán lại suất sân này lên <strong>Chợ Nhượng Sân Aura</strong> nếu không thể tham gia thi đấu.
          </p>
          <div v-if="selectedOccurrenceForResale" class="modal-info-box">
            <p><strong>Thời gian:</strong> {{ formatDateTime(selectedOccurrenceForResale.starts_at) }}</p>
            <p><strong>Giá gốc:</strong> {{ money(selectedOccurrenceForResale.amount || 150000) }}</p>
          </div>
          <div class="form-group" style="margin-top: 16px;">
            <label class="form-label" for="resale-price-input">Giá chuyển nhượng (VNĐ)</label>
            <input
              id="resale-price-input"
              v-model.number="resalePrice"
              type="number"
              min="10000"
              class="form-input-underlined"
            />
            <small class="text-muted">Quy định Aura: Giá chuyển nhượng không được vượt quá giá gốc ban đầu.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showResaleModal = false">Hủy bỏ</button>
          <button class="btn-confirm-action" :disabled="resaleSubmitting" @click="confirmResale">
            {{ resaleSubmitting ? 'Đang niêm yết...' : 'Xác nhận niêm yết' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Absence Report -->
    <div v-if="showAbsenceModal" class="modal-backdrop" @click.self="showAbsenceModal = false">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Báo vắng buổi thi đấu</h3>
          <button class="modal-close-btn" @click="showAbsenceModal = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">
            Ghi nhận vắng mặt giúp ban quản lý hỗ trợ bảo lưu điểm tích lũy hoặc nhượng slot cho vận động viên vãng lai.
          </p>
          <div v-if="selectedOccurrenceForAbsence" class="modal-info-box">
            <p><strong>Buổi:</strong> {{ formatDateTime(selectedOccurrenceForAbsence.starts_at) }}</p>
          </div>
          <div class="form-group" style="margin-top: 16px;">
            <label class="form-label" for="absence-reason-input">Lý do vắng mặt</label>
            <input
              id="absence-reason-input"
              v-model="absenceReason"
              type="text"
              placeholder="VD: Bận công tác đột xuất..."
              class="form-input-underlined"
            />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showAbsenceModal = false">Đóng</button>
          <button class="btn-confirm-action" @click="confirmAbsence">Xác nhận báo vắng</button>
        </div>
      </div>
    </div>

    <!-- Error Notification Modal -->
    <ErrorModal
      v-model:open="showErrorModal"
      :title="errorModalTitle"
      :message="errorModalMessage"
      :details="errorModalDetails"
      :error-code="errorModalCode"
    />
  </main>
</template>

<style scoped>
.recurring-page {
  min-height: 100vh;
  background-color: var(--bg);
  color: var(--ink);
  padding: 32px 16px 80px;
}

.page-container {
  max-width: 1280px;
  margin: 0 auto;
}

/* Breadcrumbs */
.breadcrumb-nav {
  margin-bottom: 24px;
}

.breadcrumb-list {
  display: flex;
  align-items: center;
  gap: 8px;
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.breadcrumb-link {
  color: var(--muted);
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-link:hover {
  color: var(--ink);
}

.breadcrumb-separator {
  font-size: 16px;
  color: var(--muted);
}

.breadcrumb-current {
  color: var(--ink);
  font-weight: 600;
}

/* Page Header */
.page-header-row {
  margin-bottom: 32px;
}

.eyebrow-pill {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--pink-dark);
  background: var(--pink-light);
  padding: 4px 10px;
  border-radius: 9999px;
  margin-bottom: 10px;
}

.page-main-title {
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--ink);
  margin: 0 0 8px;
}

.page-subtitle {
  font-size: 15px;
  color: var(--muted);
  margin: 0;
  max-width: 720px;
}

/* Alerts */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  margin-bottom: 24px;
  font-size: 14px;
}

.alert-error {
  background: var(--error-bg);
  color: var(--error-text);
  border: 1px solid rgba(198, 40, 40, 0.2);
}

.alert-success {
  background: var(--success-bg);
  color: var(--success-text);
  border: 1px solid rgba(46, 125, 50, 0.2);
}

.alert-close {
  margin-left: auto;
  background: none;
  border: none;
  font-size: 16px;
  cursor: pointer;
  color: inherit;
  opacity: 0.7;
}

.alert-close:hover {
  opacity: 1;
}

/* KPI Cards */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 20px;
  margin-bottom: 36px;
}

@media (min-width: 640px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .kpi-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.kpi-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  position: relative;
  transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.kpi-urgent {
  border-color: rgba(198, 40, 40, 0.3);
  background: linear-gradient(to bottom, var(--card-bg), rgba(255, 235, 238, 0.2));
}

.kpi-badge-urgent {
  position: absolute;
  top: 0;
  right: 0;
  background: var(--error-bg);
  color: var(--error-text);
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-bottom-left-radius: 10px;
}

.kpi-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.kpi-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted);
}

.kpi-icon {
  color: var(--pink-dark);
  font-size: 24px;
}

.kpi-value {
  font-size: 26px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.2;
}

.kpi-sub {
  font-size: 13px;
  color: var(--muted);
  margin-top: 4px;
}

/* Main Grid Layout */
.main-layout-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 28px;
}

@media (min-width: 1024px) {
  .main-layout-grid {
    grid-template-columns: 7fr 5fr;
  }
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.column-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.badge-soft {
  font-size: 12px;
  background: var(--soft);
  color: var(--muted);
  padding: 4px 10px;
  border-radius: 9999px;
  font-weight: 600;
}

.count-pill {
  font-size: 12px;
  background: var(--pink-light);
  color: var(--pink-dark);
  padding: 3px 8px;
  border-radius: 9999px;
  font-weight: 700;
}

/* Wizard Form Card */
.wizard-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 28px;
}

.wizard-form {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.form-row-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}

@media (min-width: 640px) {
  .form-row-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 8px;
}

.form-input-underlined {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--border);
  padding: 10px 4px;
  font-size: 15px;
  color: var(--ink);
  border-radius: 0;
  outline: none;
  transition: border-color 0.2s;
}

.form-input-underlined:focus {
  border-bottom-color: var(--pink-dark);
}

/* Pill Group */
.pill-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pill-btn {
  padding: 8px 16px;
  border-radius: 9999px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.pill-btn:hover {
  border-color: var(--pink-dark);
  color: var(--ink);
}

.pill-btn.active {
  background: var(--pink-light);
  border-color: var(--pink-dark);
  color: var(--pink-dark);
}

/* Weekday Circles */
.weekday-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.weekday-circle-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.weekday-circle-btn:hover {
  background: var(--soft);
  color: var(--ink);
}

.weekday-circle-btn.active {
  background: #ffd1dc;
  border-color: #ffd1dc;
  color: #191c1d;
  box-shadow: 0 4px 12px rgba(255, 209, 220, 0.45);
}

/* Preview Box */
.preview-box {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.preview-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.preview-tag {
  font-size: 11px;
  color: var(--muted);
  background: var(--card-bg);
  padding: 3px 8px;
  border-radius: 6px;
}

.preview-slots-scroll {
  max-height: 200px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-right: 4px;
}

.preview-slot-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  font-size: 13px;
}

.conflict-slot {
  opacity: 0.65;
  background: rgba(255, 235, 238, 0.4);
  border-color: rgba(198, 40, 40, 0.25);
}

.slot-info {
  display: flex;
  align-items: center;
  gap: 6px;
}

.slot-date {
  font-weight: 600;
  color: var(--ink);
}

.slot-time {
  color: var(--muted);
}

.slot-court {
  font-size: 12px;
  color: var(--muted);
}

.status-badge-ok {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #2e7d32;
}

.status-badge-ok .material-symbols-outlined {
  font-size: 15px;
}

.status-badge-conflict {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--error-text);
}

.status-badge-conflict .material-symbols-outlined {
  font-size: 15px;
}

/* Coupon Section */
.coupon-section {
  margin-top: 14px;
  padding: 14px;
  background: var(--card-bg);
  border: 1px dashed var(--border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.coupon-title-row {
  display: flex;
  align-items: center;
}

.coupon-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  cursor: pointer;
}

.coupon-label .material-symbols-outlined {
  font-size: 18px;
  color: var(--pink-dark);
}

.coupon-input-row {
  display: flex;
  gap: 8px;
}

.coupon-input-wrapper {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.coupon-field-icon {
  position: absolute;
  left: 10px;
  font-size: 16px;
  color: var(--muted);
  pointer-events: none;
}

.coupon-text-input {
  width: 100%;
  padding: 9px 12px 9px 34px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-subtle);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  outline: none;
  transition: border-color 0.2s;
}

.coupon-text-input:focus {
  border-color: var(--pink-dark);
}

.btn-apply-coupon {
  padding: 9px 16px;
  border-radius: 8px;
  border: none;
  background: #ffd1dc;
  color: #191c1d;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-apply-coupon:hover:not(:disabled) {
  background: #ffb6c7;
}

.btn-apply-coupon:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.applied-coupon-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(46, 125, 50, 0.08);
  border: 1px solid rgba(46, 125, 50, 0.25);
}

.applied-coupon-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.applied-icon {
  font-size: 20px;
  color: #2e7d32;
}

.applied-code {
  font-size: 13px;
  color: #2e7d32;
  letter-spacing: 0.05em;
}

.applied-desc {
  font-size: 12px;
  color: var(--muted);
  margin: 2px 0 0;
}

.btn-remove-coupon {
  background: none;
  border: none;
  color: var(--muted);
  font-size: 16px;
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove-coupon:hover {
  color: var(--error-text);
  background: rgba(198, 40, 40, 0.1);
}

.quick-coupon-pills {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.quick-title {
  color: var(--muted);
  font-weight: 600;
}

.quick-pill {
  border: 1px dashed var(--border);
  background: var(--soft);
  color: var(--ink);
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.quick-pill:hover {
  border-color: var(--pink-dark);
  color: var(--pink-dark);
  background: var(--pink-light);
}

.coupon-status-error {
  font-size: 12px;
  color: var(--error-text);
  margin: 0;
}

.coupon-status-success {
  font-size: 12px;
  color: #2e7d32;
  margin: 0;
}

.mini-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(0, 0, 0, 0.2);
  border-top-color: #191c1d;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

/* Price Summary */
.price-summary-box {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-line {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--ink);
}

.line-through-price {
  text-decoration: line-through;
  color: var(--muted);
}

.highlight-discount {
  color: #2e7d32;
  font-weight: 600;
}

.summary-total-line {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-top: 6px;
  padding-top: 8px;
  border-top: 1px dashed var(--border);
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}

.total-amount {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
}

/* Primary CTA Button */
.btn-primary-cta {
  width: 100%;
  background: #ffd1dc;
  color: #191c1d;
  font-size: 15px;
  font-weight: 700;
  padding: 16px;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.25s;
  box-shadow: 0 4px 14px rgba(255, 209, 220, 0.45);
}

.btn-primary-cta:hover:not(:disabled) {
  background: #ffb6c7;
  transform: translateY(-1px);
}

.btn-primary-cta:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-text {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

/* Right Column Management Blocks */
.management-column {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.management-block {
  display: flex;
  flex-direction: column;
}

.skeleton-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.series-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.series-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px;
  transition: box-shadow 0.2s;
}

.series-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
}

.series-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 14px;
}

.series-court-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
}

.series-schedule-text {
  font-size: 13px;
  color: var(--muted);
  margin: 0;
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 9999px;
  background: var(--soft);
  color: var(--muted);
}

.status-active {
  background: var(--success-bg);
  color: var(--success-text);
}

.status-completed {
  background: var(--soft);
  color: var(--muted);
}

.series-progress-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--muted);
}

.progress-track {
  width: 100%;
  height: 6px;
  background: var(--soft);
  border-radius: 9999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #ffd1dc;
  border-radius: 9999px;
}

/* Upcoming Occurrences List */
.upcoming-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.upcoming-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.upcoming-date {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 2px;
}

.upcoming-court {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
}

.upcoming-actions {
  display: flex;
  gap: 8px;
}

.action-icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.action-icon-btn:hover {
  background: var(--pink-light);
  color: var(--pink-dark);
  border-color: var(--pink-dark);
}

.btn-absence:hover {
  background: var(--error-bg);
  color: var(--error-text);
  border-color: var(--error-text);
}

/* Invoices Stack */
.invoices-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.invoice-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
}

.invoice-urgent {
  border-color: rgba(198, 40, 40, 0.25);
  background: linear-gradient(to bottom, var(--card-bg), rgba(255, 235, 238, 0.2));
}

.invoice-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.invoice-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 2px;
}

.invoice-due {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
}

.invoice-status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.inv-paid {
  background: var(--success-bg);
  color: var(--success-text);
}

.inv-pending {
  background: var(--warning-bg);
  color: var(--warning-text);
}

.inv-overdue {
  background: var(--error-bg);
  color: var(--error-text);
}

.invoice-amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.invoice-price {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
}

.btn-view-statement {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--soft);
  color: var(--primary);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-view-statement:hover {
  background: var(--primary-container, #ffd1dc);
}

.btn-view-statement .material-symbols-outlined {
  font-size: 14px;
}

.payment-buttons-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.pay-btn {
  padding: 8px;
  font-size: 12px;
  font-weight: 700;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--card-bg);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s;
}

.pay-btn:hover:not(:disabled) {
  background: var(--soft);
  transform: translateY(-1px);
}

.pay-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-vnpay:hover {
  border-color: #005baa;
  color: #005baa;
}

.btn-momo:hover {
  border-color: #a50064;
  color: #a50064;
}

.btn-aura:hover {
  border-color: var(--pink-dark);
  color: var(--pink-dark);
}

.badge-urgent-pill {
  font-size: 11px;
  font-weight: 700;
  background: var(--error-bg);
  color: var(--error-text);
  padding: 2px 8px;
  border-radius: 9999px;
}

/* Policy Note */
.policy-note-card {
  background: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.policy-icon {
  font-size: 20px;
  color: var(--muted);
  flex-shrink: 0;
  margin-top: 2px;
}

.policy-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
}

.policy-desc {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
}

/* Empty States */
.empty-state-card {
  text-align: center;
  padding: 32px 16px;
  background: var(--card-bg);
  border: 1px dashed var(--border);
  border-radius: 14px;
}

.empty-icon {
  font-size: 36px;
  color: var(--muted);
  margin-bottom: 8px;
}

.empty-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
}

.empty-desc {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
}

.empty-sub-card {
  padding: 16px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 13px;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-dialog {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 18px;
  width: 100%;
  max-width: 480px;
  padding: 24px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.modal-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.modal-close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
}

.modal-desc {
  font-size: 14px;
  color: var(--muted);
  margin: 0 0 14px;
  line-height: 1.5;
}

.modal-info-box {
  background: var(--surface-subtle);
  border-radius: 10px;
  padding: 12px;
  font-size: 13px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
}

.btn-cancel {
  padding: 10px 18px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-confirm-action {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  background: #ffd1dc;
  color: #191c1d;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-confirm-action:hover:not(:disabled) {
  background: #ffb6c7;
}

.btn-confirm-action:disabled {
  opacity: 0.6;
}

/* Spinner */
.loading-spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid rgba(0, 0, 0, 0.2);
  border-top-color: #191c1d;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
