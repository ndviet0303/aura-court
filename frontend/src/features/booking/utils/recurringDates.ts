import type { SkippedDay } from '../types/recurring'

// BR-163..BR-167: the days of a recurring booking, earliest first, each once and none before today.
// Dates are venue-local YYYY-MM-DD; months are YYYY-MM.
export type PlannedDays = { dates: string[]; skipped: SkippedDay[] }

const pad = (n: number) => String(n).padStart(2, '0')
const daysIn = (year: number, month: number) => new Date(Date.UTC(year, month, 0)).getUTCDate()
const isoWeekday = (year: number, month: number, day: number) =>
  new Date(Date.UTC(year, month - 1, day)).getUTCDay() || 7
const parseMonth = (value: string) => value.split('-').map(Number) as [number, number]

function finish(dates: string[], today: string, skipped: SkippedDay[] = []): PlannedDays {
  return { dates: [...new Set(dates)].filter((date) => date >= today).sort(), skipped }
}

// BR-163: every day of the chosen month whose ISO weekday (1 = Monday … 7 = Sunday) was chosen.
export function weekdayDays(month: string, weekdays: number[], today: string): PlannedDays {
  const [year, m] = parseMonth(month)
  const dates: string[] = []
  for (let day = 1; day <= daysIn(year, m); day++) {
    if (weekdays.includes(isoWeekday(year, m, day))) dates.push(`${month}-${pad(day)}`)
  }
  return finish(dates, today)
}

// BR-164, BR-166: the chosen days of every month from `from` to `to`; a day a month lacks is skipped, never moved.
export function monthDays(from: string, to: string, days: number[], today: string): PlannedDays {
  const dates: string[] = []
  const skipped: SkippedDay[] = []
  let [year, month] = parseMonth(from)
  const [toYear, toMonth] = parseMonth(to)
  const chosen = [...new Set(days)].sort((a, b) => a - b)
  while (year * 12 + month <= toYear * 12 + toMonth) {
    const label = `${year}-${pad(month)}`
    for (const day of chosen) {
      if (day > daysIn(year, month)) skipped.push({ month: label, day })
      else dates.push(`${label}-${pad(day)}`)
    }
    if (++month > 12) [year, month] = [year + 1, 1]
  }
  return finish(dates, today, skipped)
}

// BR-165: exactly the days picked on the calendar.
export const pickedDays = (dates: string[], today: string): PlannedDays => finish(dates, today)

// BR-168: start and end on the 30-minute grid within one day, the end after the start.
export const HALF_HOURS = Array.from(
  { length: 48 },
  (_, i) => `${pad(Math.floor(i / 2))}:${i % 2 ? '30' : '00'}`,
)
export const validTimes = (start: string, end: string) =>
  HALF_HOURS.includes(start) && HALF_HOURS.includes(end) && start < end

// A venue-local day and time as the instant the booking API takes (venues run on Vietnam time).
export const venueInstant = (date: string, time: string) => `${date}T${time}:00+07:00`

export const todayInVenue = () =>
  new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh' }).format(new Date())
