import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { isSafeInternalRedirect, resolveAuthDestination } from '../utils/authDestination'
import router, { reloadOnStaleChunk } from '@/router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '../api/authApi'

describe('Guest Navigation and Auth Destination Rules', () => {
  describe('isSafeInternalRedirect', () => {
    it('rejects null, undefined, or non-string inputs', () => {
      expect(isSafeInternalRedirect(null)).toBe(false)
      expect(isSafeInternalRedirect(undefined)).toBe(false)
      expect(isSafeInternalRedirect('')).toBe(false)
      expect(isSafeInternalRedirect('   ')).toBe(false)
    })

    it('rejects external and protocol-relative URLs', () => {
      expect(isSafeInternalRedirect('https://example.com')).toBe(false)
      expect(isSafeInternalRedirect('http://attacker.com/malicious')).toBe(false)
      expect(isSafeInternalRedirect('//attacker.com/phishing')).toBe(false)
      expect(isSafeInternalRedirect('/\\attacker.com')).toBe(false)
      expect(isSafeInternalRedirect('\\\\attacker.com')).toBe(false)
      expect(isSafeInternalRedirect('javascript:alert(1)')).toBe(false)
      expect(isSafeInternalRedirect('/http:/attacker.com')).toBe(false)
    })

    it('rejects circular auth URLs (login / register)', () => {
      expect(isSafeInternalRedirect('/login')).toBe(false)
      expect(isSafeInternalRedirect('/register')).toBe(false)
      expect(isSafeInternalRedirect('/login?redirect=/admin')).toBe(false)
      expect(isSafeInternalRedirect('/register?mode=register')).toBe(false)
    })

    it('rejects admin paths for non-admin users', () => {
      expect(isSafeInternalRedirect('/admin', 'customer')).toBe(false)
      expect(isSafeInternalRedirect('/admin/manage', 'customer')).toBe(false)
      expect(isSafeInternalRedirect('/admin/venues', 'customer')).toBe(false)
      expect(isSafeInternalRedirect('/admin/pos', undefined)).toBe(false)

      // Scoped tenant roles cannot access platform admin paths like /admin/venues
      expect(isSafeInternalRedirect('/admin/venues', 'organization_owner')).toBe(false)
      expect(isSafeInternalRedirect('/admin/venues', 'cashier')).toBe(false)
      expect(isSafeInternalRedirect('/admin/venues', 'staff')).toBe(false)
      expect(isSafeInternalRedirect('/admin/organization', 'cashier')).toBe(false)
      expect(isSafeInternalRedirect('/admin/organization', 'staff')).toBe(false)
    })

    it('allows admin paths for admin users', () => {
      expect(isSafeInternalRedirect('/admin', 'admin')).toBe(true)
      expect(isSafeInternalRedirect('/admin/manage', 'admin')).toBe(true)
      expect(isSafeInternalRedirect('/admin/venues?filter=open', 'admin')).toBe(true)
      expect(isSafeInternalRedirect('/admin/organization', 'admin')).toBe(true)
      expect(isSafeInternalRedirect('/admin/pos', 'admin')).toBe(true)
    })

    it('allows safe internal application paths', () => {
      expect(isSafeInternalRedirect('/courts')).toBe(true)
      expect(isSafeInternalRedirect('/courts/court-a/schedule')).toBe(true)
      expect(isSafeInternalRedirect('/booking')).toBe(true)
      expect(isSafeInternalRedirect('/booking?date=2026-09-22&court=1')).toBe(true)
      expect(isSafeInternalRedirect('/wallet')).toBe(true)

      // Scoped tenant roles admit /pos and /admin/organization
      expect(isSafeInternalRedirect('/pos', 'organization_owner')).toBe(true)
      expect(isSafeInternalRedirect('/admin/organization', 'organization_owner')).toBe(true)
      expect(isSafeInternalRedirect('/pos', 'cashier')).toBe(true)
      expect(isSafeInternalRedirect('/admin/pos', 'cashier')).toBe(true)
      expect(isSafeInternalRedirect('/pos', 'staff')).toBe(true)
      expect(isSafeInternalRedirect('/admin/pos', 'staff')).toBe(true)
    })
  })

  describe('resolveAuthDestination', () => {
    it('returns null for anonymous or unauthenticated users', () => {
      expect(resolveAuthDestination(null)).toBeNull()
      expect(resolveAuthDestination(undefined)).toBeNull()
    })

    it('redirects to onboarding first when user has not completed onboarding', () => {
      const incompleteCustomer = { role: 'customer', onboarded: false }
      const incompleteAdmin = { role: 'admin', onboarded: false }

      expect(resolveAuthDestination(incompleteCustomer)).toBe('/onboarding')
      expect(resolveAuthDestination(incompleteCustomer, '/courts')).toBe('/onboarding')
      expect(resolveAuthDestination(incompleteCustomer, '/admin/manage')).toBe('/onboarding')
      expect(resolveAuthDestination(incompleteAdmin)).toBe('/onboarding')
    })

    it('returns role-appropriate default when no redirect is provided', () => {
      const customer = { role: 'customer', onboarded: true }
      const admin = { role: 'admin', onboarded: true }
      const owner = { role: 'organization_owner', onboarded: true }
      const cashier = { role: 'cashier', onboarded: true }
      const staff = { role: 'staff', onboarded: true }

      expect(resolveAuthDestination(customer)).toBe('/')
      expect(resolveAuthDestination(admin)).toBe('/admin/organization')
      expect(resolveAuthDestination(owner)).toBe('/admin/organization')
      expect(resolveAuthDestination(cashier)).toBe('/pos')
      expect(resolveAuthDestination(staff)).toBe('/pos')
    })

    it('returns role-appropriate default when redirect is root "/"', () => {
      const customer = { role: 'customer', onboarded: true }
      const admin = { role: 'admin', onboarded: true }
      const owner = { role: 'organization_owner', onboarded: true }
      const cashier = { role: 'cashier', onboarded: true }
      const staff = { role: 'staff', onboarded: true }

      expect(resolveAuthDestination(customer, '/')).toBe('/')
      expect(resolveAuthDestination(admin, '/')).toBe('/admin/organization')
      expect(resolveAuthDestination(owner, '/')).toBe('/admin/organization')
      expect(resolveAuthDestination(cashier, '/')).toBe('/pos')
      expect(resolveAuthDestination(staff, '/')).toBe('/pos')
    })

    it('ignores unsafe external redirect and returns authorized default', () => {
      const customer = { role: 'customer', onboarded: true }
      const admin = { role: 'admin', onboarded: true }

      expect(resolveAuthDestination(customer, 'https://evil.com')).toBe('/')
      expect(resolveAuthDestination(customer, '//evil.com')).toBe('/')
      expect(resolveAuthDestination(admin, 'https://evil.com')).toBe('/admin/organization')
      expect(resolveAuthDestination(admin, '//evil.com/fake')).toBe('/admin/organization')
    })

    it('ignores circular guest-only authentication redirect and returns authorized default', () => {
      const customer = { role: 'customer', onboarded: true }
      const admin = { role: 'admin', onboarded: true }

      expect(resolveAuthDestination(customer, '/login')).toBe('/')
      expect(resolveAuthDestination(customer, '/register')).toBe('/')
      expect(resolveAuthDestination(admin, '/login?redirect=/admin')).toBe('/admin/organization')
    })

    it('rejects unauthorized admin redirect for customer and falls back to default', () => {
      const customer = { role: 'customer', onboarded: true }
      const owner = { role: 'organization_owner', onboarded: true }
      const cashier = { role: 'cashier', onboarded: true }
      const staff = { role: 'staff', onboarded: true }

      expect(resolveAuthDestination(customer, '/admin/manage')).toBe('/')
      expect(resolveAuthDestination(customer, '/admin/venues')).toBe('/')

      // Organization owner rejected for platform admin routes like /admin/venues -> falls back to /admin/organization
      expect(resolveAuthDestination(owner, '/admin/venues')).toBe('/admin/organization')

      // Cashier and staff rejected for /admin/venues and /admin/organization -> fall back to /pos
      expect(resolveAuthDestination(cashier, '/admin/venues')).toBe('/pos')
      expect(resolveAuthDestination(cashier, '/admin/organization')).toBe('/pos')
      expect(resolveAuthDestination(staff, '/admin/venues')).toBe('/pos')
      expect(resolveAuthDestination(staff, '/admin/organization')).toBe('/pos')
    })

    it('allows authorized internal redirect for customer', () => {
      const customer = { role: 'customer', onboarded: true }
      const owner = { role: 'organization_owner', onboarded: true }
      const cashier = { role: 'cashier', onboarded: true }
      const staff = { role: 'staff', onboarded: true }

      expect(resolveAuthDestination(customer, '/courts/1/schedule')).toBe('/courts/1/schedule')
      expect(resolveAuthDestination(customer, '/booking?tab=history')).toBe('/booking?tab=history')

      // Scoped tenant roles admitted to /pos and /admin/organization
      expect(resolveAuthDestination(owner, '/admin/organization')).toBe('/admin/organization')
      expect(resolveAuthDestination(owner, '/pos')).toBe('/pos')
      expect(resolveAuthDestination(cashier, '/pos')).toBe('/pos')
      expect(resolveAuthDestination(staff, '/pos')).toBe('/pos')
    })

    it('allows authorized admin redirect for admin', () => {
      const admin = { role: 'admin', onboarded: true }
      expect(resolveAuthDestination(admin, '/admin/venues')).toBe('/admin/venues')
      expect(resolveAuthDestination(admin, '/admin/schedule')).toBe('/admin/schedule')
      expect(resolveAuthDestination(admin, '/courts')).toBe('/courts')
    })

    it('ignores /onboarding redirect candidate if user is already onboarded', () => {
      const customer = { role: 'customer', onboarded: true }
      const admin = { role: 'admin', onboarded: true }
      expect(resolveAuthDestination(customer, '/onboarding')).toBe('/')
      expect(resolveAuthDestination(admin, '/onboarding')).toBe('/admin/organization')
    })

    it('routes users with partner application or partner intent to partner setup', () => {
      const applicant = (status: string) => ({
        role: 'customer',
        onboarded: true,
        capabilities: { has_partner_application: true, partner_application_status: status },
      })

      for (const status of ['pending', 'flagged', 'pending_review']) {
        expect(resolveAuthDestination(applicant(status))).toBe('/partner/application-status')
      }
      for (const status of ['draft', 'revision_required', 'needs_supplement']) {
        expect(resolveAuthDestination(applicant(status))).toBe('/partner-registration')
      }

      localStorage.setItem('aura_partner_intent', 'court_owner')
      const customerWithIntent = { role: 'customer', onboarded: true }
      expect(resolveAuthDestination(customerWithIntent)).toBe('/partner-registration')
      localStorage.removeItem('aura_partner_intent')
    })
  })

  describe('Router beforeEach Guard Integration', () => {
    beforeEach(() => {
      setActivePinia(createPinia())
      vi.clearAllMocks()
      vi.spyOn(authApi, 'me').mockImplementation(async () => {
        const auth = useAuthStore()
        if (auth.user) return auth.user
        throw new Error('Unauthenticated')
      })
    })

    it('redirects authenticated onboarded customer away from /login to home', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/courts')
      await router.push('/login')

      expect(router.currentRoute.value.path).toBe('/')
    })

    it.each([
      [true, '/wallet'],
      [false, '/'],
    ])('opens /wallet only when wallet_enabled is %s', async (walletEnabled, expectedPath) => {
      const auth = useAuthStore()
      auth.user = {
        id: 3,
        name: 'Wallet Customer',
        email: 'wallet@example.com',
        role: 'customer',
        onboarded: true,
        capabilities: { wallet_enabled: walletEnabled },
      }
      auth.loaded = true

      await router.push('/courts')
      await router.push('/wallet')

      expect(router.currentRoute.value.path).toBe(expectedPath)
    })

    it('redirects authenticated onboarded admin away from /login to /admin/organization by default', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 2,
        name: 'Admin User',
        email: 'admin@aura.com',
        role: 'admin',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/courts')
      await router.push('/login')

      expect(router.currentRoute.value.path).toBe('/admin/organization')
    })

    it('redirects authenticated user with incomplete onboarding to /onboarding', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 3,
        name: 'New User',
        email: 'new@example.com',
        role: 'customer',
        onboarded: false,
      }
      auth.loaded = true

      await router.push('/courts')
      await router.push('/register')

      expect(router.currentRoute.value.path).toBe('/onboarding')
    })

    it('honors safe internal redirect query parameter on guest-only route', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Customer',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/login?redirect=/courts')

      expect(router.currentRoute.value.path).toBe('/courts')
    })

    it('rejects unsafe external redirect query parameter and redirects to role default', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Customer',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/login?redirect=https://phishing.site')

      expect(router.currentRoute.value.path).toBe('/')
    })

    it('rejects unauthorized admin redirect for customer and redirects to home', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Customer',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/login?redirect=/admin/venues')

      expect(router.currentRoute.value.path).toBe('/')
    })

    it('leaves guest-only routes accessible for anonymous or expired sessions', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false
      vi.spyOn(authApi, 'me').mockRejectedValue(new Error('Unauthenticated'))

      await router.push('/courts')
      await router.push('/login')

      expect(router.currentRoute.value.path).toBe('/login')

      await router.push('/register')
      expect(router.currentRoute.value.path).toBe('/register')
    })
    it('forces session resolution and does not redirect when cached user session has expired in backend', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Stale User',
        email: 'stale@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      const loadSpy = vi.spyOn(auth, 'load')
      const meSpy = vi.spyOn(authApi, 'me').mockRejectedValue(new Error('401 Unauthenticated'))

      await router.push('/courts')
      await router.push('/login')

      expect(loadSpy).toHaveBeenCalledWith(true)
      expect(meSpy).toHaveBeenCalled()
      expect(auth.user).toBeNull()
      expect(router.currentRoute.value.path).toBe('/login')

      // Also verify on /register with cached user
      auth.user = {
        id: 1,
        name: 'Stale User',
        email: 'stale@aura.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/courts')
      await router.push('/register')

      expect(loadSpy).toHaveBeenCalledWith(true)
      expect(auth.user).toBeNull()
      expect(router.currentRoute.value.path).toBe('/register')
    })

    it('restores valid customer session on direct URL entry or reload to /login and redirects to home', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false

      const meSpy = vi.spyOn(authApi, 'me').mockResolvedValue({
        id: 1,
        name: 'Customer User',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      })

      await router.push('/courts')
      await router.push('/login')

      expect(meSpy).toHaveBeenCalled()
      expect((auth.user as any)?.id).toBe(1)
      expect(router.currentRoute.value.path).toBe('/')
    })

    it('restores valid customer session on direct URL entry or reload to /register and redirects to home', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false

      const meSpy = vi.spyOn(authApi, 'me').mockResolvedValue({
        id: 1,
        name: 'Customer User',
        email: 'customer@aura.com',
        role: 'customer',
        onboarded: true,
      })

      await router.push('/courts')
      await router.push('/register')

      expect(meSpy).toHaveBeenCalled()
      expect((auth.user as any)?.id).toBe(1)
      expect(router.currentRoute.value.path).toBe('/')
    })

    it('restores valid admin session on direct URL entry or reload to /login and redirects to /admin/organization', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false

      const meSpy = vi.spyOn(authApi, 'me').mockResolvedValue({
        id: 2,
        name: 'Admin User',
        email: 'admin@aura.com',
        role: 'admin',
        onboarded: true,
      })

      await router.push('/courts')
      await router.push('/login')

      expect(meSpy).toHaveBeenCalled()
      expect((auth.user as any)?.id).toBe(2)
      expect(router.currentRoute.value.path).toBe('/admin/organization')
    })

    it('restores valid admin session on direct URL entry or reload to /register and redirects to /admin/organization', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false

      const meSpy = vi.spyOn(authApi, 'me').mockResolvedValue({
        id: 2,
        name: 'Admin User',
        email: 'admin@aura.com',
        role: 'admin',
        onboarded: true,
      })

      await router.push('/courts')
      await router.push('/register')

      expect(meSpy).toHaveBeenCalled()
      expect((auth.user as any)?.id).toBe(2)
      expect(router.currentRoute.value.path).toBe('/admin/organization')
    })

    it('restores session on direct entry to /login with incomplete onboarding and redirects to /onboarding', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false

      const meSpy = vi.spyOn(authApi, 'me').mockResolvedValue({
        id: 3,
        name: 'Incomplete User',
        email: 'incomplete@aura.com',
        role: 'customer',
        onboarded: false,
      })

      await router.push('/courts')
      await router.push('/login')

      expect(meSpy).toHaveBeenCalled()
      expect(router.currentRoute.value.path).toBe('/onboarding')
    })

    it('allows password reset and verification journeys with query params without guestOnly redirection', async () => {
      const auth = useAuthStore()
      auth.user = null
      auth.loaded = false
      vi.spyOn(authApi, 'me').mockRejectedValue(new Error('Unauthenticated'))

      await router.push('/forgot-password')
      expect(router.currentRoute.value.path).toBe('/forgot-password')

      await router.push('/reset-password?token=abc&email=test@example.com')
      expect(router.currentRoute.value.path).toBe('/reset-password')
      expect(router.currentRoute.value.query.token).toBe('abc')
      expect(router.currentRoute.value.query.email).toBe('test@example.com')
    })

    it('allows password reset journey even when visitor has an active authenticated session', async () => {
      const auth = useAuthStore()
      auth.user = {
        id: 1,
        name: 'Customer',
        email: 'test@example.com',
        role: 'customer',
        onboarded: true,
      }
      auth.loaded = true

      await router.push('/reset-password?token=def&email=test@example.com')
      expect(router.currentRoute.value.path).toBe('/reset-password')
      expect(router.currentRoute.value.query.token).toBe('def')
    })

    it('exercises authStore.resendVerification and delegates to authApi.resendVerification', async () => {
      const resendSpy = vi
        .spyOn(authApi, 'resendVerification')
        .mockResolvedValue({ message: 'Verification sent' })
      const auth = useAuthStore()
      await auth.resendVerification()
      expect(resendSpy).toHaveBeenCalledTimes(1)
    })
  })
})

describe('reloadOnStaleChunk', () => {
  beforeEach(() => sessionStorage.clear())

  it('reloads the target once when a deploy removed the lazy route chunk', () => {
    const reload = vi.fn()
    const error = new TypeError(
      'Failed to fetch dynamically imported module: /assets/AdminLayout-old.js',
    )

    expect(reloadOnStaleChunk(error, '/admin/organization', reload)).toBe(true)
    expect(reload).toHaveBeenCalledWith('/admin/organization')
    // A second failure on the same target means the build itself is broken: no reload loop.
    expect(reloadOnStaleChunk(error, '/admin/organization', reload)).toBe(false)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('leaves unrelated navigation errors alone', () => {
    const reload = vi.fn()
    expect(reloadOnStaleChunk(new Error('boom'), '/admin', reload)).toBe(false)
    expect(reload).not.toHaveBeenCalled()
  })
})
