import { ref, computed } from 'vue'
import { api } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'
import type { Court, Venue } from '@/features/court/types/court'

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

  const specialDates = ref<SpecialDateItem[]>([
    { id: 1, date: '2026-09-02', name: 'Quốc Khánh 2/9', type: 'holiday', hours: '08:00 - 22:00' },
    { id: 2, date: '2026-10-20', name: 'Ngày Phụ Nữ VN', type: 'special_hours', hours: '06:00 - 23:00' },
  ])

  const maintenanceBlocks = ref<MaintenanceBlock[]>([
    { id: 101, court_id: 1, court_name: 'Sân 01 - Tiêu Chuẩn', starts_at: '2026-10-12T08:00', ends_at: '2026-10-14T17:00', reason: 'Court 4 Resurfacing' },
  ])

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
    try {
      const [courtsRes, venuesRes, logsRes] = await Promise.all([
        api.get<Court[]>('/admin/courts'),
        api.get<Venue[]>('/admin/venues'),
        api.get<{ data: any[] }>('/admin/audit-logs'),
      ])
      if (courtsRes) courts.value = courtsRes
      if (venuesRes) venues.value = venuesRes
      if (logsRes && logsRes.data) auditLogs.value = logsRes.data
    } catch (e) {
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

  function addDowntime(block: { court_id: number; starts_at: string; ends_at: string; reason: string }) {
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
  }

  function addSpecialDate(item: { date: string; name: string; type: SpecialDateItem['type']; hours?: string }) {
    specialDates.value.push({
      id: Date.now(),
      ...item,
    })
    showSpecialDateModal.value = false
    showNotice(`Đã thêm ngày đặc biệt: ${item.name}!`)
  }

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
