import type { AvailabilitySlot } from '@/features/court/types/court'

/**
 * Gets the current or provided date string (YYYY-MM-DD) in the venue's local timezone (default: Asia/Ho_Chi_Minh).
 * Correctly avoids UTC date slicing boundary bugs.
 */
export function getVenueLocalDate(
  dateOrTimezone?: Date | string | number,
  timeZone?: string,
): string {
  let tz = timeZone || 'Asia/Ho_Chi_Minh'
  let dInput: Date | string | number = new Date()

  if (typeof dateOrTimezone === 'string') {
    const intlWithSupported = Intl as unknown as { supportedValuesOf?: (type: string) => string[] }
    const isSupportedIana =
      typeof intlWithSupported.supportedValuesOf === 'function' &&
      intlWithSupported.supportedValuesOf('timeZone').includes(dateOrTimezone)

    if (
      !timeZone &&
      (dateOrTimezone.includes('/') ||
        ['UTC', 'GMT'].includes(dateOrTimezone.toUpperCase()) ||
        isSupportedIana)
    ) {
      // Called as getVenueLocalDate('Asia/Tokyo') or getVenueLocalDate('UTC')
      tz = dateOrTimezone
      dInput = new Date()
    } else if (/^\d{4}-\d{2}-\d{2}$/.test(dateOrTimezone)) {
      return dateOrTimezone
    } else {
      dInput = dateOrTimezone
    }
  } else if (dateOrTimezone instanceof Date || typeof dateOrTimezone === 'number') {
    dInput = dateOrTimezone
  }

  const d = typeof dInput === 'string' || typeof dInput === 'number' ? new Date(dInput) : dInput
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(d)
}

/**
 * Formats slot range with start and end time (e.g. "19:00 – 20:00") according to locale and timezone.
 */
export function formatSlotRange(
  slot: AvailabilitySlot,
  locale = 'vi',
  timeZone = 'Asia/Ho_Chi_Minh',
): string {
  const options: Intl.DateTimeFormatOptions = {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }
  const loc = locale === 'en' ? 'en-US' : 'vi-VN'

  const startDate = new Date(slot.startsAt)
  const start = new Intl.DateTimeFormat(loc, options).format(startDate)

  const endDate = slot.endsAt
    ? new Date(slot.endsAt)
    : new Date(startDate.getTime() + 60 * 60 * 1000)
  const end = new Intl.DateTimeFormat(loc, options).format(endDate)

  return `${start} – ${end}`
}
/**
 * Resolves the dynamic availability heading in the specified locale.
 * Replaces fixed "today" headings with format:
 * - vi: "Lịch trống — {court} — {date}"
 * - en: "Availability — {court} — {date}"
 */
export function resolveAvailabilityHeading(
  courtName?: string | null,
  dateStr?: string | null,
  locale: string = 'vi',
  timeZone: string = 'Asia/Ho_Chi_Minh',
): string {
  if (!courtName) {
    return locale === 'en' ? 'Live Availability' : 'Lịch sân trống trực quan'
  }
  const effectiveDate = dateStr || getVenueLocalDate(new Date(), timeZone)
  const prefix = locale === 'en' ? 'Availability' : 'Lịch trống'
  return `${prefix} — ${courtName} — ${effectiveDate}`
}
