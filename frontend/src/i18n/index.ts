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
  (i18n.global.locale as any).value = locale
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

// Ensure initial html lang attribute is set
if (typeof document !== 'undefined') {
  document.documentElement.lang = getSavedLocale()
}
