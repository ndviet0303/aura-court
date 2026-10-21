/** The four package terms in days (D3); every term grants the same functions. */
export type PackageTermDays = 30 | 90 | 180 | 360

export const PACKAGE_TERMS: readonly PackageTermDays[] = [30, 90, 180, 360]

/** A package price in whole VND, effective from a UTC instant. */
export type PackagePrice = {
  id: number
  term_days: PackageTermDays
  amount: number
  effective_at: string
}

/** One term as platform admins see it: its applicable price and its next scheduled price. */
export type PackageTermPrice = {
  term_days: PackageTermDays
  days: number
  current_price: PackagePrice | null
  next_price: PackagePrice | null
}

export type SchedulePackagePricePayload = {
  term_days: number
  amount: number | null
  /** UTC ISO instant; left out, the price takes effect now. */
  effective_at?: string
}
