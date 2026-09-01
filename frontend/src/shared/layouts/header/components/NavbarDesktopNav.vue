<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const openMenu = ref<'booking' | 'community' | null>(null)
const navRef = ref<HTMLElement | null>(null)

const isBookingActive = computed(() => {
  const path = route?.path || ''
  return (
    path.startsWith('/booking') ||
    path.startsWith('/recurring-booking') ||
    path.startsWith('/payment') ||
    path.startsWith('/wallet')
  )
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

function toggleMenu(menu: 'booking' | 'community') {
  openMenu.value = openMenu.value === menu ? null : menu
}

function closeMenu() {
  openMenu.value = null
}

function handleOutsideClick(event: MouseEvent) {
  if (navRef.value && !navRef.value.contains(event.target as Node)) {
    closeMenu()
  }
}

function handleEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeMenu()
  }
}

watch(() => route.path, closeMenu)

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <nav ref="navRef" class="nav-pill-container" aria-label="Main navigation">
    <RouterLink to="/" class="nav-pill-link" :class="{ active: (route?.path || '') === '/' }">
      {{ $t('nav.home') }}
    </RouterLink>

    <!-- Booking Dropdown -->
    <div class="nav-dropdown" :class="{ open: openMenu === 'booking' }">
      <button
        type="button"
        class="nav-pill-link nav-dropdown-trigger"
        :class="{ active: (route?.path || '').startsWith('/courts') || isBookingActive }"
        aria-haspopup="menu"
        :aria-expanded="openMenu === 'booking'"
        @click.stop="toggleMenu('booking')"
      >
        <span>{{ $t('nav.bookingGroup') }}</span>
        <span class="material-symbols-outlined nav-dropdown-arrow" aria-hidden="true">expand_more</span>
      </button>
      <Transition name="nav-dropdown-fade">
        <div v-if="openMenu === 'booking'" class="nav-dropdown-menu" role="menu">
          <RouterLink to="/courts" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">stadium</span>
            <span>{{ $t('nav.courts') }}</span>
          </RouterLink>
          <RouterLink to="/booking" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">calendar_month</span>
            <span>{{ $t('nav.booking') }}</span>
          </RouterLink>
          <RouterLink
            to="/recurring-booking"
            class="nav-dropdown-item"
            :class="{ 'router-link-active': (route?.path || '').startsWith('/recurring-booking') }"
            role="menuitem"
            @click="closeMenu"
          >
            <span class="material-symbols-outlined">event_repeat</span>
            <span>{{ $t('nav.recurringBooking') }}</span>
          </RouterLink>
          <RouterLink to="/wallet" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">account_balance_wallet</span>
            <span>{{ $t('nav.wallet') }}</span>
          </RouterLink>
        </div>
      </Transition>
    </div>

    <!-- Community Dropdown -->
    <div class="nav-dropdown" :class="{ open: openMenu === 'community' }">
      <button
        type="button"
        class="nav-pill-link nav-dropdown-trigger"
        :class="{ active: isCommunityActive }"
        aria-haspopup="menu"
        :aria-expanded="openMenu === 'community'"
        @click.stop="toggleMenu('community')"
      >
        <span>{{ $t('nav.community') }}</span>
        <span class="material-symbols-outlined nav-dropdown-arrow" aria-hidden="true">expand_more</span>
      </button>
      <Transition name="nav-dropdown-fade">
        <div v-if="openMenu === 'community'" class="nav-dropdown-menu" role="menu">
          <RouterLink to="/resale" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">swap_horiz</span>
            <span>{{ $t('nav.resale') }}</span>
          </RouterLink>
          <RouterLink to="/tournaments" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">emoji_events</span>
            <span>{{ $t('nav.tournaments') }}</span>
          </RouterLink>
          <RouterLink to="/matchmaking" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">sports_tennis</span>
            <span>{{ $t('nav.matchmaking') }}</span>
          </RouterLink>
          <RouterLink to="/matchmaking/create" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
            <span class="material-symbols-outlined">add_circle</span>
            <span>{{ $t('nav.createMatch') }}</span>
          </RouterLink>
        </div>
      </Transition>
    </div>

    <!-- About -->
    <RouterLink to="/about" class="nav-pill-link" :class="{ active: (route?.path || '') === '/about' }">
      {{ $t('nav.about') }}
    </RouterLink>

    <!-- Admin Link -->
    <RouterLink
      v-if="auth.user?.role === 'admin'"
      to="/admin"
      class="nav-pill-link"
      :class="{ active: (route?.path || '').startsWith('/admin') }"
    >
      {{ $t('nav.admin') }}
    </RouterLink>
  </nav>
</template>

<style scoped>
.nav-pill-container {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--surface-subtle, #f5f0e8);
  border: 1px solid var(--outline, #ebdccb);
  border-radius: 9999px;
  padding: 4px 6px;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

html.dark .nav-pill-container {
  background: #251e22;
  border-color: #382f35;
}

.nav-pill-link {
  text-decoration: none;
  padding: 7px 16px;
  border-radius: 9999px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--muted, #6e6668);
  transition: all 0.2s ease;
  white-space: nowrap;
}

html.dark .nav-pill-link {
  color: #c9c1c5;
}

.nav-dropdown {
  position: relative;
}

.nav-dropdown-trigger {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  border: 0;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.nav-dropdown-arrow {
  font-size: 16px;
  transition: transform 0.2s ease;
}

.nav-dropdown.open .nav-dropdown-arrow {
  transform: rotate(180deg);
}

.nav-dropdown-menu {
  position: absolute;
  top: calc(100% + 10px);
  left: 50%;
  min-width: 210px;
  padding: 8px;
  transform: translateX(-50%);
  background: #ffffff;
  border: 1px solid #ebdccb;
  border-radius: 14px;
  box-shadow: 0 16px 36px rgba(107, 45, 63, 0.12);
  z-index: 20;
}

html.dark .nav-dropdown-menu {
  background: #1e181c !important;
  border-color: #382f35 !important;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6) !important;
}

.nav-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 9px;
  color: var(--ink, #1c1618);
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  transition: background-color 0.2s ease, color 0.2s ease;
  text-decoration: none;
}

html.dark .nav-dropdown-item {
  color: #faf7f3;
}

.nav-dropdown-item .material-symbols-outlined {
  color: #6B2D3F;
  font-size: 19px;
}

html.dark .nav-dropdown-item .material-symbols-outlined {
  color: #dfba72;
}

.nav-dropdown-item:hover,
.nav-dropdown-item.router-link-active {
  background: #FAF7F3;
  color: #6B2D3F;
}

html.dark .nav-dropdown-item:hover,
html.dark .nav-dropdown-item.router-link-active {
  background: #2b2228;
  color: #dfba72;
}

.nav-pill-link:hover {
  color: #6B2D3F;
}

html.dark .nav-pill-link:hover {
  color: #dfba72;
}

.nav-pill-link.active,
.nav-pill-link.router-link-exact-active {
  background: linear-gradient(135deg, #6B2D3F 0%, #52212F 100%);
  color: #ffffff !important;
  font-weight: 700;
  box-shadow: 0 3px 12px rgba(107, 45, 63, 0.3);
}

html.dark .nav-pill-link.active,
html.dark .nav-pill-link.router-link-exact-active {
  background: linear-gradient(135deg, #6B2D3F 0%, #4A1D2B 100%);
  color: #ffffff !important;
  border: 1px solid rgba(223, 186, 114, 0.25);
  box-shadow: 0 3px 12px rgba(107, 45, 63, 0.45);
}

/* Dropdown Fade & Slide Animation */
.nav-dropdown-fade-enter-active,
.nav-dropdown-fade-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-dropdown-fade-enter-from,
.nav-dropdown-fade-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(-8px) scale(0.96);
}

@media (max-width: 1080px) {
  .nav-pill-container {
    display: none !important;
  }
}
</style>
