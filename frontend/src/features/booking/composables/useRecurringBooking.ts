import { ref, computed } from 'vue'
import { bookingApi } from '../api/bookingApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'
import type {
  RecurringSeries,
  RecurringInvoice,
  RecurringOccurrence,
  RecurringFrequency,
  WeekdayOption,
  CreateRecurringSeriesPayload,
} from '../types/recurring'

export type { RecurringSeries, RecurringInvoice, RecurringOccurrence, RecurringFrequency }

export function useRecurringBooking() {
  const courts = ref<Court[]>([])
  const seriesList = ref<RecurringSeries[]>([])
  const activeTab = ref<'create' | 'series' | 'invoices'>('create')
  const loading = ref(false)
  const submitting = ref(false)
  const notice = ref('')
  const error = ref('')

  // Wizard State
  const selectedCourtId = ref<number>(1)
  const frequency = ref<RecurringFrequency>('weekly')
  const selectedWeekdays = ref<number[]>([1, 3, 5])
  const startTime = ref('18:00')
  const endTime = ref('20:00')
  const startDate = ref('2026-09-01')
  const endDate = ref('2026-11-30')

  const weekdayButtons: WeekdayOption[] = [
    { id: 1, label: 'T2', name: 'Thứ 2', fullName: 'Thứ Hai' },
    { id: 2, label: 'T3', name: 'Thứ 3', fullName: 'Thứ Ba' },
    { id: 3, label: 'T4', name: 'Thứ 4', fullName: 'Thứ Tư' },
    { id: 4, label: 'T5', name: 'Thứ 5', fullName: 'Thứ Năm' },
    { id: 5, label: 'T6', name: 'Thứ 6', fullName: 'Thứ Sáu' },
    { id: 6, label: 'T7', name: 'Thứ 7', fullName: 'Thứ Bảy' },
    { id: 7, label: 'CN', name: 'Chủ Nhật', fullName: 'Chủ Nhật' },
  ]

  const selectedCourt = computed(() => {
    return courts.value.find((c) => c.id === selectedCourtId.value) || courts.value[0] || null
  })

  // Calculate session duration in hours
  const sessionDurationHours = computed(() => {
    const partsStart = startTime.value.split(':')
    const partsEnd = endTime.value.split(':')
    const startH = Number(partsStart[0] ?? 18)
    const startM = Number(partsStart[1] ?? 0)
    const endH = Number(partsEnd[0] ?? 20)
    const endM = Number(partsEnd[1] ?? 0)
    if (isNaN(startH) || isNaN(endH)) return 2
    const diffMinutes = endH * 60 + (endM || 0) - (startH * 60 + (startM || 0))
    return Math.max(0.5, diffMinutes > 0 ? diffMinutes / 60 : 2)
  })

  // Hourly price estimate based on court pricing
  const hourlyRate = computed(() => {
    if (!selectedCourt.value) return 120000
    const slots = selectedCourt.value.price_slots
    if (slots && slots.length > 0 && slots[0]?.price) {
      return slots[0].price
    }
    return 120000
  })

  // Preview generated sessions between startDate and endDate
  const previewSessions = computed(() => {
    if (!startDate.value || !endDate.value || selectedWeekdays.value.length === 0) {
      return []
    }
    const sessions: Array<{ date: string; weekdayName: string; time: string }> = []
    const cur = new Date(startDate.value)
    const end = new Date(endDate.value)
    if (isNaN(cur.getTime()) || isNaN(end.getTime()) || cur > end) return []

    // JS getDay(): 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    // Map our ISO weekday: 1 = Mon, ..., 7 = Sun
    const jsToIsoDay = (d: number) => (d === 0 ? 7 : d)

    let count = 0
    let stepWeek = 1
    if (frequency.value === 'biweekly') stepWeek = 2

    const maxOccurrences = 104
    while (cur <= end && count < maxOccurrences) {
      const isoDay = jsToIsoDay(cur.getDay())
      if (selectedWeekdays.value.includes(isoDay)) {
        const foundDay = weekdayButtons.find((w) => w.id === isoDay)
        const dateStr = cur.toISOString().slice(0, 10)
        sessions.push({
          date: dateStr,
          weekdayName: foundDay?.label || `T${isoDay + 1}`,
          time: `${startTime.value} - ${endTime.value}`,
        })
        count++
      }
      cur.setDate(cur.getDate() + 1)
    }
    return sessions
  })

  const estimatedSessionsCount = computed(() => {
    return previewSessions.value.length
  })

  const estimatedTotalAmount = computed(() => {
    return Math.round(estimatedSessionsCount.value * sessionDurationHours.value * hourlyRate.value)
  })

  // Aggregated all invoices from seriesList
  const allInvoices = computed<RecurringInvoice[]>(() => {
    const list: RecurringInvoice[] = []
    for (const series of seriesList.value) {
      if (series.invoices && series.invoices.length > 0) {
        for (const inv of series.invoices) {
          list.push({
            ...inv,
            series,
          })
        }
      }
    }
    return list.sort((a, b) => new Date(b.period_start).getTime() - new Date(a.period_start).getTime())
  })

  function toggleWeekday(dayId: number) {
    const idx = selectedWeekdays.value.indexOf(dayId)
    if (idx >= 0) {
      if (selectedWeekdays.value.length > 1) {
        selectedWeekdays.value.splice(idx, 1)
      }
    } else {
      selectedWeekdays.value.push(dayId)
      selectedWeekdays.value.sort((a, b) => a - b)
    }
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [courtsRes, seriesRes] = await Promise.all([
        bookingApi.getCourts<Court[]>(),
        bookingApi.getRecurringSeries<{ data: RecurringSeries[] }>(),
      ])

      if (courtsRes && Array.isArray(courtsRes)) {
        courts.value = courtsRes
        const firstCourt = courtsRes[0]
        if (firstCourt && !courtsRes.some((c) => c.id === selectedCourtId.value)) {
          selectedCourtId.value = firstCourt.id
        }
      }

      if (seriesRes && seriesRes.data) {
        seriesList.value = seriesRes.data
      }
    } catch (err: unknown) {
      // Graceful fallback for offline/development
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

  async function submitSeries() {
    if (!selectedCourtId.value) {
      error.value = 'Vui lòng chọn sân thể thao.'
      return
    }
    if (selectedWeekdays.value.length === 0) {
      error.value = 'Vui lòng chọn ít nhất một ngày trong tuần.'
      return
    }

    submitting.value = true
    error.value = ''
    try {
      const payload: CreateRecurringSeriesPayload = {
        court_id: selectedCourtId.value,
        frequency: frequency.value,
        weekdays: selectedWeekdays.value,
        starts_at: `${startDate.value} ${startTime.value}:00`,
        ends_at: `${endDate.value} ${endTime.value}:00`,
        until: `${endDate.value} ${endTime.value}:00`,
        occurrences: estimatedSessionsCount.value > 0 ? Math.min(estimatedSessionsCount.value, 12) : 12,
      }

      await bookingApi.createRecurringSeries(payload)
      showNotice('Đã tạo thành công chuỗi đặt sân định kỳ!')
      await loadData()
      activeTab.value = 'series'
    } catch (err: unknown) {
      error.value = errorMessage(err, 'Không thể tạo lịch đặt sân định kỳ.')
    } finally {
      submitting.value = false
    }
  }

  return {
    courts,
    seriesList,
    allInvoices,
    activeTab,
    loading,
    submitting,
    notice,
    error,
    selectedCourtId,
    selectedCourt,
    frequency,
    selectedWeekdays,
    startTime,
    endTime,
    startDate,
    endDate,
    weekdayButtons,
    sessionDurationHours,
    hourlyRate,
    previewSessions,
    estimatedSessionsCount,
    estimatedTotalAmount,
    toggleWeekday,
    loadData,
    showNotice,
    submitSeries,
  }
}
