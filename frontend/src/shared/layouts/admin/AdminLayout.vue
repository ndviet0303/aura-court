<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { setAppLocale, type AppLocale } from '@/i18n'
import { applySeo } from '@/lib/seo'
import { useTenantCapabilities } from '@/features/admin/composables/useTenantCapabilities'
import CapabilityRouteView from './CapabilityRouteView.vue'
import { dashboardItem, navGroups, type NavGroup, type NavItem } from './adminNav'

const { t, locale } = useI18n()
const auth = useAuthStore()
const { isEnabled } = useTenantCapabilities()

const route = useRoute()

const isMobileSidebarOpen = ref(false)
const searchQuery = ref('')

const currentLocale = computed(() => (locale.value as AppLocale) || 'vi')

function toggleLanguage(target: AppLocale) {
  if (currentLocale.value === target) return
  setAppLocale(target)
  applySeo(route)
}

const userInitial = computed(() =>
  (auth.user?.name || auth.user?.email || 'A').slice(0, 1).toUpperCase(),
)

const userRoleText = computed(() => {
  if (auth.user?.role === 'admin') {
    return t('admin.nav.adminBadge')
  }
  return t('admin.nav.managerBadge')
})

function closeMobileSidebar() {
  isMobileSidebarOpen.value = false
}

function isItemPermitted(item: NavItem): boolean {
  if (!auth.user) return true
  const userAny = auth.user as any
  const isPlatformAdmin = auth.user.role === 'admin' || userAny.capabilities?.is_platform_admin
  // A platform admin sees organization items only through their own active owner/staff membership.
  const activeOrgs =
    (userAny.organizations as Array<any> | undefined)?.filter((o: any) => o.status === 'active') ||
    []
  const isOwner =
    activeOrgs.some((o: any) => o.role === 'organization_owner') ||
    userAny.capabilities?.can_manage_venues === true
  const isStaff = activeOrgs.some((o: any) => o.role === 'staff')

  // Owner-only: courts, contracts, venues, pricing, membership, organization profile & staff, payment exceptions
  if (
    [
      '/admin/manage',
      '/admin/contracts',
      '/admin/venues',
      '/admin/pricing',
      '/admin/membership',
      '/admin/organization',
      '/admin/staff',
      '/admin/payment-exceptions',
    ].includes(item.path)
  ) {
    return isOwner
  }
  // Owner or staff: schedule, POS, shifts, inventory, tournaments
  if (
    [
      '/admin/schedule',
      '/admin/pos',
      '/pos',
      '/admin/shifts',
      '/admin/inventory',
      '/admin/tournaments',
    ].includes(item.path)
  ) {
    return isOwner || isStaff
  }
  // Payouts finance and KYC/users/disputes stay platform admin only
  return !!isPlatformAdmin
}

const permittedNavGroups = computed(() => {
  return navGroups
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) => isItemPermitted(item) && (!item.capability || isEnabled(item.capability)),
      ),
    }))
    .filter((group) => group.items.length > 0)
})

const isDashboardPermitted = computed(() => {
  if (!auth.user) return true
  const userAny = auth.user as any
  return auth.user.role === 'admin' || userAny.capabilities?.is_platform_admin === true
})
const expandedGroups = ref<Record<string, boolean>>({
  courts: true,
  pos: false,
  finance: false,
  tournaments: false,
  organization: false,
})

function isItemActive(item: NavItem): boolean {
  const currentPath = route.path || ''
  if (item.matchExact || item.path === '/admin') {
    return currentPath === item.path
  }
  return currentPath === item.path || currentPath.startsWith(item.path + '/')
}

function isGroupActive(group: NavGroup): boolean {
  return group.items.some((item) => isItemActive(item))
}

function toggleGroup(groupId: string) {
  expandedGroups.value[groupId] = !expandedGroups.value[groupId]
}

function expandActiveGroup() {
  const currentPath = route.path || ''
  if (currentPath === '/admin') return

  for (const group of navGroups) {
    if (group.items.some((item) => isItemActive(item))) {
      expandedGroups.value[group.id] = true
    }
  }
}

watch(
  () => route.path,
  () => {
    expandActiveGroup()
  },
  { immediate: true },
)

// Search filter logic
const isSearching = computed(() => searchQuery.value.trim().length > 0)

interface SearchResultItem extends NavItem {
  groupTitleKey: string
}

const searchResults = computed<SearchResultItem[]>(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return []

  const results: SearchResultItem[] = []

  // Check dashboard item
  if (isDashboardPermitted.value) {
    const dashLabel = t(dashboardItem.labelKey).toLowerCase()
    if (dashLabel.includes(query) || 'dashboard'.includes(query) || 'tổng quan'.includes(query)) {
      results.push({ ...dashboardItem, groupTitleKey: 'admin.nav.overviewSection' })
    }
  }

  // Check all permitted grouped items
  for (const group of permittedNavGroups.value) {
    const groupTitle = t(group.titleKey).toLowerCase()
    for (const item of group.items) {
      const itemLabel = t(item.labelKey).toLowerCase()
      if (
        itemLabel.includes(query) ||
        groupTitle.includes(query) ||
        item.path.toLowerCase().includes(query)
      ) {
        results.push({ ...item, groupTitleKey: group.titleKey })
      }
    }
  }

  return results
})
</script>

<template>
  <div class="admin-workspace-layout">
    <!-- Mobile Sidebar Toggle Overlay -->
    <div v-if="isMobileSidebarOpen" class="sidebar-backdrop" @click="closeMobileSidebar"></div>

    <!-- Left Admin Sidebar -->
    <aside class="admin-sidebar" :class="{ 'is-open': isMobileSidebarOpen }">
      <!-- Sidebar Header with Brand and Language Switcher -->
      <div class="sidebar-header">
        <div class="sidebar-brand-wrapper">
          <div class="sidebar-brand">
            <div class="brand-icon-box">
              <span class="material-symbols-outlined icon-22">grid_view</span>
            </div>
            <div class="brand-text">
              <span class="brand-title">AURA CONTROL</span>
              <span class="brand-subtitle">{{ t('admin.nav.adminSuiteSubtitle') }}</span>
            </div>
          </div>

          <!-- Compact Language Switcher Pill -->
          <div class="lang-pill-group" role="group" aria-label="Language Selector">
            <button
              type="button"
              class="lang-pill-btn"
              :class="{ active: currentLocale === 'vi' }"
              title="Tiếng Việt"
              @click="toggleLanguage('vi')"
            >
              VI
            </button>
            <button
              type="button"
              class="lang-pill-btn"
              :class="{ active: currentLocale === 'en' }"
              title="English"
              @click="toggleLanguage('en')"
            >
              EN
            </button>
          </div>
        </div>

        <button
          type="button"
          class="btn-close-sidebar lg:hidden"
          title="Close Sidebar"
          @click="closeMobileSidebar"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Quick Search Input -->
      <div class="sidebar-search-box">
        <div class="search-input-wrap">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            class="search-input"
            :placeholder="t('admin.nav.searchPlaceholder')"
            aria-label="Search Admin Menu"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="btn-clear-search"
            title="Clear"
            @click="searchQuery = ''"
          >
            <span class="material-symbols-outlined icon-16">close</span>
          </button>
        </div>
      </div>

      <!-- Navigation Links Area -->
      <div class="sidebar-scroll-area">
        <!-- Search Results View -->
        <template v-if="isSearching">
          <div class="nav-section-label">
            {{
              searchResults.length > 0
                ? `${t('admin.nav.quickSearch')} (${searchResults.length})`
                : t('admin.nav.quickSearch')
            }}
          </div>

          <div v-if="searchResults.length === 0" class="no-search-results">
            <span class="material-symbols-outlined no-result-icon">search_off</span>
            <p>{{ t('admin.nav.noSearchResults') }}</p>
          </div>

          <nav v-else class="sidebar-nav" aria-label="Search Results Navigation">
            <RouterLink
              v-for="item in searchResults"
              :key="item.id"
              :to="item.path"
              class="sidebar-nav-item"
              :class="{ active: isItemActive(item) }"
              @click="closeMobileSidebar"
            >
              <span class="material-symbols-outlined nav-icon">{{ item.icon }}</span>
              <div class="search-item-info">
                <span class="nav-label">{{ t(item.labelKey) }}</span>
                <span class="search-parent-badge">{{ t(item.groupTitleKey) }}</span>
              </div>
            </RouterLink>
          </nav>
        </template>

        <!-- Standard Grouped Accordion Navigation View -->
        <template v-else>
          <!-- Standalone Dashboard / Analytics -->
          <template v-if="isDashboardPermitted">
            <div class="nav-section-label">{{ t('admin.nav.overviewSection') }}</div>
            <nav class="sidebar-nav mb-2" aria-label="Dashboard Navigation">
              <RouterLink
                :to="dashboardItem.path"
                class="sidebar-nav-item"
                :class="{ active: isItemActive(dashboardItem) }"
                @click="closeMobileSidebar"
              >
                <span class="material-symbols-outlined nav-icon">{{ dashboardItem.icon }}</span>
                <span class="nav-label font-bold">{{ t(dashboardItem.labelKey) }}</span>
              </RouterLink>
            </nav>
          </template>

          <!-- Collapsible Groups -->
          <div class="accordion-groups-container">
            <div
              v-for="group in permittedNavGroups"
              :key="group.id"
              class="nav-group-block"
              :class="{
                'is-group-open': expandedGroups[group.id],
                'has-active-child': isGroupActive(group),
              }"
            >
              <!-- Group Accordion Trigger Header -->
              <button
                type="button"
                class="group-header-btn"
                :class="{ 'is-active-parent': isGroupActive(group) }"
                :aria-expanded="expandedGroups[group.id]"
                @click="toggleGroup(group.id)"
              >
                <div class="group-header-left">
                  <span class="material-symbols-outlined group-icon">{{ group.icon }}</span>
                  <span class="group-title">{{ t(group.titleKey) }}</span>
                </div>

                <div class="group-header-right">
                  <span
                    v-if="!expandedGroups[group.id] && isGroupActive(group)"
                    class="active-indicator-dot"
                    :title="t('admin.nav.activeBadge')"
                  ></span>
                  <span class="material-symbols-outlined group-chevron">expand_more</span>
                </div>
              </button>

              <!-- Group Accordion Content Submenu -->
              <div class="group-items-wrapper">
                <div class="group-items-inner">
                  <nav class="group-subnav" :aria-label="t(group.titleKey)">
                    <RouterLink
                      v-for="item in group.items"
                      :key="item.id"
                      :to="item.path"
                      class="sidebar-subnav-item"
                      :class="{ active: isItemActive(item) }"
                      @click="closeMobileSidebar"
                    >
                      <span class="subnav-bullet"></span>
                      <span class="material-symbols-outlined subnav-icon">{{ item.icon }}</span>
                      <span class="subnav-label">{{ t(item.labelKey) }}</span>
                    </RouterLink>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Sidebar Footer User Profile & Back to Home -->
      <div class="sidebar-footer">
        <RouterLink to="/" class="btn-back-home" :title="t('admin.nav.backToPlayerApp')">
          <span class="material-symbols-outlined icon-18">arrow_back</span>
          <span>{{ t('admin.nav.backToPlayerApp') }}</span>
        </RouterLink>

        <div class="user-profile-snippet">
          <div class="user-avatar-circle">
            {{ userInitial }}
          </div>
          <div class="user-info-text">
            <span class="user-name">{{ auth.user?.name || 'Administrator' }}</span>
            <span class="user-role">{{ userRoleText }}</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Workspace Container -->
    <div class="admin-main-container">
      <!-- Mobile Bar with Toggle Button -->
      <div class="mobile-admin-bar lg:hidden">
        <button type="button" class="btn-toggle-menu" @click="isMobileSidebarOpen = true">
          <span class="material-symbols-outlined">menu</span>
          <span class="font-semibold text-sm">AURA CONTROL</span>
        </button>

        <!-- Compact Language Switcher on Mobile Bar -->
        <div class="lang-pill-group ml-auto" role="group" aria-label="Language Selector">
          <button
            type="button"
            class="lang-pill-btn"
            :class="{ active: currentLocale === 'vi' }"
            @click="toggleLanguage('vi')"
          >
            VI
          </button>
          <button
            type="button"
            class="lang-pill-btn"
            :class="{ active: currentLocale === 'en' }"
            @click="toggleLanguage('en')"
          >
            EN
          </button>
        </div>
      </div>

      <!-- Page Content Slot / Nested RouterView -->
      <div class="admin-page-content">
        <slot>
          <CapabilityRouteView />
        </slot>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-workspace-layout {
  display: flex;
  height: calc(100vh - 78px);
  max-height: calc(100vh - 78px);
  overflow: hidden;
  background: var(--bg, #18191d);
  color: var(--ink, #191c1d);
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

/* Admin Sidebar Container */
.admin-sidebar {
  width: 272px;
  background: var(--surface, #ffffff);
  border-right: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  height: 100%;
  overflow: hidden;
  z-index: 900;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@media (max-width: 1023px) {
  .admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    height: 100vh;
    z-index: 1100;
    transform: translateX(-100%);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
  }

  .admin-sidebar.is-open {
    transform: translateX(0);
  }
}

/* Sidebar Header */
.sidebar-header {
  padding: 16px 14px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.sidebar-brand-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 8px;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.brand-icon-box {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

:global(html.dark) .brand-icon-box {
  background: #53333e;
  color: #f8b4c4;
}

.brand-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.brand-title {
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: var(--primary, #78555e);
  line-height: 1.2;
  white-space: nowrap;
}

:global(html.dark) .brand-title {
  color: #f8b4c4;
  text-shadow: 0 0 10px rgba(248, 180, 196, 0.35);
}

.brand-subtitle {
  font-size: 10.5px;
  color: var(--muted, #6c757d);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .brand-subtitle {
  color: #a0a6b1;
}

/* Language Pill Switcher */
.lang-pill-group {
  display: inline-flex;
  align-items: center;
  background: var(--surface-subtle, #f1f3f5);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 9999px;
  padding: 2px;
  gap: 2px;
  flex-shrink: 0;
}

:global(html.dark) .lang-pill-group {
  background: #252830;
  border-color: #3b3f4a;
}

.lang-pill-btn {
  border: none;
  background: transparent;
  padding: 3px 8px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted, #6c757d);
  cursor: pointer;
  transition: all 0.15s ease;
  line-height: 1.2;
}

.lang-pill-btn:hover {
  color: var(--ink, #191c1d);
}

.lang-pill-btn.active {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  box-shadow: 0 1px 4px rgba(107, 45, 63, 0.3);
}

:global(html.dark) .lang-pill-btn.active {
  background: var(--primary, #e58e9f);
  color: #1c0a10;
  font-weight: 800;
}

.btn-close-sidebar {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

/* Sidebar Search */
.sidebar-search-box {
  padding: 10px 12px 6px;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--surface-subtle, #f3f4f6);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 10px;
  padding: 0 8px;
  transition: all 0.15s ease;
}

:global(html.dark) .search-input-wrap {
  background: #252830;
  border-color: #383c48;
}

.search-input-wrap:focus-within {
  border-color: var(--primary, #6b2d3f);
  background: var(--surface, #ffffff);
  box-shadow: 0 0 0 2px rgba(107, 45, 63, 0.15);
}

:global(html.dark) .search-input-wrap:focus-within {
  background: #1e2026;
  border-color: #e58e9f;
  box-shadow: 0 0 0 2px rgba(229, 142, 159, 0.2);
}

.search-icon {
  font-size: 17px;
  color: var(--muted, #94a3b8);
  margin-right: 6px;
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  padding: 7px 0;
  font-size: 12px;
  color: var(--ink, #191c1d);
  min-width: 0;
}

:global(html.dark) .search-input {
  color: #f3f4f6;
}

.search-input::placeholder {
  color: var(--muted, #94a3b8);
  font-size: 11.5px;
}

.btn-clear-search {
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-clear-search:hover {
  color: var(--ink, #191c1d);
}

/* Scroll Area */
.sidebar-scroll-area {
  flex: 1;
  overflow-y: auto;
  padding: 10px 10px 16px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sidebar-scroll-area::-webkit-scrollbar {
  width: 5px;
}

.sidebar-scroll-area::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
  border-radius: 4px;
}

.nav-section-label {
  font-size: 10px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted, #94a3b8);
  padding: 8px 10px 4px;
}

:global(html.dark) .nav-section-label {
  color: #8b92a1;
}

/* Nav Item (Top-level & Flat Results) */
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sidebar-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8.5px 12px;
  border-radius: 10px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted, #64748b);
  text-decoration: none;
  transition: all 0.15s ease;
}

.sidebar-nav-item:hover {
  background: var(--surface-subtle, #f1f5f9);
  color: var(--ink, #0f172a);
}

.sidebar-nav-item.active {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 3px 12px rgba(107, 45, 63, 0.22);
}

:global(html.dark) .sidebar-nav-item {
  color: #cbd5e1;
}

:global(html.dark) .sidebar-nav-item:hover {
  background: #282b34;
  color: #ffffff;
}

:global(html.dark) .sidebar-nav-item.active {
  background: var(--primary, #e58e9f);
  color: #1c0a10;
  font-weight: 800;
  box-shadow: 0 3px 12px rgba(229, 142, 159, 0.3);
}

.nav-icon {
  font-size: 19px;
  flex-shrink: 0;
}

.nav-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Search results styles */
.search-item-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.search-parent-badge {
  font-size: 10px;
  color: var(--muted, #94a3b8);
  font-weight: 500;
}

.sidebar-nav-item.active .search-parent-badge {
  color: rgba(255, 255, 255, 0.85);
}

:global(html.dark) .sidebar-nav-item.active .search-parent-badge {
  color: rgba(28, 10, 16, 0.8);
}

.no-search-results {
  padding: 24px 12px;
  text-align: center;
  color: var(--muted, #94a3b8);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.no-result-icon {
  font-size: 28px;
  opacity: 0.6;
}

/* Accordion Groups */
.accordion-groups-container {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.nav-group-block {
  border-radius: 10px;
  transition: background-color 0.15s ease;
}

.group-header-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8.5px 10px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--ink, #334155);
  font-size: 12.5px;
  font-weight: 650;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
}

.group-header-btn:hover {
  background: var(--surface-subtle, #f1f5f9);
  color: var(--ink, #0f172a);
}

:global(html.dark) .group-header-btn {
  color: #e2e8f0;
}

:global(html.dark) .group-header-btn:hover {
  background: #282b34;
  color: #ffffff;
}

.group-header-btn.is-active-parent {
  color: var(--primary, #6b2d3f);
  font-weight: 750;
}

:global(html.dark) .group-header-btn.is-active-parent {
  color: #f8b4c4;
}

.group-header-left {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}

.group-icon {
  font-size: 18px;
  color: var(--muted, #64748b);
  flex-shrink: 0;
}

.group-header-btn.is-active-parent .group-icon {
  color: var(--primary, #6b2d3f);
}

:global(html.dark) .group-icon {
  color: #94a3b8;
}

:global(html.dark) .group-header-btn.is-active-parent .group-icon {
  color: #f8b4c4;
}

.group-title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12px;
  letter-spacing: 0.01em;
}

.group-header-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.active-indicator-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--primary, #6b2d3f);
  box-shadow: 0 0 6px rgba(107, 45, 63, 0.6);
}

:global(html.dark) .active-indicator-dot {
  background: #e58e9f;
  box-shadow: 0 0 6px rgba(229, 142, 159, 0.7);
}

.group-chevron {
  font-size: 18px;
  color: var(--muted, #94a3b8);
  transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-group-block.is-group-open .group-chevron {
  transform: rotate(180deg);
}

/* Accordion Expand/Collapse Animation */
.group-items-wrapper {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-group-block.is-group-open .group-items-wrapper {
  grid-template-rows: 1fr;
}

.group-items-inner {
  overflow: hidden;
}

.group-subnav {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 3px 0 6px 14px;
  margin-left: 12px;
  border-left: 1.5px solid var(--border, #e2e8f0);
}

:global(html.dark) .group-subnav {
  border-left-color: #353945;
}

.sidebar-subnav-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6.5px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 550;
  color: var(--muted, #64748b);
  text-decoration: none;
  transition: all 0.15s ease;
  position: relative;
}

.sidebar-subnav-item:hover {
  background: var(--surface-subtle, #f1f5f9);
  color: var(--ink, #0f172a);
}

:global(html.dark) .sidebar-subnav-item {
  color: #cbd5e1;
}

:global(html.dark) .sidebar-subnav-item:hover {
  background: #262932;
  color: #ffffff;
}

.sidebar-subnav-item.active {
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.22);
}

:global(html.dark) .sidebar-subnav-item.active {
  background: var(--primary, #e58e9f);
  color: #1c0a10;
  font-weight: 800;
  box-shadow: 0 2px 8px rgba(229, 142, 159, 0.3);
}

.subnav-bullet {
  display: none;
}

.subnav-icon {
  font-size: 16px;
  flex-shrink: 0;
  opacity: 0.85;
}

.sidebar-subnav-item.active .subnav-icon {
  opacity: 1;
}

.subnav-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 12px 12px 14px;
  border-top: 1px solid var(--border, #e0e4ea);
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: var(--surface-subtle, #f8f9fa);
}

:global(html.dark) .sidebar-footer {
  background: #18191d;
  border-color: var(--border, #353942);
}

.btn-back-home {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #64748b);
  text-decoration: none;
  padding: 6px 8px;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.btn-back-home:hover {
  background: var(--surface, #ffffff);
  color: var(--ink, #0f172a);
}

:global(html.dark) .btn-back-home {
  color: #94a3b8;
}

:global(html.dark) .btn-back-home:hover {
  background: #252830;
  color: #ffffff;
}

.user-profile-snippet {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 4px 6px;
}

.user-avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

:global(html.dark) .user-avatar-circle {
  background: #36141d;
  color: var(--primary, #e58e9f);
}

.user-info-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink, #0f172a);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .user-name {
  color: #ffffff;
}

.user-role {
  font-size: 10.5px;
  color: var(--muted, #64748b);
}

:global(html.dark) .user-role {
  color: #f8b4c4 !important;
  font-weight: 600;
}

:global(html.dark) .admin-sidebar {
  background: #1e2025;
  border-color: #353942;
}

/* Main Container */
.admin-main-container {
  flex: 1;
  min-width: 0;
  height: 100%;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.mobile-admin-bar {
  padding: 10px 14px;
  background: var(--surface, #ffffff);
  border-bottom: 1px solid var(--border, #e0e4ea);
  display: flex;
  align-items: center;
}

:global(html.dark) .mobile-admin-bar {
  background: #1e2025;
  border-color: #353942;
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

:global(html.dark) .btn-toggle-menu {
  background: #252830;
  border-color: #3b3f4a;
  color: #f1f5f9;
}

.admin-page-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 0;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

.icon-22 {
  font-size: 22px;
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
