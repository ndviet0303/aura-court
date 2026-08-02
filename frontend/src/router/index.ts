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
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/about',
      name: 'about',
      // route level code-splitting
      // this generates a separate chunk (About.[hash].js) for this route
      // which is lazy-loaded when the route is visited.
      component: () => import('../views/AboutView.vue'),
    },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordView },
    { path: '/courts', name: 'courts', component: CourtsView },
    { path: '/courts/:id', name: 'court-detail', component: CourtDetailView },
    { path: '/courts/:id/schedule', name: 'schedule', component: ScheduleView },
    { path: '/booking', name: 'booking', component: BookingView, meta: { auth: true } },
    { path: '/payment/:code?', name: 'payment', component: PaymentView, meta: { auth: true } },
    { path: '/admin', name: 'admin', component: AdminView, meta: { auth: true, admin: true } },
    { path: '/admin/manage', name: 'admin-manage', component: AdminManageView, meta: { auth: true, admin: true } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { auth: true } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.user) await auth.load()
  if (to.meta.auth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.admin && auth.user?.role !== 'admin') return { name: 'home' }
})

export default router
