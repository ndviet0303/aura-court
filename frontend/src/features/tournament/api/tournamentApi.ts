import { apiV2 } from '@/core/api/client'
import type { TournamentItem, Fixture } from '../types/tournament'

export const tournamentApi = {
  list: () => apiV2.get<TournamentItem[]>('/tournaments'),

  getById: (id: number) => apiV2.get<TournamentItem>(`/tournaments/${id}`),

  create: (payload: any) => apiV2.post<TournamentItem>('/tournaments', payload),

  checkInParticipant: (participantId: number) =>
    apiV2.post(`/tournament-participants/${participantId}/check-in`),

  scoreFixture: (tournamentId: number, fixtureId: number, scorePayload: any) =>
    apiV2.post(`/tournaments/${tournamentId}/fixtures/${fixtureId}/score`, scorePayload),

  getEvents: (tournamentId: number) =>
    apiV2.get<any[]>(`/tournaments/${tournamentId}/events`),

  poll: (tournamentId: number) =>
    apiV2.get<any>(`/tournaments/${tournamentId}/poll`),
}
