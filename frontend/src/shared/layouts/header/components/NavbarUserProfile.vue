<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { isPartnerApplicationUnderReview } from '@/core/utils/partnerApplicationStatus'
import { useBookingStore } from '@/stores/booking'
import { useThemeStore } from '@/stores/theme'
import { getCurrentLocale, setAppLocale, formatCurrency, type AppLocale } from '@/i18n'
import { applySeo } from '@/lib/seo'
import { getAvatarUrl } from '@/lib/avatar'

const auth = useAuthStore()
const bookingStore = useBookingStore()
const themeStore = useThemeStore()
const route = useRoute()
const currentLocale = ref<AppLocale>(getCurrentLocale())

const emit = defineEmits<{
  (e: 'logout'): void
}>()

const accountLinks = [
  { to: '/profile', icon: 'person', label: 'nav.profile' },
  { to: '/notifications', icon: 'notifications', label: 'nav.notifications' },
  { to: '/messages', icon: 'chat', label: 'nav.messages' },
  { to: '/booking', icon: 'calendar_month', label: 'nav.history' },
  { to: '/tournaments', icon: 'emoji_events', label: 'nav.tournaments' },
]

const isDropdownOpen = ref(false)
const dropdownRoot = ref<HTMLElement | null>(null)

const userName = computed(() => {
  if (auth.user?.name) return auth.user.name
  return auth.user ? 'Thành viên' : 'Khách'
})

const userEmail = computed(() => {
  return auth.user?.email || ''
})

const defaultAvatarUrl = computed(() => {
  return getAvatarUrl(auth.user?.name, auth.user?.avatar_url)
})

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

const partnerRoute = computed(() => {
  if (isUnderReview.value) {
    return '/partner/application-status'
  }
  return '/partner-registration'
})

const userRoleBadge = computed(() => {
  if (auth.user?.role === 'admin') return 'ADMIN'
  if (isOwner.value) return 'CHỦ SÂN'
  if (hasPartnerIntentOrApp.value) {
    if (isUnderReview.value) {
      return 'CHỜ DUYỆT'
    }
    return 'ĐỐI TÁC'
  }
  if (auth.user) return 'PRO MEMBER'
  return 'GUEST'
})
const displayedBalance = computed(() => {
  if (typeof bookingStore.walletBalance === 'number' && bookingStore.walletBalance > 0) {
    return bookingStore.walletBalance
  }
  if (typeof (auth.user as any)?.wallet_balance === 'number') {
    return (auth.user as any).wallet_balance
  }
  return bookingStore.walletBalance || 0
})

function toggleDropdown() {
  isDropdownOpen.value = !isDropdownOpen.value
  if (isDropdownOpen.value && auth.walletEnabled) {
    bookingStore.fetchWalletBalance().catch(() => {})
  }
}

function closeDropdown() {
  isDropdownOpen.value = false
}

function handleLocaleChange(target: AppLocale) {
  setAppLocale(target)
  currentLocale.value = target
  applySeo(route)
}

function handleOutsideClick(event: MouseEvent) {
  if (dropdownRoot.value && !dropdownRoot.value.contains(event.target as Node)) {
    closeDropdown()
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isDropdownOpen.value) {
    closeDropdown()
  }
}

function onLogoutClick() {
  closeDropdown()
  emit('logout')
}

watch(() => route.path, closeDropdown)

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <!-- Authenticated User Dropdown -->
  <div v-if="auth.user" ref="dropdownRoot" class="user-dropdown-wrapper">
    <button
      type="button"
      class="user-dropdown-trigger"
      :class="{ 'is-active': isDropdownOpen }"
      aria-haspopup="true"
      :aria-expanded="isDropdownOpen"
      @click="toggleDropdown"
    >
      <div class="user-meta">
        <span class="user-name">{{ userName }}</span>
        <span class="user-badge">{{ userRoleBadge }}</span>
      </div>

      <div class="user-avatar-btn">
        <img :src="defaultAvatarUrl" :alt="userName" class="user-avatar-img" />
      </div>

      <span
        class="material-symbols-outlined chevron-icon"
        :class="{ 'is-rotated': isDropdownOpen }"
      >
        expand_more
      </span>
    </button>

    <!-- Floating Dropdown Menu Card -->
    <transition name="dropdown-fade">
      <div v-if="isDropdownOpen" class="user-dropdown-menu" role="menu">
        <!-- Header User Details -->
        <div class="dropdown-user-header">
          <img :src="defaultAvatarUrl" :alt="userName" class="header-avatar" />
          <div class="header-details">
            <div class="header-name-row">
              <strong class="header-name">{{ userName }}</strong>
              <span class="user-badge">{{ userRoleBadge }}</span>
            </div>
            <span class="header-email">{{ userEmail }}</span>
          </div>
        </div>

        <template v-if="auth.walletEnabled">
          <RouterLink to="/wallet" class="dropdown-wallet-card" @click="closeDropdown">
            <div class="wallet-card-header">
              <div class="wallet-badge-pill">
                <span class="material-symbols-outlined wallet-icon-glow"
                  >account_balance_wallet</span
                >
                <span class="wallet-title-txt">VÍ AURA</span>
              </div>
              <span class="btn-wallet-topup-luxury">
                <span class="material-symbols-outlined icon-14">add</span>
                <span>Nạp tiền</span>
              </span>
            </div>
            <div class="wallet-balance-display">
              <div class="balance-meta">
                <span class="balance-sub">Số dư khả dụng</span>
                <strong class="balance-amount font-mono">{{
                  formatCurrency(displayedBalance)
                }}</strong>
              </div>
              <span class="material-symbols-outlined wallet-arrow-icon">chevron_right</span>
            </div>
          </RouterLink>
        </template>

        <div class="dropdown-divider"></div>

        <!-- Navigation Links -->
        <div class="dropdown-links-list">
          <RouterLink
            v-for="link in accountLinks"
            :key="link.to"
            :to="link.to"
            class="dropdown-item"
            @click="closeDropdown"
          >
            <span class="material-symbols-outlined item-ico">{{ link.icon }}</span>
            <span class="item-label">{{ $t(link.label) }}</span>
          </RouterLink>

          <RouterLink
            v-if="auth.user.role === 'admin'"
            to="/admin"
            class="dropdown-item admin-item"
            @click="closeDropdown"
          >
            <span class="material-symbols-outlined item-ico">admin_panel_settings</span>
            <span class="item-label">{{ $t('nav.adminSystem') }}</span>
          </RouterLink>

          <RouterLink
            v-else-if="isOwner"
            to="/admin/organization"
            class="dropdown-item admin-item"
            @click="closeDropdown"
          >
            <span class="material-symbols-outlined item-ico">domain</span>
            <span class="item-label">{{ $t('nav.organization') }}</span>
          </RouterLink>

          <RouterLink
            v-else-if="hasPartnerIntentOrApp"
            :to="partnerRoute"
            class="dropdown-item admin-item"
            @click="closeDropdown"
          >
            <span class="material-symbols-outlined item-ico">storefront</span>
            <span class="item-label">{{
              isUnderReview ? 'Hồ sơ đối tác' : 'Thiết lập cơ sở'
            }}</span>
          </RouterLink>
        </div>

        <div class="dropdown-divider"></div>
        <!-- Preferences (Theme & Language) -->
        <div class="dropdown-preferences">
          <!-- Theme Row -->
          <div class="pref-row">
            <div class="pref-label">
              <span class="material-symbols-outlined pref-icon">palette</span>
              <span>{{ $t('nav.appearance') }}</span>
            </div>
            <div class="pref-segment-group">
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: themeStore.theme !== 'dark' }"
                @click="themeStore.theme === 'dark' && themeStore.toggleTheme()"
                :title="$t('common.theme.light')"
              >
                <span class="material-symbols-outlined btn-ico">light_mode</span>
                <span>{{ $t('nav.lightMode') }}</span>
              </button>
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: themeStore.theme === 'dark' }"
                @click="themeStore.theme !== 'dark' && themeStore.toggleTheme()"
                :title="$t('common.theme.dark')"
              >
                <span class="material-symbols-outlined btn-ico">dark_mode</span>
                <span>{{ $t('nav.darkMode') }}</span>
              </button>
            </div>
          </div>

          <!-- Language Row -->
          <div class="pref-row">
            <div class="pref-label">
              <span class="material-symbols-outlined pref-icon">translate</span>
              <span>{{ $t('nav.language') }}</span>
            </div>
            <div class="pref-segment-group">
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: currentLocale === 'vi' }"
                @click="handleLocaleChange('vi')"
                title="Tiếng Việt"
              >
                <span>VI</span>
              </button>
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: currentLocale === 'en' }"
                @click="handleLocaleChange('en')"
                title="English"
              >
                <span>EN</span>
              </button>
            </div>
          </div>
        </div>

        <div class="dropdown-divider"></div>

        <!-- Logout Action -->
        <button type="button" class="dropdown-item logout-item" @click="onLogoutClick">
          <span class="material-symbols-outlined item-ico">logout</span>
          <span class="item-label">{{ $t('nav.logout') }}</span>
        </button>
      </div>
    </transition>
  </div>

  <!-- Guest State (Login / Register Trigger + Quick Preferences) -->
  <div v-else ref="dropdownRoot" class="user-dropdown-wrapper">
    <button
      type="button"
      class="user-dropdown-trigger guest-trigger"
      :class="{ 'is-active': isDropdownOpen }"
      aria-haspopup="true"
      :aria-expanded="isDropdownOpen"
      @click="toggleDropdown"
    >
      <div class="user-meta">
        <span class="user-name">{{ $t('nav.guest') }}</span>
        <span class="user-badge">{{ $t('nav.login') }}</span>
      </div>

      <div class="user-avatar-btn">
        <span class="material-symbols-outlined">person</span>
      </div>

      <span
        class="material-symbols-outlined chevron-icon"
        :class="{ 'is-rotated': isDropdownOpen }"
      >
        expand_more
      </span>
    </button>

    <!-- Guest Dropdown Menu -->
    <transition name="dropdown-fade">
      <div v-if="isDropdownOpen" class="user-dropdown-menu guest-menu" role="menu">
        <div class="guest-menu-header">
          <span class="guest-menu-title">{{ $t('nav.guest') }}</span>
          <span class="guest-menu-subtitle">{{ $t('nav.loginPrompt') }}</span>
        </div>

        <div class="guest-actions">
          <RouterLink to="/login" class="guest-primary-btn" @click="closeDropdown">
            <span class="material-symbols-outlined">login</span>
            <span>{{ $t('nav.login') }}</span>
          </RouterLink>
        </div>

        <div class="dropdown-divider"></div>

        <!-- Preferences (Theme & Language) for Guest -->
        <div class="dropdown-preferences">
          <!-- Theme Row -->
          <div class="pref-row">
            <div class="pref-label">
              <span class="material-symbols-outlined pref-icon">palette</span>
              <span>{{ $t('nav.appearance') }}</span>
            </div>
            <div class="pref-segment-group">
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: themeStore.theme !== 'dark' }"
                @click="themeStore.theme === 'dark' && themeStore.toggleTheme()"
              >
                <span class="material-symbols-outlined btn-ico">light_mode</span>
                <span>{{ $t('nav.lightMode') }}</span>
              </button>
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: themeStore.theme === 'dark' }"
                @click="themeStore.theme !== 'dark' && themeStore.toggleTheme()"
              >
                <span class="material-symbols-outlined btn-ico">dark_mode</span>
                <span>{{ $t('nav.darkMode') }}</span>
              </button>
            </div>
          </div>

          <!-- Language Row -->
          <div class="pref-row">
            <div class="pref-label">
              <span class="material-symbols-outlined pref-icon">translate</span>
              <span>{{ $t('nav.language') }}</span>
            </div>
            <div class="pref-segment-group">
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: currentLocale === 'vi' }"
                @click="handleLocaleChange('vi')"
              >
                <span>VI</span>
              </button>
              <button
                type="button"
                class="pref-segment-btn"
                :class="{ active: currentLocale === 'en' }"
                @click="handleLocaleChange('en')"
              >
                <span>EN</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.user-dropdown-wrapper {
  position: relative;
  display: inline-block;
}

/* Dropdown Trigger Button */
.user-dropdown-trigger {
  display: flex;
  align-items: center;
  gap: 10px;
  background: transparent;
  border: 1px solid transparent;
  padding: 4px 8px 4px 10px;
  border-radius: 999px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  color: var(--ink, #1c1618);
}

.user-dropdown-trigger:hover,
.user-dropdown-trigger.is-active {
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  border-color: var(--outline, rgba(0, 0, 0, 0.08));
}

html.dark .user-dropdown-trigger:hover,
html.dark .user-dropdown-trigger.is-active {
  background: var(--surface-container, rgba(255, 255, 255, 0.06));
  border-color: var(--outline, rgba(255, 255, 255, 0.12));
}

.user-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  line-height: 1.25;
  max-width: 140px;
  min-width: 0;
}

/* Phones lack room for the name beside the logo and menu button; keep it for screen readers only. */
@media (max-width: 480px) {
  .user-meta {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
  }
}

.user-name {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: right;
}

html.dark .user-name {
  color: #ffffff !important;
}

.user-badge {
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 2px 7px;
  border-radius: 5px;
  background: rgba(107, 45, 63, 0.08);
  color: #6b2d3f;
  border: 1px solid rgba(107, 45, 63, 0.18);
  text-transform: uppercase;
  margin-top: 2px;
  white-space: nowrap;
  display: inline-block;
  line-height: 1.3;
}

html.dark .user-badge {
  background: rgba(223, 186, 114, 0.15) !important;
  color: #dfba72 !important;
  border-color: rgba(223, 186, 114, 0.3) !important;
}

.user-avatar-btn {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  overflow: hidden;
  background: #f8e8ec;
  border: 1.5px solid #6b2d3f;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

html.dark .user-avatar-btn {
  background: #361a24;
  border-color: #dfba72;
}

.user-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.chevron-icon {
  font-size: 19px;
  color: var(--muted, #6e6668);
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.chevron-icon.is-rotated {
  transform: rotate(180deg);
}

/* Floating Dropdown Card */
.user-dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 100;
  min-width: 285px;
  max-width: 320px;
  background: #ffffff;
  border: 1px solid #ebdccb;
  border-radius: 20px;
  box-shadow: 0 20px 48px rgba(107, 45, 63, 0.16);
  padding: 12px;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
}

html.dark .user-dropdown-menu {
  background: #1e181c !important;
  border-color: #382f35 !important;
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.75) !important;
}

/* Header User Details */
.dropdown-user-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 8px 10px;
}

.header-avatar {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  object-fit: cover;
  border: 1.5px solid #6b2d3f;
  flex-shrink: 0;
}

html.dark .header-avatar {
  border-color: #dfba72;
}

.header-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.header-name-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.header-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

html.dark .header-name {
  color: #ffffff;
}

.header-email {
  font-size: 11.5px;
  color: var(--muted, #6e6668);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 1px;
}

html.dark .header-email {
  color: #9c9298;
}

.dropdown-divider {
  height: 1px;
  background: var(--outline, #ebdccb);
  margin: 6px 0;
}

html.dark .dropdown-divider {
  background: #382f35;
}

/* Luxury Wallet Card in Dropdown */
.dropdown-wallet-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  margin: 4px 2px 8px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(219, 39, 119, 0.08) 0%, rgba(201, 161, 90, 0.1) 100%);
  border: 1px solid rgba(219, 39, 119, 0.2);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.dropdown-wallet-card:hover {
  border-color: rgba(219, 39, 119, 0.45);
  box-shadow: 0 4px 14px rgba(219, 39, 119, 0.15);
  transform: translateY(-1px);
}

html.dark .dropdown-wallet-card {
  background: linear-gradient(135deg, rgba(236, 72, 153, 0.12) 0%, rgba(201, 161, 90, 0.08) 100%);
  border-color: rgba(236, 72, 153, 0.25);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

html.dark .dropdown-wallet-card:hover {
  border-color: rgba(236, 72, 153, 0.5);
  box-shadow: 0 6px 18px rgba(236, 72, 153, 0.2);
}

.wallet-arrow-icon {
  font-size: 18px;
  color: var(--muted, #6e6668);
  transition:
    transform 0.2s ease,
    color 0.2s ease;
}

.dropdown-wallet-card:hover .wallet-arrow-icon {
  color: var(--pink-dark, #db2777);
  transform: translateX(2px);
}

html.dark .dropdown-wallet-card:hover .wallet-arrow-icon {
  color: #f472b6;
}

.wallet-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.wallet-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.wallet-icon-glow {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
}

html.dark .wallet-icon-glow {
  color: #f472b6;
}

.wallet-title-txt {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: var(--pink-dark, #db2777);
  text-transform: uppercase;
}

html.dark .wallet-title-txt {
  color: #f472b6;
}

.btn-wallet-topup-luxury {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 4px 10px;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--pink-dark, #db2777) 0%, #be185d 100%);
  color: #ffffff;
  font-size: 11.5px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 2px 6px rgba(219, 39, 119, 0.3);
  flex-shrink: 0;
}

.btn-wallet-topup-luxury:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(219, 39, 119, 0.4);
}

.wallet-balance-display {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.balance-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.balance-sub {
  font-size: 10.5px;
  color: var(--muted, #6e6668);
  font-weight: 500;
}

html.dark .balance-sub {
  color: #a89ba2;
}

.balance-amount {
  font-size: 18px;
  font-weight: 900;
  color: var(--ink, #1c1618);
  letter-spacing: -0.02em;
  line-height: 1.2;
}

html.dark .balance-amount {
  color: #ffffff;
}

.wallet-side-badge {
  margin-left: auto;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.08);
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid rgba(219, 39, 119, 0.15);
}

html.dark .wallet-side-badge {
  color: #f472b6;
  background: rgba(236, 72, 153, 0.15);
  border-color: rgba(236, 72, 153, 0.3);
}

/* Links List */
.dropdown-links-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 9px 12px;
  border-radius: 11px;
  color: var(--ink, #1c1618);
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 600;
  transition: all 0.16s ease;
  background: transparent;
  border: none;
  width: 100%;
  text-align: left;
  cursor: pointer;
  box-sizing: border-box;
}

html.dark .dropdown-item {
  color: #ded6dc;
}

.dropdown-item .item-ico {
  font-size: 19px;
  color: #6b2d3f;
  flex-shrink: 0;
  transition: color 0.16s ease;
}

html.dark .dropdown-item .item-ico {
  color: #dfba72;
}

.dropdown-item:hover,
.dropdown-item.router-link-active {
  background: #faf7f3;
  color: #6b2d3f;
}

html.dark .dropdown-item:hover,
html.dark .dropdown-item.router-link-active {
  background: #2b2228;
  color: #dfba72;
}

.dropdown-item.admin-item {
  color: #9e3650;
}

html.dark .dropdown-item.admin-item {
  color: #e59310;
}

.dropdown-item.admin-item .item-ico {
  color: #9e3650;
}

html.dark .dropdown-item.admin-item .item-ico {
  color: #e59310;
}

.dropdown-item.logout-item {
  color: #dc2626;
}

.dropdown-item.logout-item .item-ico {
  color: #dc2626;
}

.dropdown-item.logout-item:hover {
  background: rgba(220, 38, 38, 0.08);
  color: #b91c1c;
}

html.dark .dropdown-item.logout-item:hover {
  background: rgba(220, 38, 38, 0.15);
  color: #f87171;
}

/* Preferences Section (Theme & Language) */
.dropdown-preferences {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 6px 4px;
}

.pref-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.pref-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted, #6e6668);
}

html.dark .pref-label {
  color: #a89fa5;
}

.pref-icon {
  font-size: 17px;
  color: #6b2d3f;
}

html.dark .pref-icon {
  color: #dfba72;
}

.pref-segment-group {
  display: inline-flex;
  align-items: center;
  padding: 2px;
  background: var(--surface-subtle, #f5f0e8);
  border: 1px solid var(--outline, #ebdccb);
  border-radius: 999px;
  gap: 2px;
}

html.dark .pref-segment-group {
  background: #282025;
  border-color: #382f35;
}

.pref-segment-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: none;
  background: transparent;
  color: var(--muted, #6e6668);
  font-size: 11.5px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  cursor: pointer;
  transition: all 0.18s ease;
  line-height: 1;
}

html.dark .pref-segment-btn {
  color: #a89fa5;
}

.pref-segment-btn .btn-ico {
  font-size: 14px;
}

.pref-segment-btn.active {
  background: #6b2d3f;
  color: #ffffff;
  box-shadow: 0 1px 4px rgba(107, 45, 63, 0.25);
}

html.dark .pref-segment-btn.active {
  background: #dfba72;
  color: #191c1d;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
}

/* Guest Dropdown Menu */
.guest-menu {
  min-width: 230px;
}

.guest-menu-header {
  padding: 6px 8px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.guest-menu-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #1c1618);
}

html.dark .guest-menu-title {
  color: #ffffff;
}

.guest-menu-subtitle {
  font-size: 11.5px;
  color: var(--muted, #6e6668);
}

html.dark .guest-menu-subtitle {
  color: #9c9298;
}

.guest-actions {
  padding: 4px 6px;
}

.guest-primary-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 8px 14px;
  border-radius: 10px;
  background: linear-gradient(135deg, #6b2d3f 0%, #52212f 100%);
  color: #ffffff;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.25);
  box-sizing: border-box;
}

.guest-primary-btn:hover {
  filter: brightness(1.08);
  transform: translateY(-1px);
}

/* Dropdown Animation */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition:
    opacity 0.16s ease,
    transform 0.16s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.96);
}
</style>
