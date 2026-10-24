import { api } from '@/core/api/client'
import type { Venue } from '@/features/court/types/court'
import type {
  Allocation,
  EntryPayload,
  Fixture,
  FixtureChange,
  Grant,
  InternalSetupPayload,
  ManagedEntry,
  ManagedSummary,
  ManagedTournamentDetail,
  PublicSetupPayload,
  ResultPayload,
  RosterPreview,
  Standings,
  TournamentDetailInfo,
  TournamentSetup,
} from '../types/tournament'

type TournamentResult = { tournament: TournamentDetailInfo }
const base = (id: string) => `/managed/tournaments/${id}`
const fileForm = (file: File) => {
  const form = new FormData()
  form.append('file', file)
  return form
}

/** Owner, official and representative screens; the API checks the role on every call (BR-140). */
export const tournamentManageApi = {
  list: () => api.get<{ data: ManagedSummary[] }>('/managed/tournaments'),
  // The venues the owner runs, for a new public tournament; the API rejects a venue the caller does not own.
  venues: () => api.get<Venue[]>('/admin/venues'),
  show: (id: string) => api.get<ManagedTournamentDetail>(base(id)),
  createPublic: (payload: PublicSetupPayload) =>
    api.post<TournamentResult>('/managed/tournaments', payload),
  createInternal: (payload: InternalSetupPayload) =>
    api.post<TournamentResult>('/managed/internal-tournaments', payload),
  update: (id: string, payload: TournamentSetup) => api.put<TournamentResult>(base(id), payload),
  publish: (id: string) => api.post<TournamentResult>(`${base(id)}/publish`),
  stop: (id: string, reason: string) =>
    api.post<TournamentResult>(`${base(id)}/stop`, { reason: reason || undefined }),
  lockRoster: (id: string) => api.post<TournamentResult>(`${base(id)}/lock-roster`),
  draw: (id: string) =>
    api.post<TournamentResult & { fixtures: Fixture[]; standings: Standings }>(`${base(id)}/draw`),

  allocate: (id: string, body: { court_id: number; starts_at: string; ends_at: string }) =>
    api.post<{ allocation: Allocation }>(`${base(id)}/allocations`, body),
  release: (id: string, allocationId: number) =>
    api.delete<void>(`${base(id)}/allocations/${allocationId}`),
  grant: (id: string, body: { role: Grant['role']; email: string }) =>
    api.post<{ grant: Grant }>(`${base(id)}/grants`, body),
  revoke: (id: string, grantId: number) => api.delete<void>(`${base(id)}/grants/${grantId}`),

  approve: (entryId: number) =>
    api.post<{ entry: ManagedEntry }>(`/tournament-entries/${entryId}/approve`),
  reject: (entryId: number, reason: string) =>
    api.post<{ entry: ManagedEntry }>(`/tournament-entries/${entryId}/reject`, { reason }),
  removeEntry: (entryId: number) => api.delete<void>(`/tournament-entries/${entryId}`),

  // D24: Excel stays on the server; the browser only downloads the template and uploads the file.
  downloadTemplate: (id: string) =>
    api.download(`${base(id)}/roster/template`, 'danh-sach-giai-dau.xlsx'),
  previewRoster: (id: string, file: File) =>
    api.post<RosterPreview>(`${base(id)}/roster/preview`, fileForm(file)),
  confirmRoster: (id: string, file: File) =>
    api.post<{ entries: ManagedEntry[] }>(`${base(id)}/roster/confirm`, fileForm(file)),
  addRosterEntry: (id: string, payload: EntryPayload) =>
    api.post<{ entry: ManagedEntry }>(`${base(id)}/roster/entries`, payload),

  schedule: (fixtureId: number, body: { court_id: number; starts_at: string; ends_at: string }) =>
    api.put<{ fixture: Fixture }>(`/tournament-fixtures/${fixtureId}/schedule`, body),
  result: (fixtureId: number, body: ResultPayload) =>
    api.post<FixtureChange>(
      `/tournament-fixtures/${fixtureId}/${'absent_entry_id' in body ? 'walkover' : 'result'}`,
      body,
    ),
  correct: (fixtureId: number, body: ResultPayload & { reason: string }) =>
    api.post<FixtureChange>(`/tournament-fixtures/${fixtureId}/correction`, body),
}
