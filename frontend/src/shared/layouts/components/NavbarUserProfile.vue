<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getAvatarUrl } from '@/lib/avatar'

const auth = useAuthStore()

const emit = defineEmits<{
  (e: 'logout'): void
}>()

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
      class="logout-icon-btn hide-on-mobile-action"
      :aria-label="$t('nav.wallet')"
      :title="$t('nav.wallet')"
    >
      <span class="material-symbols-outlined">account_balance_wallet</span>
    </RouterLink>
    <button
      type="button"
      class="logout-icon-btn hide-on-mobile-action"
      :aria-label="$t('nav.logout')"
      :title="$t('nav.logout')"
      @click="emit('logout')"
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
</template>

<style scoped>
.user-profile-group {
  display: flex;
  align-items: center;
  gap: 10px;
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
  width: 38px;
  height: 38px;
  border-radius: 50%;
  overflow: hidden;
  background: #FAF7F3;
  color: #6B2D3F;
  display: grid;
  place-items: center;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.12);
  border: 1.5px solid #ebdccb;
  flex-shrink: 0;
}

html.dark .user-avatar-btn {
  background: #251e22;
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
}

.user-avatar-btn:hover {
  transform: scale(1.05);
  border-color: #6B2D3F;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.22);
}

html.dark .user-avatar-btn:hover {
  border-color: #dfba72;
  box-shadow: 0 4px 14px rgba(223, 186, 114, 0.25);
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
  color: var(--muted, #6e6668);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: all 0.2s ease;
  text-decoration: none;
}

html.dark .logout-icon-btn {
  color: #a89fa4;
}

.logout-icon-btn:hover {
  background: rgba(186, 26, 26, 0.1);
  color: #ba1a1a;
}

html.dark .logout-icon-btn:hover {
  background: rgba(255, 218, 214, 0.15);
  color: #ffb4ab;
}

.logout-icon-btn .material-symbols-outlined {
  font-size: 19px;
}

@media (max-width: 1080px) {
  .user-meta,
  .hide-on-mobile-action {
    display: none !important;
  }
}

@media (max-width: 720px) {
  .user-avatar-btn {
    width: 36px;
    height: 36px;
  }
}
</style>
