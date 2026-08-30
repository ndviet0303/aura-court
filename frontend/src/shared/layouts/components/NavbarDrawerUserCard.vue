<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

const emit = defineEmits<{
  (e: 'navigate'): void
  (e: 'logout'): void
}>()

const auth = useAuthStore()

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
</script>

<template>
  <div class="drawer-user-section">
    <div v-if="auth.user" class="drawer-user-card">
      <div class="drawer-user-row">
        <div class="drawer-avatar-wrap">
          <img :src="defaultAvatarUrl" :alt="userName" class="drawer-avatar-img" />
        </div>
        <div class="drawer-user-info">
          <span class="drawer-name">{{ userName }}</span>
          <span class="drawer-badge">{{ userRoleBadge }}</span>
        </div>
      </div>
      <div class="drawer-user-shortcuts">
        <RouterLink to="/wallet" class="drawer-shortcut-btn" @click="emit('navigate')">
          <span class="material-symbols-outlined">account_balance_wallet</span>
          <span>{{ $t('nav.wallet') }}</span>
        </RouterLink>
        <RouterLink to="/profile" class="drawer-shortcut-btn" @click="emit('navigate')">
          <span class="material-symbols-outlined">person</span>
          <span>{{ $t('nav.profile') }}</span>
        </RouterLink>
        <button type="button" class="drawer-shortcut-btn logout" @click="emit('logout')">
          <span class="material-symbols-outlined">logout</span>
          <span>{{ $t('nav.logout') }}</span>
        </button>
      </div>
    </div>

    <div v-else class="drawer-guest-card">
      <div class="drawer-guest-content">
        <div class="drawer-guest-icon">
          <span class="material-symbols-outlined">sports_tennis</span>
        </div>
        <div class="drawer-guest-meta">
          <span class="guest-title">{{ $t('nav.guest') }}</span>
          <span class="guest-desc">{{ $t('nav.loginPrompt') }}</span>
        </div>
      </div>
      <RouterLink to="/login" class="drawer-login-cta" @click="emit('navigate')">
        <span>{{ $t('nav.login') }}</span>
        <span class="material-symbols-outlined">arrow_forward</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.drawer-user-section {
  padding: 14px 18px;
  background: var(--surface-subtle, #f7f3ed);
  border-bottom: 1px solid var(--outline, #dcd3c5);
  flex-shrink: 0;
}

:global(html.dark) .drawer-user-section {
  background: #201b1e;
}

.drawer-user-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.drawer-user-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.drawer-avatar-wrap {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid var(--accent, #c9a15a);
  box-shadow: 0 2px 8px rgba(201, 161, 90, 0.2);
  flex-shrink: 0;
}

.drawer-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.drawer-user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.drawer-name {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--ink, #1c1618);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.drawer-badge {
  align-self: flex-start;
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: 0.05em;
  padding: 1.5px 6px;
  border-radius: 4px;
  background: var(--primary-container, #f8e8ec);
  color: var(--on-primary-container, #4e1827);
  text-transform: uppercase;
}

.drawer-user-shortcuts {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 6px;
}

.drawer-shortcut-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 6px 8px;
  border-radius: 8px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #dcd3c5);
  color: var(--ink, #1c1618);
  font-size: 11.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.18s ease;
}

:global(html.dark) .drawer-shortcut-btn {
  background: #262024;
  border-color: #382f35;
  color: #faf7f3;
}

.drawer-shortcut-btn:hover {
  background: var(--primary-container, #f8e8ec);
  color: var(--primary, #6b2d3f);
  border-color: var(--primary, #6b2d3f);
}

.drawer-shortcut-btn.logout:hover {
  background: #ffebee;
  color: #c62828;
  border-color: #ef9a9a;
}

.drawer-shortcut-btn .material-symbols-outlined {
  font-size: 15px;
}

/* Guest Card */
.drawer-guest-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.drawer-guest-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.drawer-guest-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--primary-container, #f8e8ec);
  color: var(--primary, #6b2d3f);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.drawer-guest-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.guest-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #1c1618);
}

.guest-desc {
  font-size: 11px;
  color: var(--muted, #6e6668);
  line-height: 1.3;
}

.drawer-login-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 3px 10px rgba(107, 45, 63, 0.25);
  transition: all 0.2s ease;
}

.drawer-login-cta:hover {
  background: var(--primary-hover, #562332);
  transform: translateY(-1px);
}

:global(html.dark) .drawer-name,
:global(html.dark) .guest-title {
  color: #faf7f3 !important;
}

:global(html.dark) .guest-desc {
  color: #a89fa4 !important;
}

:global(html.dark) .drawer-guest-icon {
  background: rgba(229, 142, 159, 0.2) !important;
  color: #e58e9f !important;
  border: 1px solid rgba(229, 142, 159, 0.35);
}

:global(html.dark) .drawer-badge {
  background: rgba(229, 142, 159, 0.2) !important;
  color: #e58e9f !important;
  border: 1px solid rgba(229, 142, 159, 0.4) !important;
}

:global(html.dark) .drawer-login-cta {
  background: linear-gradient(135deg, #dfba72 0%, #c9a15a 100%) !important;
  color: #1c1202 !important;
  font-weight: 800 !important;
  box-shadow: 0 3px 12px rgba(201, 161, 90, 0.3) !important;
}

:global(html.dark) .drawer-login-cta:hover {
  background: linear-gradient(135deg, #e8c886 0%, #b88e45 100%) !important;
  color: #1a0e02 !important;
}
</style>
