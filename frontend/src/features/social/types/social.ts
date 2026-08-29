export interface SportCategory {
  id: string
  name: string
  icon: string
  tag: string
}

export interface SkillLevel {
  level: number
  name: string
  labelVi: string
  desc: string
  badgeClass: string
}

export interface CreateMatchPayload {
  sport_type?: string
  title?: string
  venue_name?: string
  court_name?: string
  starts_date?: string
  starts_time?: string
  ends_time?: string
  starts_at?: string
  ends_at?: string
  city?: string
  skill_level?: number
  min_skill?: number
  max_skill?: number
  required_participants?: number
  visibility?: 'public' | 'invite_only'
  description?: string
  game_format?: string
  shuttlecock_type?: string
  split_bill_mode?: string
  auto_matchmaking?: boolean
  [key: string]: unknown
}

export interface MatchItem {
  id: number
  sport_type?: string
  title?: string
  venue_name?: string
  court_name?: string
  starts_date?: string
  starts_time?: string
  ends_time?: string
  skill_level?: number
  required_participants?: number
  current_participants?: number
  visibility?: 'public' | 'invite_only'
}

export interface MatchResponse {
  match?: MatchItem
  message?: string
}

export interface InvitePartnerPayload {
  invitee_id: string | number
}
