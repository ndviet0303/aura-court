import type { AvailabilitySlot, Court } from '@/features/court/types/court'

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

/** "Thứ Năm, 15/10/2026" for a venue-local YYYY-MM-DD, capitalised for a heading. */
export function formatAvailabilityDate(date: string, locale: string): string {
  // Format it as a UTC midnight so the calendar day never shifts with the browser's timezone.
  const label = new Intl.DateTimeFormat(locale === 'en' ? 'en-US' : 'vi-VN', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(`${date}T00:00:00Z`))
  return label.charAt(0).toUpperCase() + label.slice(1)
}

/** Material icon for a court chip, from its sport zone or, failing that, its name. */
export function getCourtIcon(court: Court): string {
  const sport = (court.sport_zone?.sport_type || '').toLowerCase()
  if (sport === 'football_5v5' || sport === 'football_7v7' || sport === 'football')
    return 'sports_soccer'
  if (sport === 'basketball') return 'sports_basketball'
  if (sport === 'volleyball') return 'sports_volleyball'
  if (sport === 'table_tennis') return 'table_restaurant'
  if (sport === 'tennis' || sport === 'pickleball' || sport === 'badminton') return 'sports_tennis'

  const name = (court.name || '').toLowerCase()
  if (name.includes('bóng đá') || name.includes('football')) return 'sports_soccer'
  if (name.includes('bóng rổ') || name.includes('basketball') || name.includes('nba'))
    return 'sports_basketball'
  if (name.includes('bóng chuyền') || name.includes('volleyball')) return 'sports_volleyball'
  if (name.includes('bóng bàn') || name.includes('table tennis') || name.includes('stiga'))
    return 'table_restaurant'
  return 'sports_tennis'
}
