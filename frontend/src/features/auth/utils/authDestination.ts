import type { UserRole } from '@/core/types/common'
import { isPartnerApplicationUnderReview } from '@/core/utils/partnerApplicationStatus'

export interface MinimalUser {
  role?: UserRole | string
  onboarded?: boolean
  organizations?: Array<{
    organization_id?: number
    id?: number
    role?: string
    status?: string
    [key: string]: unknown
  }>
  capabilities?:
    | {
        is_platform_admin?: boolean
        can_manage_venues?: boolean
        active_organization_id?: number | null
        [key: string]: unknown
      }
    | string[]
    | Record<string, unknown>
    | unknown
  [key: string]: unknown
}

/**
 * Validates whether a redirect URL candidate is safe, internal, non-circular,
 * and authorized for the user's role.
 */
export function isSafeInternalRedirect(
  url: string | null | undefined,
  userOrRole?: string | MinimalUser,
): boolean {
  if (!url || typeof url !== 'string') return false
  const trimmed = url.trim()

  // Must be an internal path starting with a single '/'
  if (
    !trimmed.startsWith('/') ||
    trimmed.startsWith('//') ||
    trimmed.startsWith('/\\') ||
    trimmed.startsWith('\\')
  ) {
    return false
  }

  // Reject protocol-relative or scheme-like content (e.g. /http:/... or /javascript:...)
  if (/^\/[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed) || trimmed.includes('://')) {
    return false
  }

  // Parse path only (ignore query and hash for path-based rule checking)
  const pathOnly = trimmed.split('?')[0]?.split('#')[0] || ''

  // Reject circular guest-only authentication pages
  if (pathOnly === '/login' || pathOnly === '/register') {
    return false
  }

  // Check user role and organization-scoped permissions
  const userObj = typeof userOrRole === 'object' && userOrRole !== null ? userOrRole : null
  const roleStr = typeof userOrRole === 'string' ? userOrRole : userObj?.role
  const userCaps =
    typeof userObj?.capabilities === 'object' &&
    userObj?.capabilities !== null &&
    !Array.isArray(userObj.capabilities)
      ? (userObj.capabilities as Record<string, unknown>)
      : null
  const isPlatformAdmin = roleStr === 'admin' || userCaps?.is_platform_admin === true
  const activeOrgs = userObj?.organizations?.filter((o) => o.status === 'active') || []
  // A platform admin operates an organization only through an owner or staff membership.
  const isOrgOwner =
    roleStr === 'organization_owner' ||
    activeOrgs.some((o) => o.role === 'organization_owner') ||
    userCaps?.can_manage_venues === true
  const hasPosAccess =
    isOrgOwner || roleStr === 'staff' || activeOrgs.some((o) => o.role === 'staff')

  // POS route access
  if (
    pathOnly === '/pos' ||
    pathOnly === '/admin/pos' ||
    pathOnly.startsWith('/pos/') ||
    pathOnly.startsWith('/admin/pos/')
  ) {
    return hasPosAccess
  }

  // Organization management route access
  if (pathOnly === '/admin/organization' || pathOnly.startsWith('/admin/organization/')) {
    return isOrgOwner
  }
  // Reject all other platform admin paths for non-platform-admin users
  if (pathOnly.startsWith('/admin') && !isPlatformAdmin) {
    return false
  }

  return true
}

/**
 * Resolves destination rule for authenticated visitors:
 * 1. Incomplete onboarding has priority -> '/onboarding'
 * 2. Authorized internal redirect query parameter (if safe, non-circular, and authorized)
 * 3. Role-appropriate default:
 *    - Platform admin -> '/admin'; organization owner -> '/admin/organization'
 *    - Customer/other -> '/'
 */
export function resolveAuthDestination(
  user: MinimalUser | null | undefined,
  redirectCandidate?: string | null,
): string | null {
  if (!user) return null

  // 1. Incomplete onboarding has highest priority
  if (!user.onboarded) {
    return '/onboarding'
  }

  // 2. Safe & authorized internal redirect (excluding '/' which defers to role defaults for admin/tenant)
  if (isSafeInternalRedirect(redirectCandidate, user)) {
    const candidate = redirectCandidate!.trim()
    const pathOnly = candidate.split('?')[0]?.split('#')[0] || ''

    // If target is '/onboarding' but user is already onboarded, ignore it
    if (pathOnly !== '/onboarding') {
      // If candidate is root '/', defer to role-appropriate defaults
      if (candidate === '/') {
        return getDefaultDestination(user)
      }
      return candidate
    }
  }

  // 3. Existing role-appropriate default
  return getDefaultDestination(user)
}

function getDefaultDestination(user: MinimalUser): string {
  const userCaps =
    typeof user.capabilities === 'object' &&
    user.capabilities !== null &&
    !Array.isArray(user.capabilities)
      ? (user.capabilities as Record<string, unknown>)
      : null
  if (user.role === 'admin' || userCaps?.is_platform_admin === true) {
    return '/admin'
  }
  const activeOrgs = user.organizations?.filter((o) => o.status === 'active') || []
  const isOrgOwner =
    user.role === 'organization_owner' ||
    activeOrgs.some((o) => o.role === 'organization_owner') ||
    userCaps?.can_manage_venues === true
  if (isOrgOwner) {
    return '/admin/organization'
  }
  const hasPosAccess = user.role === 'staff' || activeOrgs.some((o) => o.role === 'staff')
  if (hasPosAccess) {
    return '/pos'
  }
  if (userCaps?.has_partner_application === true) {
    if (isPartnerApplicationUnderReview(userCaps?.partner_application_status)) {
      return '/partner/application-status'
    }
    return '/partner-registration'
  }
  if (
    typeof window !== 'undefined' &&
    localStorage.getItem('aura_partner_intent') === 'court_owner'
  ) {
    return '/partner-registration'
  }
  return '/'
}
