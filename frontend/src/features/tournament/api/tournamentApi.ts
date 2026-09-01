import { apiV2 } from '@/core/api/client'
import type { TournamentItem, TournamentRegistrationPayload } from '../types/tournament'

function mapRawTournament(t: any): TournamentItem {
  const settings = t.settings || {}
  const sport = settings.sport || t.sport || 'badminton'
  const maxSlots = settings.max_participants || t.max_slots || 16
  const currentSlots = t.participants_count ?? t.current_slots ?? 8
  const prizePool = settings.prize_pool || t.prize_pool || '15.000.000 ₫'
  const venue = t.venue?.name || t.venue || 'Aura Sport Arena'
  const city = t.venue?.city || t.city || 'Hồ Chí Minh'
  
  let formattedDates = t.dates
  if (!formattedDates) {
    if (t.starts_at) {
      const date = new Date(t.starts_at)
      formattedDates = `${date.toLocaleDateString('vi-VN')} - ${(date.getDate() + 2)}/${date.getMonth() + 1}/${date.getFullYear()}`
    } else {
      formattedDates = 'Tháng 09/2026'
    }
  }

  // Curated sports cover images
  let imageUrl = t.image_url
  if (!imageUrl) {
    if (sport === 'pickleball') {
      imageUrl = 'https://images.unsplash.com/photo-1599474924187-334a4ae5bd3c?w=800&auto=format&fit=crop&q=80'
    } else if (sport === 'tennis') {
      imageUrl = 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?w=800&auto=format&fit=crop&q=80'
    } else if (sport === 'football_5v5') {
      imageUrl = 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=800&auto=format&fit=crop&q=80'
    } else {
      imageUrl = 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=800&auto=format&fit=crop&q=80'
    }
  }

  return {
    id: t.id,
    uuid: t.uuid,
    name: t.name || 'Giải Đấu Aura Circuit 2026',
    slug: t.slug || `tournament-${t.id}`,
    category: t.category || (sport === 'pickleball' ? 'Pickleball Đôi Open' : sport === 'badminton' ? 'Cầu Lông Đơn Nam Nâng Cao' : 'Tennis Open'),
    sport,
    format: t.format || 'single_elimination',
    status: t.status || 'open',
    prize_pool: prizePool,
    dates: formattedDates,
    venue,
    city,
    court_name: t.court_name,
    current_slots: currentSlots,
    max_slots: maxSlots,
    entry_fee: t.entry_fee || '300.000 ₫ / VĐV',
    image_url: imageUrl,
    description: t.description || `Giải đấu chính thức thuộc hệ thống Aura Circuit 2026, tích lũy điểm thưởng và tranh cúp vô địch Master Cup.`,
    version: t.version || 1,
    current_match: t.current_match,
    winner_team: t.winner_team,
    fixtures: t.fixtures || [],
    participants: (t.participants || []).map((p: any) => ({
      id: p.id,
      user_id: p.user_id,
      name: p.user?.name || p.name || `VĐV #${p.id}`,
      seed: p.seed,
      status: p.status,
      checked_in: !!p.checked_in_at || p.status === 'checked_in',
      avatar: p.user?.avatar_url || p.avatar || `https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80`,
    })),
    standings: t.standings || [],
  }
}


export const tournamentApi = {
  async list<T = TournamentItem[]>(): Promise<T> {
    const res = await apiV2.get<any>('/tournaments')
    const rawList = Array.isArray(res) ? res : res?.data || []
    return rawList.map(mapRawTournament) as unknown as T
  },

  async getById<T = TournamentItem>(id: number | string): Promise<T> {
    const res = await apiV2.get<any>(`/tournaments/${id}`)
    const raw = res?.tournament || res
    return mapRawTournament(raw) as unknown as T
  },


  create: <T = TournamentItem>(payload: any) => apiV2.post<T>('/tournaments', payload),

  checkInParticipant: (participantId: number | string, payload?: TournamentRegistrationPayload) =>
    apiV2.post(`/tournament-participants/${participantId}/check-in`, payload),

  scoreFixture: (tournamentId: number | string, fixtureId: number | string, scorePayload: any) =>
    apiV2.post(`/tournaments/${tournamentId}/fixtures/${fixtureId}/score`, scorePayload),

  getEvents: (tournamentId: number | string) =>
    apiV2.get<any[]>(`/tournaments/${tournamentId}/events`),

  poll: (tournamentId: number | string) =>
    apiV2.get<any>(`/tournaments/${tournamentId}/poll`),
}

