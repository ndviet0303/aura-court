import { ref, onMounted } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue, CourtBlock } from '@/features/court/types/court'

export interface WeekdaySchedule {
  id: number
  dayName: string
  isOpen: boolean
  shifts: Array<{ id: number; start: string; end: string }>
}

export interface SpecialDateItem {
  id: number
  date: string
  name: string
  type: 'holiday' | 'special_hours' | 'closed'
  hours?: string
}

export interface MaintenanceBlock {
  id: number
  court_id: number
  court_name?: string
  starts_at: string
  ends_at: string
  reason: string
}

export function useAdminSchedule() {
  const loading = ref(false)
  const notice = ref('')
  const error = ref('')

  const courts = ref<Court[]>([])
  const venues = ref<Venue[]>([])
  const auditLogs = ref<any[]>([])

  const showDowntimeModal = ref(false)
  const showSpecialDateModal = ref(false)
  const showAuditLogsModal = ref(false)

  const weeklySchedule = ref<WeekdaySchedule[]>([
    { id: 1, dayName: 'Thứ 2', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
    { id: 2, dayName: 'Thứ 3', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
    { id: 3, dayName: 'Thứ 4', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
    { id: 4, dayName: 'Thứ 5', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
    { id: 5, dayName: 'Thứ 6', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '22:00' }] },
    { id: 6, dayName: 'Thứ 7', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '23:00' }] },
    { id: 7, dayName: 'Chủ Nhật', isOpen: true, shifts: [{ id: 1, start: '06:00', end: '23:00' }] },
  ])

  const specialDates = ref<SpecialDateItem[]>([])
  const maintenanceBlocks = ref<MaintenanceBlock[]>([])

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

  async function loadData() {
    loading.value = true
    error.value = ''
    try {
      const [courtsRes, venuesRes, logsRes] = await Promise.allSettled([
        adminApi.getCourts(),
        adminApi.getVenues(),
        adminApi.getAuditLogs<{ data: any[] }>(),
      ])
      if (courtsRes.status === 'fulfilled' && courtsRes.value) courts.value = courtsRes.value
      if (venuesRes.status === 'fulfilled' && venuesRes.value) venues.value = venuesRes.value
      if (logsRes.status === 'fulfilled' && logsRes.value?.data) auditLogs.value = logsRes.value.data

      // Load court maintenance blocks if courts exist
      if (courts.value.length > 0) {
        const blocksPromises = courts.value.slice(0, 5).map((c) => adminApi.getBlocks(c.id).catch(() => []))
        const blocksResults = await Promise.all(blocksPromises)
        const allBlocks: MaintenanceBlock[] = []
        blocksResults.forEach((blockList: CourtBlock[], idx) => {
          const court = courts.value[idx]
          if (Array.isArray(blockList) && court) {
            blockList.forEach((b) => {
              allBlocks.push({
                id: b.id,
                court_id: court.id,
                court_name: court.name,
                starts_at: b.starts_at,
                ends_at: b.ends_at,
                reason: b.reason || 'Bảo trì',
              })
            })
          }
        })
        maintenanceBlocks.value = allBlocks
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu lịch vận hành.')
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

  async function addDowntime(block: { court_id: number; starts_at: string; ends_at: string; reason: string }) {
    loading.value = true
    try {
      await adminApi.createBlock(block.court_id, {
        starts_at: block.starts_at,
        ends_at: block.ends_at,
        reason: block.reason,
      })
      maintenanceBlocks.value.push({
        id: Date.now(),
        court_id: block.court_id,
        court_name: courts.value.find((c) => c.id === block.court_id)?.name || 'Sân đã chọn',
        starts_at: block.starts_at,
        ends_at: block.ends_at,
        reason: block.reason,
      })
      showDowntimeModal.value = false
      showNotice('Đã lên lịch bảo trì sân thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lên lịch bảo trì.')
    } finally {
      loading.value = false
    }
  }

  function addSpecialDate(item: { date: string; name: string; type: SpecialDateItem['type']; hours?: string }) {
    specialDates.value.push({
      id: Date.now(),
      ...item,
    })
    showSpecialDateModal.value = false
    showNotice(`Đã thêm ngày đặc biệt: ${item.name}!`)
  }

  onMounted(() => {
    loadData()
  })

  return {
    loading,
    notice,
    error,
    courts,
    venues,
    auditLogs,
    weeklySchedule,
    specialDates,
    maintenanceBlocks,
    showDowntimeModal,
    showSpecialDateModal,
    showAuditLogsModal,
    addShift,
    removeShift,
    loadData,
    showNotice,
    addDowntime,
    addSpecialDate,
  }
}
