import type { AvailabilitySlot } from '@/features/court/types/court'

export type { AvailabilitySlot }

export type PromotionCampaign = {
  id: number
  organization_id: number
  venue_id?: number | null
  court_id?: number | null
  name: string
  description?: string | null
  status: 'draft' | 'active' | 'suspended' | 'retired' | string
  eligibility_group?: 'all_customers' | 'first_time_customers' | 'returning_customers' | string
  starts_at?: string | null
  ends_at?: string | null
  usage_limit?: number | null
  usage_count?: number
  min_spend?: number
  can_combine_with_membership?: boolean
  version: number
}

export type Coupon = {
  id: number
  code: string
  organization_id?: number | null
  venue_id?: number | null
  court_id?: number | null
  promotion_campaign_id?: number | null
  campaign?: PromotionCampaign | null
  status?: 'draft' | 'active' | 'suspended' | 'retired' | string | null
  eligibility_group?: 'all_customers' | 'first_time_customers' | 'returning_customers' | string
  version?: number
  discount_type: 'fixed' | 'percent'
  discount_value: number
  max_discount?: number | null
  min_spend?: number | null
  usage_count?: number | null
  usage_limit?: number | null
  is_active?: boolean
  starts_at?: string | null
  ends_at?: string | null
}
