import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '@/features/home/views/HomeView.vue'
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
      component: () => import('@/features/auth/views/LoginView.vue'),
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
      component: () => import('@/features/auth/views/LoginView.vue'),
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
      component: () => import('@/features/auth/views/ForgotPasswordView.vue'),
      meta: {
        titleKey: 'seo.forgotPasswordTitle',
        descriptionKey: 'seo.forgotPasswordDesc',
        indexable: false,
      },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/features/auth/views/ResetPasswordView.vue'),
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
      component: () => import('@/features/court/views/CourtsView.vue'),
      meta: { titleKey: 'seo.courtsTitle', descriptionKey: 'seo.courtsDesc' },
    },
    {
      // Courts are addressed by id only; old slug URLs fall through to not-found.
      path: '/courts/:id(\\d+)',
      name: 'court-detail',
      component: () => import('@/features/court/views/CourtDetailView.vue'),
      meta: {
        titleKey: 'seo.courtDetailTitle',
        descriptionKey: 'seo.courtDetailDesc',
        indexable: false,
      },
    },
    {
      path: '/courts/:id(\\d+)/schedule',
      name: 'schedule',
      component: () => import('@/features/court/views/ScheduleView.vue'),
      meta: { titleKey: 'seo.scheduleTitle', descriptionKey: 'seo.scheduleDesc', indexable: false },
    },
    {
      // A court id, or a venue slug from the venue QR and card links.
      path: '/courts/:id/directions',
      name: 'court-directions',
      component: () => import('@/features/court/views/VenueDirectionsView.vue'),
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
      component: () => import('@/features/booking/views/BookingView.vue'),
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
      path: '/tournaments/me',
      name: 'tournament-my-entries',
      component: () => import('@/features/tournament/views/MyTournamentEntriesView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.tournamentMyEntriesTitle',
        descriptionKey: 'seo.tournamentMyEntriesDesc',
        indexable: false,
      },
    },
    {
      path: '/tournaments/manage',
      name: 'tournament-manage',
      component: () => import('@/features/tournament/views/ManagedTournamentsView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.tournamentManageTitle',
        descriptionKey: 'seo.tournamentManageDesc',
        indexable: false,
      },
    },
    {
      path: '/tournaments/manage/:id',
      name: 'tournament-manage-detail',
      component: () => import('@/features/tournament/views/ManagedTournamentView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.tournamentManageTitle',
        descriptionKey: 'seo.tournamentManageDesc',
        indexable: false,
      },
    },
    {
      path: '/tournaments/manage/:id/edit',
      name: 'tournament-manage-edit',
      component: () => import('@/features/tournament/views/TournamentSetupView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.tournamentSetupTitle',
        descriptionKey: 'seo.tournamentSetupDesc',
        indexable: false,
      },
    },
    {
      path: '/tournaments/:id',
      name: 'tournament-detail',
      component: () => import('@/features/tournament/views/TournamentDetailView.vue'),
      meta: {
        titleKey: 'seo.tournamentDetailTitle',
        descriptionKey: 'seo.tournamentDetailDesc',
        indexable: true,
      },
    },
    {
      path: '/tournaments/:id/register',
      name: 'tournament-register',
      component: () => import('@/features/tournament/views/TournamentRegisterView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.tournamentRegisterTitle',
        descriptionKey: 'seo.tournamentRegisterDesc',
        indexable: false,
      },
    },
    {
      path: '/community',
      name: 'community',
      component: () => import('@/features/community/views/CommunityView.vue'),
      meta: {
        titleKey: 'seo.communityTitle',
        descriptionKey: 'seo.communityDesc',
        indexable: true,
      },
    },
    {
      path: '/community/posts/:id(\\d+)',
      name: 'community-post',
      component: () => import('@/features/community/views/CommunityPostView.vue'),
      meta: { titleKey: 'seo.communityPostTitle', indexable: false },
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
          component: () => import('@/features/admin/views/AdminView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminTitle', indexable: false },
        },
        {
          path: 'manage',
          name: 'admin-manage',
          component: () => import('@/features/admin/views/AdminManageView.vue'),
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
          component: () => import('@/features/admin/views/AdminVenuesView.vue'),
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
          path: 'customers',
          name: 'admin-customers',
          component: () => import('@/features/customers/views/CustomersView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminCustomersTitle',
            indexable: false,
          },
        },
        {
          // Owners, staff and the platform admin; the API decides which conversations each one sees.
          path: 'messages',
          name: 'admin-messages',
          component: () => import('@/features/chat/views/ChatView.vue'),
          meta: { titleKey: 'seo.messagesTitle', indexable: false },
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
          path: 'reviews',
          name: 'admin-reviews',
          component: () => import('@/features/community/views/OwnerReviewsView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.adminReviewsTitle',
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
          component: () => import('@/features/tournament/views/ManagedTournamentsView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.adminTournamentsTitle',
            indexable: false,
          },
        },
        {
          path: 'tournaments/new',
          name: 'admin-tournament-new',
          component: () => import('@/features/tournament/views/TournamentSetupView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.tournamentSetupTitle',
            indexable: false,
          },
        },
        {
          path: 'tournaments/:id',
          name: 'admin-tournament-detail',
          component: () => import('@/features/tournament/views/ManagedTournamentView.vue'),
          meta: {
            orgRoles: ['organization_owner', 'staff'],
            titleKey: 'seo.tournamentManageTitle',
            indexable: false,
          },
        },
        {
          path: 'tournaments/:id/edit',
          name: 'admin-tournament-edit',
          component: () => import('@/features/tournament/views/TournamentSetupView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            titleKey: 'seo.tournamentSetupTitle',
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
          // The organization's unspent venue credit from resale (design D8).
          path: 'venue-credits',
          name: 'admin-venue-credits',
          component: () => import('@/features/finance/views/OwnerVenueCreditsView.vue'),
          meta: {
            orgRoles: ['organization_owner'],
            capability: 'direct_owner_payments',
            titleKey: 'seo.adminVenueCreditsTitle',
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
          path: 'content-reports',
          name: 'admin-content-reports',
          component: () => import('@/features/community/views/AdminContentReportsView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminContentReportsTitle', indexable: false },
        },
        {
          path: 'content-reports/:id(\\d+)',
          name: 'admin-content-report',
          component: () => import('@/features/community/views/AdminContentReportView.vue'),
          meta: { platformAdmin: true, titleKey: 'seo.adminContentReportsTitle', indexable: false },
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
      component: () => import('@/features/profile/views/ProfileView.vue'),
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
      component: () => import('@/features/notification/views/NotificationsView.vue'),
      meta: {
        auth: true,
        titleKey: 'seo.notificationsTitle',
        descriptionKey: 'seo.notificationsDesc',
        indexable: false,
      },
    },
    {
      path: '/messages',
      name: 'messages',
      component: () => import('@/features/chat/views/ChatView.vue'),
      meta: { auth: true, titleKey: 'seo.messagesTitle', indexable: false },
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
      component: () => import('@/shared/views/NotFoundView.vue'),
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
    // Public pages render for guests either way, so they don't wait on /auth/me (it gated LCP).
    const gated = to.meta.auth || to.meta.admin || to.meta.platformAdmin || to.meta.orgRoles
    if (gated) await auth.load()
    else void auth.load()
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
