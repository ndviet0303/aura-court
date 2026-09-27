export interface PlatformPlan {
  id: number
  code: string
  name: string
  version: number
  price: number
  price_formatted: string
  currency: string
  billing_cycle: string
  trial_days: number
  trial_enabled: boolean
  quotas: { max_venues: number; max_courts: number; max_staff: number }
  features: string[]
  terms: { version: string; text: string | null }
  fee_disclosure: { tax_note: string; payment_processing_fee: string; refund_policy: string }
  status: string
  is_active: boolean
  published_at: string | null
  retired_at: string | null
}

export interface PlatformSubscription {
  id: number
  organization_id: number
  plan_id: number
  plan_name: string | null
  plan_code: string | null
  plan_version: number
  status: string
  is_valid: boolean
  is_trial: boolean
  price: number
  currency: string
  billing_cycle: string
  starts_at: string | null
  ends_at: string | null
  trial_starts_at: string | null
  trial_ends_at: string | null
  trial_days_remaining: number
  auto_renew: boolean
  accepted_terms_version: string | null
  quotas: { max_venues: number; max_courts: number; max_staff: number }
  features: string[]
}

export interface SubscriptionQuotaUsage {
  venues_count: number
  max_venues: number
  courts_count: number
  max_courts: number
  staff_count: number
  max_staff: number
}

export interface PaymentAttempt {
  id: number
  organization_id: number
  plan_id: number
  plan_version: number
  reference_code: string
  status: 'pending' | 'succeeded' | 'failed' | 'expired' | string
  amount: number
  currency: string
  provider: string
  checkout_url: string | null
  qr_code: string | null
  created_at: string
}

export interface SelectPlanPayload {
  plan_id: number
  accepted_terms_version: string
}

export interface PlanSelectionSnapshot {
  organization_id: number
  plan_id: number
  plan_name: string
  plan_version: number
  price: number
  currency: string
  billing_cycle: string
  accepted_terms_version: string
  trial_available: boolean
  trial_days: number
}

export type ActivateTrialPayload = SelectPlanPayload

export interface InitiatePaymentPayload extends SelectPlanPayload {
  return_url?: string
  cancel_url?: string
}

export type CurrentSubscriptionResponse = {
  data: PlatformSubscription | null
  has_active_subscription: boolean
  has_used_trial: boolean
  trial_eligible: boolean
  can_take_bookings: boolean
  payment_attempt: PaymentAttempt | null
  quotas: SubscriptionQuotaUsage
}
