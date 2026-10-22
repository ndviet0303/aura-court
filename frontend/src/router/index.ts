import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '@/features/home/views/HomeView.vue'
import LoginView from '@/features/auth/views/LoginView.vue'
import BookingView from '@/features/booking/views/BookingView.vue'
import AdminView from '@/features/admin/views/AdminView.vue'
import CourtsView from '@/features/court/views/CourtsView.vue'
import ScheduleView from '@/features/court/views/ScheduleView.vue'
import CourtDetailView from '@/features/court/views/CourtDetailView.vue'
import AdminManageView from '@/features/admin/views/AdminManageView.vue'
import ForgotPasswordView from '@/features/auth/views/ForgotPasswordView.vue'
import ResetPasswordView from '@/features/auth/views/ResetPasswordView.vue'
import ProfileView from '@/features/profile/views/ProfileView.vue'
import NotFoundView from '@/shared/views/NotFoundView.vue'
import NotificationsView from '@/features/notification/views/NotificationsView.vue'
import VenueDirectionsView from '@/features/court/views/VenueDirectionsView.vue'
import AdminVenuesView from '@/features/admin/views/AdminVenuesView.vue'
import { useAuthStore } from '@/stores/auth'
import { applySeo } from '@/lib/seo'
import { startRouteNav, endRouteNav } from '@/stores/navigation'
import { resolveAuthDestination } from '@/features/auth/utils/authDestination'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { titleKey: 'seo.homeTitle', descriptionKey: 'seo.homeDesc' },
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('@/features/home/views/AboutView.vue'),
      meta: { titleKey: 'seo.aboutTitle', descriptionKey: 'seo.aboutDesc' },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: {
        guestOnly: true,
        titleKey: 'seo.loginTitle',
        descriptionKey: 'seo.loginDesc',
        indexable: false,
      },
    },
    {
      path: '/register',
      name: 'register',
      component: LoginView,
      meta: {
        guestOnly: true,
        titleKey: 'seo.registerTitle',
        descriptionKey: 'seo.registerDesc',
        indexable: false,
      },
      props: { initialMode: 'register' },
    },
    {
      path: '/onboarding',
      name: 'onboarding',
      component: () => import('@/features/auth/views/OnboardingView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.onboardingTitle',
        descriptionKey: 'seo.onboardingDesc',
        indexable: false,
      },
    },
    {
      path: '/partner-registration',
      name: 'partner-registration',
      component: () => import('@/features/partner-registration/views/PartnerRegistrationView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.partnerRegistrationTitle',
        descriptionKey: 'seo.partnerRegistrationDesc',
        indexable: false,
      },
    },
    {
      path: '/partner/application-status',
      name: 'partner-application-status',
      component: () =>
        import('@/features/partner-registration/views/PartnerApplicationStatusView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.partnerApplicationStatusTitle',
        descriptionKey: 'seo.partnerApplicationStatusDesc',
        indexable: false,
      },
    },
    {
      // Kept for notification links (package paid, price scheduled); the page lives in the admin shell.
      path: '/partner/subscription',
      redirect: '/admin/packages',
      meta: { indexable: false },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPasswordView,
      meta: {
        titleKey: 'seo.forgotPasswordTitle',
        descriptionKey: 'seo.forgotPasswordDesc',
        indexable: false,
      },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: ResetPasswordView,
      meta: {
        titleKey: 'seo.resetPasswordTitle',
        descriptionKey: 'seo.resetPasswordDesc',
        indexable: false,
      },
    },
    {
      path: '/terms',
      name: 'terms',
      component: () => import('@/features/legal/views/TermsView.vue'),
      meta: { titleKey: 'seo.termsTitle', descriptionKey: 'seo.termsDesc' },
    },
    {
      path: '/privacy',
      name: 'privacy',
      component: () => import('@/features/legal/views/PrivacyView.vue'),
      meta: { titleKey: 'seo.privacyTitle', descriptionKey: 'seo.privacyDesc' },
    },
    {
      path: '/courts',
      name: 'courts',
      component: CourtsView,
      meta: { titleKey: 'seo.courtsTitle', descriptionKey: 'seo.courtsDesc' },
    },
    {
      // Courts are addressed by id only; old slug URLs fall through to not-found.
      path: '/courts/:id(\\d+)',
      name: 'court-detail',
      component: CourtDetailView,
      meta: {
        titleKey: 'seo.courtDetailTitle',
        descriptionKey: 'seo.courtDetailDesc',
        indexable: false,
      },
    },
    {
      path: '/courts/:id(\\d+)/schedule',
      name: 'schedule',
      component: ScheduleView,
      meta: { titleKey: 'seo.scheduleTitle', descriptionKey: 'seo.scheduleDesc', indexable: false },
    },
    {
      // A court id, or a venue slug from the venue QR and card links.
      path: '/courts/:id/directions',
      name: 'court-directions',
      component: VenueDirectionsView,
      meta: {
        titleKey: 'seo.courtDirectionsTitle',
        descriptionKey: 'seo.courtDirectionsDesc',
        indexable: false,
      },
    },
    {
      path: '/booking',
      alias: ['/bookings', '/history', '/my-bookings'],
      name: 'booking',
      component: BookingView,
      meta: {
        auth: true,
        titleKey: 'seo.bookingTitle',
        descriptionKey: 'seo.bookingDesc',
        indexable: false,
      },
    },
    {
      path: '/reservations/:code?',
      name: 'reservations',
      component: () => import('@/features/booking/views/ReservationView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.reservationsTitle',
        descriptionKey: 'seo.reservationsDesc',
        indexable: false,
      },
    },
    {
      path: '/wallet',
      name: 'wallet',
      component: () => import('@/features/wallet/views/WalletView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.walletTitle',
        descriptionKey: 'seo.walletDesc',
        indexable: false,
      },
    },
    {
      path: '/resale',
      name: 'resale-marketplace',
      component: () => import('@/features/marketplace/views/ResaleMarketplaceView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.resaleTitle',
        descriptionKey: 'seo.resaleDesc',
        indexable: false,
      },
    },
    {
      path: '/recurring-booking',
      name: 'recurring-booking',
      component: () => import('@/features/booking/views/RecurringBookingView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.recurringBookingTitle',
        descriptionKey: 'seo.recurringBookingDesc',
        indexable: false,
      },
    },
    {
      path: '/recurring-booking/cycles/:id',
      name: 'payment-cycle-detail',
      component: () => import('@/features/finance/views/PaymentCycleDetailView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.paymentCycleDetailTitle',
        descriptionKey: 'seo.paymentCycleDetailDesc',
        indexable: false,
      },
    },
    {
      path: '/tournaments',
      name: 'tournaments',
      component: () => import('@/features/tournament/views/TournamentsView.vue'),
      meta: {
        titleKey: 'seo.tournamentsTitle',
        descriptionKey: 'seo.tournamentsDesc',
        indexable: true,
      },
    },
    {
      path: '/tournaments/:id/bracket',
      name: 'tournament-bracket',
      component: () => import('@/features/tournament/views/TournamentBracketView.vue'),
      meta: {
        titleKey: 'seo.tournamentBracketTitle',
        descriptionKey: 'seo.tournamentBracketDesc',
        indexable: true,
      },
    },
    {
      path: '/tournaments/:id/live',
      name: 'tournament-live',
      component: () => import('@/features/tournament/views/LiveScoreView.vue'),
      meta: {
        titleKey: 'seo.tournamentLiveTitle',
        descriptionKey: 'seo.tournamentLiveDesc',
        indexable: true,
      },
    },
    {
      path: '/matchmaking',
      alias: '/tournaments/social',
      name: 'matchmaking',
      component: () => import('@/features/social/views/MatchmakingView.vue'),
      meta: {
        titleKey: 'seo.matchmakingTitle',
        descriptionKey: 'seo.matchmakingDesc',
        indexable: true,
      },
    },
    {
      path: '/matchmaking/create',
      name: 'create-match',
      component: () => import('@/features/social/views/CreateMatchView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.createMatchTitle',
        descriptionKey: 'seo.createMatchDesc',
        indexable: false,
      },
    },
    {
      path: '/split-bill/:code?',
      name: 'split-bill',
      component: () => import('@/features/finance/views/GroupSplitBillView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.splitBillTitle',
        descriptionKey: 'seo.splitBillDesc',
        indexable: false,
      },
    },
    {
      path: '/social/sessions/:id/bill',
      name: 'drop-in-session-bill',
      component: () => import('@/features/social/views/DropInSessionBillView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.dropInBillTitle',
        descriptionKey: 'seo.dropInBillDesc',
        indexable: false,
      },
    },
    {
      path: '/pos',
      redirect: '/admin/pos',
      meta: { titleKey: 'seo.adminPosTitle', indexable: false },
    },
    {
      path: '/admin',
      component: () => import('@/shared/layouts/admin/AdminLayout.vue'),
      meta: { auth: true },
      children: [
        {
          path: '',
          name: 'admin',
          component: AdminView,
          meta: { platformAdmin: true, titleKey: 'seo.adminTitle', indexable: false },
        },
        {
          path: 'manage',
          name: 'admin-manage',
          component: AdminManageView,
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminManageTitle',
            indexable: false,
          },
        },
        {
          path: 'schedule',
          name: 'admin-schedule',
          component: () => import('@/features/admin/views/AdminScheduleView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminScheduleTitle',
            indexable: false,
          },
        },
        {
          path: 'venues',
          name: 'admin-venues',
          component: AdminVenuesView,
          meta: {
            // Staff list their assigned venues read-only (the API scopes the list).
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminVenuesTitle',
            indexable: false,
          },
        },
        {
          path: 'affected-bookings',
          name: 'admin-affected-bookings',
          component: () => import('@/features/admin/views/AdminAffectedBookingsView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminAffectedBookingsTitle',
            indexable: false,
          },
        },
        {
          path: 'operations',
          name: 'admin-operations',
          component: () => import('@/features/operations/views/OperationsView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminOperationsTitle',
            indexable: false,
          },
        },
        {
          path: 'organizations/:organizationId(\\d+)/venues/:venueId(\\d+)',
          name: 'admin-venue-detail',
          component: () => import('@/features/admin/views/AdminVenueDetailView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminVenuesTitle',
            indexable: false,
          },
        },
        {
          path: 'organizations/:organizationId(\\d+)/venues/:venueId(\\d+)/suspensions/:suspensionId(\\d+)',
          name: 'admin-venue-suspensions',
          component: () => import('@/features/admin/views/AdminVenueSuspensionsView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminVenueSuspensionsTitle',
            indexable: false,
          },
        },
        {
          path: 'organizations/:organizationId(\\d+)/venues/:venueId(\\d+)/courts/:courtId(\\d+)',
          name: 'admin-court-detail',
          component: () => import('@/features/admin/views/AdminCourtDetailView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminVenuesTitle',
            indexable: false,
          },
        },
        {
          path: 'packages',
          name: 'admin-packages',
          component: () =>
            import('@/features/platform-subscriptions/views/PlatformSubscriptionView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.partnerSubscriptionTitle',
            descriptionKey: 'seo.partnerSubscriptionDesc',
            indexable: false,
          },
        },
        {
          path: 'pricing',
          name: 'admin-pricing',
          component: () => import('@/features/admin/views/AdminPriceTablesView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminPricingTitle',
            indexable: false,
          },
        },
        {
          path: 'promotions',
          name: 'admin-promotions',
          component: () => import('@/features/admin/views/AdminPromotionProgramsView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminPromotionsTitle',
            indexable: false,
          },
        },
        {
          path: 'contracts',
          name: 'admin-contracts',
          component: () => import('@/features/admin/views/AdminRecurringContractsView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminContractsTitle',
            indexable: false,
          },
        },
        {
          path: 'organization',
          name: 'admin-organization',
          component: () => import('@/features/admin/views/OrganizationOwnerView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminOrgTitle',
            indexable: false,
          },
        },
        {
          path: 'staff',
          name: 'admin-staff',
          component: () => import('@/features/admin/views/AdminStaffView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminStaffTitle',
            indexable: false,
          },
        },
        {
          path: 'payouts',
          name: 'admin-payouts',
          component: () => import('@/features/finance/views/AdminPayoutsView.vue'),
          meta: {
            platformAdmin: true,
            capability: 'marketplace_payouts',
            titleKey: 'seo.adminPayoutsTitle',
            indexable: false,
          },
        },
        {
          path: 'tournaments',
          name: 'admin-tournaments',
          component: () => import('@/features/admin/views/AdminTournamentBracketView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminTournamentsTitle',
            indexable: false,
          },
        },
        {
          path: 'pos',
          name: 'admin-pos',
          component: () => import('@/features/pos/views/PosView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminPosTitle',
            indexable: false,
          },
        },
        {
          path: 'shifts',
          name: 'admin-shifts',
          component: () => import('@/features/admin/views/AdminShiftReconciliationView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminShiftsTitle',
            indexable: false,
          },
        },
        {
          path: 'inventory',
          name: 'admin-inventory',
          component: () => import('@/features/admin/views/AdminInventoryView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminInventoryTitle',
            indexable: false,
          },
        },
        {
          path: 'membership',
          name: 'admin-membership',
          component: () => import('@/features/admin/views/AdminMembershipView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminMembershipTitle',
            indexable: false,
          },
        },
        {
          path: 'financial-exceptions',
          name: 'admin-financial-exceptions',
          component: () => import('@/features/admin/views/AdminFinancialExceptionsView.vue'),
          meta: {
            platformAdmin: true,
            capability: 'marketplace_payouts',
            titleKey: 'seo.adminExceptionsTitle',
            indexable: false,
          },
        },
        {
          // Direct-owner payments, not payouts, open this read-only lookup (FR-81).
          path: 'payment-exceptions',
          name: 'admin-payment-exceptions',
          component: () => import('@/features/finance/views/OwnerPaymentExceptionsView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            capability: 'direct_owner_payments',
            titleKey: 'seo.adminPaymentExceptionsTitle',
            indexable: false,
          },
        },
        {
          path: 'finance',
          name: 'admin-finance',
          component: () => import('@/features/owner-finance/views/OwnerFinanceView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.ownerFinanceTitle',
            indexable: false,
          },
        },
        {
          path: 'reconciliation',
          name: 'admin-reconciliation',
          component: () => import('@/features/admin/views/AdminFinancialReconciliationView.vue'),
          meta: {
            platformAdmin: true,
            capability: 'marketplace_payouts',
            titleKey: 'seo.adminReconciliationTitle',
            indexable: false,
          },
        },
        {
          path: 'kyc',
          name: 'admin-kyc',
          component: () => import('@/features/admin/views/AdminKycVerificationView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminKycTitle', indexable: false },
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/features/admin/views/AdminUsersView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminUsersTitle', indexable: false },
        },
        {
          path: 'platform-fees',
          name: 'admin-platform-fees',
          component: () => import('@/features/platform-fees/views/PlatformFeesView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminPlatformFeesTitle', indexable: false },
        },
        {
          path: 'monitoring/venues',
          name: 'admin-monitoring-venues',
          component: () => import('@/features/admin/views/AdminVenueMonitoringView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminMonitoringTitle', indexable: false },
        },
        {
          path: 'monitoring/venues/:venueId(\\d+)',
          name: 'admin-monitoring-venue-detail',
          component: () => import('@/features/admin/views/AdminMonitoredVenueDetailView.vue'),
          meta: {
            platformAdmin: true,
            titleKey: 'seo.adminMonitoringDetailTitle',
            indexable: false,
          },
        },
        {
          path: 'venue-remediations',
          name: 'admin-venue-remediations',
          component: () => import('@/features/admin/views/AdminRemediationQueueView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminRemediationsTitle', indexable: false },
        },
        {
          path: 'venue-remediations/:id(\\d+)',
          name: 'admin-venue-remediation',
          component: () => import('@/features/admin/views/AdminRemediationReviewView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminRemediationTitle', indexable: false },
        },
        {
          path: 'disputes',
          name: 'admin-disputes',
          component: () => import('@/features/admin/views/AdminDisputeResolutionView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminDisputesTitle', indexable: false },
        },
      ],
    },
    {
      path: '/profile',
      name: 'profile',
      component: ProfileView,
      meta: {
        auth: true,
        titleKey: 'seo.profileTitle',
        descriptionKey: 'seo.profileDesc',
        indexable: false,
      },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: NotificationsView,
      meta: {
        auth: true,
        titleKey: 'seo.notificationsTitle',
        descriptionKey: 'seo.notificationsDesc',
        indexable: false,
      },
    },
    {
      path: '/invitations',
      name: 'invitations',
      component: () => import('@/features/notification/views/InvitationsView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.invitationsTitle',
        descriptionKey: 'seo.invitationsDesc',
        indexable: false,
      },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: NotFoundView,
      meta: { titleKey: 'seo.notFoundTitle', descriptionKey: 'seo.notFoundDesc', indexable: false },
    },
  ],
})

router.beforeEach(async (to) => {
  startRouteNav()
  const auth = useAuthStore()
  if (to.meta.guestOnly) {
    await auth.load(true)
  } else if (!auth.user) {
    await auth.load()
  }
  if (to.meta.auth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  // The server turns the legacy wallet off for direct-owner cohorts (`/auth/me` capabilities).
  if (to.name === 'wallet' && !auth.walletEnabled) return { name: 'home' }

  if (to.meta.guestOnly && auth.user) {
    const redirectParam = typeof to.query.redirect === 'string' ? to.query.redirect : null
    const destination = resolveAuthDestination(auth.user, redirectParam)
    if (destination) return destination
  }

  if (auth.user) {
    const userAny = auth.user as any
    const isPlatformAdmin =
      auth.user.role === 'admin' || userAny.capabilities?.is_platform_admin === true
    const activeOrgs =
      (userAny.organizations as Array<any> | undefined)?.filter(
        (o: any) => o.status === 'active',
      ) || []
    // Platform admin does not stand in for an owner or staff membership in organization operations.
    const isOwner =
      activeOrgs.some((o: any) => o.role === 'organization_owner') ||
      userAny.capabilities?.can_manage_venues === true
    const isStaff = activeOrgs.some((o: any) => o.role === 'staff')
    // Access guard for all /admin routes: pure customers without staff/owner roles are redirected to home
    const isAdminRoute = to.matched.some(
      (record) => record.path === '/admin' || record.path.startsWith('/admin/'),
    )
    if (isAdminRoute && !isPlatformAdmin && !isOwner && !isStaff) {
      return { name: 'home' }
    }

    // Direct entry to /admin root
    if (to.path === '/admin') {
      if (!isPlatformAdmin) {
        if (isOwner) return { name: 'admin-manage' }
        if (isStaff) return { name: 'admin-pos' }
        return { name: 'home' }
      }
    }
    // Platform-only routes require platform admin
    if ((to.meta.platformAdmin || to.meta.admin) && !isPlatformAdmin) {
      return { name: 'home' }
    }

    // Organization/POS scoped routes
    if (to.meta.orgRoles && Array.isArray(to.meta.orgRoles)) {
      const allowedRoles = to.meta.orgRoles as string[]
      const hasMatchingRole =
        activeOrgs.some((o: any) => allowedRoles.includes(o.role)) ||
        (allowedRoles.includes('organization_owner') && isOwner)
      if (!hasMatchingRole) {
        return { name: isPlatformAdmin ? 'admin' : 'home' }
      }
    }
  }
})

const STALE_CHUNK_KEY = 'aura_stale_chunk_reload'

router.afterEach((to) => {
  applySeo(to)
  endRouteNav()
  sessionStorage.removeItem(STALE_CHUNK_KEY)
})

/**
 * A deploy replaces the hashed chunks an open tab still references, and the host answers the old
 * URLs with index.html, so the lazy route import rejects and the navigation silently stops.
 * Load the target page from the server once instead; the key stops a reload loop on a real break.
 */
export function reloadOnStaleChunk(
  error: unknown,
  path: string,
  reload: (path: string) => void = (target) => window.location.assign(target),
): boolean {
  const message = error instanceof Error ? error.message : String(error)
  const stale =
    /dynamically imported module|Importing a module script failed|not a valid JavaScript MIME type/i
  if (!stale.test(message) || sessionStorage.getItem(STALE_CHUNK_KEY) === path) return false
  sessionStorage.setItem(STALE_CHUNK_KEY, path)
  reload(path)
  return true
}

router.onError((error, to) => {
  reloadOnStaleChunk(error, to.fullPath)
})

export default router
