import type { Court } from '@/features/court/types/court'

export type ServiceInterval = { start: string; end: string }

/** Keyed "1" (Monday) to "7" (Sunday), as the operating-schedule API stores it. */
export type WeeklyHours = Record<string, { closed: boolean; intervals: ServiceInterval[] }>

export type OperatingSchedule = { timezone: string; weekly_hours: WeeklyHours }

export type ScheduleException = {
  id: number
  court_id: number | null
  exception_date: string
  name: string | { vi?: string; en?: string }
  type: 'holiday' | 'special_hours' | 'closed'
  intervals: ServiceInterval[] | null
}

/** GET partner/organizations/{org}/venues/{venue}/courts/{court}, with the effective schedule. */
export type CourtDetail = Court & {
  service_schedule: OperatingSchedule | null
  service_schedule_inherited: boolean
}
