import { apiV2 } from '@/core/api/client'
import type { TournamentItem, Fixture } from '../types/tournament'

export const tournamentApi = {
  list: <T = TournamentItem[]>() => apiV2.get<T>('/tournaments'),

  getById: <T = TournamentItem>(id: number | string) => apiV2.get<T>(`/tournaments/${id}`),

  create: <T = TournamentItem>(payload: any) => apiV2.post<T>('/tournaments', payload),

  checkInParticipant: (participantId: number | string, payload?: any) =>
    apiV2.post(`/tournament-participants/${participantId}/check-in`, payload),

  scoreFixture: (tournamentId: number | string, fixtureId: number | string, scorePayload: any) =>
    apiV2.post(`/tournaments/${tournamentId}/fixtures/${fixtureId}/score`, scorePayload),

  getEvents: (tournamentId: number | string) =>
    apiV2.get<any[]>(`/tournaments/${tournamentId}/events`),

  poll: (tournamentId: number | string) =>
    apiV2.get<any>(`/tournaments/${tournamentId}/poll`),
}
