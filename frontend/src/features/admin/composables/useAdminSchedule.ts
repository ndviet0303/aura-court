import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { resolveLocalizedText } from '@/i18n'
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

const DEFAULT_WEEKLY_SCHEDULE: WeekdaySchedule[] = [
  { id: 1, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
  { id: 2, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
  { id: 3, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
  { id: 4, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
  { id: 5, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
  { id: 6, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '23:00' }] },
  { id: 7, isOpen: true, shifts: [{ id: 1, start: '06:00', end: '23:00' }] },
]

const DEFAULT_SPECIAL_DATES: SpecialDateItem[] = [
  {
    id: 1,
    date: '2026-09-02',
    name: { vi: 'Quốc Khánh 2/9', en: 'National Day (Sep 2)' },
    type: 'holiday',
    hours: '08:00 - 20:00',
  },
  {
    id: 2,
    date: '2026-10-10',
    name: { vi: 'Giải Đấu Mở Rộng Aura Open Cup', en: 'Aura Open Tournament' },
    type: 'special_hours',
    hours: '07:00 - 23:00',
  },
  {
    id: 3,
    date: '2026-12-25',
    name: { vi: 'Lễ Giáng Sinh', en: 'Christmas Day' },
    type: 'holiday',
    hours: '08:00 - 22:00',
  },
]

export function useAdminSchedule() {
  const { t } = useI18n()
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const auditLogs = ref<any[]>([])
  const selectedVenueFilter = ref<number | 'all'>('all')

  const showDowntimeModal = ref(false)
  const showSpecialDateModal = ref(false)
  const showAuditLogsModal = ref(false)
  const showUnlockConfirmModal = ref(false)
  const blockToUnlock = ref<MaintenanceBlock | null>(null)

  // Initialize weekly schedule from localStorage or default
  function loadSavedWeeklySchedule(): WeekdaySchedule[] {
    try {
      const saved = localStorage.getItem('aura_admin_weekly_schedule')
      if (saved) return JSON.parse(saved)
    } catch {
      // ignore
    }
    return JSON.parse(JSON.stringify(DEFAULT_WEEKLY_SCHEDULE))
  }

  // Initialize special dates from localStorage or default
  function loadSavedSpecialDates(): SpecialDateItem[] {
    try {
      const saved = localStorage.getItem('aura_admin_special_dates')
      if (saved) return JSON.parse(saved)
    } catch {
      // ignore
    }
    return JSON.parse(JSON.stringify(DEFAULT_SPECIAL_DATES))
  }

  const weeklySchedule = ref<WeekdaySchedule[]>(loadSavedWeeklySchedule())
  const specialDates = ref<SpecialDateItem[]>(loadSavedSpecialDates())
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
      if (logsRes.status === 'fulfilled' && logsRes.value) {
        const rawLogs = (logsRes.value as any)?.data || logsRes.value
        auditLogs.value = Array.isArray(rawLogs) ? rawLogs : []
      }

      // Extract court maintenance blocks from eager-loaded court.blocks
      if (courts.value.length > 0) {
        const allBlocks: MaintenanceBlock[] = []
        courts.value.forEach((court: any) => {
          const rawBlocks = court.blocks || []
          const blockList = Array.isArray(rawBlocks) ? rawBlocks : (rawBlocks.data || [])
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

  function saveWeeklySchedule(notify = true) {
    saving.value = true
    try {
      localStorage.setItem('aura_admin_weekly_schedule', JSON.stringify(weeklySchedule.value))
      if (notify) {
        showNotice(t('admin.schedule.saveNotice'))
      }
    } catch {
      error.value = t('admin.schedule.saveError')
    } finally {
      saving.value = false
    }
  }

  async function addDowntime(block: { court_id: number; starts_at: string; ends_at: string; reason: string }) {
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

  function addSpecialDate(item: { date: string; name: string; type: SpecialDateItem['type']; hours?: string }) {
    const newItem: SpecialDateItem = {
      id: Date.now(),
      ...item,
    }
    specialDates.value.unshift(newItem)
    try {
      localStorage.setItem('aura_admin_special_dates', JSON.stringify(specialDates.value))
    } catch {
      // ignore
    }
    showSpecialDateModal.value = false
    showNotice(t('admin.schedule.specialDateAdded', { name: item.name }))
  }

  function deleteSpecialDate(id: number) {
    specialDates.value = specialDates.value.filter((d) => d.id !== id)
    try {
      localStorage.setItem('aura_admin_special_dates', JSON.stringify(specialDates.value))
    } catch {
      // ignore
    }
    showNotice(t('admin.schedule.specialDateDeleted'))
  }

  // Filtered computed values
  const filteredCourts = computed(() => {
    if (selectedVenueFilter.value === 'all') return courts.value
    return courts.value.filter(
      (c) => c.venue_id === selectedVenueFilter.value || c.venue?.id === selectedVenueFilter.value
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
