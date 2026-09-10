import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '@/features/home/views/HomeView.vue'
import LoginView from '@/features/auth/views/LoginView.vue'
import BookingView from '@/features/booking/views/BookingView.vue'
import AdminView from '@/features/admin/views/AdminView.vue'
import CourtsView from '@/features/court/views/CourtsView.vue'
import ScheduleView from '@/features/court/views/ScheduleView.vue'
import CourtDetailView from '@/features/court/views/CourtDetailView.vue'
import PaymentView from '@/features/booking/views/PaymentView.vue'
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
    { path: '/login', name: 'login', component: LoginView, meta: { titleKey: 'seo.loginTitle', descriptionKey: 'seo.loginDesc', indexable: false } },
    { path: '/register', name: 'register', component: LoginView, meta: { titleKey: 'seo.registerTitle', descriptionKey: 'seo.registerDesc', indexable: false }, props: { initialMode: 'register' } },
    { path: '/onboarding', name: 'onboarding', component: () => import('@/features/auth/views/OnboardingView.vue'), meta: { auth: true, titleKey: 'seo.onboardingTitle', descriptionKey: 'seo.onboardingDesc', indexable: false } },
    {
      path: '/partner-registration',
      name: 'partner-registration',
      component: () => import('@/features/partner-registration/views/PartnerRegistrationView.vue'),
      meta: { auth: true, titleKey: 'seo.partnerRegistrationTitle', descriptionKey: 'seo.partnerRegistrationDesc', indexable: false },
    },
    {
      path: '/partner/application-status',
      name: 'partner-application-status',
      component: () => import('@/features/partner-registration/views/PartnerApplicationStatusView.vue'),
      meta: { auth: true, titleKey: 'seo.partnerApplicationStatusTitle', descriptionKey: 'seo.partnerApplicationStatusDesc', indexable: false },
    },
    {
      path: '/partner/subscription',
      name: 'partner-subscription',
      component: () => import('@/features/platform-subscriptions/views/PlatformSubscriptionView.vue'),
      meta: { auth: true, titleKey: 'seo.partnerSubscriptionTitle', descriptionKey: 'seo.partnerSubscriptionDesc', indexable: false },
    },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { titleKey: 'seo.forgotPasswordTitle', descriptionKey: 'seo.forgotPasswordDesc', indexable: false } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordView, meta: { titleKey: 'seo.resetPasswordTitle', descriptionKey: 'seo.resetPasswordDesc', indexable: false } },
    { path: '/terms', name: 'terms', component: () => import('@/features/legal/views/TermsView.vue'), meta: { titleKey: 'seo.termsTitle', descriptionKey: 'seo.termsDesc' } },
    { path: '/privacy', name: 'privacy', component: () => import('@/features/legal/views/PrivacyView.vue'), meta: { titleKey: 'seo.privacyTitle', descriptionKey: 'seo.privacyDesc' } },
    { path: '/courts', name: 'courts', component: CourtsView, meta: { titleKey: 'seo.courtsTitle', descriptionKey: 'seo.courtsDesc' } },
    { path: '/courts/:slug', name: 'court-detail', component: CourtDetailView, meta: { titleKey: 'seo.courtDetailTitle', descriptionKey: 'seo.courtDetailDesc', indexable: false } },
    { path: '/courts/:slug/schedule', name: 'schedule', component: ScheduleView, meta: { titleKey: 'seo.scheduleTitle', descriptionKey: 'seo.scheduleDesc', indexable: false } },
    { path: '/courts/:slug/directions', name: 'court-directions', component: VenueDirectionsView, meta: { titleKey: 'seo.courtDirectionsTitle', descriptionKey: 'seo.courtDirectionsDesc', indexable: false } },
    { path: '/booking', alias: ['/bookings', '/history', '/my-bookings'], name: 'booking', component: BookingView, meta: { auth: true, titleKey: 'seo.bookingTitle', descriptionKey: 'seo.bookingDesc', indexable: false } },
    { path: '/wallet', name: 'wallet', component: () => import('@/features/wallet/views/WalletView.vue'), meta: { auth: true, titleKey: 'seo.walletTitle', descriptionKey: 'seo.walletDesc', indexable: false } },
    { path: '/resale', name: 'resale-marketplace', component: () => import('@/features/marketplace/views/ResaleMarketplaceView.vue'), meta: { auth: true, titleKey: 'seo.resaleTitle', descriptionKey: 'seo.resaleDesc', indexable: false } },
    { path: '/recurring-booking', name: 'recurring-booking', component: () => import('@/features/booking/views/RecurringBookingView.vue'), meta: { auth: true, titleKey: 'seo.recurringBookingTitle', descriptionKey: 'seo.recurringBookingDesc', indexable: false } },
    { path: '/recurring-booking/cycles/:id', name: 'payment-cycle-detail', component: () => import('@/features/finance/views/PaymentCycleDetailView.vue'), meta: { auth: true, titleKey: 'seo.paymentCycleDetailTitle', descriptionKey: 'seo.paymentCycleDetailDesc', indexable: false } },
    { path: '/tournaments', name: 'tournaments', component: () => import('@/features/tournament/views/TournamentsView.vue'), meta: { titleKey: 'seo.tournamentsTitle', descriptionKey: 'seo.tournamentsDesc', indexable: true } },
    { path: '/tournaments/:id/bracket', name: 'tournament-bracket', component: () => import('@/features/tournament/views/TournamentBracketView.vue'), meta: { titleKey: 'seo.tournamentBracketTitle', descriptionKey: 'seo.tournamentBracketDesc', indexable: true } },
    { path: '/tournaments/:id/live', name: 'tournament-live', component: () => import('@/features/tournament/views/LiveScoreView.vue'), meta: { titleKey: 'seo.tournamentLiveTitle', descriptionKey: 'seo.tournamentLiveDesc', indexable: true } },
    { path: '/matchmaking', alias: '/tournaments/social', name: 'matchmaking', component: () => import('@/features/social/views/MatchmakingView.vue'), meta: { titleKey: 'seo.matchmakingTitle', descriptionKey: 'seo.matchmakingDesc', indexable: true } },
    { path: '/matchmaking/create', name: 'create-match', component: () => import('@/features/social/views/CreateMatchView.vue'), meta: { auth: true, titleKey: 'seo.createMatchTitle', descriptionKey: 'seo.createMatchDesc', indexable: false } },
    { path: '/payment/:code?', name: 'payment', component: PaymentView, meta: { auth: true, titleKey: 'seo.paymentTitle', descriptionKey: 'seo.paymentDesc', indexable: false } },
    { path: '/split-bill/:code?', name: 'split-bill', component: () => import('@/features/finance/views/GroupSplitBillView.vue'), meta: { auth: true, titleKey: 'seo.splitBillTitle', descriptionKey: 'seo.splitBillDesc', indexable: false } },
    {
      path: '/admin',
      component: () => import('@/shared/layouts/admin/AdminLayout.vue'),
      meta: { auth: true, admin: true },
      children: [
        { path: '', name: 'admin', component: AdminView, meta: { titleKey: 'seo.adminTitle', indexable: false } },
        { path: 'manage', name: 'admin-manage', component: AdminManageView, meta: { titleKey: 'seo.adminManageTitle', indexable: false } },
        { path: 'schedule', name: 'admin-schedule', component: () => import('@/features/admin/views/AdminScheduleView.vue'), meta: { titleKey: 'seo.adminScheduleTitle', indexable: false } },
        { path: 'venues', name: 'admin-venues', component: AdminVenuesView, meta: { titleKey: 'seo.adminVenuesTitle', indexable: false } },
        { path: 'venues/publication-queue', name: 'admin-venue-publication-queue', component: () => import('@/features/admin/views/AdminPublicationQueueView.vue'), meta: { titleKey: 'seo.adminVenuePublicationTitle', indexable: false } },
        { path: 'pricing', name: 'admin-pricing', component: () => import('@/features/admin/views/V2PricingCalendarView.vue'), meta: { titleKey: 'seo.adminPricingTitle', indexable: false } },
        { path: 'contracts', name: 'admin-contracts', component: () => import('@/features/admin/views/AdminRecurringContractsView.vue'), meta: { titleKey: 'seo.adminContractsTitle', indexable: false } },
        { path: 'organization', name: 'admin-organization', component: () => import('@/features/admin/views/OrganizationOwnerView.vue'), meta: { titleKey: 'seo.adminOrgTitle', indexable: false } },
        { path: 'staff', name: 'admin-staff', component: () => import('@/features/admin/views/AdminStaffView.vue'), meta: { titleKey: 'seo.adminStaffTitle', indexable: false } },
        { path: 'payouts', name: 'admin-payouts', component: () => import('@/features/finance/views/AdminPayoutsView.vue'), meta: { titleKey: 'seo.adminPayoutsTitle', indexable: false } },
        { path: 'tournaments', name: 'admin-tournaments', component: () => import('@/features/admin/views/AdminTournamentBracketView.vue'), meta: { titleKey: 'seo.adminTournamentsTitle', indexable: false } },
        { path: 'pos', name: 'admin-pos', component: () => import('@/features/pos/views/PosView.vue'), meta: { titleKey: 'seo.adminPosTitle', indexable: false } },
        { path: 'shifts', name: 'admin-shifts', component: () => import('@/features/admin/views/AdminShiftReconciliationView.vue'), meta: { titleKey: 'seo.adminShiftsTitle', indexable: false } },
        { path: 'inventory', name: 'admin-inventory', component: () => import('@/features/admin/views/AdminInventoryView.vue'), meta: { titleKey: 'seo.adminInventoryTitle', indexable: false } },
        { path: 'membership', name: 'admin-membership', component: () => import('@/features/admin/views/AdminMembershipView.vue'), meta: { titleKey: 'seo.adminMembershipTitle', indexable: false } },
        { path: 'financial-exceptions', name: 'admin-financial-exceptions', component: () => import('@/features/admin/views/AdminFinancialExceptionsView.vue'), meta: { titleKey: 'seo.adminExceptionsTitle', indexable: false } },
        { path: 'reconciliation', name: 'admin-reconciliation', component: () => import('@/features/admin/views/AdminFinancialReconciliationView.vue'), meta: { titleKey: 'seo.adminReconciliationTitle', indexable: false } },
        { path: 'kyc', name: 'admin-kyc', component: () => import('@/features/admin/views/AdminKycVerificationView.vue'), meta: { titleKey: 'seo.adminKycTitle', indexable: false } },
        { path: 'disputes', name: 'admin-disputes', component: () => import('@/features/admin/views/AdminDisputeResolutionView.vue'), meta: { titleKey: 'seo.adminDisputesTitle', indexable: false } },
      ],  
    },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { auth: true, titleKey: 'seo.profileTitle', descriptionKey: 'seo.profileDesc', indexable: false } },
    { path: '/notifications', name: 'notifications', component: NotificationsView, meta: { auth: true, titleKey: 'seo.notificationsTitle', descriptionKey: 'seo.notificationsDesc', indexable: false } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { titleKey: 'seo.notFoundTitle', descriptionKey: 'seo.notFoundDesc', indexable: false } },
  ],
})

router.beforeEach(async (to) => {
  startRouteNav()
  const auth = useAuthStore()
  if (!auth.user) await auth.load()
  if (to.meta.auth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.admin && auth.user?.role !== 'admin') return { name: 'home' }
})

router.afterEach((to) => {
  applySeo(to)
  endRouteNav()
})

export default router
