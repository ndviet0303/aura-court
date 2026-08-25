<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const route = (() => {
  try {
    return useRoute() || { path: window.location.pathname }
  } catch {
    return { path: window.location.pathname }
  }
})()

const isMobileSidebarOpen = ref(false)

const userInitial = computed(() => {
  return (auth.user?.name || auth.user?.email || 'A').slice(0, 1).toUpperCase()
})

function closeMobileSidebar() {
  isMobileSidebarOpen.value = false
}
</script>

<template>
  <div class="admin-workspace-layout">
    <!-- Mobile Sidebar Toggle Overlay -->
    <div
      v-if="isMobileSidebarOpen"
      class="sidebar-backdrop"
      @click="closeMobileSidebar"
    ></div>

    <!-- Left Admin Sidebar -->
    <aside class="admin-sidebar" :class="{ 'is-open': isMobileSidebarOpen }">
      <!-- Sidebar Brand Header -->
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <div class="brand-icon-box">
            <span class="material-symbols-outlined icon-22">grid_view</span>
          </div>
          <div class="brand-text">
            <span class="brand-title">AURA CONTROL</span>
            <span class="brand-subtitle">Facility Management</span>
          </div>
        </div>

        <button
          type="button"
          class="btn-close-sidebar lg:hidden"
          title="Đóng sidebar"
          @click="closeMobileSidebar"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Navigation Links -->
      <div class="sidebar-scroll-area">
        <div class="nav-section-label">VẬN HÀNH CHÍNH</div>
        <nav class="sidebar-nav" aria-label="Admin Primary Navigation">
          <RouterLink
            to="/admin"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/admin' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">analytics</span>
            <span class="nav-label">{{ t('admin.reports') }}</span>
          </RouterLink>

          <RouterLink
            to="/admin/manage"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/admin/manage' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">sports_tennis</span>
            <span class="nav-label">{{ t('admin.manageCourts') }}</span>
          </RouterLink>

          <RouterLink
            to="/admin/schedule"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/admin/schedule' || route.path === '/admin/operating-hours' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">schedule</span>
            <span class="nav-label">{{ t('admin.scheduleManage') }}</span>
          </RouterLink>

          <RouterLink
            to="/admin/contracts"
            class="sidebar-nav-item"
            :class="{ active: route.path.startsWith('/admin/contracts') }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">contract</span>
            <span class="nav-label">Hợp đồng định kỳ</span>
          </RouterLink>

          <RouterLink
            to="/admin/venues"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/admin/venues' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">location_on</span>
            <span class="nav-label">{{ t('admin.manageVenues') }}</span>
          </RouterLink>

          <RouterLink
            to="/admin/pricing"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/admin/pricing' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">price_change</span>
            <span class="nav-label">{{ t('admin.pricingCalendar') }}</span>
          </RouterLink>

          <RouterLink
            to="/admin/organization"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/admin/organization' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">corporate_fare</span>
            <span class="nav-label">{{ t('admin.organization') }}</span>
          </RouterLink>
        </nav>

        <div class="nav-section-label mt-4">MỞ RỘNG & DỊCH VỤ</div>
        <nav class="sidebar-nav" aria-label="Admin Secondary Navigation">
          <RouterLink
            to="/pos"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/pos' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">point_of_sale</span>
            <span class="nav-label">POS & Thu ngân</span>
          </RouterLink>

          <RouterLink
            to="/social-tournaments"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/social-tournaments' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">groups</span>
            <span class="nav-label">Người chơi & Giải đấu</span>
          </RouterLink>

          <RouterLink
            to="/advanced-booking"
            class="sidebar-nav-item"
            :class="{ active: route.path === '/advanced-booking' }"
            @click="closeMobileSidebar"
          >
            <span class="material-symbols-outlined nav-icon">event_repeat</span>
            <span class="nav-label">Lịch đặt định kỳ</span>
          </RouterLink>
        </nav>
      </div>

      <!-- Sidebar Footer User Profile & Back to Home -->
      <div class="sidebar-footer">
        <RouterLink to="/" class="btn-back-home" title="Về trang người chơi">
          <span class="material-symbols-outlined icon-18">arrow_back</span>
          <span>Về trang người chơi</span>
        </RouterLink>

        <div class="user-profile-snippet">
          <div class="user-avatar-circle">
            {{ userInitial }}
          </div>
          <div class="user-info-text">
            <span class="user-name">{{ auth.user?.name || 'Administrator' }}</span>
            <span class="user-role">{{ auth.user?.role === 'admin' ? 'Quản trị viên' : 'Quản lý cụm sân' }}</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Workspace Container -->
    <div class="admin-main-container">
      <!-- Mobile Bar with Toggle Button -->
      <div class="mobile-admin-bar lg:hidden">
        <button
          type="button"
          class="btn-toggle-menu"
          @click="isMobileSidebarOpen = true"
        >
          <span class="material-symbols-outlined">menu</span>
          <span class="font-semibold text-sm">Menu Quản trị</span>
        </button>
      </div>

      <!-- Page Content Slot / Nested RouterView -->
      <div class="admin-page-content">
        <slot>
          <RouterView />
        </slot>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-workspace-layout {
  display: flex;
  min-height: calc(100vh - 78px);
  background: var(--bg, #18191d);
  color: var(--ink);
  position: relative;
}

/* Sidebar Backdrop for Mobile */
.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 90;
}

/* Admin Sidebar */
.admin-sidebar {
  width: 260px;
  background: var(--surface, #ffffff);
  border-right: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: sticky;
  top: 78px;
  height: calc(100vh - 78px);
  z-index: 95;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@media (max-width: 1023px) {
  .admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    height: 100vh;
    transform: translateX(-100%);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
  }

  .admin-sidebar.is-open {
    transform: translateX(0);
  }
}

/* Sidebar Header */
.sidebar-header {
  padding: 20px 18px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.brand-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-text {
  display: flex;
  flex-direction: column;
}

.brand-title {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: var(--pink-dark, #78555e);
  line-height: 1.2;
}

.brand-subtitle {
  font-size: 11px;
  color: var(--muted, #6c757d);
  font-weight: 500;
}

.btn-close-sidebar {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

/* Scroll Area */
.sidebar-scroll-area {
  flex: 1;
  overflow-y: auto;
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-section-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted, #94a3b8);
  padding: 8px 12px 4px;
}

/* Nav Items */
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.sidebar-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  text-decoration: none;
  transition: all 0.15s ease;
}

.sidebar-nav-item:hover {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--ink, #191c1d);
}

.sidebar-nav-item.active {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.nav-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.nav-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 14px 14px 18px;
  border-top: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--surface-subtle, #f8f9fa);
}

.btn-back-home {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #6c757d);
  text-decoration: none;
  padding: 6px 8px;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.btn-back-home:hover {
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
}

.user-profile-snippet {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 6px;
}

.user-avatar-circle {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #78555e;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-info-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

/* Main Container */
.admin-main-container {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.mobile-admin-bar {
  padding: 12px 16px;
  background: var(--surface, #ffffff);
  border-bottom: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
}

.btn-toggle-menu {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #191c1d);
  cursor: pointer;
}

.admin-page-content {
  flex: 1;
  padding: 0;
}

@media (max-width: 1023px) {
  .lg\:hidden {
    display: flex;
  }
}

@media (min-width: 1024px) {
  .lg\:hidden {
    display: none;
  }
}
</style>
