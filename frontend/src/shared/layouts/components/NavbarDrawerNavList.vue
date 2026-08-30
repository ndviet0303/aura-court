<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

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
  return path.startsWith('/booking') || path.startsWith('/payment') || path.startsWith('/wallet')
})

const isCommunityActive = computed(() => {
  const path = route?.path || ''
  return (
    path.startsWith('/resale') ||
    path.startsWith('/marketplace') ||
    path.startsWith('/matchmaking') ||
    path.startsWith('/social-tournaments') ||
    path.startsWith('/tournaments')
  )
})

const bookingItems = [
  { to: '/courts', icon: 'stadium', key: 'nav.courts' },
  { to: '/booking', icon: 'calendar_month', key: 'nav.booking' },
  { to: '/wallet', icon: 'account_balance_wallet', key: 'nav.wallet' },
]

const communityItems = [
  { to: '/resale', icon: 'swap_horiz', key: 'nav.resale' },
  { to: '/tournaments', icon: 'emoji_events', key: 'nav.tournaments' },
  { to: '/tournaments/bracket', icon: 'account_tree', key: 'nav.bracket' },
  { to: '/tournaments/live', icon: 'live_tv', key: 'nav.liveScore' },
  { to: '/matchmaking', icon: 'sports_tennis', key: 'nav.matchmaking' },
  { to: '/matchmaking/create', icon: 'add_circle', key: 'nav.createMatch' },
]
</script>

<template>
  <div class="drawer-nav-container">
    <nav class="drawer-nav-list" aria-label="Mobile navigation links">
      <!-- Home -->
      <RouterLink to="/" class="drawer-nav-row" :class="{ active: route.path === '/' }" @click="emit('navigate')">
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
          :class="{ active: route.path.startsWith('/courts') || isBookingActive, open: openAccordion === 'booking' }"
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
              :class="{ active: route.path === item.to }"
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
      <RouterLink to="/about" class="drawer-nav-row" :class="{ active: route.path === '/about' }" @click="emit('navigate')">
        <div class="nav-row-main">
          <span class="material-symbols-outlined row-icon">info</span>
          <span class="row-label">{{ $t('nav.about') }}</span>
        </div>
      </RouterLink>

      <!-- Admin Link -->
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
  background: var(--surface-subtle, #f7f3ed);
  color: var(--primary, #6b2d3f);
}

:global(html.dark) .drawer-nav-row {
  color: #faf7f3;
}

:global(html.dark) .drawer-nav-row:hover {
  background: #262024;
  color: #e58e9f;
}

.drawer-nav-row.active {
  background: var(--primary-container, #f8e8ec);
  color: var(--primary, #6b2d3f);
  font-weight: 700;
  border-color: rgba(107, 45, 63, 0.15);
}

:global(html.dark) .drawer-nav-row.active {
  background: rgba(229, 142, 159, 0.18);
  border-color: rgba(229, 142, 159, 0.35);
  color: var(--primary, #e58e9f);
}

.nav-row-main {
  display: flex;
  align-items: center;
  gap: 12px;
}

.row-icon,
.drawer-subnav-item .sub-icon {
  font-size: 20px;
  color: var(--primary, #6b2d3f);
  flex-shrink: 0;
}

:global(html.dark) .row-icon,
:global(html.dark) .drawer-subnav-item .sub-icon {
  color: var(--primary, #e58e9f);
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

:global(html.dark) .row-arrow {
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
  transition: max-height 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.22s ease, transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
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
  border-left: 2px solid var(--outline, #dcd3c5);
}

:global(html.dark) .drawer-subnav-list {
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
  background: var(--surface-subtle, #f7f3ed);
  color: var(--primary, #6b2d3f);
}

.drawer-subnav-item.active {
  background: var(--primary-container, #f8e8ec);
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

:global(html.dark) .drawer-subnav-item {
  color: #faf7f3;
}

:global(html.dark) .drawer-subnav-item:hover {
  background: #262024;
  color: #e58e9f;
}

:global(html.dark) .drawer-subnav-item.active {
  background: rgba(229, 142, 159, 0.18);
  color: var(--primary, #e58e9f);
}

.admin-row {
  margin-top: 6px;
  border: 1px dashed var(--accent, #c9a15a);
}

:global(html.dark) .admin-row {
  border-color: #d8b46e;
}

.drawer-admin-tag {
  font-size: 9.5px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--accent-container, #faf2e3);
  color: var(--on-accent-container, #684f17);
}

:global(html.dark) .drawer-admin-tag {
  background: rgba(216, 180, 110, 0.22);
  color: #d8b46e;
  border: 1px solid rgba(216, 180, 110, 0.4);
}
</style>
