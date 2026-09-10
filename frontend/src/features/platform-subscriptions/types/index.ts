export interface PlatformPlan {
  id: number
  code: string
  name: string
  description?: string | null
  price: number
  currency: string
  billing_cycle: 'monthly' | 'yearly' | string
  trial_period_days: number
  max_venues: number
  max_courts: number
  max_staff: number
  features: string[]
  is_active: boolean
}

export interface PlatformSubscription {
  id: number
  organization_id: number
  plan_id: number
  plan?: PlatformPlan
  status: 'active' | 'expired' | 'cancelled' | 'pending_payment' | string
  starts_at: string
  ends_at: string
  trial_ends_at?: string | null
  auto_renew: boolean
  cancelled_at?: string | null
  created_at?: string
  updated_at?: string
}

export interface PaymentAttempt {
  id: number
  organization_id: number
  plan_id: number
  attempt_code: string
  status: 'pending' | 'succeeded' | 'failed' | 'expired' | string
  amount: number
  currency: string
  provider: string
  checkout_url?: string
  qr_code_url?: string
  expires_at: string
  created_at: string
}

export interface SelectPlanPayload {
  plan_code: string
  terms_version?: string
}

export interface ActivateTrialPayload {
  plan_code: string
  terms_version: string
}

export interface InitiatePaymentPayload {
  plan_code: string
  terms_version: string
}

export interface ConfirmPaymentPayload {
  payment_attempt_id: number
  provider_transaction_id?: string
}
