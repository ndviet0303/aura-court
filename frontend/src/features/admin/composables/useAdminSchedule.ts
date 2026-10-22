import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, OperatingStatus, OperationalStop, Venue } from '@/features/court/types/court'

export interface ShiftTime {
  id: number
  start: string
  end: string
}

export interface WeekdaySchedule {
  id: number
  isOpen: boolean
  shifts: ShiftTime[]
}

export interface SpecialDateItem {
  id: number
  date: string
  name: string | { vi: string; en: string }
  type: 'holiday' | 'special_hours' | 'closed'
  hours?: string
  court_id?: number | null
}

/** An open stop of one court, listed on the schedule page. */
export interface CourtStop extends OperationalStop {
  court_id: number
  court_name: Court['name']
  court_status?: OperatingStatus
  organization_id?: number
  status: 'upcoming' | 'ongoing'
}

function emptyWeeklySchedule(): WeekdaySchedule[] {
  return Array.from({ length: 7 }, (_, index) => ({ id: index + 1, isOpen: false, shifts: [] }))
}

export function useAdminSchedule() {
  const { t } = useI18n()
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const selectedScheduleCourtId = ref<number | 'venue'>('venue')
  const auditLogs = ref<any[]>([])
  const selectedVenueFilter = ref<number | 'all'>('all')

  const showSpecialDateModal = ref(false)
  const showAuditLogsModal = ref(false)

  const weeklySchedule = ref<WeekdaySchedule[]>(emptyWeeklySchedule())
  const specialDates = ref<SpecialDateItem[]>([])

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  const selectedVenue = computed(() =>
    selectedVenueFilter.value === 'all'
      ? null
      : venues.value.find((venue) => venue.id === selectedVenueFilter.value) || null,
  )

  async function loadCalendarSettings() {
    const venue = selectedVenue.value
    if (!venue?.organization_id) {
      weeklySchedule.value = emptyWeeklySchedule()
      specialDates.value = []
      return
    }

    try {
      const courtId =
        typeof selectedScheduleCourtId.value === 'number'
          ? selectedScheduleCourtId.value
          : undefined
      const [scheduleResponse, exceptionResponse] = await Promise.all([
        adminApi.getOperatingSchedule(venue.organization_id, venue.id, courtId),
        adminApi.getScheduleExceptions(venue.organization_id, venue.id, courtId),
      ])
      const persisted = scheduleResponse.data?.weekly_hours
      weeklySchedule.value = Array.from({ length: 7 }, (_, index) => {
        const dayId = index + 1
        const day = persisted?.[String(dayId)] ?? persisted?.[dayId]
        return {
          id: dayId,
          isOpen: day ? !day.closed : false,
          shifts: (day?.intervals || []).map((interval: any, intervalIndex: number) => ({
            id: intervalIndex + 1,
            start: interval.start,
            end: interval.end,
          })),
        }
      })
      specialDates.value = (exceptionResponse.data || []).map((exception: any) => ({
        id: exception.id,
        date: String(exception.exception_date).slice(0, 10),
        name: exception.name,
        type: exception.type,
        court_id: exception.court_id,
        hours:
          (exception.intervals || [])
            .map((interval: any) => `${interval.start} - ${interval.end}`)
            .join(', ') || undefined,
      }))
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.schedule.loadError'))
      weeklySchedule.value = emptyWeeklySchedule()
      specialDates.value = []
    }
  }

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [courtsRes, venuesRes, logsRes] = await Promise.allSettled([
        adminApi.getCourts(),
        adminApi.getVenues(),
        adminApi.getAuditLogs<{ data: any[] }>(),
      ])

      if (courtsRes.status === 'rejected') {
        error.value = errorMessage(courtsRes.reason, t('admin.schedule.loadError'))
      } else if (courtsRes.value) {
        courts.value = courtsRes.value
      }
      if (venuesRes.status === 'rejected') {
        error.value = errorMessage(venuesRes.reason, t('admin.schedule.loadError'))
      } else if (venuesRes.value) {
        venues.value = venuesRes.value
      }
      if (selectedVenueFilter.value === 'all' && venues.value.length === 1) {
        selectedVenueFilter.value = venues.value[0]?.id ?? 'all'
      }
      if (logsRes.status === 'fulfilled' && logsRes.value) {
        const rawLogs = (logsRes.value as any)?.data || logsRes.value
        auditLogs.value = Array.isArray(rawLogs) ? rawLogs : []
      }
      await loadCalendarSettings()
    } catch (e: unknown) {
      error.value = errorMessage(e, t('admin.schedule.loadError'))
    } finally {
      loading.value = false
    }
  }

  function addShift(dayId: number) {
    const day = weeklySchedule.value.find((d) => d.id === dayId)
    if (day) {
      day.shifts.push({
        id: Date.now(),
        start: '17:00',
        end: '22:00',
      })
    }
  }

  function removeShift(dayId: number, shiftIndex: number) {
    const day = weeklySchedule.value.find((d) => d.id === dayId)
    if (day && day.shifts.length > 1) {
      day.shifts.splice(shiftIndex, 1)
    }
  }

  function toggleDayOpen(dayId: number) {
    const day = weeklySchedule.value.find((d) => d.id === dayId)
    if (day) {
      day.isOpen = !day.isOpen
    }
  }

  function copySchedule(fromDayId: number, target: 'weekdays' | 'all') {
    const sourceDay = weeklySchedule.value.find((d) => d.id === fromDayId)
    if (!sourceDay) return

    weeklySchedule.value.forEach((day) => {
      if (day.id === fromDayId) return
      if (target === 'weekdays' && (day.id === 6 || day.id === 7)) return

      day.isOpen = sourceDay.isOpen
      day.shifts = sourceDay.shifts.map((s, idx) => ({
        id: Date.now() + idx + day.id,
        start: s.start,
        end: s.end,
      }))
    })

    saveWeeklySchedule(false)
  }

  async function saveWeeklySchedule(notify = true) {
    saving.value = true
    try {
      const venue = selectedVenue.value
      if (!venue?.organization_id) {
        error.value = t('admin.schedule.selectVenueToSave')
        return
      }
      await adminApi.saveOperatingSchedule(venue.organization_id, venue.id, {
        ...(typeof selectedScheduleCourtId.value === 'number'
          ? { court_id: selectedScheduleCourtId.value }
          : {}),
        timezone: venue.timezone || 'Asia/Ho_Chi_Minh',
        weekly_hours: Object.fromEntries(
          weeklySchedule.value.map((day) => [
            String(day.id),
            {
              closed: !day.isOpen,
              intervals: day.isOpen
                ? day.shifts.map((shift) => ({ start: shift.start, end: shift.end }))
                : [],
            },
          ]),
        ),
      })
      if (notify) {
        showNotice(t('admin.schedule.saveNotice'))
      }
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.schedule.saveError'))
    } finally {
      saving.value = false
    }
  }

  async function addSpecialDate(item: {
    date: string
    name: string
    type: SpecialDateItem['type']
    hours?: string
  }) {
    const venue = selectedVenue.value
    if (!venue?.organization_id) {
      error.value = t('admin.schedule.selectVenueToSave')
      return
    }
    const intervals =
      item.hours?.split(',').map((range) => {
        const match = range.trim().match(/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/)
        return match ? { start: match[1], end: match[2] } : null
      }) || []
    if (item.hours && intervals.some((interval) => interval === null)) {
      error.value = t('admin.schedule.invalidHours')
      return
    }
    try {
      await adminApi.saveScheduleException(venue.organization_id, venue.id, {
        ...(typeof selectedScheduleCourtId.value === 'number'
          ? { court_id: selectedScheduleCourtId.value }
          : {}),
        exception_date: item.date,
        name: item.name,
        type: item.type,
        timezone: venue.timezone || 'Asia/Ho_Chi_Minh',
        intervals: intervals.filter(Boolean),
      })
      await loadCalendarSettings()
      showSpecialDateModal.value = false
      showNotice(t('admin.schedule.specialDateAdded', { name: item.name }))
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.schedule.loadError'))
    }
  }

  async function deleteSpecialDate(id: number) {
    const venue = selectedVenue.value
    if (!venue?.organization_id) return
    try {
      await adminApi.deleteScheduleException(venue.organization_id, venue.id, id)
      specialDates.value = specialDates.value.filter((item) => item.id !== id)
      showNotice(t('admin.schedule.specialDateDeleted'))
    } catch (cause: unknown) {
      error.value = errorMessage(cause, t('admin.schedule.loadError'))
    }
  }

  watch(selectedVenueFilter, () => {
    selectedScheduleCourtId.value = 'venue'
    void loadCalendarSettings()
  })
  watch(selectedScheduleCourtId, () => {
    void loadCalendarSettings()
  })

  // Filtered computed values
  const filteredCourts = computed(() => {
    if (selectedVenueFilter.value === 'all') return courts.value
    return courts.value.filter(
      (c) => c.venue_id === selectedVenueFilter.value || c.venue?.id === selectedVenueFilter.value,
    )
  })

  // Open stops come eager-loaded on each court (operational_stops, ended_at null).
  const filteredStops = computed(() =>
    filteredCourts.value.flatMap((court) =>
      (court.operational_stops ?? []).map((stop): CourtStop => ({
        ...stop,
        court_id: court.id,
        court_name: court.name,
        court_status: court.status,
        organization_id: court.venue?.organization_id,
        status: new Date(stop.starts_at).getTime() > Date.now() ? 'upcoming' : 'ongoing',
      })),
    ),
  )

  const totalVenuesCount = computed(() => venues.value.length)
  const totalCourtsCount = computed(() => courts.value.length)
  const activeCourtsCount = computed(() => courts.value.filter((c) => c.status === 'active').length)
  const specialDatesCount = computed(() => specialDates.value.length)

  return {
    loading,
    saving,
    notice,
    error,
    courts,
    venues,
    auditLogs,
    selectedVenueFilter,
    selectedScheduleCourtId,
    selectedVenue,
    weeklySchedule,
    specialDates,
    showSpecialDateModal,
    showAuditLogsModal,
    filteredCourts,
    filteredStops,
    totalVenuesCount,
    totalCourtsCount,
    activeCourtsCount,
    specialDatesCount,
    loadData,
    showNotice,
    addShift,
    removeShift,
    toggleDayOpen,
    copySchedule,
    saveWeeklySchedule,
    addSpecialDate,
    deleteSpecialDate,
  }
}
