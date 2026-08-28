import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import BookingView from '../views/BookingView.vue'
import AdminView from '../views/AdminView.vue'
import CourtsView from '../views/CourtsView.vue'
import ScheduleView from '../views/ScheduleView.vue'
import CourtDetailView from '../views/CourtDetailView.vue'
import PaymentView from '../views/PaymentView.vue'
import AdminManageView from '../views/AdminManageView.vue'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import ProfileView from '../views/ProfileView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import NotificationsView from '../views/NotificationsView.vue'
import VenueDirectionsView from '../views/VenueDirectionsView.vue'
import AdminVenuesView from '../views/AdminVenuesView.vue'
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
    { path: '/onboarding', name: 'onboarding', component: () => import('../views/OnboardingView.vue'), meta: { auth: true, title: 'Thiết lập tài khoản | Aura Court', indexable: false } },
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
    { path: '/reservations-v2', name: 'reservations-v2', component: () => import('../views/V2ReservationView.vue'), meta: { auth: true, title: 'Báo giá & lịch đặt v2 | Aura Court', indexable: false } },
    { path: '/advanced-booking', name: 'advanced-booking', component: () => import('../views/AdvancedBookingView.vue'), meta: { auth: true, title: 'Lịch định kỳ & marketplace | Aura Court', indexable: false } },
    { path: '/resale', name: 'resale-marketplace', component: () => import('../views/ResaleMarketplaceView.vue'), meta: { auth: true, title: 'Chợ Nhượng Sân | Aura Court', indexable: false } },
    { path: '/marketplace', redirect: '/resale' },
    { path: '/recurring-booking', name: 'recurring-booking', component: () => import('../views/RecurringBookingView.vue'), meta: { auth: true, title: 'Đặt sân định kỳ & Lịch cố định | Aura Court', indexable: false } },
    { path: '/recurring-booking/cycles/:id', name: 'payment-cycle-detail', component: () => import('../views/PaymentCycleDetailView.vue'), meta: { auth: true, title: 'Chi tiết kỳ thanh toán | Aura Court', indexable: false } },
    { path: '/billing-cycles/:id', redirect: (to) => `/recurring-booking/cycles/${to.params.id}` },
    { path: '/social-tournaments', name: 'social-tournaments', component: () => import('../views/SocialTournamentView.vue'), meta: { auth: true, title: 'Tìm Kèo Giao Lưu | Aura Court', indexable: false } },
    { path: '/matchmaking', redirect: '/social-tournaments' },
    { path: '/social', redirect: '/social-tournaments' },
    { path: '/matchmaking/create', name: 'create-match', component: () => import('../views/CreateMatchView.vue'), meta: { auth: true, titleKey: 'seo.createMatchTitle', indexable: false } },
    { path: '/social-tournaments/create', redirect: '/matchmaking/create' },
    { path: '/social/create-match', redirect: '/matchmaking/create' },
    { path: '/pos', name: 'pos', component: () => import('../views/PosView.vue'), meta: { auth: true, title: 'POS & cashier | Aura Court', indexable: false } },
    { path: '/payment/:code?', name: 'payment', component: PaymentView, meta: { auth: true, titleKey: 'seo.paymentTitle', indexable: false } },
    { path: '/split-bill/:code?', name: 'split-bill', component: () => import('../views/GroupSplitBillView.vue'), meta: { auth: true, title: 'Chia tiền & Quyết toán nhóm | Aura Court', indexable: false } },
    { path: '/group-split/:code?', redirect: (to) => `/split-bill/${to.params.code || ''}` },
    {
      path: '/admin',
      component: () => import('@/components/AdminLayout.vue'),
      meta: { auth: true, admin: true },
      children: [
        { path: '', name: 'admin', component: AdminView, meta: { titleKey: 'seo.adminTitle', indexable: false } },
        { path: 'manage', name: 'admin-manage', component: AdminManageView, meta: { title: 'Quản lý sân | Aura Court', indexable: false } },
        { path: 'schedule', name: 'admin-schedule', component: () => import('../views/AdminScheduleView.vue'), meta: { title: 'Lịch hoạt động & Bảo trì | Aura Court', indexable: false } },
        { path: 'venues', name: 'admin-venues', component: AdminVenuesView, meta: { title: 'Quản lý địa điểm | Aura Court', indexable: false } },
        { path: 'pricing', name: 'admin-pricing', component: () => import('../views/V2PricingCalendarView.vue'), meta: { title: 'Lịch giá v2 | Aura Court', indexable: false } },
        { path: 'contracts', name: 'admin-contracts', component: () => import('../views/AdminRecurringContractsView.vue'), meta: { title: 'Hợp đồng định kỳ | Aura Court', indexable: false } },
        { path: 'organization', name: 'admin-organization', component: () => import('../views/OrganizationOwnerView.vue'), meta: { title: 'Quản trị Tổ chức | Aura Court', indexable: false } },
      ],
    },
    { path: '/admin/operating-hours', redirect: '/admin/schedule' },
    { path: '/admin/recurring-contracts', redirect: '/admin/contracts' },
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
