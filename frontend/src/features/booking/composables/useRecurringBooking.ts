import { ref, computed, watch } from 'vue'
import { bookingApi } from '../api/bookingApi'
import { idempotencyKey } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court } from '@/features/court/types/court'
import type { ApiError } from '@/core/types/api'
import type {
  RecurringSeries,
  RecurringInvoice,
  RecurringOccurrence,
  RecurringFrequency,
  WeekdayOption,
  CreateRecurringSeriesPayload,
  RecurringSeriesPreview,
  RecurringSeriesPreviewPayload,
  RecurringPartialConflict,
  AcceptedPreview,
} from '../types/recurring'

export type { RecurringSeries, RecurringInvoice, RecurringOccurrence, RecurringFrequency }

export type SubmitOutcome =
  | { kind: 'confirmed' }
  | { kind: 'pending' }
  | { kind: 'partial'; conflict: RecurringPartialConflict }
  | { kind: 'drift' }
  | { kind: 'conflict'; message: string }
  | { kind: 'invalid'; reason: 'court' | 'weekdays' | 'preview' }
  | { kind: 'error'; message: string }

export function useRecurringBooking() {
  const courts = ref<Court[]>([])
  const seriesList = ref<RecurringSeries[]>([])
  const activeTab = ref<'create' | 'series' | 'invoices'>('create')
  const loading = ref(false)
  const submitting = ref(false)
  const notice = ref('')
  const error = ref('')

  const formatIsoDate = (d: Date): string => {
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const defaultStart = new Date()
  defaultStart.setDate(defaultStart.getDate() + 1)
  const defaultEnd = new Date()
  defaultEnd.setMonth(defaultEnd.getMonth() + 3)

  // Wizard State
  const selectedCourtId = ref<number>(1)
  const frequency = ref<RecurringFrequency>('weekly')
  const selectedWeekdays = ref<number[]>([1, 3, 5])
  const startTime = ref('18:00')
  const endTime = ref('20:00')
  const startDate = ref(formatIsoDate(defaultStart))
  const endDate = ref(formatIsoDate(defaultEnd))

  // Explicit occurrence count is only set when the user picks a fixed number of
  // sessions; left null the series is bounded by the date range (until/ends_at).
  const explicitOccurrences = ref<number | null>(null)

  // Server-side preview state (authoritative occurrence list + totals).
  const preview = ref<RecurringSeriesPreview | null>(null)
  const previewLoading = ref(false)
  const previewError = ref('')

  // Server-negotiated partial-conflict outcome: some occurrences are already booked and the
  // venue policy allows keeping the remainder. Rendered so the user can accept the remaining
  // sessions (re-submit with accept_partial) or change the schedule.
  const partialConflict = ref<RecurringPartialConflict | null>(null)

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

  // The court's "from" price; no invented price when it has none.
  const hourlyRate = computed(() => selectedCourt.value?.from_price ?? null)

  // Local fallback estimate of the number of sessions, used only until the
  // server preview loads (or if it fails). It never drives the persisted series.
  const localSessionCountEstimate = computed(() => {
    const start = new Date(startDate.value)
    const end = new Date(endDate.value)
    if (
      !startDate.value ||
      !endDate.value ||
      selectedWeekdays.value.length === 0 ||
      isNaN(start.getTime()) ||
      isNaN(end.getTime()) ||
      start > end
    ) {
      return 0
    }
    if (frequency.value === 'monthly') {
      const months =
        (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1
      return Math.max(0, months) * selectedWeekdays.value.length
    }
    let weekly = 0
    const walker = new Date(start)
    let guard = 0
    while (walker <= end && guard < 4000) {
      const jsDay = walker.getDay()
      if (selectedWeekdays.value.includes(jsDay === 0 ? 7 : jsDay)) weekly++
      walker.setDate(walker.getDate() + 1)
      guard++
    }
    return frequency.value === 'biweekly' ? Math.ceil(weekly / 2) : weekly
  })

  // Rendered from the server preview so monthly cadence and the platform cap are
  // handled server-side; each entry carries its per-occurrence conflict flag.
  // The server returns UTC instants; display them in the venue timezone to match
  // the rest of the app (see useReservations / court schedule formatters).
  const previewSessions = computed(() => {
    const occurrences = preview.value?.occurrences ?? []
    return occurrences.map((o) => {
      const day = weekdayButtons.find((w) => w.id === o.weekday)
      const start = new Date(o.starts_at)
      const end = new Date(o.ends_at)
      const timeOptions: Intl.DateTimeFormatOptions = {
        timeZone: 'Asia/Ho_Chi_Minh',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      }
      return {
        date: start.toLocaleDateString('en-CA', { timeZone: 'Asia/Ho_Chi_Minh' }),
        weekdayName: day?.label ?? `T${o.weekday}`,
        time: `${start.toLocaleTimeString('vi-VN', timeOptions)} - ${end.toLocaleTimeString('vi-VN', timeOptions)}`,
        conflict: o.conflict,
        conflictReason: o.conflict_reason,
      }
    })
  })

  async function fetchPreview() {
    if (!selectedCourtId.value || selectedWeekdays.value.length === 0 || !startDate.value) {
      preview.value = null
      previewError.value = ''
      return
    }
    const payload: RecurringSeriesPreviewPayload = {
      court_id: selectedCourtId.value,
      frequency: frequency.value,
      weekdays: [...selectedWeekdays.value],
      starts_at: `${startDate.value} ${startTime.value}:00`,
      ends_at: `${startDate.value} ${endTime.value}:00`,
    }
    if (endDate.value) {
      payload.until = `${endDate.value} ${endTime.value}:00`
    }
    if (explicitOccurrences.value && explicitOccurrences.value > 0) {
      payload.occurrences = explicitOccurrences.value
    }
    previewLoading.value = true
    previewError.value = ''
    try {
      preview.value = await bookingApi.previewRecurringSeries(payload)
    } catch (err: unknown) {
      preview.value = null
      previewError.value = errorMessage(err, 'Không thể tải bản xem trước lịch định kỳ.')
    } finally {
      previewLoading.value = false
    }
  }

  let previewTimer: number | undefined
  function schedulePreview() {
    window.clearTimeout(previewTimer)
    previewTimer = window.setTimeout(() => {
      void fetchPreview()
    }, 300)
  }

  watch(
    () =>
      [
        selectedCourtId.value,
        frequency.value,
        selectedWeekdays.value.join(','),
        startDate.value,
        endDate.value,
        startTime.value,
        endTime.value,
        explicitOccurrences.value ?? '',
      ].join('|'),
    schedulePreview,
    { immediate: true },
  )

  const estimatedSessionsCount = computed(() =>
    preview.value ? preview.value.count : localSessionCountEstimate.value,
  )

  // Only the server preview prices the series; there is no local estimate.
  const estimatedTotalAmount = computed(() => preview.value?.total_amount ?? null)

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
    return list.sort(
      (a, b) => new Date(b.period_start).getTime() - new Date(a.period_start).getTime(),
    )
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

      if (seriesRes) {
        if (Array.isArray(seriesRes)) {
          seriesList.value = seriesRes
        } else if (Array.isArray((seriesRes as Record<string, any>).data)) {
          seriesList.value = (seriesRes as Record<string, any>).data
        }
      }
    } catch {
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

  function buildAccepted(source: RecurringSeriesPreview): AcceptedPreview {
    return {
      total: source.total_amount,
      occurrences: source.occurrences.map((o) => ({ starts_at: o.starts_at, price: o.price })),
    }
  }

  async function submitSeries(acceptPartial = false): Promise<SubmitOutcome> {
    if (!selectedCourtId.value) {
      return { kind: 'invalid', reason: 'court' }
    }
    if (selectedWeekdays.value.length === 0) {
      return { kind: 'invalid', reason: 'weekdays' }
    }
    // The persisted series must equal the accepted preview, so never submit without one.
    const accepted = preview.value
    if (!accepted || accepted.occurrences.length === 0) {
      return { kind: 'invalid', reason: 'preview' }
    }

    submitting.value = true
    error.value = ''
    try {
      const payload: CreateRecurringSeriesPayload = {
        court_id: selectedCourtId.value,
        frequency: frequency.value,
        weekdays: [...selectedWeekdays.value],
        starts_at: `${startDate.value} ${startTime.value}:00`,
        ends_at: `${startDate.value} ${endTime.value}:00`,
        until: `${endDate.value} ${endTime.value}:00`,
        accepted: buildAccepted(accepted),
        accept_partial: acceptPartial,
      }
      // Only pin an explicit occurrence count when the user chose one, so the
      // persisted series matches the previewed list exactly (no local 12-cap).
      if (explicitOccurrences.value && explicitOccurrences.value > 0) {
        payload.occurrences = explicitOccurrences.value
      }

      const result = await bookingApi.createRecurringSeries(payload, idempotencyKey())
      if ('series' in result) {
        partialConflict.value = null
        await loadData()
        activeTab.value = 'series'
        return { kind: result.series.status === 'confirmed' ? 'confirmed' : 'pending' }
      }

      // Partial-conflict negotiation: surface the excluded sessions for explicit re-accept.
      partialConflict.value = result
      return { kind: 'partial', conflict: result }
    } catch (err: unknown) {
      const status = (err as ApiError)?.status
      const message = errorMessage(err, 'Không thể tạo lịch đặt sân định kỳ.')
      // A 409 means live inventory/pricing drifted from the preview (or an all-or-nothing
      // conflict). Re-fetch the preview so the refreshed list/prices/conflict flags are shown.
      if (status === 409) {
        partialConflict.value = null
        await fetchPreview()
        return /xem tr/i.test(message) ? { kind: 'drift' } : { kind: 'conflict', message }
      }
      return { kind: 'error', message }
    } finally {
      submitting.value = false
    }
  }

  function dismissPartialConflict() {
    partialConflict.value = null
    void fetchPreview()
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
    explicitOccurrences,
    previewLoading,
    previewError,
    preview,
    partialConflict,
    dismissPartialConflict,
    toggleWeekday,
    loadData,
    showNotice,
    submitSeries,
  }
}
