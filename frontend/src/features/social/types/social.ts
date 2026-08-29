export interface MatchPost {
  id: number
  title: string
  venue_name?: string
  court_name?: string
  city?: string
  starts_at: string
  ends_at?: string
  joined_count: number
  required_participants: number
  visibility: 'public' | 'invite_only' | 'private' | string
  min_skill?: number
  max_skill?: number
  host_name?: string
  host_avatar?: string
  description?: string
  participants?: Array<{
    id: number
    name: string
    avatar?: string
    skill_level?: number
    is_host?: boolean
  }>
  auto_matchmaking?: boolean
}

export interface PlayerProfile {
  id: number
  user_id: number
  play_level?: string
  favorite_sports?: string[]
  reputation_score?: number
}
