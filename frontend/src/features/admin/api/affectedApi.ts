import { api } from '@/core/api/client'
import type { Reservation } from '@/features/booking/types/reservation'
import type {
  AffectedCase,
  AffectedCaseFilters,
  AffectedCasePage,
  CaseOutcomeForm,
  OperatorCommand,
  OperatorCommandForm,
} from '../types/affected'

/** Design D7 handling cases and the D8 operator commands. */
export const affectedApi = {
  listCases: (filters: AffectedCaseFilters, page = 1) =>
    api.get<AffectedCasePage>('/admin/affected-reservations', {
      params: {
        ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
        page,
      },
    }),
  recordOutcome: (caseId: number, payload: CaseOutcomeForm) =>
    api.patch<{ data: AffectedCase }>(`/admin/affected-reservations/${caseId}`, payload),
  // The booking's organization is the tenant; without the header a multi-organization user hits their first one.
  runCommand: (
    organizationId: number | null,
    code: string,
    command: OperatorCommand,
    payload: OperatorCommandForm,
  ) =>
    api.post<{ reservation: Reservation }>(
      `/reservations/${encodeURIComponent(code)}/${command}`,
      payload,
      organizationId ? { headers: { 'X-Organization-ID': String(organizationId) } } : undefined,
    ),
}
