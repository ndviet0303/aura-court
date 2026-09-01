import type { SportType } from '@/features/court/types/court'

export type SocialSportType = 'all' | SportType | 'football'

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

export interface MatchParticipant {
  id: number
  name: string
  avatar?: string
  skill_level?: number
  is_host?: boolean
  role?: string
}

export interface SocialMatch {
  id: number
  title: string
  sport_type: SocialSportType
  sport_name: string
  sport_icon: string
  venue_name: string
  court_name?: string
  city: string
  starts_at: string
  ends_at?: string
  joined_count: number
  required_participants: number
  visibility: 'public' | 'invite_only' | 'private'
  min_skill?: number
  max_skill?: number
  game_format?: string
  split_bill_mode?: string
  cost_per_person?: number
  host_id?: number
  host_name: string
  host_avatar?: string
  host_reputation?: number
  host_level?: string
  description?: string
  status: 'open' | 'full' | 'in_progress' | 'completed' | 'cancelled'
  participants: MatchParticipant[]
  is_joined?: boolean
  is_host?: boolean
}

export interface PartnerPlayer {
  id: number
  name: string
  avatar?: string
  sport_type: SocialSportType
  sport_name: string
  level_label: string
  skill_level: number
  reputation_score: number
  city: string
  favorite_venues: string[]
  bio?: string
  is_online?: boolean
  matches_played?: number
}

export interface MatchmakingStats {
  totalMatches: number
  openMatches: number
  activePlayers: number
  totalVenues: number
}

export interface CreateMatchPayload {
  sport_type?: string
  title?: string
  venue_name?: string
  court_name?: string
  court_id?: number
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
  cost_per_person?: number
  auto_matchmaking?: boolean
  availability?: Record<string, unknown>
  metadata?: Record<string, unknown>
  invited_user_ids?: number[]
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
  match?: SocialMatch | MatchItem
  message?: string
}

export interface InvitePartnerPayload {
  invitee_id: string | number
}
