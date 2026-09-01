<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

const auth = useAuthStore()
const route = useRoute()

const emit = defineEmits<{
  (e: 'logout'): void
}>()

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

const userRoleBadge = computed(() => {
  if (auth.user?.role === 'admin') return 'ADMIN'
  if (auth.user) return 'PRO MEMBER'
  return 'GUEST'
})

function toggleDropdown() {
  isDropdownOpen.value = !isDropdownOpen.value
}

function closeDropdown() {
  isDropdownOpen.value = false
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

      <span class="material-symbols-outlined chevron-icon" :class="{ 'is-rotated': isDropdownOpen }">
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

        <div class="dropdown-divider"></div>

        <!-- Navigation Links -->
        <div class="dropdown-links-list">
          <RouterLink to="/profile" class="dropdown-item" @click="closeDropdown">
            <span class="material-symbols-outlined item-ico">person</span>
            <span class="item-label">Hồ sơ cá nhân</span>
          </RouterLink>

          <RouterLink to="/wallet" class="dropdown-item" @click="closeDropdown">
            <span class="material-symbols-outlined item-ico">account_balance_wallet</span>
            <span class="item-label">Ví Aura & Nạp tiền</span>
          </RouterLink>

          <RouterLink to="/bookings" class="dropdown-item" @click="closeDropdown">
            <span class="material-symbols-outlined item-ico">calendar_month</span>
            <span class="item-label">Lịch sử đặt sân</span>
          </RouterLink>

          <RouterLink to="/tournaments" class="dropdown-item" @click="closeDropdown">
            <span class="material-symbols-outlined item-ico">emoji_events</span>
            <span class="item-label">Giải đấu & Tranh tài</span>
          </RouterLink>

          <RouterLink
            v-if="auth.user.role === 'admin'"
            to="/admin"
            class="dropdown-item admin-item"
            @click="closeDropdown"
          >
            <span class="material-symbols-outlined item-ico">admin_panel_settings</span>
            <span class="item-label">Quản trị hệ thống</span>
          </RouterLink>
        </div>

        <div class="dropdown-divider"></div>

        <!-- Logout Action -->
        <button type="button" class="dropdown-item logout-item" @click="onLogoutClick">
          <span class="material-symbols-outlined item-ico">logout</span>
          <span class="item-label">Đăng xuất</span>
        </button>
      </div>
    </transition>
  </div>

  <!-- Guest State (Login / Register Trigger) -->
  <div v-else class="user-profile-group">
    <RouterLink to="/login" class="user-meta">
      <span class="user-name">{{ $t('nav.guest') }}</span>
      <span class="user-badge">{{ $t('nav.login') }}</span>
    </RouterLink>
    <RouterLink to="/login" class="user-avatar-btn" :aria-label="$t('nav.login')" :title="$t('nav.login')">
      <span class="material-symbols-outlined">person</span>
    </RouterLink>
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
  color: #6B2D3F;
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
  border: 1px solid rgba(223, 186, 114, 0.35) !important;
  box-shadow: 0 0 10px rgba(223, 186, 114, 0.15) !important;
}

.user-avatar-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: #FAF7F3;
  color: #6B2D3F;
  display: grid;
  place-items: center;
  border: 1.5px solid #ebdccb;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.12);
}

html.dark .user-avatar-btn {
  background: #251e22;
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
}

.user-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.chevron-icon {
  font-size: 19px;
  color: var(--muted, #6e6668);
  transition: transform 0.2s ease, color 0.2s ease;
}

.chevron-icon.is-rotated {
  transform: rotate(180deg);
  color: var(--primary, #6B2D3F);
}

html.dark .chevron-icon.is-rotated {
  color: #dfba72;
}

/* Floating Dropdown Menu */
.user-dropdown-menu {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  min-width: 250px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2dad0);
  border-radius: 18px;
  padding: 10px;
  box-shadow: 0 12px 36px rgba(0, 0, 0, 0.14), 0 4px 12px rgba(0, 0, 0, 0.06);
  z-index: 1050;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  transform-origin: top right;
}

html.dark .user-dropdown-menu {
  background: #1c181b;
  border-color: #3b3238;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5), 0 4px 14px rgba(0, 0, 0, 0.3);
}

/* Dropdown User Header */
.dropdown-user-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
}

.header-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--outline, #ebdccb);
  flex-shrink: 0;
}

html.dark .header-avatar {
  border-color: rgba(223, 186, 114, 0.4);
}

.header-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
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
  font-weight: 750;
  color: var(--ink, #1c1618);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

html.dark .header-name {
  color: #faf7f3;
}

.header-email {
  font-size: 12px;
  color: var(--muted, #6e6668);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dropdown-divider {
  height: 1px;
  background: var(--outline, rgba(0, 0, 0, 0.08));
  margin: 6px 4px;
}

html.dark .dropdown-divider {
  background: rgba(255, 255, 255, 0.08);
}

/* Dropdown Menu Links */
.dropdown-links-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 9px 12px;
  border-radius: 10px;
  border: 0;
  background: transparent;
  color: var(--ink, #1c1618);
  font-size: 13.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

html.dark .dropdown-item {
  color: #e5dde2;
}

.dropdown-item:hover {
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  color: var(--primary, #6B2D3F);
}

html.dark .dropdown-item:hover {
  background: rgba(255, 255, 255, 0.07);
  color: #dfba72;
}

.dropdown-item .item-ico {
  font-size: 19px;
  color: var(--muted, #6e6668);
  transition: color 0.15s ease;
}

.dropdown-item:hover .item-ico {
  color: var(--primary, #6B2D3F);
}

html.dark .dropdown-item:hover .item-ico {
  color: #dfba72;
}

.admin-item {
  color: #9333ea;
}

html.dark .admin-item {
  color: #c084fc;
}

.admin-item:hover {
  background: rgba(147, 51, 234, 0.08);
  color: #7e22ce;
}

.admin-item .item-ico {
  color: #9333ea;
}

html.dark .admin-item .item-ico {
  color: #c084fc;
}

/* Logout Item */
.logout-item {
  color: #dc2626;
}

.logout-item .item-ico {
  color: #dc2626;
}

.logout-item:hover {
  background: rgba(220, 38, 38, 0.08);
  color: #b91c1c;
}

html.dark .logout-item {
  color: #f87171;
}

html.dark .logout-item .item-ico {
  color: #f87171;
}

html.dark .logout-item:hover {
  background: rgba(248, 113, 113, 0.12);
  color: #fca5a5;
}

/* Transition Animations */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.96);
}

/* Guest State */
.user-profile-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-profile-group .user-meta {
  text-decoration: none;
}

@media (max-width: 1080px) {
  .user-dropdown-trigger .user-meta {
    display: none !important;
  }
}
</style>
