import type { Venue, SportZone, Court } from '@/features/court/types/court'

export type PriceRuleType =
  | 'base'
  | 'standard'
  | 'weekly'
  | 'peak'
  | 'holiday'
  | 'date_exception'
  | 'surge'
  | 'special_event'
  | 'bad_weather'

export type AdjustmentType = 'fixed' | 'percentage_add' | 'percentage_discount'

export interface PriceRuleItem {
  id: number
  organization_id?: number
  venue_id?: number | null
  sport_zone_id?: number | null
  court_id?: number | null
  name?: string
  type: PriceRuleType
  weekday?: number | null // 1 = Mon, 7 = Sun
  exception_date?: string | null // YYYY-MM-DD
  starts_at?: string | null // HH:mm
  ends_at?: string | null // HH:mm
  price: number // VND
  adjustment_type?: AdjustmentType
  adjustment_value?: number
  priority: number
  is_active: boolean
  version: number
  metadata?: Record<string, any> | null
  created_at?: string
  updated_at?: string
  venue?: Venue | null
  sport_zone?: SportZone | null
  sportZone?: SportZone | null
  court?: Court | null
}

export interface PriceRuleFormPayload {
  name?: string
  venue_id?: number | null
  sport_zone_id?: number | null
  court_id?: number | null
  type: PriceRuleType
  weekday?: number | null
  exception_date?: string | null
  starts_at?: string | null
  ends_at?: string | null
  price: number
  priority?: number
  is_active?: boolean
  metadata?: Record<string, any> | null
}

export interface PriceQuoteSlotItem {
  court_id: number
  starts_at: string
  ends_at: string
  unit_price: number
  rule?: {
    id: number | string
    type: string
    version: number
    price: number
  }
}

export interface PriceQuoteResponse {
  id: number
  court_id: number
  quote_token: string
  status: string
  currency: string
  subtotal: number
  total_amount: number
  rule_version: number
  slot_snapshot: PriceQuoteSlotItem[]
  applied_rule_ids: number[]
  expires_at: string
  court?: Court
}

export interface PricingPresetTemplate {
  id: string
  nameKey: string
  descKey: string
  badgeKey: string
  icon: string
  rules: Array<{
    name: string
    type: PriceRuleType
    weekday?: number | null
    starts_at?: string | null
    ends_at?: string | null
    priceMultiplier: number // multiplier relative to base court price or fixed price
    fixedPrice?: number
    priority: number
  }>
}
