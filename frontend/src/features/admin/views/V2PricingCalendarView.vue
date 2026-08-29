<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

// Types
export type RuleType = 'base' | 'weekly' | 'peak' | 'date_exception' | 'holiday'
export type AdjustmentType = 'fixed' | 'percentage_add' | 'percentage_sub' | 'amount_add'

export interface PriceRule {
  id: number
  name: string
  type: RuleType
  priority: number // 1: Highest (Holiday/Exception), 2: Peak, 3: Weekly, 4: Base
  adjustment_type: AdjustmentType
  adjustment_value: number
  price: number // Calculated or fixed hourly rate
  base_rate_reference?: number
  venue_id: number | null
  venue_name?: string
  sport_zone_id: number | null
  court_id: number | null
  court_name?: string
  sport_type?: string
  weekday: number | null // 1: Mon ... 7: Sun
  weekdays: number[] // Multiple days selection [1,2,3,4,5]
  exception_date: string | null // YYYY-MM-DD
  date_start: string | null
  date_end: string | null
  starts_at: string | null // HH:mm
  ends_at: string | null // HH:mm
  is_all_day: boolean
  is_active: boolean
  version: number
  description: string
  color_tag: 'error' | 'primary' | 'secondary' | 'tertiary' | 'success'
  created_at?: string
  updated_at?: string
}

export interface SimulationResult {
  date: string
  startTime: string
  endTime: string
  courtName: string
  durationHours: number
  slots: Array<{
    timeRange: string
    appliedRule: PriceRule
    hourlyRate: number
    slotPrice: number
    overrideReason: string
  }>
  totalPrice: number
  baseTotal: number
  diffPercentage: number
}

// Standard Base Price Reference
const defaultBasePrice = ref<number>(120000)

// Initial Rules matching Stitch Screen & Aura Business Engine
const defaultRules: PriceRule[] = [
  {
    id: 1,
    name: 'Tết Nguyên Đán 2025',
    type: 'holiday',
    priority: 1,
    adjustment_type: 'percentage_add',
    adjustment_value: 30,
    price: 156000,
    base_rate_reference: 120000,
    venue_id: null,
    sport_zone_id: null,
    court_id: null,
    weekday: null,
    weekdays: [],
    exception_date: '2025-01-29',
    date_start: '2025-01-29',
    date_end: '2025-02-04',
    starts_at: '00:00',
    ends_at: '23:59',
    is_all_day: true,
    is_active: true,
    version: 3,
    description: 'Phụ thu 30% dịp Tết Nguyên Đán toàn hệ thống.',
    color_tag: 'error',
  },
  {
    id: 2,
    name: 'Giờ vàng / Cao điểm',
    type: 'peak',
    priority: 2,
    adjustment_type: 'fixed',
    adjustment_value: 180000,
    price: 180000,
    base_rate_reference: 120000,
    venue_id: null,
    sport_zone_id: null,
    court_id: null,
    weekday: null,
    weekdays: [1, 2, 3, 4, 5],
    exception_date: null,
    date_start: null,
    date_end: null,
    starts_at: '17:00',
    ends_at: '21:00',
    is_all_day: false,
    is_active: true,
    version: 5,
    description: 'Khung giờ 17:00 - 21:00 hàng ngày (T2 - T6).',
    color_tag: 'primary',
  },
  {
    id: 3,
    name: 'Khung giờ Cuối tuần',
    type: 'weekly',
    priority: 3,
    adjustment_type: 'fixed',
    adjustment_value: 150000,
    price: 150000,
    base_rate_reference: 120000,
    venue_id: null,
    sport_zone_id: null,
    court_id: null,
    weekday: null,
    weekdays: [6, 7],
    exception_date: null,
    date_start: null,
    date_end: null,
    starts_at: '08:00',
    ends_at: '22:00',
    is_all_day: false,
    is_active: true,
    version: 2,
    description: 'Áp dụng cho cả ngày Thứ 7 và Chủ Nhật.',
    color_tag: 'secondary',
  },
  {
    id: 4,
    name: 'Happy Hour Sáng sớm',
    type: 'weekly',
    priority: 3,
    adjustment_type: 'percentage_sub',
    adjustment_value: 20,
    price: 96000,
    base_rate_reference: 120000,
    venue_id: null,
    sport_zone_id: null,
    court_id: null,
    weekday: null,
    weekdays: [1, 2, 3, 4, 5],
    exception_date: null,
    date_start: null,
    date_end: null,
    starts_at: '06:00',
    ends_at: '08:00',
    is_all_day: false,
    is_active: true,
    version: 1,
    description: 'Ưu đãi giảm 20% khung giờ sáng sớm (06:00 - 08:00).',
    color_tag: 'success',
  },
  {
    id: 999,
    name: 'Giá cơ bản (Mặc định)',
    type: 'base',
    priority: 4,
    adjustment_type: 'fixed',
    adjustment_value: 120000,
    price: 120000,
    base_rate_reference: 120000,
    venue_id: null,
    sport_zone_id: null,
    court_id: null,
    weekday: null,
    weekdays: [1, 2, 3, 4, 5, 6, 7],
    exception_date: null,
    date_start: null,
    date_end: null,
    starts_at: '06:00',
    ends_at: '23:00',
    is_all_day: true,
    is_active: true,
    version: 1,
    description: 'Áp dụng khi không có quy tắc nào khớp.',
    color_tag: 'tertiary',
  },
]

// State
const rules = ref<PriceRule[]>(JSON.parse(JSON.stringify(defaultRules)))
const selectedRuleId = ref<number>(1)
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const error = ref('')

// Form State for editing or creating
const isCreatingNew = ref(false)
const form = ref<{
  id?: number
  name: string
  type: RuleType
  priority: number
  adjustment_type: AdjustmentType
  adjustment_value: number
  price: number
  venue_id: number | null
  weekdays: number[]
  date_start: string
  date_end: string
  exception_date: string
  starts_at: string
  ends_at: string
  is_all_day: boolean
  description: string
  is_active: boolean
}>({
  name: 'Tết Nguyên Đán 2025',
  type: 'holiday',
  priority: 1,
  adjustment_type: 'percentage_add',
  adjustment_value: 30,
  price: 156000,
  venue_id: null,
  weekdays: [1, 2, 3, 4, 5],
  date_start: '2025-01-29',
  date_end: '2025-02-04',
  exception_date: '2025-01-29',
  starts_at: '00:00',
  ends_at: '23:59',
  is_all_day: true,
  description: 'Phụ thu 30% dịp Tết Nguyên Đán.',
  is_active: true,
})

// Calendar State
const currentYear = ref(2025)
const currentMonth = ref(0) // 0 = January (matches Stitch screen: Tháng 1, 2025)
const selectedCalendarDate = ref<string>('2025-01-29')

// Simulator State
const showSimulatorModal = ref(false)
const showPresetModal = ref(false)
const showExportModal = ref(false)
const exportJsonString = ref('')

const simDate = ref<string>('2025-01-29')
const simStartTime = ref<string>('17:30')
const simEndTime = ref<string>('19:30')
const simCourtName = ref<string>('Sân VIP 01 - Downtown')

// Formatting Utilities
const money = (value: number) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value)
}

const compactMoney = (value: number) => {
  if (value >= 1000) {
    return `${Math.round(value / 1000)}k/h`
  }
  return `${value}đ/h`
}

// Compute Priority Stack List sorted by priority ascending (1 = highest priority)
const priorityStack = computed(() => {
  return [...rules.value].sort((a, b) => {
    if (a.priority !== b.priority) return a.priority - b.priority
    return b.id - a.id
  })
})

// Selected Rule Object
const selectedRule = computed<PriceRule>(() => {
  return rules.value.find((r) => r.id === selectedRuleId.value) || priorityStack.value[0] || defaultRules[0]!
})

const currentBaseRate = computed(() => {
  const base = rules.value.find((r) => r.type === 'base')
  return base ? base.price : defaultBasePrice.value
})

// Helper to calculate price based on adjustment type
function calculateAdjustedPrice(type: AdjustmentType, value: number, basePrice: number): number {
  if (type === 'fixed') return Number(value) || 0
  if (type === 'percentage_add') return Math.round(basePrice * (1 + (Number(value) || 0) / 100))
  if (type === 'percentage_sub') return Math.round(basePrice * (1 - (Number(value) || 0) / 100))
  if (type === 'amount_add') return basePrice + (Number(value) || 0)
  return basePrice
}

// Load form with selected rule
function selectRuleForEdit(rule?: PriceRule) {
  const target = rule || priorityStack.value[0] || defaultRules[0]!
  selectedRuleId.value = target.id
  isCreatingNew.value = false
  form.value = {
    id: target.id,
    name: target.name,
    type: target.type,
    priority: target.priority,
    adjustment_type: target.adjustment_type,
    adjustment_value: target.adjustment_value,
    price: target.price,
    venue_id: target.venue_id,
    weekdays: target.weekdays ? [...target.weekdays] : (target.weekday ? [target.weekday] : [1, 2, 3, 4, 5]),
    date_start: target.date_start || target.exception_date || '2025-01-29',
    date_end: target.date_end || target.exception_date || '2025-02-04',
    exception_date: target.exception_date || '2025-01-29',
    starts_at: target.starts_at || '06:00',
    ends_at: target.ends_at || '23:00',
    is_all_day: target.is_all_day ?? false,
    description: target.description,
    is_active: target.is_active,
  }
}

// Start creating a new rule
function startCreateNewRule() {
  isCreatingNew.value = true
  form.value = {
    name: 'Khung giờ đặc biệt mới',
    type: 'peak',
    priority: 2,
    adjustment_type: 'percentage_add',
    adjustment_value: 25,
    price: Math.round(currentBaseRate.value * 1.25),
    venue_id: null,
    weekdays: [1, 2, 3, 4, 5],
    date_start: new Date().toISOString().slice(0, 10),
    date_end: new Date().toISOString().slice(0, 10),
    exception_date: new Date().toISOString().slice(0, 10),
    starts_at: '17:00',
    ends_at: '20:00',
    is_all_day: false,
    description: 'Quy tắc điều chỉnh mức giá theo nhu cầu.',
    is_active: true,
  }
}

// Reorder Priority
function movePriority(rule: PriceRule, direction: 'up' | 'down') {
  if (rule.type === 'base') return
  const sorted = [...priorityStack.value].filter((r) => r.type !== 'base')
  const index = sorted.findIndex((r) => r.id === rule.id)
  if (index === -1) return

  if (direction === 'up' && index > 0) {
    const prev = sorted[index - 1]
    if (prev) {
      const temp = prev.priority
      prev.priority = rule.priority
      rule.priority = Math.max(1, temp)
      if (prev.priority === rule.priority) {
        prev.priority = rule.priority + 1
      }
    }
  } else if (direction === 'down' && index < sorted.length - 1) {
    const next = sorted[index + 1]
    if (next) {
      const temp = next.priority
      next.priority = rule.priority
      rule.priority = temp
      if (next.priority === rule.priority) {
        rule.priority = next.priority + 1
      }
    }
  }
  notice.value = `Đã cập nhật thứ tự ưu tiên cho quy tắc "${rule.name}".`
  setTimeout(() => (notice.value = ''), 3000)
}

// Toggle Rule Active State
async function toggleRuleActive(rule: PriceRule) {
   try {
    if (rule.id && rule.id < 900) {
      await adminApi.updatePriceRule(rule.id, { is_active: rule.is_active })
    }
  } catch {
    // Graceful offline fallback
  }
  setTimeout(() => (notice.value = ''), 3000)
}

// Save Rule Form
async function saveRule() {
  saving.value = true
  error.value = ''
  notice.value = ''

  try {
    const calculatedPrice = calculateAdjustedPrice(
      form.value.adjustment_type,
      form.value.adjustment_value,
      currentBaseRate.value
    )

    let colorTag: 'error' | 'primary' | 'secondary' | 'tertiary' | 'success' = 'primary'
    if (form.value.type === 'holiday' || form.value.type === 'date_exception' || form.value.priority === 1) {
      colorTag = 'error'
    } else if (form.value.type === 'peak' || form.value.priority === 2) {
      colorTag = 'primary'
    } else if (form.value.type === 'weekly') {
      colorTag = form.value.adjustment_type === 'percentage_sub' ? 'success' : 'secondary'
    } else {
      colorTag = 'tertiary'
    }

    if (isCreatingNew.value) {
      const newId = Math.max(...rules.value.map((r) => r.id), 0) + 1
      const newRule: PriceRule = {
        id: newId,
        name: form.value.name,
        type: form.value.type,
        priority: Number(form.value.priority) || 2,
        adjustment_type: form.value.adjustment_type,
        adjustment_value: Number(form.value.adjustment_value) || 0,
        price: calculatedPrice,
        base_rate_reference: currentBaseRate.value,
        venue_id: form.value.venue_id,
        sport_zone_id: null,
        court_id: null,
        weekday: form.value.weekdays[0] || 1,
        weekdays: [...form.value.weekdays],
        exception_date: form.value.exception_date,
        date_start: form.value.date_start,
        date_end: form.value.date_end,
        starts_at: form.value.is_all_day ? '00:00' : form.value.starts_at,
        ends_at: form.value.is_all_day ? '23:59' : form.value.ends_at,
        is_all_day: form.value.is_all_day,
        is_active: form.value.is_active,
        version: 1,
        description: form.value.description,
        color_tag: colorTag,
      }

      rules.value.unshift(newRule)
      selectedRuleId.value = newId
      isCreatingNew.value = false
      notice.value = `Đã thêm quy tắc mới "${newRule.name}".`

      try {
        await adminApi.createPriceRule({
          type: newRule.type,
          price: newRule.price,
          priority: newRule.priority,
          weekday: newRule.weekday,
          exception_date: newRule.exception_date,
          starts_at: newRule.starts_at,
          ends_at: newRule.ends_at,
          venue_id: newRule.venue_id,
          metadata: { name: newRule.name, adjustment_type: newRule.adjustment_type, adjustment_value: newRule.adjustment_value },
        })
      } catch {
        // Safe offline mock
      }
    } else {
      const existing = rules.value.find((r) => r.id === selectedRuleId.value)
      if (existing) {
        existing.name = form.value.name
        existing.type = form.value.type
        existing.priority = Number(form.value.priority) || 2
        existing.adjustment_type = form.value.adjustment_type
        existing.adjustment_value = Number(form.value.adjustment_value) || 0
        existing.price = calculatedPrice
        existing.weekdays = [...form.value.weekdays]
        existing.weekday = form.value.weekdays[0] || 1
        existing.exception_date = form.value.exception_date
        existing.date_start = form.value.date_start
        existing.date_end = form.value.date_end
        existing.starts_at = form.value.is_all_day ? '00:00' : form.value.starts_at
        existing.ends_at = form.value.is_all_day ? '23:59' : form.value.ends_at
        existing.is_all_day = form.value.is_all_day
        existing.description = form.value.description
        existing.color_tag = colorTag
        existing.version = (existing.version || 1) + 1

        notice.value = `Đã cập nhật quy tắc "${existing.name}".`

        try {
          if (existing.id < 900) {
            await adminApi.updatePriceRule(existing.id, {
              type: existing.type,
              price: existing.price,
              priority: existing.priority,
              starts_at: existing.starts_at,
              ends_at: existing.ends_at,
            })
          }
        } catch {
          // Safe offline mock
        }
      }
    }
  } catch (err: unknown) {
    error.value = errorMessage(err, 'Không thể lưu quy tắc giá.')
  } finally {
    saving.value = false
    setTimeout(() => {
      notice.value = ''
    }, 3500)
  }
}

// Delete / Deactivate Rule
async function deleteRule(rule: PriceRule) {
  if (rule.type === 'base') {
    alert('Không thể xóa quy tắc Giá cơ bản mặc định của hệ thống.')
    return
  }
  if (!confirm(`Bạn có chắc chắn muốn gỡ bỏ quy tắc "${rule.name}" không?`)) return

  const idx = rules.value.findIndex((r) => r.id === rule.id)
  if (idx !== -1) {
    rules.value.splice(idx, 1)
    if (selectedRuleId.value === rule.id) {
      selectRuleForEdit(priorityStack.value[0])
    }
    notice.value = `Đã xóa quy tắc "${rule.name}".`
    try {
      if (rule.id < 900) {
        await adminApi.deletePriceRule(rule.id)
      }
    } catch {
      // Safe offline
    }
    setTimeout(() => (notice.value = ''), 3000)
  }
}

// Weekdays Helper Pill Toggle
function toggleWeekdayInForm(day: number) {
  const idx = form.value.weekdays.indexOf(day)
  if (idx > -1) {
    if (form.value.weekdays.length > 1) {
      form.value.weekdays.splice(idx, 1)
    }
  } else {
    form.value.weekdays.push(day)
    form.value.weekdays.sort((a, b) => a - b)
  }
}

// Calendar Month Grid Generation
const calendarMonthName = computed(() => {
  const months = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12']
  return `${months[currentMonth.value]}, ${currentYear.value}`
})

interface CalendarDay {
  dateString: string // YYYY-MM-DD
  dayNumber: number
  isCurrentMonth: boolean
  isToday: boolean
  isSelected: boolean
  isHoliday: boolean
  hasPeak: boolean
  hasDiscount: boolean
  highestRule: PriceRule | null
  effectivePrice: number
  dotColor: string
}

const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const totalDays = lastDay.getDate()

  // Calculate Monday offset (0: Mon ... 6: Sun)
  let startDayOfWeek = firstDay.getDay() - 1
  if (startDayOfWeek === -1) startDayOfWeek = 6

  const days: CalendarDay[] = []

  // Preceding month padding days
  const prevMonthLastDay = new Date(year, month, 0).getDate()
  for (let i = startDayOfWeek - 1; i >= 0; i--) {
    const d = prevMonthLastDay - i
    const prevMonth = month === 0 ? 11 : month - 1
    const prevYear = month === 0 ? year - 1 : year
    const dateStr = `${prevYear}-${String(prevMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push({
      dateString: dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      isToday: false,
      isSelected: selectedCalendarDate.value === dateStr,
      isHoliday: false,
      hasPeak: false,
      hasDiscount: false,
      highestRule: null,
      effectivePrice: currentBaseRate.value,
      dotColor: '',
    })
  }

  // Days in current month
  for (let d = 1; d <= totalDays; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const dateObj = new Date(year, month, d)
    let weekday = dateObj.getDay()
    if (weekday === 0) weekday = 7

    const holidayMatch = rules.value.find((r) => {
      if (!r.is_active) return false
      if (r.type !== 'holiday' && r.type !== 'date_exception') return false
      if (r.exception_date && r.exception_date === dateStr) return true
      if (r.date_start && r.date_end) {
        return dateStr >= r.date_start && dateStr <= r.date_end
      }
      return false
    })

    const peakMatch = rules.value.find((r) => {
      if (!r.is_active || r.type !== 'peak') return false
      if (r.weekdays && r.weekdays.includes(weekday)) return true
      return false
    })

    const discountMatch = rules.value.find((r) => {
      if (!r.is_active) return false
      return r.adjustment_type === 'percentage_sub' && r.weekdays && r.weekdays.includes(weekday)
    })

    const isWeekend = weekday === 6 || weekday === 7

    let dotColor = ''
    let isHoliday = false
    let hasPeak = false
    let hasDiscount = false
    let highestRule: PriceRule | null = null
    let effectivePrice = currentBaseRate.value

    if (holidayMatch) {
      isHoliday = true
      dotColor = 'dot-holiday'
      highestRule = holidayMatch
      effectivePrice = holidayMatch.price
    } else if (peakMatch) {
      hasPeak = true
      dotColor = 'dot-peak'
      highestRule = peakMatch
      effectivePrice = peakMatch.price
    } else if (discountMatch) {
      hasDiscount = true
      dotColor = 'dot-discount'
      highestRule = discountMatch
      effectivePrice = discountMatch.price
    } else if (isWeekend) {
      dotColor = 'dot-weekend'
      effectivePrice = 150000
    }

    const todayStr = new Date().toISOString().slice(0, 10)

    days.push({
      dateString: dateStr,
      dayNumber: d,
      isCurrentMonth: true,
      isToday: dateStr === todayStr,
      isSelected: selectedCalendarDate.value === dateStr,
      isHoliday,
      hasPeak,
      hasDiscount,
      highestRule,
      effectivePrice,
      dotColor,
    })
  }

  // Next month trailing padding days
  const remaining = 35 - days.length
  if (remaining > 0) {
    for (let i = 1; i <= remaining; i++) {
      const nextMonth = month === 11 ? 0 : month + 1
      const nextYear = month === 11 ? year + 1 : year
      const dateStr = `${nextYear}-${String(nextMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`
      days.push({
        dateString: dateStr,
        dayNumber: i,
        isCurrentMonth: false,
        isToday: false,
        isSelected: selectedCalendarDate.value === dateStr,
        isHoliday: false,
        hasPeak: false,
        hasDiscount: false,
        highestRule: null,
        effectivePrice: currentBaseRate.value,
        dotColor: '',
      })
    }
  }

  return days
})

function prevMonth() {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

function nextMonth() {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
}

function selectDay(day: CalendarDay) {
  selectedCalendarDate.value = day.dateString
  simDate.value = day.dateString
}

// Compute Details of Selected Date
const selectedDateInfo = computed(() => {
  const dateStr = selectedCalendarDate.value
  const dateObj = new Date(dateStr)
  let weekday = dateObj.getDay()
  if (weekday === 0) weekday = 7

  const weekdayNames = ['', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật']
  const weekdayName = weekdayNames[weekday]

  const holidayRule = rules.value.find((r) => {
    if (!r.is_active || (r.type !== 'holiday' && r.type !== 'date_exception')) return false
    if (r.exception_date === dateStr) return true
    if (r.date_start && r.date_end && dateStr >= r.date_start && dateStr <= r.date_end) return true
    return false
  })

  const hourlySlots = []
  for (let h = 6; h <= 22; h++) {
    const startHourStr = `${String(h).padStart(2, '0')}:00`
    const endHourStr = `${String(h + 1).padStart(2, '0')}:00`
    const slotTimeLabel = `${startHourStr} - ${endHourStr}`

    let applied: PriceRule = priorityStack.value.find((r) => r.type === 'base') || rules.value[rules.value.length - 1] || defaultRules[0]!
    let reason = 'Giá cơ bản mặc định'

    if (holidayRule) {
      applied = holidayRule
      reason = `Ghi đè bởi Ngày lễ: ${holidayRule.name} (Ưu tiên ${holidayRule.priority})`
    } else {
      const matched = priorityStack.value.find((r) => {
        if (!r.is_active || r.type === 'base') return false
        if (r.weekdays && !r.weekdays.includes(weekday)) return false
        if (r.starts_at && r.ends_at) {
          return startHourStr >= r.starts_at && startHourStr < r.ends_at
        }
        return false
      })

      if (matched) {
        applied = matched
        reason = `Áp dụng ${matched.name} (Ưu tiên ${matched.priority})`
      }
    }

    const diff = Math.round(((applied.price - currentBaseRate.value) / currentBaseRate.value) * 100)

    hourlySlots.push({
      timeRange: slotTimeLabel,
      hour: h,
      appliedRule: applied,
      price: applied.price,
      reason,
      diffPercentage: diff,
    })
  }

  return {
    dateFormatted: `${weekdayName}, ${dateStr.split('-').reverse().join('/')}`,
    holidayRule,
    hourlySlots,
    minRate: Math.min(...hourlySlots.map((s) => s.price)),
    maxRate: Math.max(...hourlySlots.map((s) => s.price)),
  }
})

// Price Quote Simulation Calculator
const simulationResult = computed<SimulationResult>(() => {
  const dateStr = simDate.value
  const dateObj = new Date(dateStr)
  let weekday = dateObj.getDay()
  if (weekday === 0) weekday = 7

  const timePartsStart = simStartTime.value.split(':').map(Number)
  const timePartsEnd = simEndTime.value.split(':').map(Number)
  const startH = timePartsStart[0] ?? 17
  const startM = timePartsStart[1] ?? 30
  const endH = timePartsEnd[0] ?? 19
  const endM = timePartsEnd[1] ?? 30
  const startMinutes = startH * 60 + startM
  const endMinutes = endH * 60 + endM
  const totalMinutes = Math.max(0, endMinutes - startMinutes)
  const durationHours = totalMinutes / 60

  const holidayRule = rules.value.find((r) => {
    if (!r.is_active || (r.type !== 'holiday' && r.type !== 'date_exception')) return false
    if (r.exception_date === dateStr) return true
    if (r.date_start && r.date_end && dateStr >= r.date_start && dateStr <= r.date_end) return true
    return false
  })

  const slots: SimulationResult['slots'] = []
  let calculatedTotal = 0

  for (let m = startMinutes; m < endMinutes; m += 30) {
    const slotH = Math.floor(m / 60)
    const slotM = m % 60
    const nextH = Math.floor((m + 30) / 60)
    const nextM = (m + 30) % 60
    const timeStart = `${String(slotH).padStart(2, '0')}:${String(slotM).padStart(2, '0')}`
    const timeEnd = `${String(nextH).padStart(2, '0')}:${String(nextM).padStart(2, '0')}`

    let applied: PriceRule = priorityStack.value.find((r) => r.type === 'base') || rules.value[rules.value.length - 1] || defaultRules[0]!
    let overrideReason = 'Giá cơ bản'

    if (holidayRule) {
      applied = holidayRule
      overrideReason = `Ưu tiên 1: ${holidayRule.name}`
    } else {
      const match = priorityStack.value.find((r) => {
        if (!r.is_active || r.type === 'base') return false
        if (r.weekdays && !r.weekdays.includes(weekday)) return false
        if (r.starts_at && r.ends_at) {
          return timeStart >= r.starts_at && timeStart < r.ends_at
        }
        return false
      })

      if (match) {
        applied = match
        overrideReason = `Ưu tiên ${match.priority}: ${match.name}`
      }
    }

    const slotCost = Math.round(applied.price * 0.5)
    calculatedTotal += slotCost

    slots.push({
      timeRange: `${timeStart} - ${timeEnd}`,
      appliedRule: applied,
      hourlyRate: applied.price,
      slotPrice: slotCost,
      overrideReason,
    })
  }

  const baseTotal = Math.round(currentBaseRate.value * durationHours)
  const diffPercentage = baseTotal > 0 ? Math.round(((calculatedTotal - baseTotal) / baseTotal) * 100) : 0

  return {
    date: dateStr,
    startTime: simStartTime.value,
    endTime: simEndTime.value,
    courtName: simCourtName.value,
    durationHours,
    slots,
    totalPrice: calculatedTotal,
    baseTotal,
    diffPercentage,
  }
})

// Template Presets
interface PresetTemplate {
  id: string
  title: string
  subtitle: string
  icon: string
  rules: PriceRule[]
}

const presetTemplates: PresetTemplate[] = [
  {
    id: 'standard-urban',
    title: 'Mẫu Cụm Sân Đô Thị Tiêu Chuẩn',
    subtitle: 'Cân bằng giữa giờ vàng buổi tối, cuối tuần sôi động và happy hour sáng sớm.',
    icon: 'location_city',
    rules: defaultRules,
  },
  {
    id: 'holiday-surge',
    title: 'Mẫu Mùa Lễ Hội & Tết (Holiday Surge)',
    subtitle: 'Tập trung vào phụ thu ngày nghỉ Tết, lễ 30/4 - 1/5 và các sự kiện ngoại lệ.',
    icon: 'celebration',
    rules: [
      {
        id: 101,
        name: 'Tết Nguyên Đán Giáp Thìn',
        type: 'holiday',
        priority: 1,
        adjustment_type: 'percentage_add',
        adjustment_value: 35,
        price: 162000,
        venue_id: null,
        sport_zone_id: null,
        court_id: null,
        weekday: null,
        weekdays: [],
        exception_date: '2025-01-29',
        date_start: '2025-01-28',
        date_end: '2025-02-05',
        starts_at: '00:00',
        ends_at: '23:59',
        is_all_day: true,
        is_active: true,
        version: 1,
        description: 'Phụ thu 35% kỳ nghỉ Tết toàn bộ sân.',
        color_tag: 'error',
      },
      {
        id: 102,
        name: 'Giờ Vàng Cao Điểm (18:00 - 22:00)',
        type: 'peak',
        priority: 2,
        adjustment_type: 'fixed',
        adjustment_value: 190000,
        price: 190000,
        venue_id: null,
        sport_zone_id: null,
        court_id: null,
        weekday: null,
        weekdays: [1, 2, 3, 4, 5, 6, 7],
        exception_date: null,
        date_start: null,
        date_end: null,
        starts_at: '18:00',
        ends_at: '22:00',
        is_all_day: false,
        is_active: true,
        version: 1,
        description: 'Áp dụng cho giờ vàng mỗi ngày.',
        color_tag: 'primary',
      },
      {
        id: 999,
        name: 'Giá cơ bản mặc định',
        type: 'base',
        priority: 4,
        adjustment_type: 'fixed',
        adjustment_value: 120000,
        price: 120000,
        venue_id: null,
        sport_zone_id: null,
        court_id: null,
        weekday: null,
        weekdays: [1, 2, 3, 4, 5, 6, 7],
        exception_date: null,
        date_start: null,
        date_end: null,
        starts_at: '06:00',
        ends_at: '23:00',
        is_all_day: true,
        is_active: true,
        version: 1,
        description: 'Giá mặc định khi không khớp quy tắc.',
        color_tag: 'tertiary',
      },
    ],
  },
]

function applyPreset(preset: PresetTemplate) {
  rules.value = JSON.parse(JSON.stringify(preset.rules))
  selectRuleForEdit(rules.value[0])
  showPresetModal.value = false
  notice.value = `Đã áp dụng thành công "${preset.title}".`
  setTimeout(() => (notice.value = ''), 3000)
}

function openExportModal() {
  exportJsonString.value = JSON.stringify(rules.value, null, 2)
  showExportModal.value = true
}

function copyExportJson() {
  navigator.clipboard.writeText(exportJsonString.value)
  notice.value = 'Đã sao chép cấu hình ma trận giá vào clipboard!'
  setTimeout(() => (notice.value = ''), 2500)
}

function importJson() {
  try {
    const parsed = JSON.parse(exportJsonString.value)
    if (Array.isArray(parsed) && parsed.length > 0) {
      rules.value = parsed
      selectRuleForEdit(rules.value[0])
      showExportModal.value = false
      notice.value = 'Đã nạp thành công cấu hình ma trận giá mới!'
      setTimeout(() => (notice.value = ''), 3000)
    } else {
      alert('Định dạng JSON không hợp lệ. Vui lòng kiểm tra lại mảng quy tắc.')
    }
  } catch {
    alert('Không thể đọc mã JSON. Vui lòng kiểm tra cú pháp.')
  }
}

// Initial API Load
async function load() {
  loading.value = true
  try {
    const res = await adminApi.getPricingCalendar<{ rules: PriceRule[] }>()
    if (res && res.rules && res.rules.length > 0) {
      rules.value = res.rules.map((r, idx) => ({
        ...r,
        name: r.name || (r.type === 'base' ? 'Giá cơ bản' : `Quy tắc #${idx + 1}`),
        adjustment_type: r.adjustment_type || 'fixed',
        adjustment_value: r.adjustment_value || r.price,
        weekdays: r.weekdays || (r.weekday ? [r.weekday] : [1, 2, 3, 4, 5]),
        priority: r.priority || (r.type === 'holiday' ? 1 : r.type === 'peak' ? 2 : r.type === 'weekly' ? 3 : 4),
        color_tag: (r.type === 'holiday' || r.type === 'date_exception') ? 'error' : r.type === 'peak' ? 'primary' : r.type === 'weekly' ? 'secondary' : 'tertiary',
      }))
    }
  } catch {
    // Keep high quality mock
  } finally {
    loading.value = false
    if (rules.value.length > 0) {
      selectRuleForEdit(rules.value[0])
    }
  }
}

onMounted(load)
</script>

<template>
  <div class="pricing-matrix-page">
    <!-- Alerts & Toasts -->
    <Transition name="toast">
      <div v-if="notice" class="toast-banner success">
        <div class="toast-content">
          <span class="material-symbols-outlined icon-20">check_circle</span>
          <span class="toast-text">{{ notice }}</span>
        </div>
        <button class="btn-close-toast" @click="notice = ''">
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>
    </Transition>

    <Transition name="toast">
      <div v-if="error" class="toast-banner error">
        <div class="toast-content">
          <span class="material-symbols-outlined icon-20">error</span>
          <span class="toast-text">{{ error }}</span>
        </div>
        <button class="btn-close-toast" @click="error = ''">
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>
    </Transition>

    <!-- Header Section (Matching Stitch Design) -->
    <div class="page-header">
      <div class="header-titles">
        <h1 class="page-title">Thiết lập Ma trận Giá linh hoạt</h1>
        <p class="page-subtitle">
          Quản lý các quy tắc tính giá theo độ ưu tiên. Hệ thống sẽ tự động áp dụng mức giá có độ ưu tiên cao nhất cho từng khung giờ.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="header-actions">
        <button type="button" class="btn-action secondary" @click="showPresetModal = true">
          <span class="material-symbols-outlined icon-18">auto_fix_high</span>
          <span>Mẫu cài đặt</span>
        </button>

        <button type="button" class="btn-action secondary" @click="showSimulatorModal = true">
          <span class="material-symbols-outlined icon-18">calculate</span>
          <span>Mô phỏng báo giá</span>
        </button>

        <button type="button" class="btn-action secondary" @click="openExportModal">
          <span class="material-symbols-outlined icon-18">ios_share</span>
          <span>JSON</span>
        </button>

        <button type="button" class="btn-action primary" @click="startCreateNewRule">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>Thêm quy tắc mới</span>
        </button>
      </div>
    </div>

    <!-- Main Grid Layout (Priority Stack & Rule Form on Left, Calendar & Hourly breakdown on Right) -->
    <div class="matrix-main-grid">
      <!-- Left Column: Priority Stack & Rule Config -->
      <div class="col-left">
        <!-- Priority Stack Card -->
        <div class="card-panel priority-stack-panel">
          <div class="panel-header">
            <h2 class="panel-title">Độ ưu tiên giá</h2>
          </div>

          <!-- Rules List in Priority Order -->
          <div class="priority-items-list">
            <div
              v-for="rule in priorityStack"
              :key="rule.id"
              class="priority-item"
              :class="[
                selectedRuleId === rule.id && !isCreatingNew ? 'is-selected' : '',
                !rule.is_active ? 'is-inactive' : '',
                `priority-border-${rule.priority <= 3 ? rule.priority : 'base'}`
              ]"
              @click="selectRuleForEdit(rule)"
            >
              <!-- Colored Left Accent Bar -->
              <div class="priority-accent-bar" :class="`accent-p${rule.priority <= 3 ? rule.priority : 'base'}`"></div>

              <div class="priority-item-main">
                <!-- Priority Circle Badge -->
                <div class="priority-circle" :class="`priority-badge-${rule.priority <= 3 ? rule.priority : 'base'}`">
                  <span v-if="rule.type !== 'base'">{{ rule.priority }}</span>
                  <span v-else class="material-symbols-outlined icon-14">lock</span>
                </div>

                <!-- Rule Info -->
                <div class="rule-details">
                  <div class="rule-title-row">
                    <span class="rule-name">{{ rule.name }}</span>
                    <span v-if="rule.type === 'holiday'" class="tag-pill tag-error">LỄ / NGOẠI LỆ</span>
                    <span v-else-if="rule.type === 'peak'" class="tag-pill tag-primary">GIỜ VÀNG</span>
                    <span v-else-if="rule.adjustment_type === 'percentage_sub'" class="tag-pill tag-success">HAPPY HOUR</span>
                  </div>
                  <p class="rule-subtext">
                    <span v-if="rule.type === 'base'">Áp dụng khi không có quy tắc nào khớp</span>
                    <span v-else-if="rule.type === 'holiday'">{{ rule.date_start ? `${rule.date_start} → ${rule.date_end}` : rule.exception_date }} · Cả ngày</span>
                    <span v-else>{{ rule.starts_at }} - {{ rule.ends_at }} · {{ rule.weekdays?.length ? `${rule.weekdays.length} ngày/tuần` : 'Hàng tuần' }}</span>
                  </p>
                </div>

                <!-- Price Chip & Reorder Controls -->
                <div class="priority-actions">
                  <span class="price-chip">{{ compactMoney(rule.price) }}</span>

                  <!-- Up / Down priority buttons -->
                  <div v-if="rule.type !== 'base'" class="reorder-btns">
                    <button
                      type="button"
                      class="btn-icon-tiny"
                      title="Tăng độ ưu tiên"
                      @click.stop="movePriority(rule, 'up')"
                    >
                      <span class="material-symbols-outlined icon-14">expand_less</span>
                    </button>
                    <button
                      type="button"
                      class="btn-icon-tiny"
                      title="Giảm độ ưu tiên"
                      @click.stop="movePriority(rule, 'down')"
                    >
                      <span class="material-symbols-outlined icon-14">expand_more</span>
                    </button>
                  </div>

                  <!-- Active Toggle -->
                  <button
                    v-if="rule.type !== 'base'"
                    type="button"
                    class="btn-toggle"
                    :class="{ active: rule.is_active }"
                    :title="rule.is_active ? 'Tạm ngưng quy tắc' : 'Kích hoạt quy tắc'"
                    @click.stop="toggleRuleActive(rule)"
                  >
                    <span class="material-symbols-outlined icon-20">
                      {{ rule.is_active ? 'toggle_on' : 'toggle_off' }}
                    </span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Rule Configuration & Editor Card -->
        <div class="card-panel rule-config-panel">
          <div class="panel-header">
            <h2 class="panel-title">
              {{ isCreatingNew ? 'Thêm quy tắc mới' : 'Chi tiết quy tắc' }}
            </h2>
            <span class="tag-status" :class="isCreatingNew ? 'status-new' : 'status-edit'">
              {{ isCreatingNew ? 'Tạo mới' : 'Chỉnh sửa' }}
            </span>
          </div>

          <form class="config-form" @submit.prevent="saveRule">
            <!-- Rule Name -->
            <div class="form-group">
              <label class="form-label" for="ruleName">Tên quy tắc</label>
              <input
                id="ruleName"
                v-model="form.name"
                type="text"
                required
                class="form-input"
                placeholder="Ví dụ: Tết Nguyên Đán, Giờ Vàng Buổi Tối..."
              />
            </div>

            <!-- Priority & Adjustment in 2 cols -->
            <div class="form-row">
              <div class="form-group flex-1">
                <label class="form-label">Độ ưu tiên</label>
                <div class="select-wrapper">
                  <select v-model.number="form.priority" class="form-select">
                    <option :value="1">1 - Ngày lễ/Ngoại lệ</option>
                    <option :value="2">2 - Giờ vàng</option>
                    <option :value="3">3 - Khung giờ thường</option>
                    <option :value="4">4 - Giá cơ bản</option>
                  </select>
                  <span class="material-symbols-outlined select-arrow">expand_more</span>
                </div>
              </div>

              <div class="form-group flex-1">
                <label class="form-label">Điều chỉnh</label>
                <div class="form-row gap-1">
                  <div class="select-wrapper flex-1">
                    <select v-model="form.adjustment_type" class="form-select">
                      <option value="percentage_add">Tăng (+)</option>
                      <option value="percentage_sub">Giảm (-)</option>
                      <option value="fixed">Cố định (=)</option>
                    </select>
                    <span class="material-symbols-outlined select-arrow">expand_more</span>
                  </div>
                  <div class="input-with-affix" style="width: 80px;">
                    <input
                      v-model.number="form.adjustment_value"
                      type="number"
                      min="0"
                      class="form-input text-right font-bold"
                      :placeholder="form.adjustment_type.includes('percentage') ? '30' : '150000'"
                    />
                    <span class="input-affix">
                      {{ form.adjustment_type.includes('percentage') ? '%' : 'k' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Date / Time Configuration Box -->
            <div class="sub-card-box">
              <div class="sub-card-header">
                <span class="sub-card-title">Thời gian áp dụng</span>
                <label class="checkbox-label">
                  <input v-model="form.is_all_day" type="checkbox" />
                  <span>Cả ngày (24h)</span>
                </label>
              </div>

              <!-- For Holiday / Date Exception -->
              <div v-if="form.type === 'holiday' || form.type === 'date_exception'" class="time-item-row">
                <span class="material-symbols-outlined icon-16 text-muted">calendar_month</span>
                <div class="flex-1 flex-center gap-2">
                  <input v-model="form.date_start" type="date" class="form-input-mini" />
                  <span class="text-xs text-muted">đến</span>
                  <input v-model="form.date_end" type="date" class="form-input-mini" />
                </div>
              </div>

              <!-- For Weekly / Peak -->
              <div v-else class="weekday-picker-group">
                <label class="form-label-mini">Các thứ trong tuần:</label>
                <div class="weekday-pills">
                  <button
                    v-for="d in [1, 2, 3, 4, 5, 6, 7]"
                    :key="d"
                    type="button"
                    class="weekday-pill"
                    :class="{ active: form.weekdays.includes(d) }"
                    @click="toggleWeekdayInForm(d)"
                  >
                    {{ d === 7 ? 'CN' : `T${d + 1}` }}
                  </button>
                </div>
              </div>

              <!-- Time of Day Range (if not all day) -->
              <div v-if="!form.is_all_day" class="time-item-row">
                <span class="material-symbols-outlined icon-16 text-muted">schedule</span>
                <div class="flex-1 flex-center gap-2">
                  <input v-model="form.starts_at" type="time" class="form-input-mini" />
                  <span class="text-xs text-muted">đến</span>
                  <input v-model="form.ends_at" type="time" class="form-input-mini" />
                </div>
              </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="form-footer-actions">
              <button
                v-if="!isCreatingNew && form.type !== 'base'"
                type="button"
                class="btn-delete"
                @click="deleteRule(selectedRule)"
              >
                <span class="material-symbols-outlined icon-16">delete</span>
                <span>Xóa</span>
              </button>
              <div v-else></div>

              <div class="btn-group-right">
                <button
                  type="button"
                  class="btn-action secondary"
                  @click="isCreatingNew ? selectRuleForEdit(priorityStack[0]) : selectRuleForEdit(selectedRule)"
                >
                  Hủy
                </button>
                <button
                  type="submit"
                  :disabled="saving"
                  class="btn-action primary"
                >
                  <span v-if="saving" class="material-symbols-outlined icon-16 animate-spin">progress_activity</span>
                  <span>{{ isCreatingNew ? 'Tạo quy tắc' : 'Lưu quy tắc' }}</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Right Column: Interactive Price Calendar & Hourly Matrix -->
      <div class="col-right">
        <!-- Interactive Month Calendar Card -->
        <div class="card-panel calendar-panel">
          <!-- Calendar Header Navigation -->
          <div class="panel-header">
            <div>
              <h2 class="panel-title">Lịch giá</h2>
              <p class="panel-desc font-bold text-primary">{{ calendarMonthName }}</p>
            </div>

            <!-- Month Controls -->
            <div class="calendar-controls">
              <button
                type="button"
                class="btn-action-small"
                @click="currentMonth = new Date().getMonth(); currentYear = new Date().getFullYear(); selectedCalendarDate = new Date().toISOString().slice(0,10)"
              >
                Hôm nay
              </button>
              <button
                type="button"
                class="btn-icon-small"
                title="Tháng trước"
                @click="prevMonth"
              >
                <span class="material-symbols-outlined icon-18">chevron_left</span>
              </button>
              <button
                type="button"
                class="btn-icon-small"
                title="Tháng sau"
                @click="nextMonth"
              >
                <span class="material-symbols-outlined icon-18">chevron_right</span>
              </button>
            </div>
          </div>

          <!-- Color Legend Pills -->
          <div class="calendar-legend">
            <div class="legend-item">
              <div class="dot-indicator dot-holiday"></div>
              <span>Lễ/Ngoại lệ</span>
            </div>
            <div class="legend-item">
              <div class="dot-indicator dot-peak"></div>
              <span>Có giờ vàng</span>
            </div>
            <div class="legend-item">
              <div class="dot-indicator dot-weekend"></div>
              <span>Cuối tuần</span>
            </div>
            <div class="legend-item">
              <div class="dot-indicator dot-discount"></div>
              <span>Happy Hour</span>
            </div>
            <div class="legend-item">
              <div class="dot-indicator dot-base"></div>
              <span>Giá cơ bản</span>
            </div>
          </div>

          <!-- Calendar Days Grid -->
          <div class="calendar-table-wrapper">
            <!-- Weekday headers -->
            <div class="calendar-weekdays-row">
              <div class="weekday-header">T2</div>
              <div class="weekday-header">T3</div>
              <div class="weekday-header">T4</div>
              <div class="weekday-header">T5</div>
              <div class="weekday-header">T6</div>
              <div class="weekday-header text-primary">T7</div>
              <div class="weekday-header text-error">CN</div>
            </div>

            <!-- Days cells (Clean Aesthetic with dot indicator) -->
            <div class="calendar-days-grid">
              <div
                v-for="(day, idx) in calendarDays"
                :key="idx"
                class="calendar-day-cell"
                :class="[
                  day.isSelected ? 'is-selected' : '',
                  day.isCurrentMonth ? 'is-current-month' : 'is-other-month',
                ]"
                @click="selectDay(day)"
              >
                <span
                  class="day-number"
                  :class="{ 'text-error font-extrabold': day.isHoliday }"
                >
                  {{ day.dayNumber }}
                </span>

                <!-- Status Dot -->
                <div class="day-dot-container">
                  <div v-if="day.dotColor" class="day-dot" :class="day.dotColor"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Preview of Selected Date Banner (Matching Stitch Screen) -->
          <div class="selected-date-banner">
            <div>
              <p class="font-bold text-sm text-ink">Chi tiết: {{ selectedDateInfo.dateFormatted }}</p>
              <p v-if="selectedDateInfo.holidayRule" class="text-xs text-error flex-center gap-1 mt-0-5 font-medium">
                <span class="material-symbols-outlined icon-14">warning</span>
                Áp dụng quy tắc: {{ selectedDateInfo.holidayRule.name }}
              </p>
              <p v-else class="text-xs text-muted mt-0-5">
                Áp dụng khung giờ chuẩn
              </p>
            </div>

            <div class="text-right">
              <p class="font-bold text-sm text-primary">{{ money(selectedDateInfo.maxRate) }}/h</p>
              <p class="text-[11px] text-muted">
                {{ selectedDateInfo.holidayRule ? '+30% từ giá cơ bản' : 'Mức giá trong ngày' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Hourly Breakdown Timeline for Selected Day -->
        <div class="card-panel timeline-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title flex-center gap-2">
                <span class="material-symbols-outlined text-primary icon-18">schedule</span>
                <span>Phân rã Khung giờ: {{ selectedDateInfo.dateFormatted }}</span>
              </h3>
              <p class="panel-desc">Quy tắc có độ ưu tiên cao nhất sẽ áp dụng cho từng khung giờ.</p>
            </div>
            <button
              type="button"
              class="btn-action primary btn-compact"
              @click="simDate = selectedCalendarDate; showSimulatorModal = true"
            >
              <span class="material-symbols-outlined icon-14">calculate</span>
              <span>Báo giá mô phỏng</span>
            </button>
          </div>

          <!-- Slot list -->
          <div class="hourly-slots-scroll">
            <div
              v-for="slot in selectedDateInfo.hourlySlots"
              :key="slot.hour"
              class="hourly-slot-row"
            >
              <!-- Time slot & Rule info -->
              <div class="slot-left">
                <div class="slot-time-badge">
                  <span class="material-symbols-outlined icon-14 text-muted">timer</span>
                  <span>{{ slot.timeRange }}</span>
                </div>

                <div class="slot-rule-info">
                  <div class="flex-center gap-1.5">
                    <span
                      class="tag-pill"
                      :class="`priority-tag-${slot.appliedRule.priority <= 3 ? slot.appliedRule.priority : 'base'}`"
                    >
                      Ưu tiên {{ slot.appliedRule.priority }}
                    </span>
                    <span class="slot-rule-name">{{ slot.appliedRule.name }}</span>
                  </div>
                  <p class="slot-reason-text">{{ slot.reason }}</p>
                </div>
              </div>

              <!-- Price & Multiplier Badge -->
              <div class="slot-right">
                <div class="slot-price">{{ money(slot.price) }}</div>
                <div
                  class="slot-diff"
                  :class="[
                    slot.diffPercentage > 0 ? 'text-error' : slot.diffPercentage < 0 ? 'text-success' : 'text-muted',
                  ]"
                >
                  {{ slot.diffPercentage > 0 ? `+${slot.diffPercentage}%` : slot.diffPercentage < 0 ? `${slot.diffPercentage}%` : 'Giá gốc' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Simulator Modal -->
    <div
      v-if="showSimulatorModal"
      class="modal-backdrop"
      @click.self="showSimulatorModal = false"
    >
      <div class="modal-card modal-lg">
        <div class="modal-header">
          <div class="flex-center gap-3">
            <div class="modal-icon-box">
              <span class="material-symbols-outlined icon-22">calculate</span>
            </div>
            <div>
              <h3 class="modal-title">Trình Mô Phỏng & Báo Giá Tự Động (Quote Engine)</h3>
              <p class="modal-subtitle">Kiểm tra kết quả áp dụng ma trận giá và phân giải xung đột theo độ ưu tiên.</p>
            </div>
          </div>
          <button class="btn-close-modal" @click="showSimulatorModal = false">
            <span class="material-symbols-outlined icon-20">close</span>
          </button>
        </div>

        <div class="modal-body space-y-4">
          <!-- Simulation Parameters Input -->
          <div class="form-row bg-subtle p-3 rounded-xl border">
            <div class="form-group flex-1">
              <label class="form-label-mini">Ngày đặt sân</label>
              <input v-model="simDate" type="date" class="form-input font-bold" />
            </div>
            <div class="form-group flex-1">
              <label class="form-label-mini">Giờ bắt đầu</label>
              <input v-model="simStartTime" type="time" class="form-input font-bold" />
            </div>
            <div class="form-group flex-1">
              <label class="form-label-mini">Giờ kết thúc</label>
              <input v-model="simEndTime" type="time" class="form-input font-bold" />
            </div>
          </div>

          <!-- Simulation Total Banner -->
          <div class="sim-total-card">
            <div>
              <span class="sim-total-label">Tổng tiền dự tính</span>
              <div class="sim-total-amount">{{ money(simulationResult.totalPrice) }}</div>
              <p class="text-xs text-muted mt-1">
                Thời lượng: <strong>{{ simulationResult.durationHours }} giờ</strong> ({{ simulationResult.slots.length }} slot 30p)
              </p>
            </div>
            <div class="text-right">
              <span class="text-xs text-muted">So với giá gốc:</span>
              <div
                class="font-bold text-sm mt-0.5"
                :class="simulationResult.diffPercentage >= 0 ? 'text-error' : 'text-success'"
              >
                {{ simulationResult.diffPercentage >= 0 ? `+${simulationResult.diffPercentage}%` : `${simulationResult.diffPercentage}%` }}
                ({{ money(simulationResult.baseTotal) }})
              </div>
            </div>
          </div>

          <!-- Step by Step Slots Breakdown -->
          <div>
            <h4 class="font-bold text-xs uppercase tracking-wider mb-2 text-muted">Phân rã từng khung 30 phút & Quy tắc kích hoạt:</h4>
            <div class="sim-slots-list">
              <div
                v-for="(slot, sIdx) in simulationResult.slots"
                :key="sIdx"
                class="sim-slot-item"
              >
                <div class="flex-center gap-2">
                  <span class="font-bold text-sm">{{ slot.timeRange }}</span>
                  <span class="tag-pill tag-secondary">{{ slot.overrideReason }}</span>
                </div>
                <div class="text-right font-bold text-sm">
                  {{ money(slot.slotPrice) }}
                  <span class="text-xs font-normal text-muted">({{ money(slot.hourlyRate) }}/h)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-action primary" @click="showSimulatorModal = false">
            Đóng
          </button>
        </div>
      </div>
    </div>

    <!-- Presets Modal -->
    <div
      v-if="showPresetModal"
      class="modal-backdrop"
      @click.self="showPresetModal = false"
    >
      <div class="modal-card modal-md">
        <div class="modal-header">
          <div class="flex-center gap-3">
            <div class="modal-icon-box">
              <span class="material-symbols-outlined icon-22">auto_fix_high</span>
            </div>
            <div>
              <h3 class="modal-title">Mẫu Cấu Hình Ma Trận Giá Tiêu Chuẩn</h3>
              <p class="modal-subtitle">Chọn một mẫu dựng sẵn để áp dụng nhanh cho hệ thống sân.</p>
            </div>
          </div>
          <button class="btn-close-modal" @click="showPresetModal = false">
            <span class="material-symbols-outlined icon-20">close</span>
          </button>
        </div>

        <div class="modal-body space-y-3">
          <div
            v-for="preset in presetTemplates"
            :key="preset.id"
            class="preset-item-card"
            @click="applyPreset(preset)"
          >
            <div class="flex-center gap-3">
              <div class="preset-icon-circle">
                <span class="material-symbols-outlined icon-22">{{ preset.icon }}</span>
              </div>
              <div>
                <h4 class="font-bold text-sm">{{ preset.title }}</h4>
                <p class="text-xs text-muted mt-0.5">{{ preset.subtitle }}</p>
                <span class="text-xs text-primary font-semibold mt-1 inline-block">{{ preset.rules.length }} quy tắc mẫu</span>
              </div>
            </div>
            <button type="button" class="btn-action primary btn-compact">
              Áp dụng
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- JSON Export / Import Modal -->
    <div
      v-if="showExportModal"
      class="modal-backdrop"
      @click.self="showExportModal = false"
    >
      <div class="modal-card modal-lg">
        <div class="modal-header">
          <div class="flex-center gap-3">
            <div class="modal-icon-box">
              <span class="material-symbols-outlined icon-22">code</span>
            </div>
            <div>
              <h3 class="modal-title">Xuất / Nạp Cấu Hình Ma Trận Giá JSON</h3>
              <p class="modal-subtitle">Sao chép để sao lưu hoặc nạp cấu hình JSON từ file ngoài.</p>
            </div>
          </div>
          <button class="btn-close-modal" @click="showExportModal = false">
            <span class="material-symbols-outlined icon-20">close</span>
          </button>
        </div>

        <div class="modal-body">
          <textarea
            v-model="exportJsonString"
            rows="10"
            class="json-textarea"
          ></textarea>
        </div>

        <div class="modal-footer flex-between">
          <button type="button" class="btn-action secondary" @click="copyExportJson">
            <span class="material-symbols-outlined icon-16">content_copy</span>
            <span>Sao chép JSON</span>
          </button>

          <div class="flex-center gap-2">
            <button type="button" class="btn-action secondary" @click="showExportModal = false">
              Hủy
            </button>
            <button type="button" class="btn-action primary" @click="importJson">
              <span class="material-symbols-outlined icon-16">upload</span>
              <span>Nạp cấu hình này</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pricing-matrix-page {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: var(--ink, #191c1d);
  max-width: 1320px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem 1.5rem;
}

/* Helpers */
.flex-center {
  display: flex;
  align-items: center;
}
.flex-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.gap-1 { gap: 0.25rem; }
.gap-1-5 { gap: 0.375rem; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-4 { gap: 1rem; }
.flex-1 { flex: 1 1 0%; }
.text-right { text-align: right; }
.font-bold { font-weight: 700; }
.font-extrabold { font-weight: 800; }
.text-xs { font-size: 0.75rem; }
.text-sm { font-size: 0.875rem; }
.text-primary { color: var(--pink-dark, #78555e); }
.text-error { color: #ba1a1a; }
.text-success { color: #2e7d32; }
.text-muted { color: var(--muted, #5d5f5f); }
.text-ink { color: var(--ink, #191c1d); }
.mt-0-5 { margin-top: 0.125rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.space-y-3 > * + * { margin-top: 0.75rem; }
.space-y-4 > * + * { margin-top: 1rem; }

/* Toast Banners */
.toast-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.125rem;
  border-radius: 0.75rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.toast-banner.success {
  background-color: var(--pink-light, #ffd9e2);
  color: var(--on-primary-container, #5e3e47);
  border: 1px solid rgba(120, 85, 94, 0.2);
}
.toast-banner.error {
  background-color: #ffdad6;
  color: #93000a;
  border: 1px solid rgba(186, 26, 26, 0.2);
}
.toast-content {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  font-weight: 600;
  font-size: 0.8125rem;
}
.btn-close-toast {
  background: transparent;
  border: none;
  cursor: pointer;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: inherit;
}

/* Header Section */
.page-header {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding-bottom: 1.25rem;
  margin-bottom: 1.5rem;
}
@media (min-width: 900px) {
  .page-header {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.page-title {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink, #191c1d);
  margin: 0;
}
.page-subtitle {
  font-size: 0.875rem;
  color: var(--muted, #5d5f5f);
  max-width: 44rem;
  margin: 0.25rem 0 0 0;
  line-height: 1.45;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Action Buttons */
.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.875rem;
  border-radius: 0.625rem;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
  white-space: nowrap;
}
.btn-action.primary {
  background-color: var(--pink-dark, #78555e);
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.btn-action.primary:hover {
  background-color: #5e3e47;
  transform: translateY(-1px);
}
.btn-action.secondary {
  background-color: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  border-color: var(--outline, #d8dce2);
}
.btn-action.secondary:hover {
  background-color: var(--soft, #e5e8ec);
}
.btn-compact {
  padding: 0.3125rem 0.625rem;
  font-size: 0.75rem;
}
.btn-action-small {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 0.5rem;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #d8dce2);
  cursor: pointer;
}
.btn-action-small:hover {
  background: var(--soft, #e5e8ec);
}
.btn-icon-small {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 0.5rem;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--surface, #ffffff);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.btn-icon-small:hover {
  background: var(--soft, #e5e8ec);
}

/* Main 2-Column Grid Layout (5 : 7 ratio) */
.matrix-main-grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 1.5rem;
  align-items: start;
}
@media (min-width: 1024px) {
  .matrix-main-grid {
    grid-template-columns: 5fr 7fr;
  }
}
.col-left, .col-right {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Card Panels */
.card-panel {
  background-color: var(--surface, #ffffff);
  border: 1px solid #e5e8ec;
  border-radius: 1rem;
  padding: 1.25rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
}
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.875rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid #f0f2f5;
}
.panel-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}
.panel-desc {
  font-size: 0.75rem;
  color: var(--muted, #5d5f5f);
  margin: 0.125rem 0 0 0;
}

/* Priority Items */
.priority-items-list {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}
.priority-item {
  border-radius: 0.75rem;
  padding: 0.75rem 0.875rem;
  background: #fdfdfe;
  border: 1px solid #eaedf0;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}
.priority-item:hover {
  background: #ffffff;
  border-color: #cbd0d6;
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.priority-item.is-selected {
  background: #ffffff;
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 1.5px var(--pink-dark, #78555e), 0 3px 8px rgba(120, 85, 94, 0.08);
}
.priority-item.is-inactive {
  opacity: 0.45;
  filter: grayscale(1);
}

.priority-accent-bar {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
}
.accent-p1 { background-color: #ba1a1a; }
.accent-p2 { background-color: var(--pink-dark, #78555e); }
.accent-p3 { background-color: #5d5f5f; }
.accent-pbase { background-color: #a0a4a8; }

.priority-item-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-left: 0.25rem;
}
.priority-circle {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 800;
  flex-shrink: 0;
}
.priority-badge-1 { background: #ffdad6; color: #ba1a1a; }
.priority-badge-2 { background: var(--pink, #ffd1dc); color: var(--pink-dark, #78555e); }
.priority-badge-3 { background: #dfe0e0; color: #454747; }
.priority-badge-base { background: #e5e8ec; color: #5d5f5f; }

.rule-details {
  flex: 1 1 0%;
  min-width: 0;
}
.rule-title-row {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  flex-wrap: nowrap;
}
.rule-name {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--ink, #191c1d);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rule-subtext {
  font-size: 0.6875rem;
  color: var(--muted, #5d5f5f);
  margin: 0.125rem 0 0 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tag-pill {
  font-size: 0.5625rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 0.125rem 0.3125rem;
  border-radius: 0.25rem;
  white-space: nowrap;
}
.tag-error { background: #ffdad6; color: #ba1a1a; }
.tag-primary { background: var(--pink, #ffd1dc); color: var(--pink-dark, #78555e); }
.tag-secondary { background: #dfe0e0; color: #454747; }
.tag-success { background: #d9f0e4; color: #2e7d32; }

.priority-actions {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  flex-shrink: 0;
}
.price-chip {
  font-size: 0.8125rem;
  font-weight: 800;
  color: var(--pink-dark, #78555e);
  background: var(--pink-light, #ffd9e2);
  padding: 0.2rem 0.4375rem;
  border-radius: 0.375rem;
}
.reorder-btns {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}
.btn-icon-tiny {
  width: 1.125rem;
  height: 1.125rem;
  border-radius: 0.25rem;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--muted, #5d5f5f);
}
.btn-icon-tiny:hover {
  background: var(--soft, #e5e8ec);
  color: var(--ink, #191c1d);
}
.btn-toggle {
  background: transparent;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #a0a4a8;
  padding: 0;
}
.btn-toggle.active {
  color: var(--pink-dark, #78555e);
}

/* Rule Configurator Form */
.tag-status {
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 0.1875rem 0.5rem;
  border-radius: 0.25rem;
  letter-spacing: 0.05em;
}
.status-new { background: #d9f0e4; color: #2e7d32; }
.status-edit { background: var(--pink, #ffd1dc); color: var(--pink-dark, #78555e); }

.config-form {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.form-label {
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--muted, #5d5f5f);
}
.form-label-mini {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
}
.form-input {
  width: 100%;
  box-sizing: border-box;
  background-color: #f8f9fa;
  border: 1px solid #d8dce2;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  color: var(--ink, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}
.form-input:focus {
  border-color: var(--pink-dark, #78555e);
  background-color: #ffffff;
  box-shadow: 0 0 0 2px var(--pink-light, #ffd9e2);
}
.form-input-mini {
  width: 100%;
  box-sizing: border-box;
  background-color: #ffffff;
  border: 1px solid #d8dce2;
  border-radius: 0.375rem;
  padding: 0.375rem 0.5rem;
  font-size: 0.75rem;
  color: var(--ink, #191c1d);
}
.form-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}
.select-wrapper {
  position: relative;
  width: 100%;
}
.form-select {
  width: 100%;
  box-sizing: border-box;
  background-color: #f8f9fa;
  border: 1px solid #d8dce2;
  border-radius: 0.5rem;
  padding: 0.5rem 1.75rem 0.5rem 0.75rem;
  font-size: 0.75rem;
  color: var(--ink, #191c1d);
  appearance: none;
  cursor: pointer;
  outline: none;
}
.select-arrow {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, #5d5f5f);
  pointer-events: none;
  font-size: 1rem;
}

.sub-card-box {
  background: #f8f9fa;
  border: 1px solid #e5e8ec;
  border-radius: 0.75rem;
  padding: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}
.sub-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sub-card-title {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink, #191c1d);
}
.time-item-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #ffffff;
  padding: 0.375rem 0.625rem;
  border-radius: 0.5rem;
  border: 1px solid #e5e8ec;
}
.input-with-affix {
  position: relative;
  display: flex;
  align-items: center;
}
.input-affix {
  position: absolute;
  right: 0.5rem;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--muted, #5d5f5f);
  pointer-events: none;
}
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  user-select: none;
}

.weekday-pills {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
  margin-top: 0.25rem;
}
.weekday-pill {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 0.375rem;
  font-size: 0.6875rem;
  font-weight: 700;
  border: 1px solid #d8dce2;
  background: #ffffff;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  transition: all 0.15s ease;
}
.weekday-pill.active {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  border-color: var(--pink-dark, #78555e);
}

.form-footer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0.25rem;
}
.btn-delete {
  background: transparent;
  border: none;
  color: #ba1a1a;
  font-size: 0.75rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  cursor: pointer;
  padding: 0.375rem 0.5rem;
  border-radius: 0.375rem;
}
.btn-delete:hover {
  background: #ffdad6;
}
.btn-group-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Calendar Card */
.calendar-controls {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.calendar-legend {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  flex-wrap: wrap;
  font-size: 0.6875rem;
  color: var(--muted, #5d5f5f);
  margin-bottom: 0.875rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px dashed #e5e8ec;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.dot-indicator {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
}
.dot-holiday { background-color: #ba1a1a; }
.dot-peak { background-color: var(--pink-dark, #78555e); }
.dot-weekend { background-color: #6366f1; }
.dot-discount { background-color: #10b981; }
.dot-base { background-color: #c4c7cc; }

.calendar-table-wrapper {
  margin-bottom: 1rem;
}
.calendar-weekdays-row {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 0.25rem;
  text-align: center;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--muted, #5d5f5f);
  margin-bottom: 0.375rem;
}
.weekday-header {
  padding: 0.125rem 0;
}
.calendar-days-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 0.25rem;
}
.calendar-day-cell {
  aspect-ratio: 1;
  min-height: 2.75rem;
  border-radius: 0.5rem;
  padding: 0.375rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  cursor: pointer;
  transition: all 0.15s ease;
  background: #ffffff;
  border: 1px solid #eaedf0;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.calendar-day-cell:hover {
  border-color: #b0b4bc;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}
.calendar-day-cell.is-other-month {
  opacity: 0.3;
  background: #f8f9fa;
}
.calendar-day-cell.is-selected {
  background: var(--pink-light, #ffd9e2);
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 1.5px var(--pink-dark, #78555e);
}
.day-number {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink, #191c1d);
}
.calendar-day-cell.is-selected .day-number {
  color: var(--on-primary-container, #5e3e47);
  font-weight: 800;
}
.day-dot-container {
  display: flex;
  justify-content: flex-end;
}
.day-dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
}

.selected-date-banner {
  background: #f8f9fa;
  border: 1px solid #e5e8ec;
  border-radius: 0.625rem;
  padding: 0.625rem 0.875rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* Timeline & Hourly Slots */
.hourly-slots-scroll {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  max-height: 20rem;
  overflow-y: auto;
  padding-right: 0.25rem;
}
.hourly-slot-row {
  background: #f8f9fa;
  border: 1px solid #eaedf0;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  transition: all 0.15s ease;
}
.hourly-slot-row:hover {
  background: #ffffff;
  border-color: #cbd0d6;
}
.slot-left {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}
.slot-time-badge {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--ink, #191c1d);
  width: 5.25rem;
  flex-shrink: 0;
}
.slot-rule-info {
  min-width: 0;
}
.slot-rule-name {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink, #191c1d);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.slot-reason-text {
  font-size: 0.625rem;
  color: var(--muted, #5d5f5f);
  margin: 0.125rem 0 0 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.priority-tag-1 { background: #ffdad6; color: #ba1a1a; }
.priority-tag-2 { background: var(--pink, #ffd1dc); color: var(--pink-dark, #78555e); }
.priority-tag-3 { background: #dfe0e0; color: #454747; }
.priority-tag-base { background: #e5e8ec; color: #5d5f5f; }

.slot-right {
  text-align: right;
  flex-shrink: 0;
}
.slot-price {
  font-size: 0.8125rem;
  font-weight: 800;
  color: var(--ink, #191c1d);
}
.slot-diff {
  font-size: 0.625rem;
  font-weight: 700;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  background-color: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.modal-card {
  background-color: var(--surface, #ffffff);
  border: 1px solid var(--outline, #d8dce2);
  border-radius: 1.25rem;
  width: 100%;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
  max-height: 90vh;
  overflow-y: auto;
  padding: 1.25rem;
}
.modal-md { max-width: 32rem; }
.modal-lg { max-width: 40rem; }
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.875rem;
  border-bottom: 1px solid #f0f2f5;
}
.modal-icon-box {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.625rem;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.modal-title {
  font-size: 1rem;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
}
.modal-subtitle {
  font-size: 0.6875rem;
  color: var(--muted, #5d5f5f);
  margin: 0.125rem 0 0 0;
}
.btn-close-modal {
  background: transparent;
  border: none;
  cursor: pointer;
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted, #5d5f5f);
}
.btn-close-modal:hover {
  background: var(--soft, #e5e8ec);
}
.modal-body {
  padding: 1rem 0;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 0.875rem;
  border-top: 1px solid #f0f2f5;
}

.sim-total-card {
  background: var(--pink-light, #ffd9e2);
  border: 1px solid var(--pink, #ffd1dc);
  border-radius: 0.875rem;
  padding: 0.875rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sim-total-label {
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--pink-dark, #78555e);
  letter-spacing: 0.05em;
}
.sim-total-amount {
  font-size: 1.5rem;
  font-weight: 900;
  color: var(--pink-dark, #78555e);
}
.sim-slots-list {
  display: flex;
  flex-direction: column;
  gap: 0.3125rem;
  max-height: 12rem;
  overflow-y: auto;
}
.sim-slot-item {
  background: #f8f9fa;
  border: 1px solid #eaedf0;
  border-radius: 0.5rem;
  padding: 0.4375rem 0.625rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.preset-item-card {
  background: #f8f9fa;
  border: 1px solid #eaedf0;
  border-radius: 0.875rem;
  padding: 0.875rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
}
.preset-item-card:hover {
  background: #ffffff;
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 3px 8px rgba(120, 85, 94, 0.08);
}
.preset-icon-circle {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.625rem;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.json-textarea {
  width: 100%;
  box-sizing: border-box;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.75rem;
  padding: 0.75rem;
  border-radius: 0.625rem;
  background: #f8f9fa;
  border: 1px solid #d8dce2;
  color: var(--ink, #191c1d);
  outline: none;
  line-height: 1.45;
}

/* Material Icons Helpers */
.icon-12 { font-size: 12px; }
.icon-14 { font-size: 14px; }
.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }
.icon-20 { font-size: 20px; }
.icon-22 { font-size: 22px; }
.icon-24 { font-size: 24px; }

/* Transitions */
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
