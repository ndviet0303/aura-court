export type BillingCycle = 'monthly' | 'quarterly' | 'yearly'
export type PlanStatus = 'active' | 'archived' | 'draft'

export type MembershipPlan = {
  id: string | number
  name: string
  slug: string
  code: string
  description: string
  price: number
  billingCycle: BillingCycle
  version: string
  status: PlanStatus
  perks: string[]
  courtDiscountPercent: number
  pointMultiplier: number
  advanceBookingDays: number
  freeWeekendHours: number
  activeSubscribers: number
  isPopular?: boolean
  highlightColor?: string
  updatedAt: string
}

export type LoyaltyConversionConfig = {
  spendAmountVnd: number
  pointsEarned: number
  pointsRedeemed: number
  rewardValueVnd: number
  updatedAt: string
}

export type MultiplierRule = {
  id: string
  name: string
  description: string
  multiplier: number
  tag: string
  schedule: string
  isActive: boolean
}

export type PlanVersionAudit = {
  id: string
  planId: string | number
  planName: string
  version: string
  status: 'active' | 'archived'
  updatedAt: string
  updatedBy: string
  changes: string[]
  activeSubscribersCount: number
  note?: string
}

export type MembershipKpiStats = {
  totalVipMembers: number
  monthlyRecurringRevenue: number
  totalPointsIssued: number
  redemptionRatePercent: number
}
