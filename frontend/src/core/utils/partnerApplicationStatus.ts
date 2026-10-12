// host_kycs.status values the applicant sees as "under review": flagged is an internal
// escalation of a pending application; pending_review is the partner feature's alias.
const UNDER_REVIEW_STATUSES: readonly string[] = ['pending', 'pending_review', 'flagged']

export function isPartnerApplicationUnderReview(status: unknown): boolean {
  return typeof status === 'string' && UNDER_REVIEW_STATUSES.includes(status)
}
