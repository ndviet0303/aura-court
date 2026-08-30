<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'
import LanguageSwitcher from '@/shared/components/LanguageSwitcher.vue'
import ThemeToggle from '@/shared/components/ThemeToggle.vue'
import NavbarSkeleton from './NavbarSkeleton.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const topbar = ref<HTMLElement | null>(null)
const openMenu = ref<'booking' | 'community' | null>(null)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

const userName = computed(() => {
  if (auth.user?.name) return auth.user.name
  return auth.user ? 'Alex Nguyen' : 'Khách'
})

const defaultAvatarUrl = computed(() => {
  return getAvatarUrl(auth.user?.name, auth.user?.avatar_url)
})

const userRoleBadge = computed(() => {
  if (auth.user?.role === 'admin') return 'ADMIN'
  if (auth.user) return 'PRO MEMBER'
  return 'GUEST'
})

const isBookingActive = computed(() => {
  const path = route?.path || ''
  return path.startsWith('/booking') || path.startsWith('/payment') || path.startsWith('/wallet')
})

const isCommunityActive = computed(() => {
  const path = route?.path || ''
  return path.startsWith('/resale') || path.startsWith('/marketplace') || path.startsWith('/matchmaking') || path.startsWith('/social-tournaments') || path.startsWith('/tournaments')
})

function toggleMenu(menu: 'booking' | 'community') {
  openMenu.value = openMenu.value === menu ? null : menu
}

function closeMenu() {
  openMenu.value = null
}

function closeMenuOnOutsideClick(event: MouseEvent) {
  if (topbar.value && !topbar.value.contains(event.target as Node)) closeMenu()
}

function closeMenuOnEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') closeMenu()
}

onMounted(() => {
  document.addEventListener('click', closeMenuOnOutsideClick)
  document.addEventListener('keydown', closeMenuOnEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeMenuOnOutsideClick)
  document.removeEventListener('keydown', closeMenuOnEscape)
})
</script>

<template>
  <NavbarSkeleton v-if="!auth.loaded && auth.loading" />
  <header v-else ref="topbar" class="topbar">
    <div class="topbar-inner">
      <!-- Brand Left -->
      <RouterLink to="/" class="brand" title="Aura Court">
        <div class="brand-logo-box">
          <img src="/aura/badge-logo.png" alt="Aura Court" class="brand-logo-img" />
        </div>
        <span class="brand-title">Aura Court</span>
      </RouterLink>

      <!-- Center Floating Pill Nav -->
      <nav class="nav-pill-container" aria-label="Main navigation">
        <RouterLink to="/" class="nav-pill-link" :class="{ active: (route?.path || '') === '/' }">
          {{ $t('nav.home') }}
        </RouterLink>
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
          <div v-if="openMenu === 'booking'" class="nav-dropdown-menu" role="menu">
            <RouterLink to="/courts" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">stadium</span>
              <span>{{ $t('nav.courts') }}</span>
            </RouterLink>
            <RouterLink to="/booking" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">calendar_month</span>
              <span>{{ $t('nav.booking') }}</span>
            </RouterLink>
            <RouterLink to="/wallet" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">account_balance_wallet</span>
              <span>{{ $t('nav.wallet') }}</span>
            </RouterLink>
          </div>
        </div>
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
          <div v-if="openMenu === 'community'" class="nav-dropdown-menu" role="menu">
            <RouterLink to="/resale" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">swap_horiz</span>
              <span>{{ $t('nav.resale') }}</span>
            </RouterLink>
            <RouterLink to="/tournaments" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">emoji_events</span>
              <span>{{ $t('nav.tournaments') }}</span>
            </RouterLink>
            <RouterLink to="/tournaments/bracket" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">account_tree</span>
              <span>{{ $t('nav.bracket') }}</span>
            </RouterLink>
            <RouterLink to="/tournaments/live" class="nav-dropdown-item" role="menuitem" @click="closeMenu">
              <span class="material-symbols-outlined">live_tv</span>
              <span>{{ $t('nav.liveScore') }}</span>
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
        </div>
        <RouterLink to="/about" class="nav-pill-link" :class="{ active: (route?.path || '') === '/about' }">
          {{ $t('nav.promotions') }}
        </RouterLink>
        <RouterLink
          v-if="auth.user?.role === 'admin'"
          to="/admin"
          class="nav-pill-link"
          :class="{ active: (route?.path || '').startsWith('/admin') }"
        >
          {{ $t('nav.admin') }}
        </RouterLink>
      </nav>

      <!-- Right Actions & Profile -->
      <div class="nav-right">
        <ThemeToggle />
        <LanguageSwitcher />

        <div v-if="auth.user" class="user-profile-group">
          <RouterLink to="/profile" class="user-meta" :title="userName">
            <span class="user-name">{{ userName }}</span>
            <span class="user-badge">{{ userRoleBadge }}</span>
          </RouterLink>
          <RouterLink to="/profile" class="user-avatar-btn" :aria-label="$t('nav.profile')" :title="$t('nav.profile')">
            <img :src="defaultAvatarUrl" :alt="userName" class="user-avatar-img" />
          </RouterLink>
          <RouterLink
            to="/wallet"
            class="logout-icon-btn"
            :aria-label="$t('nav.wallet')"
            :title="$t('nav.wallet')"
          >
            <span class="material-symbols-outlined">account_balance_wallet</span>
          </RouterLink>
          <button
            type="button"
            class="logout-icon-btn"
            :aria-label="$t('nav.logout')"
            :title="$t('nav.logout')"
            @click="handleLogout"
          >
            <span class="material-symbols-outlined">logout</span>
          </button>
        </div>

        <div v-else class="user-profile-group">
          <RouterLink to="/login" class="user-meta">
            <span class="user-name">{{ $t('nav.guest') }}</span>
            <span class="user-badge">{{ $t('nav.login') }}</span>
          </RouterLink>
          <RouterLink to="/login" class="user-avatar-btn" :aria-label="$t('nav.login')" :title="$t('nav.login')">
            <span class="material-symbols-outlined">person</span>
          </RouterLink>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  z-index: 1000;
  height: 78px;
  background: var(--topbar-bg, rgba(255, 255, 255, 0.92));
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--outline, rgba(0, 0, 0, 0.06));
  box-sizing: border-box;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.topbar-inner {
  width: 100%;
  max-width: 1280px;
  height: 100%;
  margin: 0 auto;
  padding: 0 28px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

/* Brand */
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  flex-shrink: 0;
}

.brand-logo-box {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--pink-light, #ffd9e2);
  display: grid;
  place-items: center;
  transition: transform 0.2s ease;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
}

.brand:hover .brand-logo-box {
  transform: scale(1.05);
}

.brand-logo-img {
  width: 25px;
  height: 25px;
  object-fit: contain;
}

.brand-title {
  font-size: 21px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  letter-spacing: -0.03em;
  transition: color 0.3s ease;
}

/* Floating Center Capsule Nav */
.nav-pill-container {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--surface-subtle, #f4f5f6);
  border: 1px solid var(--outline, rgba(0, 0, 0, 0.05));
  border-radius: 9999px;
  padding: 4px 6px;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.nav-pill-link {
  text-decoration: none;
  padding: 7px 16px;
  border-radius: 9999px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  transition: all 0.2s ease;
  white-space: nowrap;
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
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #d8dce2);
  border-radius: 14px;
  box-shadow: 0 14px 32px rgba(25, 28, 29, 0.16);
  z-index: 20;
}

.nav-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 9px;
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.nav-dropdown-item .material-symbols-outlined {
  color: var(--primary, #6B2D3F);
  font-size: 19px;
}

.nav-dropdown-item:hover,
.nav-dropdown-item.router-link-active {
  background: var(--surface-subtle, #f7f3ed);
  color: var(--primary, #6B2D3F);
}

.nav-pill-link:hover {
  color: var(--primary, #6B2D3F);
}

.nav-pill-link.active,
.nav-pill-link.router-link-exact-active {
  background: var(--nav-active-bg, #6B2D3F);
  color: var(--nav-active-text, #ffffff);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.22);
}

/* Right Section */
.nav-right {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-shrink: 0;
}

.user-profile-group {
  display: flex;
  align-items: center;
  gap: 11px;
}

.user-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-decoration: none;
  line-height: 1.25;
  max-width: 160px;
  min-width: 0;
}

.user-name {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  transition: color 0.3s ease;
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: right;
}

:global(html.dark) .user-name {
  color: #ffffff !important;
}

.user-badge {
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 1.5px 6px;
  border-radius: 4px;
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
  text-transform: uppercase;
  margin-top: 2px;
  white-space: nowrap;
  display: inline-block;
  line-height: 1.3;
}

:global(html.dark) .user-badge {
  background: rgba(229, 142, 159, 0.22) !important;
  color: var(--primary, #E58E9F) !important;
  border: 1px solid rgba(229, 142, 159, 0.45) !important;
  box-shadow: 0 0 10px rgba(229, 142, 159, 0.25) !important;
}

.user-avatar-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--primary-container, #F8E8EC);
  color: var(--primary, #6B2D3F);
  display: grid;
  place-items: center;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.15);
  border: 1.5px solid var(--outline);
  flex-shrink: 0;
}

:global(html.dark) .user-avatar-btn {
  background: #36141d;
  color: var(--primary, #E58E9F);
  border-color: rgba(229, 142, 159, 0.35);
  box-shadow: 0 2px 10px rgba(229, 142, 159, 0.2);
}

.user-avatar-btn:hover {
  transform: scale(1.05);
}

.user-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.user-avatar-btn .material-symbols-outlined {
  font-size: 21px;
}

.logout-icon-btn {
  border: 0;
  background: transparent;
  color: var(--muted, #817476);
  width: 30px;
  height: 30px;
  border-radius: 50%;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: all 0.2s ease;
}

.logout-icon-btn:hover {
  background: var(--soft, #f3f4f5);
  color: #ba1a1a;
}

.logout-icon-btn .material-symbols-outlined {
  font-size: 18px;
}

/* User profile loading skeleton */
.user-profile-skeleton {
  display: flex;
  align-items: center;
  gap: 11px;
}

.user-meta-skeleton {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
}

.user-skel-bar {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 4px;
}

.user-skel-bar::after,
.user-skel-avatar::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(201, 161, 90, 0.22) 45%,
    rgba(255, 255, 255, 0.7) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: user-skel-shimmer 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .user-skel-bar,
:global(html.dark) .user-skel-avatar {
  background-color: var(--soft, #262024);
}

:global(html.dark) .user-skel-bar::after,
:global(html.dark) .user-skel-avatar::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(229, 142, 159, 0.25) 45%,
    rgba(255, 255, 255, 0.12) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

.user-skel-bar.name {
  width: 76px;
  height: 13px;
}

.user-skel-bar.badge {
  width: 48px;
  height: 11px;
}

.user-skel-avatar {
  position: relative;
  overflow: hidden;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--soft, #eff1f4);
  flex-shrink: 0;
}

@keyframes user-skel-shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@media (max-width: 1150px) {
  .user-meta {
    max-width: 110px;
  }
}

@media (max-width: 960px) {
  .user-meta,
  .user-meta-skeleton {
    display: none;
  }
  .nav-pill-link {
    padding: 6px 14px;
    font-size: 12.5px;
  }

  .nav-dropdown-menu {
    left: auto;
    right: 0;
    transform: none;
  }
}

@media (max-width: 720px) {
  .topbar {
    height: 66px;
  }
  .topbar-inner {
    padding: 0 16px;
  }
  .brand-title {
    display: none;
  }
  .nav-pill-container {
    padding: 3px 4px;
    gap: 2px;
  }
  .nav-pill-link {
    padding: 5px 11px;
    font-size: 12px;
  }
}
</style>
