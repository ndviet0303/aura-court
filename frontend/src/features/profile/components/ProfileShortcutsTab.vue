<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const isAdmin = computed(() => auth.user?.role === 'admin')

const userShortcuts = [
  {
    key: 'Bookings',
    nameKey: 'profile.scBookingsTitle',
    descKey: 'profile.scBookingsDesc',
    path: '/booking',
    icon: 'calendar_month',
    color: '#db2777',
  },
  {
    key: 'Wallet',
    nameKey: 'profile.scWalletTitle',
    descKey: 'profile.scWalletDesc',
    path: '/wallet',
    icon: 'account_balance_wallet',
    color: '#6B2D3F',
  },
  {
    key: 'Matchmaking',
    nameKey: 'profile.scMatchmakingTitle',
    descKey: 'profile.scMatchmakingDesc',
    path: '/matchmaking',
    icon: 'groups',
    color: '#0284c7',
  },
  {
    key: 'Resale',
    nameKey: 'profile.scResaleTitle',
    descKey: 'profile.scResaleDesc',
    path: '/resale',
    icon: 'swap_horiz',
    color: '#059669',
  },
  {
    key: 'Tournaments',
    nameKey: 'profile.scTournamentsTitle',
    descKey: 'profile.scTournamentsDesc',
    path: '/tournaments',
    icon: 'emoji_events',
    color: '#d97706',
  },
  {
    key: 'SplitBill',
    nameKey: 'profile.scSplitBillTitle',
    descKey: 'profile.scSplitBillDesc',
    path: '/split-bill',
    icon: 'call_split',
    color: '#7c3aed',
  },
]
</script>

<template>
  <div class="profile-tab-card">
    <div class="section-title-row">
      <div class="title-with-icon">
        <span class="material-symbols-outlined title-icon">grid_view</span>
        <div>
          <h2 class="section-heading">{{ $t('profile.shortcutsTitle') }}</h2>
          <p class="section-sub">{{ $t('profile.shortcutsSubtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Admin Center Featured Banner (Full Width) -->
    <RouterLink
      v-if="isAdmin"
      to="/admin"
      class="admin-portal-banner"
    >
      <div class="admin-icon-box">
        <span class="material-symbols-outlined">admin_panel_settings</span>
      </div>

      <div class="admin-info-col">
        <div class="admin-title-row">
          <strong class="admin-title">{{ $t('profile.scAdminTitle') }}</strong>
          <span class="admin-tag font-mono">ADMIN ACCESS</span>
        </div>
        <p class="admin-desc">{{ $t('profile.scAdminDesc') }}</p>
      </div>

      <div class="admin-action-btn">
        <span>{{ $t('nav.admin') }}</span>
        <span class="material-symbols-outlined btn-arrow">arrow_forward</span>
      </div>
    </RouterLink>

    <!-- Balanced 2-Column User Shortcuts Grid -->
    <div class="shortcuts-grid">
      <RouterLink
        v-for="s in userShortcuts"
        :key="s.path"
        :to="s.path"
        class="shortcut-card"
      >
        <div class="shortcut-icon-box" :style="{ color: s.color, backgroundColor: `${s.color}15` }">
          <span class="material-symbols-outlined">{{ s.icon }}</span>
        </div>

        <div class="shortcut-info">
          <strong class="shortcut-title">{{ $t(s.nameKey) }}</strong>
          <p class="shortcut-desc">{{ $t(s.descKey) }}</p>
        </div>

        <span class="material-symbols-outlined shortcut-arrow">arrow_forward</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 28px;
  border-radius: 24px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  overflow: hidden;
}

html.dark .profile-tab-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-with-icon {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.title-icon {
  font-size: 26px;
  color: var(--primary, #6B2D3F);
  margin-top: 2px;
}

html.dark .title-icon {
  color: #dfba72;
}

.section-heading {
  margin: 0;
  font-size: 18px;
  font-weight: 750;
  color: var(--ink, #1c1618);
}

html.dark .section-heading {
  color: #faf7f3;
}

.section-sub {
  margin: 4px 0 0;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
}

/* Admin Portal Featured Banner */
.admin-portal-banner {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 18px 22px;
  border-radius: 18px;
  background: linear-gradient(135deg, rgba(147, 51, 234, 0.08) 0%, rgba(107, 45, 63, 0.06) 100%);
  border: 1.5px solid rgba(147, 51, 234, 0.25);
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
}

html.dark .admin-portal-banner {
  background: linear-gradient(135deg, rgba(192, 132, 252, 0.12) 0%, rgba(223, 186, 114, 0.08) 100%);
  border-color: rgba(192, 132, 252, 0.35);
}

.admin-portal-banner:hover {
  border-color: #9333ea;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(147, 51, 234, 0.15);
}

html.dark .admin-portal-banner:hover {
  border-color: #c084fc;
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.5);
}

.admin-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(147, 51, 234, 0.12);
  color: #9333ea;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

html.dark .admin-icon-box {
  background: rgba(192, 132, 252, 0.2);
  color: #c084fc;
}

.admin-icon-box .material-symbols-outlined {
  font-size: 26px;
}

.admin-info-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.admin-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.admin-title {
  font-size: 15.5px;
  font-weight: 800;
  color: var(--ink, #1c1618);
}

html.dark .admin-title {
  color: #faf7f3;
}

.admin-tag {
  font-size: 10px;
  font-weight: 850;
  letter-spacing: 0.06em;
  padding: 2px 8px;
  border-radius: 6px;
  background: #9333ea;
  color: #ffffff;
}

.admin-desc {
  margin: 0;
  font-size: 13px;
  color: var(--muted, #6e6668);
  line-height: 1.4;
}

.admin-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 10px;
  background: #9333ea;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

html.dark .admin-action-btn {
  background: #c084fc;
  color: #1a1118;
}

.admin-action-btn .btn-arrow {
  font-size: 16px;
  transition: transform 0.2s ease;
}

.admin-portal-banner:hover .btn-arrow {
  transform: translateX(4px);
}

/* Symmetrical User Shortcuts Grid */
.shortcuts-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.shortcut-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border-radius: 16px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-sizing: border-box;
  min-width: 0;
  width: 100%;
}

html.dark .shortcut-card {
  background: rgba(255, 255, 255, 0.03);
  border-color: #352c32;
}

.shortcut-card:hover {
  border-color: var(--primary, #6B2D3F);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

html.dark .shortcut-card:hover {
  border-color: #dfba72;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}

.shortcut-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.shortcut-icon-box .material-symbols-outlined {
  font-size: 24px;
}

.shortcut-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.shortcut-title {
  font-size: 14.5px;
  font-weight: 750;
  color: var(--ink, #1c1618);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

html.dark .shortcut-title {
  color: #faf7f3;
}

.shortcut-desc {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted, #6e6668);
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.shortcut-arrow {
  font-size: 18px;
  color: var(--muted, #6e6668);
  transition: transform 0.2s ease, color 0.2s ease;
  flex-shrink: 0;
}

.shortcut-card:hover .shortcut-arrow {
  transform: translateX(4px);
  color: var(--primary, #6B2D3F);
}

html.dark .shortcut-card:hover .shortcut-arrow {
  color: #dfba72;
}

@media (max-width: 768px) {
  .profile-tab-card {
    padding: 20px 16px;
  }
  .shortcuts-grid {
    grid-template-columns: 1fr;
  }
  .admin-portal-banner {
    flex-direction: column;
    align-items: flex-start;
    gap: 14px;
  }
  .admin-action-btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
