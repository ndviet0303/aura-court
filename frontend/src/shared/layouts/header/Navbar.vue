<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { LanguageSwitcher, ThemeToggle } from '@/shared/components'
import NavbarSkeleton from './NavbarSkeleton.vue'

import NavbarBrand from './components/NavbarBrand.vue'
import NavbarDesktopNav from './components/NavbarDesktopNav.vue'
import NavbarUserProfile from './components/NavbarUserProfile.vue'
import NavbarMobileDrawer from './components/NavbarMobileDrawer.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const topbar = ref<HTMLElement | null>(null)
const isMobileMenuOpen = ref(false)

function toggleMobileMenu() {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

function closeMobileMenu() {
  isMobileMenuOpen.value = false
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

watch(() => route.path, closeMobileMenu)
</script>

<template>
  <NavbarSkeleton v-if="!auth.loaded && auth.loading" />
  <header v-else ref="topbar" class="topbar">
    <div class="topbar-inner">
      <!-- Brand Left -->
      <NavbarBrand @click="closeMobileMenu" />

      <!-- Center Floating Pill Nav (Desktop) -->
      <NavbarDesktopNav />

      <!-- Right Actions & Profile -->
      <div class="nav-right">
        <!-- Desktop theme & language toggles -->
        <div class="desktop-only-controls">
          <ThemeToggle />
          <LanguageSwitcher />
        </div>

        <!-- User Profile Component -->
        <NavbarUserProfile @logout="handleLogout" />

        <!-- Mobile Drawer Toggle Button -->
        <button
          type="button"
          class="mobile-menu-btn"
          @click="toggleMobileMenu"
          :aria-expanded="isMobileMenuOpen"
          aria-label="Toggle navigation menu"
        >
          <span class="material-symbols-outlined menu-icon" :class="{ 'is-open': isMobileMenuOpen }">
            {{ isMobileMenuOpen ? 'close' : 'menu' }}
          </span>
        </button>
      </div>
    </div>

    <!-- Mobile Drawer (Offcanvas Sheet) -->
    <NavbarMobileDrawer v-model:open="isMobileMenuOpen" @logout="handleLogout" />
  </header>
</template>

<style scoped>
.topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  max-width: 100%;
  z-index: 1000;
  height: 78px;
  background: var(--topbar-bg, rgba(250, 247, 243, 0.94));
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
  padding: 0 24px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  min-width: 0;
}

.desktop-only-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Mobile Menu Toggle Button */
.mobile-menu-btn {
  display: none;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #dcd3c5);
  color: var(--ink, #1c1618);
  cursor: pointer;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  align-items: center;
  justify-content: center;
  padding: 0;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  flex-shrink: 0;
}

:global(html.dark) .mobile-menu-btn {
  background: #1e191c;
  border-color: #382f35;
  color: #faf7f3;
}

.mobile-menu-btn:hover {
  background: var(--primary-container, #f8e8ec);
  color: var(--primary, #6b2d3f);
  border-color: var(--accent, #c9a15a);
}

.mobile-menu-btn .menu-icon {
  font-size: 22px;
  transition: transform 0.25s ease;
}

.mobile-menu-btn .menu-icon.is-open {
  transform: rotate(90deg);
}

@media (max-width: 1080px) {
  .desktop-only-controls {
    display: none !important;
  }
  .mobile-menu-btn {
    display: flex !important;
  }
}

@media (max-width: 720px) {
  .topbar {
    height: 66px;
  }
  .topbar-inner {
    padding: 0 16px;
  }
  .nav-right {
    gap: 8px;
  }
  .mobile-menu-btn {
    width: 38px;
    height: 38px;
  }
}
</style>
