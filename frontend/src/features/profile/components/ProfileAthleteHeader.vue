<script setup lang="ts">
import type { User } from '@/core/types/common'
import type { ProfileTabType } from '../types/profile'

defineProps<{
  user: User | null
  initials: string
  avatarUrl: string
  activeTab: ProfileTabType
  isEmailVerified: boolean
  needsEmail?: boolean
  resendingVerification: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: ProfileTabType): void
  (e: 'resendVerification'): void
}>()

const TABS: { id: ProfileTabType; icon: string; label: string }[] = [
  { id: 'general', icon: 'person', label: 'profile.tabGeneral' },
  { id: 'security', icon: 'lock', label: 'profile.tabSecurity' },
  { id: 'membership', icon: 'workspace_premium', label: 'profile.tabMembership' },
  { id: 'shortcuts', icon: 'grid_view', label: 'profile.tabQuickLinks' },
]
</script>

<template>
  <div class="athlete-hero-card">
    <div class="athlete-top-bar">
      <div class="avatar-wrapper">
        <img :src="avatarUrl" :alt="user?.name || 'Aura Athlete'" class="athlete-avatar-img" />
        <span class="online-indicator" title="Tài khoản đang hoạt động"></span>
      </div>

      <div class="athlete-info-col">
        <div class="name-badge-row">
          <h1 class="athlete-name">{{ user?.name || 'Vận Động Viên Aura' }}</h1>
          <span class="role-badge font-mono" :class="{ 'role-admin': user?.role === 'admin' }">
            <span class="material-symbols-outlined role-icon">
              {{ user?.role === 'admin' ? 'admin_panel_settings' : 'sports_tennis' }}
            </span>
            {{ (user?.role === 'admin' ? 'ADMIN' : 'ATHLETE').toUpperCase() }}
          </span>

          <span v-if="user?.social_provider" class="provider-pill font-mono">
            {{ user.social_provider.toUpperCase() }}
          </span>
        </div>

        <div class="contact-meta-row">
          <div class="meta-item">
            <span class="material-symbols-outlined meta-ico">mail</span>
            <span>{{ user?.email || 'Chưa có email' }}</span>
          </div>

          <div class="meta-item">
            <span class="material-symbols-outlined meta-ico">call</span>
            <span>{{ user?.phone || 'Chưa cập nhật SĐT' }}</span>
          </div>

          <!-- Email Verification Status Badge -->
          <div v-if="isEmailVerified" class="verify-status-badge verified">
            <span class="material-symbols-outlined verify-ico">verified</span>
            <span>{{ $t('profile.emailVerified') }}</span>
          </div>
          <div v-else class="verify-status-badge unverified">
            <span class="material-symbols-outlined verify-ico">warning</span>
            <span>{{ $t('profile.emailUnverified') }}</span>
            <button
              v-if="!needsEmail"
              type="button"
              class="resend-btn"
              :disabled="resendingVerification"
              @click="emit('resendVerification')"
            >
              {{
                resendingVerification ? $t('common.submitting') : $t('profile.resendVerification')
              }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Clean Navigation Tabs (No Raw Emojis) -->
    <nav class="profile-tabs-nav" aria-label="Profile navigation tabs">
      <button
        v-for="tab in TABS"
        :key="tab.id"
        type="button"
        class="tab-item-btn"
        :class="{ 'is-active': activeTab === tab.id }"
        :data-testid="`profile-tab-${tab.id}`"
        @click="emit('update:activeTab', tab.id)"
      >
        <span class="material-symbols-outlined tab-ico">{{ tab.icon }}</span>
        <span class="tab-text">{{ $t(tab.label) }}</span>
      </button>
    </nav>
  </div>
</template>

<style scoped>
.athlete-hero-card {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 24px 28px 0;
  border-radius: 24px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  overflow: hidden;
}

html.dark .athlete-hero-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
}

.athlete-top-bar {
  display: flex;
  align-items: center;
  gap: 22px;
  width: 100%;
  min-width: 0;
}

.avatar-wrapper {
  position: relative;
  width: 76px;
  height: 76px;
  border-radius: 50%;
  border: 3px solid var(--outline, #ebdccb);
  background: #faf7f3;
  flex-shrink: 0;
  overflow: visible;
  box-shadow: 0 6px 16px rgba(107, 45, 63, 0.12);
}

html.dark .avatar-wrapper {
  border-color: rgba(223, 186, 114, 0.35);
  background: #251e22;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.athlete-avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}

.online-indicator {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #10b981;
  border: 2.5px solid var(--surface, #ffffff);
}

.athlete-info-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
  flex: 1;
}

.name-badge-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.athlete-name {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink, #1c1618);
}

html.dark .athlete-name {
  color: #faf7f3;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  padding: 3px 9px;
  border-radius: 6px;
  background: rgba(107, 45, 63, 0.08);
  color: #6b2d3f;
  border: 1px solid rgba(107, 45, 63, 0.2);
}

html.dark .role-badge {
  background: rgba(223, 186, 114, 0.15);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
}

.role-badge.role-admin {
  background: rgba(147, 51, 234, 0.1);
  color: #9333ea;
  border-color: rgba(147, 51, 234, 0.3);
}

html.dark .role-badge.role-admin {
  background: rgba(192, 132, 252, 0.15);
  color: #c084fc;
  border-color: rgba(192, 132, 252, 0.35);
}

.role-icon {
  font-size: 14px;
}

.provider-pill {
  font-size: 10px;
  font-weight: 750;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-container, rgba(0, 0, 0, 0.05));
  color: var(--muted, #6e6668);
  border: 1px solid var(--outline, #e2dad0);
}

.contact-meta-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13px;
  color: var(--muted, #6e6668);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.meta-ico {
  font-size: 16px;
  color: var(--muted, #6e6668);
}

.verify-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
}

.verify-status-badge.verified {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.verify-status-badge.unverified {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}

.verify-ico {
  font-size: 15px;
}

.resend-btn {
  background: none;
  border: none;
  color: var(--pink-dark, #db2777);
  font-weight: 700;
  font-size: 11.5px;
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
  margin-left: 2px;
}

.resend-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Tabs Navigation */
.profile-tabs-nav {
  display: flex;
  gap: 8px;
  border-top: 1px solid var(--outline, #e2dad0);
  margin: 0 -28px;
  padding: 0 28px;
  overflow-x: auto;
  scrollbar-width: none;
}

.profile-tabs-nav::-webkit-scrollbar {
  display: none;
}

.tab-item-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
  border: none;
  border-bottom: 2.5px solid transparent;
  background: transparent;
  color: var(--muted, #6e6668);
  font-size: 13.5px;
  font-weight: 650;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
  margin-bottom: -1px;
}

.tab-item-btn:hover {
  color: var(--ink, #1c1618);
}

html.dark .tab-item-btn:hover {
  color: #faf7f3;
}

.tab-item-btn.is-active {
  color: var(--pink-dark, #db2777);
  border-bottom-color: var(--pink-dark, #db2777);
}

html.dark .tab-item-btn.is-active {
  color: #dfba72;
  border-bottom-color: #dfba72;
}

.tab-ico {
  font-size: 18px;
}

@media (max-width: 640px) {
  .athlete-hero-card {
    padding: 20px 16px 0;
  }
  .athlete-top-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 14px;
  }
  .profile-tabs-nav {
    margin: 0 -16px;
    padding: 0 16px;
  }
  .contact-meta-row {
    gap: 10px;
  }
  .tab-item-btn {
    padding: 12px 14px;
    font-size: 12.5px;
  }
}
</style>
