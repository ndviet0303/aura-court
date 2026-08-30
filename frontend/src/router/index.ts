import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
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
import NotFoundView from '../views/NotFoundView.vue'
import NotificationsView from '../views/NotificationsView.vue'
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
      component: () => import('../views/AboutView.vue'),
      meta: { title: 'Về Aura Court | Không gian cầu lông chuyên nghiệp', description: 'Khám phá tiêu chuẩn sân, trải nghiệm đặt sân và tinh thần thể thao của Aura Court.' },
    },
    { path: '/login', name: 'login', component: LoginView, meta: { titleKey: 'seo.loginTitle', indexable: false } },
    { path: '/register', name: 'register', component: LoginView, meta: { titleKey: 'auth.registerTitle', indexable: false }, props: { initialMode: 'register' } },
    { path: '/onboarding', name: 'onboarding', component: () => import('@/features/auth/views/OnboardingView.vue'), meta: { auth: true, title: 'Thiết lập tài khoản | Aura Court', indexable: false } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { titleKey: 'seo.forgotPasswordTitle', indexable: false } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordView, meta: { titleKey: 'seo.resetPasswordTitle', indexable: false } },
    { path: '/terms', name: 'terms', component: () => import('../views/TermsView.vue'), meta: { titleKey: 'seo.termsTitle', descriptionKey: 'seo.termsDesc' } },
    { path: '/terms-of-service', redirect: '/terms' },
    { path: '/privacy', name: 'privacy', component: () => import('../views/PrivacyView.vue'), meta: { titleKey: 'seo.privacyTitle', descriptionKey: 'seo.privacyDesc' } },
    { path: '/privacy-policy', redirect: '/privacy' },
    { path: '/courts', name: 'courts', component: CourtsView, meta: { titleKey: 'seo.courtsTitle', descriptionKey: 'seo.courtsDesc' } },
    { path: '/courts/:slug', name: 'court-detail', component: CourtDetailView, meta: { titleKey: 'seo.courtDetailTitle', indexable: false } },
    { path: '/courts/:slug/schedule', name: 'schedule', component: ScheduleView, meta: { titleKey: 'seo.scheduleTitle', indexable: false } },
    { path: '/venues/:slug/directions', name: 'venue-directions', component: VenueDirectionsView, meta: { titleKey: 'directions.title', description: 'Xem địa chỉ và mở Google Maps để đến Aura Court.' } },
    { path: '/booking', name: 'booking', component: BookingView, meta: { auth: true, titleKey: 'seo.bookingTitle', indexable: false } },
    { path: '/wallet', name: 'wallet', component: () => import('@/features/wallet/views/WalletView.vue'), meta: { auth: true, title: 'Ví Của Tôi & Nạp Tiền Cụm Sân | Aura Court Control', indexable: false } },
    { path: '/reservations-v2', name: 'reservations-v2', component: () => import('../views/V2ReservationView.vue'), meta: { auth: true, title: 'Báo giá & lịch đặt v2 | Aura Court', indexable: false } },
    { path: '/advanced-booking', name: 'advanced-booking', component: () => import('../views/AdvancedBookingView.vue'), meta: { auth: true, title: 'Lịch định kỳ & marketplace | Aura Court', indexable: false } },
    { path: '/resale', name: 'resale-marketplace', component: () => import('@/features/marketplace/views/ResaleMarketplaceView.vue'), meta: { auth: true, title: 'Chợ Nhượng Sân | Aura Court', indexable: false } },
    { path: '/marketplace', redirect: '/resale' },
    { path: '/recurring-booking', name: 'recurring-booking', component: () => import('@/features/booking/views/RecurringBookingView.vue'), meta: { auth: true, title: 'Đặt sân định kỳ & Lịch cố định | Aura Court', indexable: false } },
    { path: '/recurring-booking/cycles/:id', name: 'payment-cycle-detail', component: () => import('@/features/finance/views/PaymentCycleDetailView.vue'), meta: { auth: true, title: 'Chi tiết kỳ thanh toán | Aura Court', indexable: false } },
    { path: '/billing-cycles/:id', redirect: (to) => `/recurring-booking/cycles/${to.params.id}` },
    { path: '/tournaments', name: 'tournaments', component: () => import('@/features/tournament/views/TournamentsView.vue'), meta: { auth: true, title: 'Danh Sách Giải Đấu | Aura Court Control', indexable: false } },
    { path: '/tournaments/circuit', redirect: '/tournaments' },
    { path: '/tournaments/bracket', name: 'tournament-bracket', component: () => import('@/features/tournament/views/TournamentBracketView.vue'), meta: { auth: true, title: 'Sơ Đồ Thi Đấu & Nhánh Đấu | Aura Court Control', indexable: false } },
    { path: '/tournaments/:id/bracket', redirect: '/tournaments/bracket' },
    { path: '/tournaments/live', name: 'tournament-live', component: () => import('@/features/tournament/views/LiveScoreView.vue'), meta: { auth: true, title: 'Tỷ Số Trực Tiếp | Aura Court Control', indexable: false } },
    { path: '/live-score', redirect: '/tournaments/live' },
    { path: '/social-tournaments', name: 'social-tournaments', component: () => import('@/features/tournament/views/SocialTournamentView.vue'), meta: { auth: true, title: 'Tìm Kèo Giao Lưu | Aura Court', indexable: false } },
    { path: '/matchmaking', redirect: '/social-tournaments' },
    { path: '/social', redirect: '/social-tournaments' },
    { path: '/matchmaking/create', name: 'create-match', component: () => import('@/features/social/views/CreateMatchView.vue'), meta: { auth: true, titleKey: 'seo.createMatchTitle', indexable: false } },
    { path: '/social-tournaments/create', redirect: '/matchmaking/create' },
    { path: '/social/create-match', redirect: '/matchmaking/create' },
    { path: '/pos', redirect: '/admin/pos' },
    { path: '/point-of-sale', redirect: '/admin/pos' },
    { path: '/payment/:code?', name: 'payment', component: PaymentView, meta: { auth: true, titleKey: 'seo.paymentTitle', indexable: false } },
    { path: '/split-bill/:code?', name: 'split-bill', component: () => import('@/features/finance/views/GroupSplitBillView.vue'), meta: { auth: true, title: 'Chia tiền & Quyết toán nhóm | Aura Court', indexable: false } },
    { path: '/group-split/:code?', redirect: (to) => `/split-bill/${to.params.code || ''}` },
    {
      path: '/admin',
      component: () => import('@/shared/layouts/AdminLayout.vue'),
      meta: { auth: true, admin: true },
      children: [
        { path: '', name: 'admin', component: AdminView, meta: { titleKey: 'seo.adminTitle', indexable: false } },
        { path: 'manage', name: 'admin-manage', component: AdminManageView, meta: { title: 'Quản lý sân | Aura Court', indexable: false } },
        { path: 'schedule', name: 'admin-schedule', component: () => import('@/features/admin/views/AdminScheduleView.vue'), meta: { title: 'Lịch hoạt động & Bảo trì | Aura Court', indexable: false } },
        { path: 'venues', name: 'admin-venues', component: AdminVenuesView, meta: { title: 'Quản lý địa điểm | Aura Court', indexable: false } },
        { path: 'pricing', name: 'admin-pricing', component: () => import('@/features/admin/views/V2PricingCalendarView.vue'), meta: { title: 'Thiết lập Ma trận Giá linh hoạt | Aura Court Control', indexable: false } },
        { path: 'contracts', name: 'admin-contracts', component: () => import('@/features/admin/views/AdminRecurringContractsView.vue'), meta: { title: 'Hợp đồng định kỳ | Aura Court', indexable: false } },
        { path: 'organization', name: 'admin-organization', component: () => import('@/features/admin/views/OrganizationOwnerView.vue'), meta: { title: 'Quản trị Tổ chức | Aura Court', indexable: false } },
        { path: 'staff', name: 'admin-staff', component: () => import('@/features/admin/views/AdminStaffView.vue'), meta: { title: 'Quản lý Nhân sự & Phân quyền | Aura Court Control', indexable: false } },
        { path: 'payouts', name: 'admin-payouts', component: () => import('@/features/finance/views/AdminPayoutsView.vue'), meta: { title: 'Báo cáo Doanh thu & Rút tiền | Aura Court Control', indexable: false } },
        { path: 'tournaments', name: 'admin-tournaments', component: () => import('@/features/admin/views/AdminTournamentBracketView.vue'), meta: { title: 'Quản trị Giải đấu & Bracket | Aura Court Control', indexable: false } },
        { path: 'pos', name: 'admin-pos', component: () => import('@/features/pos/views/PosView.vue'), meta: { title: 'Bán lẻ POS & Thanh toán | Aura Court Control', indexable: false } },
        { path: 'shifts', name: 'admin-shifts', component: () => import('@/features/admin/views/AdminShiftReconciliationView.vue'), meta: { title: 'Quản lý Ca trực & Đối soát Tiền mặt | Aura Court Control', indexable: false } },
        { path: 'inventory', name: 'admin-inventory', component: () => import('@/features/admin/views/AdminInventoryView.vue'), meta: { title: 'Quản lý Kho & Hàng hóa | Aura Court Control', indexable: false } },
        { path: 'membership', name: 'admin-membership', component: () => import('@/features/admin/views/AdminMembershipView.vue'), meta: { title: 'Quản lý Gói hội viên & Điểm thưởng | Aura Court Control', indexable: false } },
        { path: 'financial-exceptions', name: 'admin-financial-exceptions', component: () => import('@/features/admin/views/AdminFinancialExceptionsView.vue'), meta: { title: 'Ngoại lệ Tài chính & Sổ cái Kiểm toán | Aura Court Control', indexable: false } },
        { path: 'kyc', name: 'admin-kyc', component: () => import('@/features/admin/views/AdminKycVerificationView.vue'), meta: { title: 'Phê duyệt KYC & Thẩm định Chủ sân | Aura Court Control', indexable: false } },
      ],
    },
    { path: '/admin/kyc-verification', redirect: '/admin/kyc' },
    { path: '/admin/host-kyc', redirect: '/admin/kyc' },
    { path: '/admin/exceptions', redirect: '/admin/financial-exceptions' },
    { path: '/admin/audit-logs', redirect: '/admin/financial-exceptions' },
    { path: '/admin/audit', redirect: '/admin/financial-exceptions' },
    { path: '/admin/financial-exception', redirect: '/admin/financial-exceptions' },
    { path: '/admin/loyalty', redirect: '/admin/membership' },
    { path: '/admin/membership-loyalty', redirect: '/admin/membership' },
    { path: '/admin/memberships', redirect: '/admin/membership' },
    { path: '/admin/vip', redirect: '/admin/membership' },
    { path: '/admin/shift-reconciliation', redirect: '/admin/shifts' },
    { path: '/admin/cash-reconciliation', redirect: '/admin/shifts' },
    { path: '/admin/shifts-reconciliation', redirect: '/admin/shifts' },
    { path: '/admin/cashier-shifts', redirect: '/admin/shifts' },
    { path: '/admin/shift', redirect: '/admin/shifts' },
    { path: '/shifts', redirect: '/admin/shifts' },
    { path: '/admin/point-of-sale', redirect: '/admin/pos' },
    { path: '/admin/warehouse', redirect: '/admin/inventory' },
    { path: '/admin/stock', redirect: '/admin/inventory' },
    { path: '/admin/products', redirect: '/admin/inventory' },
    { path: '/inventory', redirect: '/admin/inventory' },
    { path: '/warehouse', redirect: '/admin/inventory' },

    { path: '/admin/operating-hours', redirect: '/admin/schedule' },
    { path: '/admin/pricing-revenue', redirect: '/admin/pricing' },
    { path: '/admin/price-rules', redirect: '/admin/pricing' },
    { path: '/admin/recurring-contracts', redirect: '/admin/contracts' },
    { path: '/admin/permissions', redirect: '/admin/staff' },
    { path: '/admin/staff-permissions', redirect: '/admin/staff' },
    { path: '/admin/payouts-settlement', redirect: '/admin/payouts' },
    { path: '/admin/settlement', redirect: '/admin/payouts' },
    { path: '/admin/settlements', redirect: '/admin/payouts' },
    { path: '/admin/withdrawal', redirect: '/admin/payouts' },
    { path: '/admin/bracket', redirect: '/admin/tournaments' },
    { path: '/admin/tournament-bracket', redirect: '/admin/tournaments' },
    { path: '/admin/tournaments/check-in', redirect: '/admin/tournaments?tab=athlete-check-in' },
    { path: '/admin/athlete-check-in', redirect: '/admin/tournaments?tab=athlete-check-in' },
    { path: '/admin/check-in', redirect: '/admin/tournaments?tab=athlete-check-in' },
    { path: '/admin/checkin', redirect: '/admin/tournaments?tab=athlete-check-in' },
    { path: '/admin/tournaments/referee', redirect: '/admin/tournaments?tab=live-referee-console' },
    { path: '/admin/referee', redirect: '/admin/tournaments?tab=live-referee-console' },
    { path: '/admin/referee-console', redirect: '/admin/tournaments?tab=live-referee-console' },
    { path: '/tournaments/referee', redirect: '/admin/tournaments?tab=live-referee-console' },
    { path: '/tournaments/check-in', redirect: '/tournaments?tab=check-in' },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { auth: true, titleKey: 'seo.profileTitle', indexable: false } },
    { path: '/notifications', name: 'notifications', component: NotificationsView, meta: { auth: true, titleKey: 'seo.notificationsTitle', indexable: false } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { titleKey: 'seo.notFoundTitle', indexable: false } },
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
  endRouteNav()
  applySeo(to)
})

router.onError(() => {
  endRouteNav()
})

export default router
