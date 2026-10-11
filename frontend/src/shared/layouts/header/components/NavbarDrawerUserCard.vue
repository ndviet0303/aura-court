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

const isOwner = computed(() => {
  const userAny = auth.user as any
  if (!userAny) return false
  return (
    userAny.role === 'organization_owner' ||
    userAny.role === 'venue_manager' ||
    userAny.capabilities?.can_manage_venues === true ||
    (Array.isArray(userAny.organizations) &&
      userAny.organizations.some(
        (o: any) =>
          o.status === 'active' && (o.role === 'organization_owner' || o.role === 'venue_manager'),
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

const partnerStatus = computed(() => {
  const userAny = auth.user as any
  return userAny?.capabilities?.partner_application_status || ''
})

const userRoleBadge = computed(() => {
  if (auth.user?.role === 'admin') return 'ADMIN'
  if (isOwner.value) return 'CHỦ SÂN'
  if (hasPartnerIntentOrApp.value) {
    if (partnerStatus.value === 'submitted' || partnerStatus.value === 'in_review') {
      return 'CHỜ DUYỆT'
    }
    return 'ĐỐI TÁC'
  }
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
        <RouterLink to="/notifications" class="drawer-shortcut-btn" @click="emit('navigate')">
          <span class="material-symbols-outlined">notifications</span>
          <span>{{ $t('nav.notifications') }}</span>
        </RouterLink>
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
  background: var(--surface-subtle, #faf7f3);
  border-bottom: 1px solid var(--outline, #ebdccb);
  flex-shrink: 0;
}

:global(html.dark) .drawer-user-section {
  background: #201a1e;
  border-bottom-color: #382f35;
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
  border: 2px solid #dfba72;
  box-shadow: 0 2px 8px rgba(223, 186, 114, 0.25);
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
  padding: 2px 7px;
  border-radius: 4px;
  background: rgba(107, 45, 63, 0.08);
  color: #6b2d3f;
  border: 1px solid rgba(107, 45, 63, 0.18);
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
  background: #ffffff;
  border: 1px solid #ebdccb;
  color: var(--ink, #1c1618);
  font-size: 11.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.18s ease;
}

html.dark .drawer-shortcut-btn {
  background: #262024;
  border-color: #382f35;
  color: #faf7f3;
}

.drawer-shortcut-btn:hover {
  background: #faf7f3;
  color: #6b2d3f;
  border-color: #6b2d3f;
}

html.dark .drawer-shortcut-btn:hover {
  background: #2e242a;
  color: #dfba72;
  border-color: #dfba72;
}

.drawer-shortcut-btn.logout:hover {
  background: rgba(186, 26, 26, 0.1);
  color: #ba1a1a;
  border-color: rgba(186, 26, 26, 0.3);
}

html.dark .drawer-shortcut-btn.logout:hover {
  background: rgba(255, 218, 214, 0.15);
  color: #ffb4ab;
  border-color: rgba(255, 218, 214, 0.3);
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
  background: rgba(107, 45, 63, 0.08);
  color: #6b2d3f;
  border: 1px solid rgba(107, 45, 63, 0.15);
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
  background: linear-gradient(135deg, #6b2d3f 0%, #52212f 100%);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 3px 10px rgba(107, 45, 63, 0.3);
  transition: all 0.2s ease;
}

.drawer-login-cta:hover {
  filter: brightness(1.08);
  transform: translateY(-1px);
}

html.dark .drawer-name,
html.dark .guest-title {
  color: #faf7f3 !important;
}

html.dark .guest-desc {
  color: #a89fa4 !important;
}

html.dark .drawer-guest-icon {
  background: rgba(223, 186, 114, 0.15) !important;
  color: #dfba72 !important;
  border: 1px solid rgba(223, 186, 114, 0.35);
}

html.dark .drawer-badge {
  background: rgba(223, 186, 114, 0.15) !important;
  color: #dfba72 !important;
  border: 1px solid rgba(223, 186, 114, 0.35) !important;
}

html.dark .drawer-login-cta {
  background: linear-gradient(135deg, #6b2d3f 0%, #4a1d2b 100%) !important;
  color: #ffffff !important;
  border: 1px solid rgba(223, 186, 114, 0.3) !important;
  box-shadow: 0 3px 12px rgba(107, 45, 63, 0.45) !important;
}

html.dark .drawer-login-cta:hover {
  background: linear-gradient(135deg, #7d3549 0%, #52212f 100%) !important;
  color: #ffffff !important;
}
</style>
