import { api } from '@/core/api/client'
import type {
  AdjustmentForm,
  AttendanceAdjustment,
  AttendanceCommand,
  AttendancePayment,
  AttendanceResult,
  OperationalReservation,
  OperationsDay,
} from '../types/operations'

// Module 06 runs in the venue's organization; the API checks the role there and the venue (BR-119).
const org = (organizationId: number) => ({
  headers: { 'X-Organization-ID': String(organizationId) },
})

export const operationsApi = {
  day: (organizationId: number, venueId: number, date: string) =>
    api.get<OperationsDay>(`/operations/venues/${venueId}/day`, {
      ...org(organizationId),
      params: { date },
    }),
  inUse: (organizationId: number, venueId: number) =>
    api.get<{ reservations: OperationalReservation[] }>(
      `/operations/venues/${venueId}/in-use`,
      org(organizationId),
    ),
  show: (organizationId: number, code: string) =>
    api.get<{ reservation: OperationalReservation; payment: AttendancePayment | null }>(
      `/attendance/${encodeURIComponent(code)}`,
      org(organizationId),
    ),
  // api.post adds the Idempotency-Key that check-in and check-out are replayed by.
  command: (organizationId: number, command: AttendanceCommand, code: string) =>
    api.post<AttendanceResult>(`/attendance/${command}`, { code }, org(organizationId)),
  adjust: (organizationId: number, form: AdjustmentForm) =>
    api.post<AttendanceResult>('/attendance/adjustments', form, org(organizationId)),
  adjustments: (organizationId: number, code: string) =>
    api.get<{ data: AttendanceAdjustment[] }>(
      `/attendance/${encodeURIComponent(code)}/adjustments`,
      org(organizationId),
    ),
}
