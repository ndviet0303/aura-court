<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

const userName = computed(() => {
  if (auth.user?.name) return auth.user.name
  return auth.user ? 'Alex Nguyen' : 'Khách'
})

const defaultAvatarUrl = computed(() => {
  const id = auth.user?.id || 1
  return id % 2 === 0 ? '/avatars/athlete-girl.jpg' : '/avatars/athlete-boy.jpg'
})

const userRoleBadge = computed(() => {
  if (auth.user?.role === 'admin') return 'ADMIN'
  if (auth.user) return 'PRO MEMBER'
  return 'GUEST'
})

const isBookingActive = computed(() => {
  return route.path.startsWith('/booking') || route.path.startsWith('/payment')
})
</script>

<template>
  <header class="topbar">
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
        <RouterLink to="/" class="nav-pill-link" :class="{ active: route.path === '/' }">
          {{ $t('nav.home') }}
        </RouterLink>
        <RouterLink to="/courts" class="nav-pill-link" :class="{ active: route.path.startsWith('/courts') }">
          {{ $t('nav.courts') }}
        </RouterLink>
        <RouterLink to="/booking" class="nav-pill-link" :class="{ active: isBookingActive }">
          {{ $t('nav.booking') }}
        </RouterLink>
        <RouterLink to="/about" class="nav-pill-link" :class="{ active: route.path === '/about' }">
          {{ $t('nav.promotions') }}
        </RouterLink>
        <RouterLink
          v-if="auth.user?.role === 'admin'"
          to="/admin"
          class="nav-pill-link"
          :class="{ active: route.path.startsWith('/admin') }"
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
  z-index: 50;
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
  padding: 8px 20px;
  border-radius: 9999px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  transition: all 0.2s ease;
  white-space: nowrap;
}

.nav-pill-link:hover {
  color: var(--pink-dark, #78555e);
}

.nav-pill-link.active,
.nav-pill-link.router-link-exact-active {
  background: var(--pink-dark, #6e4752);
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(110, 71, 82, 0.25);
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
  color: var(--ink, #191c1d);
  transition: color 0.3s ease;
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: right;
}

.user-badge {
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--pink-dark, #8c5b67);
  text-transform: uppercase;
  margin-top: 1px;
  white-space: nowrap;
}

.user-avatar-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #5e3e47);
  display: grid;
  place-items: center;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.15);
  border: 1.5px solid var(--outline);
  flex-shrink: 0;
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

@media (max-width: 1150px) {
  .user-meta {
    max-width: 110px;
  }
}

@media (max-width: 960px) {
  .user-meta {
    display: none;
  }
  .nav-pill-link {
    padding: 6px 14px;
    font-size: 12.5px;
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
