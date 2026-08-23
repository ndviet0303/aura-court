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
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { titleKey: 'seo.forgotPasswordTitle', indexable: false } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordView, meta: { titleKey: 'seo.resetPasswordTitle', indexable: false } },
    { path: '/terms', name: 'terms', component: () => import('../views/TermsView.vue'), meta: { titleKey: 'seo.termsTitle', descriptionKey: 'seo.termsDesc' } },
    { path: '/terms-of-service', redirect: '/terms' },
    { path: '/privacy', name: 'privacy', component: () => import('../views/PrivacyView.vue'), meta: { titleKey: 'seo.privacyTitle', descriptionKey: 'seo.privacyDesc' } },
    { path: '/privacy-policy', redirect: '/privacy' },
    { path: '/courts', name: 'courts', component: CourtsView, meta: { titleKey: 'seo.courtsTitle', descriptionKey: 'seo.courtsDesc' } },
    { path: '/courts/:id', name: 'court-detail', component: CourtDetailView, meta: { titleKey: 'seo.courtDetailTitle', indexable: false } },
    { path: '/courts/:id/schedule', name: 'schedule', component: ScheduleView, meta: { titleKey: 'seo.scheduleTitle', indexable: false } },
    { path: '/venues/:slug/directions', name: 'venue-directions', component: VenueDirectionsView, meta: { titleKey: 'directions.title', description: 'Xem địa chỉ và mở Google Maps để đến Aura Court.' } },
    { path: '/booking', name: 'booking', component: BookingView, meta: { auth: true, titleKey: 'seo.bookingTitle', indexable: false } },
    { path: '/reservations-v2', name: 'reservations-v2', component: () => import('../views/V2ReservationView.vue'), meta: { auth: true, title: 'Báo giá & lịch đặt v2 | Aura Court', indexable: false } },
    { path: '/advanced-booking', name: 'advanced-booking', component: () => import('../views/AdvancedBookingView.vue'), meta: { auth: true, title: 'Lịch định kỳ & marketplace | Aura Court', indexable: false } },
    { path: '/social-tournaments', name: 'social-tournaments', component: () => import('../views/SocialTournamentView.vue'), meta: { auth: true, title: 'Người chơi & giải đấu | Aura Court', indexable: false } },
    { path: '/pos', name: 'pos', component: () => import('../views/PosView.vue'), meta: { auth: true, title: 'POS & cashier | Aura Court', indexable: false } },
    { path: '/payment/:code?', name: 'payment', component: PaymentView, meta: { auth: true, titleKey: 'seo.paymentTitle', indexable: false } },
    { path: '/admin', name: 'admin', component: AdminView, meta: { auth: true, admin: true, titleKey: 'seo.adminTitle', indexable: false } },
    { path: '/admin/manage', name: 'admin-manage', component: AdminManageView, meta: { auth: true, admin: true, title: 'Quản lý sân | Aura Court', indexable: false } },
    { path: '/admin/venues', name: 'admin-venues', component: AdminVenuesView, meta: { auth: true, admin: true, title: 'Quản lý địa điểm | Aura Court', indexable: false } },
    { path: '/admin/organization', name: 'admin-organization', component: () => import('../views/OrganizationOwnerView.vue'), meta: { auth: true, title: 'Quản trị Tổ chức | Aura Court', indexable: false } },
    { path: '/admin/pricing', name: 'admin-pricing', component: () => import('../views/V2PricingCalendarView.vue'), meta: { auth: true, title: 'Lịch giá v2 | Aura Court', indexable: false } },
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
