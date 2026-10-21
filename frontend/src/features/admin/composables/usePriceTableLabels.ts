import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type {
  PriceCoverageMissingItem,
  PriceTableInterval,
  PriceTableKind,
  PriceTableStatus,
} from '../types/priceTable'

export const ISO_WEEKDAYS = [1, 2, 3, 4, 5, 6, 7]

/** Locale-aware labels for price table days, times and money. */
export function usePriceTableLabels() {
  const { t, d, locale } = useI18n()

  // 1 January 2024 is a Monday, so ISO weekday n falls on 2024-01-0n.
  const weekday = (n: number, style: 'long' | 'short' = 'long') =>
    new Intl.DateTimeFormat(locale.value, { weekday: style, timeZone: 'UTC' }).format(
      new Date(Date.UTC(2024, 0, n)),
    )
  const calendarDate = (date: string) => d(new Date(`${date}T00:00:00+07:00`), 'date')
  const instant = (iso: string) => d(new Date(iso), 'datetime')
  const kind = (value: PriceTableKind) =>
    t(value === 'holiday' ? 'admin.priceTables.kindHoliday' : 'admin.priceTables.kindNormal')
  const status = (value: PriceTableStatus) =>
    t(value === 'retired' ? 'admin.priceTables.statusRetired' : 'admin.priceTables.statusActive')
  const days = (interval: PriceTableInterval) =>
    interval.weekdays
      ? interval.weekdays.map((n) => weekday(n, 'short')).join(', ')
      : (interval.dates ?? []).map(calendarDate).join(', ')
  const missingItem = (item: PriceCoverageMissingItem) =>
    t('admin.priceTables.coverage.item', {
      court: item.court_name,
      day: item.weekday ? weekday(item.weekday) : calendarDate(item.date ?? ''),
      start: item.start,
      end: item.end,
    })

  return { weekday, calendarDate, instant, kind, status, days, missingItem, money: formatCurrency }
}
