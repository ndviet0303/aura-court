import { api } from '@/core/api/client'
import type {
  EntryPayload,
  ManagedEntry,
  MyEntry,
  PageMeta,
  PaymentInstructions,
  Sport,
  TournamentDetail,
  TournamentStatus,
  TournamentSummary,
} from '../types/tournament'

/** Public pages and the customer's own entries (FR-122..FR-125). */
export const tournamentApi = {
  list: (filters: { sport: Sport | ''; status: TournamentStatus | '' }, page: number) =>
    api.get<{ data: TournamentSummary[]; meta: PageMeta }>('/tournaments', {
      params: { sport: filters.sport || undefined, status: filters.status || undefined, page },
    }),
  show: (id: string) => api.get<TournamentDetail>(`/tournaments/${id}`),
  register: (id: string, payload: EntryPayload) =>
    api.post<{ entry: ManagedEntry }>(`/tournaments/${id}/entries`, payload),
  myEntries: () => api.get<{ data: MyEntry[] }>('/me/tournament-entries'),
  withdraw: (entryId: number) =>
    api.post<{ entry: ManagedEntry }>(`/tournament-entries/${entryId}/withdraw`),
  // Opens the payment, or returns the one still pending; it ends at the shared payment deadline.
  pay: (entryId: number) =>
    api.post<{ entry: ManagedEntry; payment: PaymentInstructions }>(
      `/tournament-entries/${entryId}/payment`,
    ),
}
