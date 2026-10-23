import type { OperatingStatus, SportType, VenueSuspension } from '@/features/court/types/court'
import type { AffectedBooking } from './affected'
import type { WeeklyHours } from './venueDetail'

type LocalizedName = string | Record<string, string>
type Actor = { id: number; name: string } | null

/** State of a remediation request: the state of its latest submission (design D14). */
export const REMEDIATION_STATUSES = [
  'pending',
  'more_info_requested',
  'rejected',
  'approved',
] as const
export type RemediationStatus = (typeof REMEDIATION_STATUSES)[number]

export type RemediationDecision = 'request_more_info' | 'reject' | 'approve'

export type RemediationFile = {
  name: string
  mime_type: string
  size: number
  /** Time-limited link; reload the page for a fresh one. */
  url: string
}

/** SuspensionRemediationResource. */
export type Remediation = {
  id: number
  venue_suspension_id: number
  status: RemediationStatus
  content: string
  files: RemediationFile[]
  submitted_by?: Actor
  submitted_at: string
  reviewed_by?: Actor
  reviewed_at: string | null
  review_note: string | null
  submission_count?: number
  suspension?: Suspension
}

/** VenueSuspensionResource. */
export type Suspension = {
  id: number
  venue_id: number
  venue?: {
    id: number
    name: LocalizedName
    organization_id: number
    organization: { id: number; name: string } | null
  }
  reason: string
  is_active: boolean
  suspended_at: string
  suspended_by?: Actor
  lifted_at: string | null
  lifted_by?: Actor
  conclusion: string | null
  remediation_status: RemediationStatus | null
  /** Oldest first. */
  remediations?: Remediation[]
}

/** The platform admin's suspension preview: no customer contacts (BR-38). */
export type SuspensionPreview = {
  venue_id: number
  court_count: number
  already_suspended: boolean
  bookings: AffectedBooking[]
}

/** MonitoredVenueResource (list). */
export type MonitoredVenue = {
  id: number
  name: LocalizedName
  organization: { id: number; name: string } | null
  address: LocalizedName | null
  phone: string | null
  email: string | null
  status: OperatingStatus
  courts_count?: number
  active_suspension: VenueSuspension | null
}

/** MonitoredVenueResource (detail). */
export type MonitoredVenueDetail = MonitoredVenue & {
  owners: Array<{ id: number; name: string; email: string | null; phone: string | null }>
  operating_hours: WeeklyHours | null
  courts: Array<{
    id: number
    code: string
    sport_type: SportType
    description: string | null
    images: string[]
    status: OperatingStatus
    schedule_inherited: boolean
    service_schedule: WeeklyHours | null
  }>
  suspensions: Suspension[]
}

/** An empty value means "not filtered". */
export type MonitoringFilters = {
  organization_id: number | ''
  status: OperatingStatus | ''
  suspended: '0' | '1' | ''
  keyword: string
}

export type Page<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}
