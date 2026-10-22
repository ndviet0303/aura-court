// Venues run on Vietnam time; show API datetimes on that clock, whatever the browser's zone.
export const VENUE_TIME_ZONE = 'Asia/Ho_Chi_Minh'

const clockFormat = new Intl.DateTimeFormat('en-GB', {
  timeZone: VENUE_TIME_ZONE,
  hour: '2-digit',
  minute: '2-digit',
})
// en-CA formats dates as YYYY-MM-DD.
const dateFormat = new Intl.DateTimeFormat('en-CA', {
  timeZone: VENUE_TIME_ZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})

// "…Z" / "…+07:00" is an instant to convert; a datetime without an offset is already venue wall-clock time.
const hasOffset = (value: string) => /(Z|[+-]\d{2}:?\d{2})$/.test(value)

/** HH:mm on the venue clock. */
export function venueClock(value?: string | Date | null): string {
  if (!value) return ''
  if (typeof value === 'string' && !hasOffset(value)) return value.slice(11, 16)
  const instant = new Date(value)
  return Number.isNaN(instant.getTime()) ? '' : clockFormat.format(instant)
}

/** YYYY-MM-DD on the venue calendar. */
export function venueDate(value?: string | null): string {
  if (!value) return ''
  if (!hasOffset(value)) return value.slice(0, 10)
  const instant = new Date(value)
  return Number.isNaN(instant.getTime()) ? '' : dateFormat.format(instant)
}

/** "HH:mm - HH:mm" on the venue clock. */
export function venueClockRange(start?: string | null, end?: string | null): string {
  return `${venueClock(start)} - ${venueClock(end)}`
}

/** ISO instant of a venue wall-clock "YYYY-MM-DDTHH:mm"; Vietnam has no DST, so the offset is fixed. */
export function venueInstant(local: string): string {
  return `${local}:00+07:00`
}

/** "YYYY-MM-DD HH:mm" on the venue clock, or '' without a value. */
export function venueDateTime(value?: string | null): string {
  return value ? `${venueDate(value)} ${venueClock(value)}` : ''
}
