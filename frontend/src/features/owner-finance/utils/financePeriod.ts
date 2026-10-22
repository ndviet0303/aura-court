import type { FinancePeriodKind } from '../types/ownerFinance'

export type CalendarPeriodKind = Exclude<FinancePeriodKind, 'range'>

const MONTHS = { month: 1, quarter: 3, year: 12 } as const

// Plain calendar arithmetic in UTC, so the browser's time zone never shifts a Vietnam date.
const isoDate = (date: Date) => date.toISOString().slice(0, 10)
const parts = (date: string) => date.split('-').map(Number)

/** The calendar day, month, quarter or year containing `anchor` (YYYY-MM-DD), both ends inclusive, never a package term. */
export function periodRange(kind: CalendarPeriodKind, anchor: string) {
  if (kind === 'day') return { from: anchor, to: anchor }
  const [year = 1970, month = 1] = parts(anchor)
  const months = MONTHS[kind]
  const firstMonth = month - ((month - 1) % months)
  return {
    from: isoDate(new Date(Date.UTC(year, firstMonth - 1, 1))),
    // Day 0 of the month after the period is its last day: 28/29 Feb, 30 or 31.
    to: isoDate(new Date(Date.UTC(year, firstMonth - 1 + months, 0))),
  }
}

/** A date in the period before (-1) or after (+1) the one starting at `from`. */
export function shiftPeriod(kind: CalendarPeriodKind, from: string, step: 1 | -1): string {
  const [year = 1970, month = 1, day = 1] = parts(from)
  return kind === 'day'
    ? isoDate(new Date(Date.UTC(year, month - 1, day + step)))
    : isoDate(new Date(Date.UTC(year, month - 1 + step * MONTHS[kind], 1)))
}
