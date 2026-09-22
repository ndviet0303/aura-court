export type UserRole = 'customer' | 'admin' | string

export type User = {
  id: number
  name: string
  email: string
  role: UserRole
  phone?: string | null
  play_level?: string | null
  favorite_sports?: string[] | null
  avatar_url?: string | null
  onboarded?: boolean
  password_set?: boolean
  social_provider?: string | null
  email_verified_at?: string | null
  organizations?: Array<{
    id?: number
    organization_id?: number
    name?: string
    role?: string
    slug?: string
    is_active?: boolean
    status?: string
    venues?: Array<any>
    [key: string]: any
  }>
  capabilities?: string[] | Record<string, any>
}

export type AppNotification = {
  id: string
  type: string
  data: {
    event: string
    title: string
    body: string
    action_url?: string | null
  }
  read_at?: string | null
  created_at: string
}

export type MembershipPlan = {
  name: string
  description?: string
  discount_percent: number
}

export type Membership = {
  id: number
  starts_at: string
  ends_at: string
  status: string
  plan: MembershipPlan
}
