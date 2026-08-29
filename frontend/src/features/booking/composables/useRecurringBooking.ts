import { ref, computed } from 'vue'
import { api, apiV2 } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'

export interface RecurringInvoice {
  id: number
  recurring_series_id: number
  period_start: string
  period_end: string
  due_at: string
  amount: number
  status: 'pending' | 'paid' | 'overdue'
}

export interface RecurringOccurrence {
  id: number
  recurring_series_id: number
  sequence: number
  starts_at: string
  ends_at: string
  amount: number
  status: 'scheduled' | 'completed' | 'cancelled'
}

export interface RecurringSeries {
  id: number
  court_id: number
  frequency: 'weekly' | 'biweekly' | 'monthly'
  starts_at: string
  ends_at: string
  status: 'active' | 'paused' | 'cancelled'
  total_amount: number
  invoices?: RecurringInvoice[]
  occurrences?: RecurringOccurrence[]
}

export function useRecurringBooking() {
  const courts = ref<Court[]>([])
  const seriesList = ref<RecurringSeries[]>([])
  const loading = ref(false)
  const submitting = ref(false)
  const notice = ref('')
  const error = ref('')

  // Wizard State
  const selectedCourtId = ref<number>(1)
  const frequency = ref<'weekly' | 'biweekly' | 'monthly'>('weekly')
  const selectedWeekdays = ref<number[]>([1, 3, 5])
  const startTime = ref('18:00')
  const endTime = ref('20:00')
  const startDate = ref('2026-09-01')
  const endDate = ref('2026-11-30')

  const weekdayButtons = [
    { id: 1, label: 'T2', name: 'Thứ 2' },
    { id: 2, label: 'T3', name: 'Thứ 3' },
    { id: 3, label: 'T4', name: 'Thứ 4' },
    { id: 4, label: 'T5', name: 'Thứ 5' },
    { id: 5, label: 'T6', name: 'Thứ 6' },
    { id: 6, label: 'T7', name: 'Thứ 7' },
    { id: 7, label: 'CN', name: 'Chủ Nhật' },
  ]

  function toggleWeekday(dayId: number) {
    const idx = selectedWeekdays.value.indexOf(dayId)
    if (idx >= 0) {
      selectedWeekdays.value.splice(idx, 1)
    } else {
      selectedWeekdays.value.push(dayId)
    }
  }

  async function loadData() {
    loading.value = true
    try {
      const [courtsRes, seriesRes] = await Promise.all([
        api.get<Court[]>('/courts'),
        apiV2.get<{ data: RecurringSeries[] }>('/recurring-series'),
      ])
      courts.value = courtsRes || []
      if (seriesRes && seriesRes.data) {
        seriesList.value = seriesRes.data
      }
    } catch (err) {
      // fallback
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
    submitting.value = true
    try {
      const payload = {
        court_id: selectedCourtId.value,
        frequency: frequency.value,
        weekdays: selectedWeekdays.value,
        starts_at: `${startDate.value}T${startTime.value}:00Z`,
        ends_at: `${endDate.value}T${endTime.value}:00Z`,
      }
      await apiV2.post('/recurring-series', payload)
      showNotice('Đã tạo thành công chuỗi đặt sân định kỳ!')
      await loadData()
    } catch (err: unknown) {
      error.value = errorMessage(err, 'Không thể tạo lịch đặt sân định kỳ.')
    } finally {
      submitting.value = false
    }
  }

  return {
    courts,
    seriesList,
    loading,
    submitting,
    notice,
    error,
    selectedCourtId,
    frequency,
    selectedWeekdays,
    startTime,
    endTime,
    startDate,
    endDate,
    weekdayButtons,
    toggleWeekday,
    loadData,
    showNotice,
    submitSeries,
  }
}
