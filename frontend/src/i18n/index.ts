import { createI18n } from 'vue-i18n'
import vi from './locales/vi'
import en from './locales/en'

export type AppLocale = 'vi' | 'en'

const STORAGE_KEY = 'aura_locale'

export function getSavedLocale(): AppLocale {
  try {
    if (typeof localStorage !== 'undefined') {
      const saved = localStorage.getItem(STORAGE_KEY)
      if (saved === 'vi' || saved === 'en') {
        return saved
      }
    }
  } catch {
    // ignore
  }
  return 'vi'
}

export const i18n = createI18n({
  legacy: false,
  locale: getSavedLocale(),
  fallbackLocale: 'vi',
  messages: {
    vi,
    en,
  },
})

export function setAppLocale(locale: AppLocale): void {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  ;(i18n.global.locale as any).value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.lang = locale
}

export function getCurrentLocale(): AppLocale {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return ((i18n.global.locale as any).value || 'vi') as AppLocale
}

export function formatCurrency(amount: number): string {
  const loc = getCurrentLocale()
  return new Intl.NumberFormat(loc === 'en' ? 'en-US' : 'vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(amount)
}

/**
 * Rút gọn số tiền:
 * 100000 -> "100k"
 * 150000 -> "150k"
 * 1500000 -> "1.5tr" (hoặc "1.5M" tiếng Anh)
 * 1000000000 -> "1 tỷ" (hoặc "1B" tiếng Anh)
 */
export function formatShortCurrency(amount: number): string {
  if (typeof amount !== 'number' || isNaN(amount)) return '0 ₫'
  const loc = getCurrentLocale()
  const isEn = loc === 'en'

  const abs = Math.abs(amount)
  const sign = amount < 0 ? '-' : ''

  if (abs >= 1_000_000_000) {
    const val = parseFloat((abs / 1_000_000_000).toFixed(2))
    return `${sign}${val} ${isEn ? 'B' : 'tỷ'}`
  }

  if (abs >= 1_000_000) {
    const val = parseFloat((abs / 1_000_000).toFixed(1))
    return `${sign}${val}${isEn ? 'M' : 'tr'}`
  }

  if (abs >= 1_000) {
    const val = parseFloat((abs / 1_000).toFixed(1))
    return `${sign}${val}k`
  }

  return formatCurrency(amount)
}

export function formatDateTime(
  dateValue: string | number | Date,
  options?: Intl.DateTimeFormatOptions,
): string {
  const loc = getCurrentLocale()
  const defaultOptions: Intl.DateTimeFormatOptions = {
    timeZone: 'Asia/Ho_Chi_Minh',
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    ...options,
  }
  return new Date(dateValue).toLocaleString(loc === 'en' ? 'en-US' : 'vi-VN', defaultOptions)
}

export function formatDate(
  dateValue: string | number | Date,
  options?: Intl.DateTimeFormatOptions,
): string {
  const loc = getCurrentLocale()
  const defaultOptions: Intl.DateTimeFormatOptions = {
    timeZone: 'Asia/Ho_Chi_Minh',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...options,
  }
  return new Date(dateValue).toLocaleDateString(loc === 'en' ? 'en-US' : 'vi-VN', defaultOptions)
}

export function formatTime(
  dateValue: string | number | Date,
  options?: Intl.DateTimeFormatOptions,
): string {
  const loc = getCurrentLocale()
  const defaultOptions: Intl.DateTimeFormatOptions = {
    timeZone: 'Asia/Ho_Chi_Minh',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    ...options,
  }
  return new Date(dateValue).toLocaleTimeString(loc === 'en' ? 'en-US' : 'vi-VN', defaultOptions)
}

export function resolveLocalizedText(value: unknown, targetLocale?: AppLocale): string {
  if (value === null || value === undefined) return ''
  const loc = targetLocale || getCurrentLocale()
  if (typeof value === 'object') {
    const obj = value as Record<string, any>
    const resolved = obj[loc] || obj['vi'] || obj['en'] || Object.values(obj)[0] || ''
    return typeof resolved === 'string' &&
      (resolved.trim().startsWith('{') || resolved.trim().startsWith('"{'))
      ? resolveLocalizedText(resolved, loc)
      : String(resolved)
  }
  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (
      (trimmed.startsWith('{') || trimmed.startsWith('"{')) &&
      (trimmed.includes('vi') || trimmed.includes('en'))
    ) {
      try {
        const unescaped =
          trimmed.startsWith('"{') && trimmed.endsWith('}"') ? JSON.parse(trimmed) : trimmed
        const parsed = typeof unescaped === 'string' ? JSON.parse(unescaped) : unescaped
        if (parsed && typeof parsed === 'object') {
          const resolved =
            parsed[loc] || parsed['vi'] || parsed['en'] || Object.values(parsed)[0] || trimmed
          return resolveLocalizedText(resolved, loc)
        }
      } catch {
        // invalid json, return original string
      }
    }
    return value
  }
  return String(value)
}

export function formatSlotRange(startsAt?: string, endsAt?: string): string {
  if (!startsAt) return ''
  const startD = new Date(startsAt)
  if (isNaN(startD.getTime())) return startsAt

  const startFormatted = formatTime(startD)
  if (!endsAt) {
    return `${startFormatted} · ${formatDate(startD)}`
  }

  const endD = new Date(endsAt)
  if (isNaN(endD.getTime())) {
    return `${startFormatted} · ${formatDate(startD)}`
  }

  const endFormatted = formatTime(endD)
  return `${startFormatted} - ${endFormatted} · ${formatDate(startD)}`
}

// Ensure initial html lang attribute is set
if (typeof document !== 'undefined') {
  document.documentElement.lang = getSavedLocale()
}
