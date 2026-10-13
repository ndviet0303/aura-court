import { api } from '@/core/api/client'
import { formatDate } from '@/i18n'
import type {
  TournamentCustomerOption,
  TournamentItem,
  TournamentRegistrationPayload,
  TournamentRegistrationStatus,
} from '../types/tournament'

// Backend lifecycle (draft/open/ready/in_progress/finalized) -> the card/tab vocabulary.
const STATUS_LABELS: Record<string, TournamentItem['status']> = {
  draft: 'upcoming',
  ready: 'upcoming',
  in_progress: 'ongoing',
  finalized: 'completed',
}

// settings.start_date/end_date are plain Y-m-d strings; format without Date to avoid timezone shifts.
const formatDay = (ymd: string) => ymd.slice(0, 10).split('-').reverse().join('/')

function mapRawTournament(t: any): TournamentItem {
  const settings = t.settings || {}
  const sport = settings.sport || t.sport
  const maxSlots = settings.max_participants || t.max_slots || t.max_participants
  const currentSlots = t.participants_count ?? t.current_slots ?? t.participants?.length
  const prizePool = settings.prize_pool || t.prize_pool
  const venue = settings.venue || t.venue?.name || t.venue
  const city = settings.city || t.venue?.city || t.city

  let formattedDates = t.dates
  if (!formattedDates) {
    if (settings.start_date) {
      formattedDates = settings.end_date
        ? `${formatDay(settings.start_date)} - ${formatDay(settings.end_date)}`
        : formatDay(settings.start_date)
    } else {
      // starts_at carries no end day, so show the start alone; nothing when neither is set.
      formattedDates = t.starts_at ? formatDate(t.starts_at) : ''
    }
  }

  // Curated sports cover images
  let imageUrl = settings.image_url || t.image_url
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
    name: t.name,
    slug: t.slug || `tournament-${t.id}`,
    category: settings.category || t.category,
    sport,
    format: t.format,
    status: STATUS_LABELS[t.status] || t.status,
    prize_pool: prizePool,
    dates: formattedDates,
    start_date: settings.start_date,
    end_date: settings.end_date,
    venue,
    city,
    court_name: t.court_name,
    current_slots: currentSlots,
    max_slots: maxSlots,
    entry_fee: settings.entry_fee,
    image_url: imageUrl,
    description: settings.description || t.description,
    version: t.version,
    current_match: t.current_match,
    winner_team: t.winner_team,
    fixtures: (t.fixtures || []).map((f: any) => {
      const p1 =
        f.participant_one ||
        (f.participant_one_id
          ? t.participants?.find((p: any) => p.id === f.participant_one_id)
          : null) ||
        f.player1
      const p2 =
        f.participant_two ||
        (f.participant_two_id
          ? t.participants?.find((p: any) => p.id === f.participant_two_id)
          : null) ||
        f.player2
      const p1Name =
        p1?.user?.name ||
        p1?.name ||
        (f.participant_one_id ? `VĐV #${f.participant_one_id}` : 'TBD')
      const p2Name =
        p2?.user?.name ||
        p2?.name ||
        (f.participant_two_id ? `VĐV #${f.participant_two_id}` : 'TBD')

      const p1Sets = Array.isArray(f.score?.sets)
        ? f.score.sets.map((s: any) => s.p1)
        : Array.isArray(f.player1_sets)
          ? f.player1_sets
          : f.score?.p1 !== undefined
            ? [f.score.p1]
            : f.player1_score !== undefined
              ? [f.player1_score]
              : []
      const p2Sets = Array.isArray(f.score?.sets)
        ? f.score.sets.map((s: any) => s.p2)
        : Array.isArray(f.player2_sets)
          ? f.player2_sets
          : f.score?.p2 !== undefined
            ? [f.score.p2]
            : f.player2_score !== undefined
              ? [f.player2_score]
              : []

      return {
        id: f.id,
        round: f.round,
        position: f.position,
        status: f.status,
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
      club: p.club,
      level: p.level,
      phone: p.user?.phone || p.phone,
      category: p.category,
      qr_code: p.qr_code || `ATH-${p.id}`,
      status: p.status || (p.checked_in ? 'checked_in' : 'pending'),
      checked_in: !!p.checked_in_at || p.status === 'checked_in' || !!p.checked_in,
      avatar: p.user?.avatar_url || p.avatar,
    })),
    standings: t.standings || [],
  }
}

export const tournamentApi = {
  async list<T = TournamentItem[]>(params?: {
    sport?: string
    status?: string
    search?: string
  }): Promise<T> {
    const res = await api.get<any>('/tournaments', { params })
    const rawList = Array.isArray(res) ? res : res?.data || []
    return rawList.map(mapRawTournament) as unknown as T
  },

  async getById<T = TournamentItem>(id: number | string): Promise<T> {
    const res = await api.get<any>(`/tournaments/${id}`)
    const raw = res?.tournament || res?.data || res
    return mapRawTournament(raw) as unknown as T
  },

  create: <T = TournamentItem>(payload: any) => api.post<T>('/tournaments', payload),

  regenerateBracket: <T = TournamentItem>(tournamentId: number | string) =>
    api.post<T>(`/tournaments/${tournamentId}/regenerate`),

  checkInParticipant: (participantId: number | string, payload?: TournamentRegistrationPayload) =>
    api.post(`/tournament-participants/${participantId}/check-in`, payload),

  scoreFixture: (tournamentId: number | string, fixtureId: number | string, scorePayload: any) =>
    api.post(`/tournaments/${tournamentId}/fixtures/${fixtureId}/score`, scorePayload),

  // A paid tournament answers 202 with `payment`; the player is registered once the owner receipt lands.
  register: (tournamentId: number | string, payload?: TournamentRegistrationPayload) =>
    api.post<TournamentRegistrationStatus & { message: string; already_registered: boolean }>(
      `/tournaments/${tournamentId}/register`,
      payload,
    ),

  getRegistration: (tournamentId: number | string) =>
    api.get<TournamentRegistrationStatus>(`/tournaments/${tournamentId}/registration`),

  addParticipant: (
    tournamentId: number | string,
    payload: { user_id: number; waiver_reason?: string },
  ) => api.post(`/tournaments/${tournamentId}/participants`, payload),

  async searchCustomers(q: string): Promise<TournamentCustomerOption[]> {
    const res = await api.get<unknown>(`/customers?q=${encodeURIComponent(q.trim())}`)
    const list = (
      Array.isArray(res) ? res : ((res as { customers?: unknown[] })?.customers ?? [])
    ) as TournamentCustomerOption[]
    return list.filter((c) => c.user_id)
  },

  getEvents: (tournamentId: number | string) =>
    api.get<any[]>(`/tournaments/${tournamentId}/events`),

  poll: (tournamentId: number | string) => api.get<any>(`/tournaments/${tournamentId}/poll`),
}
