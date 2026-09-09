import { apiV2 } from '@/core/api/client'
import type { TournamentItem, TournamentRegistrationPayload } from '../types/tournament'

function mapRawTournament(t: any): TournamentItem {
  const settings = t.settings || {}
  const sport = settings.sport || t.sport || 'badminton'
  const maxSlots = settings.max_participants || t.max_slots || t.max_participants || 16
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
      imageUrl = '/courts/pickleball-neon.jpg'
    } else if (sport === 'tennis') {
      imageUrl = '/courts/tennis-1.jpg'
    } else if (sport === 'football_5v5' || sport === 'football_7v7') {
      imageUrl = '/courts/football-mini-turf.jpg'
    } else {
      imageUrl = '/courts/badminton-0.jpg'
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
    entry_fee: settings.entry_fee || t.entry_fee || '300.000 ₫ / VĐV',
    image_url: imageUrl,
    description: t.description || `Giải đấu chính thức thuộc hệ thống Aura Circuit 2026, tích lũy điểm thưởng và tranh cúp vô địch Master Cup.`,
    version: t.version || 1,
    current_match: t.current_match,
    winner_team: t.winner_team,
    fixtures: (t.fixtures || []).map((f: any) => {
      const p1 = f.participant_one || (f.participant_one_id ? t.participants?.find((p: any) => p.id === f.participant_one_id) : null) || f.player1
      const p2 = f.participant_two || (f.participant_two_id ? t.participants?.find((p: any) => p.id === f.participant_two_id) : null) || f.player2
      const p1Name = p1?.user?.name || p1?.name || (f.participant_one_id ? `VĐV #${f.participant_one_id}` : 'TBD')
      const p2Name = p2?.user?.name || p2?.name || (f.participant_two_id ? `VĐV #${f.participant_two_id}` : 'TBD')
      
      const p1Sets = Array.isArray(f.score?.sets) ? f.score.sets.map((s: any) => s.p1) : (Array.isArray(f.player1_sets) ? f.player1_sets : (f.score?.p1 !== undefined ? [f.score.p1] : (f.player1_score !== undefined ? [f.player1_score] : [])))
      const p2Sets = Array.isArray(f.score?.sets) ? f.score.sets.map((s: any) => s.p2) : (Array.isArray(f.player2_sets) ? f.player2_sets : (f.score?.p2 !== undefined ? [f.score.p2] : (f.player2_score !== undefined ? [f.player2_score] : [])))

      return {
        id: f.id,
        round: f.round || 1,
        position: f.position || 1,
        status: f.status || 'pending',
        court_name: f.court_name,
        court_id: f.court_id,
        scheduled_time: f.scheduled_time,
        participant_one_id: f.participant_one_id || p1?.id,
        participant_two_id: f.participant_two_id || p2?.id,
        participant_one_name: p1Name,
        participant_two_name: p2Name,
        winner_id: f.winner_id,
        score: f.score,
        player1: {
          id: f.participant_one_id || p1?.id,
          name: p1Name,
          seed: p1?.seed,
          avatar: p1?.user?.avatar_url || p1?.avatar,
          sets: p1Sets,
          isWinner: !!f.winner_id && f.winner_id === (f.participant_one_id || p1?.id),
        },
        player2: {
          id: f.participant_two_id || p2?.id,
          name: p2Name,
          seed: p2?.seed,
          avatar: p2?.user?.avatar_url || p2?.avatar,
          sets: p2Sets,
          isWinner: !!f.winner_id && f.winner_id === (f.participant_two_id || p2?.id),
        },
      }
    }),
    participants: (t.participants || []).map((p: any) => ({
      id: p.id,
      user_id: p.user_id,
      name: p.user?.name || p.name || `VĐV #${p.id}`,
      seed: p.seed,
      club: p.club || 'Independent',
      level: p.level || 'Hạng B',
      phone: p.user?.phone || p.phone || '0901234567',
      category: p.category || (sport === 'pickleball' ? 'Pickleball Đôi Open' : 'Đơn Nam Nâng Cao'),
      qr_code: p.qr_code || `ATH-${p.id}`,
      status: p.status || (p.checked_in ? 'checked_in' : 'pending'),
      checked_in: !!p.checked_in_at || p.status === 'checked_in' || !!p.checked_in,
      avatar: p.user?.avatar_url || p.avatar || `https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&auto=format&fit=crop&q=80`,
    })),
    standings: t.standings || [],
  }
}

export const tournamentApi = {
  async list<T = TournamentItem[]>(params?: { sport?: string; status?: string; search?: string }): Promise<T> {
    const res = await apiV2.get<any>('/tournaments', { params })
    const rawList = Array.isArray(res) ? res : res?.data || []
    return rawList.map(mapRawTournament) as unknown as T
  },

  async getById<T = TournamentItem>(id: number | string): Promise<T> {
    const res = await apiV2.get<any>(`/tournaments/${id}`)
    const raw = res?.tournament || res?.data || res
    return mapRawTournament(raw) as unknown as T
  },

  create: <T = TournamentItem>(payload: any) => apiV2.post<T>('/tournaments', payload),

  regenerateBracket: <T = TournamentItem>(tournamentId: number | string) =>
    apiV2.post<T>(`/tournaments/${tournamentId}/regenerate`),

  checkInParticipant: (participantId: number | string, payload?: TournamentRegistrationPayload) =>
    apiV2.post(`/tournament-participants/${participantId}/check-in`, payload),

  scoreFixture: (tournamentId: number | string, fixtureId: number | string, scorePayload: any) =>
    apiV2.post(`/tournaments/${tournamentId}/fixtures/${fixtureId}/score`, scorePayload),

  register: (tournamentId: number | string, payload?: any) =>
    apiV2.post<{ participant: any; receipt: any; already_registered: boolean; message: string }>(`/tournaments/${tournamentId}/register`, payload),

  getRegistration: (tournamentId: number | string) =>
    apiV2.get<{ registered: boolean; participant?: any; receipt?: any }>(`/tournaments/${tournamentId}/registration`),

  getEvents: (tournamentId: number | string) =>
    apiV2.get<any[]>(`/tournaments/${tournamentId}/events`),

  poll: (tournamentId: number | string) =>
    apiV2.get<any>(`/tournaments/${tournamentId}/poll`),
}

