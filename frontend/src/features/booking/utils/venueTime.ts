const VENUE_TIME_ZONE = 'Asia/Ho_Chi_Minh'

// API datetimes arrive as UTC ISO strings (…Z); a string without an offset is already venue wall-clock time.
const hasOffset = (value: string) => /(Z|[+-]\d{2}:?\d{2})$/.test(value)

/** HH:mm in venue time. */
export function venueClock(value?: string | null): string {
  if (!value) return ''
  if (!hasOffset(value)) return value.slice(11, 16)
  return new Date(value).toLocaleTimeString('en-GB', {
    timeZone: VENUE_TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** YYYY-MM-DD in venue time. */
export function venueDate(value?: string | null): string {
  if (!value) return ''
  if (!hasOffset(value)) return value.slice(0, 10)
  return new Date(value).toLocaleDateString('en-CA', { timeZone: VENUE_TIME_ZONE })
}
