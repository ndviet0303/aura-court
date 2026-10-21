export type PriceTableKind = 'normal' | 'holiday'
export type PriceTableStatus = 'active' | 'retired'
/** Computed by the server: in effect now, waiting for its effective time, or replaced. */
export type PriceTableVersionState = 'active' | 'scheduled' | 'superseded'

export type PriceTableInterval = {
  /** ISO weekdays 1 = Monday … 7 = Sunday; null on holiday tables. */
  weekdays: number[] | null
  /** YYYY-MM-DD; null on normal tables. */
  dates: string[] | null
  /** HH:mm on the venue clock; an end of 23:59 means the end of the day. */
  start: string
  end: string
  price_per_hour: number
}

export type PriceTableVersion = {
  id: number
  published_at: string
  effective_at: string
  state: PriceTableVersionState
  intervals: PriceTableInterval[]
}

export type PriceTableLink = {
  court_id: number
  court_name: string
  starts_at: string
  ends_at: string | null
}

export type PriceTable = {
  id: number
  venue_id: number
  name: string
  kind: PriceTableKind
  status: PriceTableStatus
  /** Links in effect now or scheduled later. */
  links: PriceTableLink[]
}

export type PriceTableDetail = PriceTable & {
  /** Newest first. */
  versions: PriceTableVersion[]
}

export type CreatePriceTablePayload = {
  name: string
  kind: PriceTableKind
  effective_at: string
  intervals: Array<{
    weekdays?: number[]
    dates?: string[]
    start: string
    end: string
    price_per_hour: number
  }>
}

export type CreatePriceTableVersionPayload = {
  /** Omitted = now; a past time takes effect at publication. */
  effective_at?: string
  intervals: CreatePriceTablePayload['intervals']
}

export type PriceCoverageMissingItem = {
  court_id: number
  court_name: string
  weekday: number | null
  date: string | null
  start: string
  end: string
}
