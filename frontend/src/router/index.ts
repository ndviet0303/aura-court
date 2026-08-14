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

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { title: 'Aura Court | Đặt sân cầu lông trực tuyến', description: 'Xem sân còn trống, chọn khung giờ yêu thích và đặt sân cầu lông trong vài giây.' },
    },
    {
      path: '/about',
      name: 'about',
      // route level code-splitting
      // this generates a separate chunk (About.[hash].js) for this route
      // which is lazy-loaded when the route is visited.
      component: () => import('../views/AboutView.vue'),
      meta: { title: 'Về Aura Court | Không gian cầu lông chuyên nghiệp', description: 'Khám phá tiêu chuẩn sân, trải nghiệm đặt sân và tinh thần thể thao của Aura Court.' },
    },
    { path: '/login', name: 'login', component: LoginView, meta: { title: 'Đăng nhập | Aura Court', indexable: false } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { title: 'Quên mật khẩu | Aura Court', indexable: false } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordView, meta: { title: 'Đặt lại mật khẩu | Aura Court', indexable: false } },
    { path: '/courts', name: 'courts', component: CourtsView, meta: { title: 'Danh sách sân cầu lông | Aura Court', description: 'Tìm sân cầu lông phù hợp, xem giá và khung giờ còn trống tại Aura Court.' } },
    { path: '/courts/:id', name: 'court-detail', component: CourtDetailView, meta: { title: 'Chi tiết sân cầu lông | Aura Court', description: 'Xem thông tin sân, bảng giá và lịch trống để chọn khung giờ phù hợp.', indexable: false } },
    { path: '/courts/:id/schedule', name: 'schedule', component: ScheduleView, meta: { title: 'Lịch sân cầu lông | Aura Court', description: 'Chọn ngày và khung giờ còn trống để bắt đầu đặt sân.', indexable: false } },
    { path: '/venues/:slug/directions', name: 'venue-directions', component: VenueDirectionsView, meta: { title: 'Chỉ đường đến Aura Court', description: 'Xem địa chỉ và mở Google Maps để đến Aura Court.' } },
    { path: '/booking', name: 'booking', component: BookingView, meta: { auth: true, title: 'Lịch đặt sân | Aura Court', indexable: false } },
    { path: '/payment/:code?', name: 'payment', component: PaymentView, meta: { auth: true, title: 'Thanh toán đặt sân | Aura Court', indexable: false } },
    { path: '/admin', name: 'admin', component: AdminView, meta: { auth: true, admin: true, title: 'Báo cáo quản trị | Aura Court', indexable: false } },
    { path: '/admin/manage', name: 'admin-manage', component: AdminManageView, meta: { auth: true, admin: true, title: 'Quản lý sân | Aura Court', indexable: false } },
    { path: '/admin/venues', name: 'admin-venues', component: AdminVenuesView, meta: { auth: true, admin: true, title: 'Quản lý địa điểm | Aura Court', indexable: false } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { auth: true, title: 'Hồ sơ cá nhân | Aura Court', indexable: false } },
    { path: '/notifications', name: 'notifications', component: NotificationsView, meta: { auth: true, title: 'Thông báo | Aura Court', indexable: false } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'Không tìm thấy trang | Aura Court', indexable: false } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.user) await auth.load()
  if (to.meta.auth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.admin && auth.user?.role !== 'admin') return { name: 'home' }
})

router.afterEach((to) => applySeo(to))

export default router
