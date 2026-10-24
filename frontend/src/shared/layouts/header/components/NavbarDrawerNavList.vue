<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { isPartnerApplicationUnderReview } from '@/core/utils/partnerApplicationStatus'

const emit = defineEmits<{
  (e: 'navigate'): void
}>()

const auth = useAuthStore()
const route = useRoute()
const openAccordion = ref<'booking' | 'community' | null>(null)

function toggle(menu: 'booking' | 'community') {
  openAccordion.value = openAccordion.value === menu ? null : menu
}

const isBookingActive = computed(() => {
  const path = route?.path || ''
  return (
    path.startsWith('/booking') ||
    path.startsWith('/recurring-booking') ||
    path.startsWith('/reservations') ||
    path.startsWith('/wallet')
  )
})

const isCommunityActive = computed(() => {
  const path = route?.path || ''
  return (
    path.startsWith('/resale') ||
    path.startsWith('/marketplace') ||
    path.startsWith('/community') ||
    path.startsWith('/tournaments')
  )
})

const bookingItems = computed(() => [
  { to: '/courts', icon: 'stadium', key: 'nav.courts' },
  { to: '/booking', icon: 'calendar_month', key: 'nav.booking' },
  { to: '/recurring-booking', icon: 'event_repeat', key: 'nav.recurringBooking' },
  ...(auth.walletEnabled
    ? [{ to: '/wallet', icon: 'account_balance_wallet', key: 'nav.wallet' }]
    : []),
])

const communityItems = [
  { to: '/resale', icon: 'swap_horiz', key: 'nav.resale' },
  { to: '/tournaments', icon: 'emoji_events', key: 'nav.tournaments' },
  { to: '/community', icon: 'forum', key: 'nav.communityFeed' },
]

const isOwner = computed(() => {
  const userAny = auth.user as any
  if (!userAny) return false
  return (
    userAny.role === 'organization_owner' ||
    userAny.capabilities?.can_manage_venues === true ||
    (Array.isArray(userAny.organizations) &&
      userAny.organizations.some(
        (o: any) => o.status === 'active' && o.role === 'organization_owner',
      ))
  )
})

const hasPartnerIntentOrApp = computed(() => {
  const userAny = auth.user as any
  if (!userAny) return false
  if (isOwner.value) return false
  if (userAny.capabilities?.has_partner_application === true) return true
  if (
    typeof window !== 'undefined' &&
    localStorage.getItem('aura_partner_intent') === 'court_owner'
  )
    return true
  return false
})

const isUnderReview = computed(() =>
  isPartnerApplicationUnderReview((auth.user as any)?.capabilities?.partner_application_status),
)

const partnerRoute = computed(() =>
  isUnderReview.value ? '/partner/application-status' : '/partner-registration',
)
</script>

<template>
  <div class="drawer-nav-container">
    <nav class="drawer-nav-list" aria-label="Mobile navigation links">
      <!-- Home -->
      <RouterLink
        to="/"
        class="drawer-nav-row"
        :class="{ active: route.path === '/' }"
        @click="emit('navigate')"
      >
        <div class="nav-row-main">
          <span class="material-symbols-outlined row-icon">home</span>
          <span class="row-label">{{ $t('nav.home') }}</span>
        </div>
      </RouterLink>

      <!-- Booking Accordion -->
      <div class="drawer-accordion">
        <button
          type="button"
          class="drawer-nav-row accordion-trigger"
          :class="{
            active: route.path.startsWith('/courts') || isBookingActive,
            open: openAccordion === 'booking',
          }"
          @click="toggle('booking')"
        >
          <div class="nav-row-main">
            <span class="material-symbols-outlined row-icon">stadium</span>
            <span class="row-label">{{ $t('nav.bookingGroup') }}</span>
          </div>
          <span class="material-symbols-outlined row-arrow accordion-icon">expand_more</span>
        </button>

        <Transition name="accordion">
          <div v-if="openAccordion === 'booking'" class="drawer-subnav-list">
            <RouterLink
              v-for="item in bookingItems"
              :key="item.to"
              :to="item.to"
              class="drawer-subnav-item"
              :class="{
                active:
                  route.path === item.to ||
                  (item.to === '/recurring-booking' && route.path.startsWith('/recurring-booking')),
              }"
              @click="emit('navigate')"
            >
              <span class="material-symbols-outlined sub-icon">{{ item.icon }}</span>
              <span>{{ $t(item.key) }}</span>
            </RouterLink>
          </div>
        </Transition>
      </div>

      <!-- Community Accordion -->
      <div class="drawer-accordion">
        <button
          type="button"
          class="drawer-nav-row accordion-trigger"
          :class="{ active: isCommunityActive, open: openAccordion === 'community' }"
          @click="toggle('community')"
        >
          <div class="nav-row-main">
            <span class="material-symbols-outlined row-icon">diversity_3</span>
            <span class="row-label">{{ $t('nav.community') }}</span>
          </div>
          <span class="material-symbols-outlined row-arrow accordion-icon">expand_more</span>
        </button>

        <Transition name="accordion">
          <div v-if="openAccordion === 'community'" class="drawer-subnav-list">
            <RouterLink
              v-for="item in communityItems"
              :key="item.to"
              :to="item.to"
              class="drawer-subnav-item"
              :class="{ active: route.path === item.to }"
              @click="emit('navigate')"
            >
              <span class="material-symbols-outlined sub-icon">{{ item.icon }}</span>
              <span>{{ $t(item.key) }}</span>
            </RouterLink>
          </div>
        </Transition>
      </div>

      <!-- About -->
      <RouterLink
        to="/about"
        class="drawer-nav-row"
        :class="{ active: route.path === '/about' }"
        @click="emit('navigate')"
      >
        <div class="nav-row-main">
          <span class="material-symbols-outlined row-icon">info</span>
          <span class="row-label">{{ $t('nav.about') }}</span>
        </div>
      </RouterLink>

      <!-- Admin Link -->
      <!-- Admin / Org / Partner Link -->
      <RouterLink
        v-if="auth.user?.role === 'admin'"
        to="/admin"
        class="drawer-nav-row admin-row"
        :class="{ active: route.path.startsWith('/admin') }"
        @click="emit('navigate')"
      >
        <div class="nav-row-main">
          <span class="material-symbols-outlined row-icon">admin_panel_settings</span>
          <span class="row-label">{{ $t('nav.admin') }}</span>
        </div>
        <span class="drawer-admin-tag">ADMIN</span>
      </RouterLink>

      <RouterLink
        v-else-if="isOwner"
        to="/admin/organization"
        class="drawer-nav-row admin-row"
        :class="{ active: route.path.startsWith('/admin') }"
        @click="emit('navigate')"
      >
        <div class="nav-row-main">
          <span class="material-symbols-outlined row-icon">domain</span>
          <span class="row-label">{{ $t('nav.organization') }}</span>
        </div>
        <span class="drawer-admin-tag">CHỦ SÂN</span>
      </RouterLink>

      <RouterLink
        v-else-if="hasPartnerIntentOrApp"
        :to="partnerRoute"
        class="drawer-nav-row admin-row"
        :class="{ active: route.path.startsWith('/partner') }"
        @click="emit('navigate')"
      >
        <div class="nav-row-main">
          <span class="material-symbols-outlined row-icon">storefront</span>
          <span class="row-label">{{ isUnderReview ? 'Hồ sơ đối tác' : 'Thiết lập cơ sở' }}</span>
        </div>
        <span class="drawer-admin-tag">{{ isUnderReview ? 'CHỜ DUYỆT' : 'ĐỐI TÁC' }}</span>
      </RouterLink>
    </nav>
  </div>
</template>

<style scoped>
.drawer-nav-container {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
  padding: 12px 14px;
}

.drawer-nav-list,
.drawer-accordion {
  display: flex;
  flex-direction: column;
}

.drawer-nav-list {
  gap: 4px;
}

.drawer-nav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 11px 14px;
  border-radius: 12px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--ink, #1c1618);
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  box-sizing: border-box;
  transition: all 0.18s ease;
  text-align: left;
}

.drawer-nav-row:hover {
  background: var(--surface-subtle, #faf7f3);
  color: #6b2d3f;
}

html.dark .drawer-nav-row {
  color: #faf7f3;
}

html.dark .drawer-nav-row:hover {
  background: #262024;
  color: #dfba72;
}

.drawer-nav-row.active {
  background: rgba(107, 45, 63, 0.08);
  color: #6b2d3f;
  font-weight: 700;
  border-color: rgba(107, 45, 63, 0.18);
}

html.dark .drawer-nav-row.active {
  background: rgba(223, 186, 114, 0.12);
  border-color: rgba(223, 186, 114, 0.25);
  color: #dfba72;
}

.nav-row-main {
  display: flex;
  align-items: center;
  gap: 12px;
}

.row-icon,
.drawer-subnav-item .sub-icon {
  font-size: 20px;
  color: #6b2d3f;
  flex-shrink: 0;
}

html.dark .row-icon,
html.dark .drawer-subnav-item .sub-icon {
  color: #dfba72;
}

.row-label {
  font-size: 14px;
  color: inherit;
}

.row-arrow {
  font-size: 18px;
  color: var(--muted, #6e6668);
  transition: transform 0.2s ease;
}

html.dark .row-arrow {
  color: #a89fa4;
}

.accordion-icon {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.accordion-trigger.open .accordion-icon {
  transform: rotate(180deg);
}

/* Accordion Transition Animation */
.accordion-enter-active,
.accordion-leave-active {
  transition:
    max-height 0.3s cubic-bezier(0.16, 1, 0.3, 1),
    opacity 0.22s ease,
    transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
  max-height: 400px;
}

.accordion-enter-from,
.accordion-leave-to {
  max-height: 0;
  opacity: 0;
  transform: translateY(-8px);
}

.drawer-subnav-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 4px 0 6px 20px;
  margin-left: 14px;
  border-left: 2px solid #ebdccb;
}

html.dark .drawer-subnav-list {
  border-left-color: #382f35;
}

.drawer-subnav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 9px;
  color: var(--ink, #1c1618);
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.18s ease;
}

.drawer-subnav-item:hover {
  background: #faf7f3;
  color: #6b2d3f;
}

.drawer-subnav-item.active {
  background: rgba(107, 45, 63, 0.08);
  color: #6b2d3f;
  font-weight: 700;
}

html.dark .drawer-subnav-item {
  color: #faf7f3;
}

html.dark .drawer-subnav-item:hover {
  background: #262024;
  color: #dfba72;
}

html.dark .drawer-subnav-item.active {
  background: rgba(223, 186, 114, 0.12);
  color: #dfba72;
}

.admin-row {
  margin-top: 6px;
  border: 1px dashed #c9a15a;
}

html.dark .admin-row {
  border-color: rgba(223, 186, 114, 0.4);
}

.drawer-admin-tag {
  font-size: 9.5px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  background: rgba(201, 161, 90, 0.15);
  color: #8f651b;
  border: 1px solid rgba(201, 161, 90, 0.35);
}

html.dark .drawer-admin-tag {
  background: rgba(223, 186, 114, 0.18);
  color: #dfba72;
  border: 1px solid rgba(223, 186, 114, 0.35);
}
</style>
