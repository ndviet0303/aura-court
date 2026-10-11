import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue } from '@/features/court/types/court'

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

export interface MaintenanceBlock {
  id: number
  court_id: number
  court_name?: string | { vi: string; en: string }
  venue_id?: number
  starts_at: string
  ends_at: string
  reason: string | { vi: string; en: string }
  status: 'upcoming' | 'ongoing' | 'ended'
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

  const showDowntimeModal = ref(false)
  const showSpecialDateModal = ref(false)
  const showAuditLogsModal = ref(false)
  const showUnlockConfirmModal = ref(false)
  const blockToUnlock = ref<MaintenanceBlock | null>(null)

  const weeklySchedule = ref<WeekdaySchedule[]>(emptyWeeklySchedule())
  const specialDates = ref<SpecialDateItem[]>([])
  const maintenanceBlocks = ref<MaintenanceBlock[]>([])

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function computeBlockStatus(startsAt: string, endsAt: string): 'upcoming' | 'ongoing' | 'ended' {
    const now = new Date().getTime()
    const start = new Date(startsAt).getTime()
    const end = new Date(endsAt).getTime()
    if (now < start) return 'upcoming'
    if (now >= start && now <= end) return 'ongoing'
    return 'ended'
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

      // Extract court maintenance blocks from eager-loaded court.blocks
      if (courts.value.length > 0) {
        const allBlocks: MaintenanceBlock[] = []
        courts.value.forEach((court: any) => {
          const rawBlocks = court.blocks || []
          const blockList = Array.isArray(rawBlocks) ? rawBlocks : rawBlocks.data || []
          blockList.forEach((b: any) => {
            allBlocks.push({
              id: b.id,
              court_id: court.id,
              court_name: court.name,
              venue_id: court.venue_id || court.venue?.id,
              starts_at: b.starts_at,
              ends_at: b.ends_at,
              reason: b.reason || t('admin.schedule.defaultDowntimeReason'),
              status: computeBlockStatus(b.starts_at, b.ends_at),
            })
          })
        })
        maintenanceBlocks.value = allBlocks
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

  async function addDowntime(block: {
    court_id: number
    starts_at: string
    ends_at: string
    reason: string
  }) {
    loading.value = true
    try {
      const created = await adminApi.createBlock(block.court_id, {
        starts_at: block.starts_at,
        ends_at: block.ends_at,
        reason: block.reason,
      })
      const court = courts.value.find((c) => c.id === block.court_id)
      if (court) {
        if (!(court as any).blocks) (court as any).blocks = []
        ;(court as any).blocks.push(created)
      }
      maintenanceBlocks.value.unshift({
        id: (created as any)?.id || Date.now(),
        court_id: block.court_id,
        court_name: court ? court.name : { vi: 'Sân đã chọn', en: 'Selected court' },
        venue_id: court?.venue_id || court?.venue?.id,
        starts_at: block.starts_at,
        ends_at: block.ends_at,
        reason: block.reason,
        status: computeBlockStatus(block.starts_at, block.ends_at),
      })

      showDowntimeModal.value = false
      showNotice(t('admin.schedule.downtimeCreated'))
    } catch (e: unknown) {
      error.value = errorMessage(e, t('admin.schedule.loadError'))
    } finally {
      loading.value = false
    }
  }

  function promptUnlockCourt(block: MaintenanceBlock) {
    blockToUnlock.value = block
    showUnlockConfirmModal.value = true
  }

  async function confirmUnlockCourt() {
    if (!blockToUnlock.value) return
    const block = blockToUnlock.value
    loading.value = true
    try {
      await adminApi.deleteBlock(block.court_id, block.id)
      maintenanceBlocks.value = maintenanceBlocks.value.filter((b) => b.id !== block.id)
      const court = courts.value.find((c) => c.id === block.court_id)
      if (court && Array.isArray((court as any).blocks)) {
        ;(court as any).blocks = (court as any).blocks.filter((b: any) => b.id !== block.id)
      }
      showUnlockConfirmModal.value = false
      blockToUnlock.value = null
    } catch (e: unknown) {
      error.value = errorMessage(e, t('admin.schedule.unlockError'))
    } finally {
      loading.value = false
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

  const filteredMaintenanceBlocks = computed(() => {
    if (selectedVenueFilter.value === 'all') return maintenanceBlocks.value
    const courtIds = new Set(filteredCourts.value.map((c) => c.id))
    return maintenanceBlocks.value.filter((b) => courtIds.has(b.court_id))
  })

  const totalVenuesCount = computed(() => venues.value.length)
  const totalCourtsCount = computed(() => courts.value.length)
  const activeCourtsCount = computed(() => courts.value.filter((c) => c.is_active).length)
  const totalBlocksCount = computed(() => maintenanceBlocks.value.length)
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
    maintenanceBlocks,
    showDowntimeModal,
    showSpecialDateModal,
    showAuditLogsModal,
    showUnlockConfirmModal,
    blockToUnlock,
    filteredCourts,
    filteredMaintenanceBlocks,
    totalVenuesCount,
    totalCourtsCount,
    activeCourtsCount,
    totalBlocksCount,
    specialDatesCount,
    loadData,
    showNotice,
    addShift,
    removeShift,
    toggleDayOpen,
    copySchedule,
    saveWeeklySchedule,
    addDowntime,
    promptUnlockCourt,
    confirmUnlockCourt,
    addSpecialDate,
    deleteSpecialDate,
  }
}
