export interface LoyaltyPolicy {
  id: number
  version: number
  points_redeemed: number
  reward_value_vnd: number
}

export interface LoyaltyPreviewResponse {
  organization_id: number
  balance: number
  policy: LoyaltyPolicy | null
  points_selected: number
  discount_vnd: number
  remaining_payable_vnd: number
  max_usable_points: number
}

export interface LoyaltyPreviewPayload {
  court_id?: number
  slots?: string[]
  quote_token?: string
  points?: number
  coupon_code?: string
  expected_policy_version?: number
}
