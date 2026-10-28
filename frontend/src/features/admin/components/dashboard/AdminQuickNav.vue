<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTenantCapabilities } from '@/features/admin/composables/useTenantCapabilities'

const { t } = useI18n()
const { isPayoutsEnabled } = useTenantCapabilities()

interface QuickNavItem {
  id: string
  labelKey: string
  path: string
  icon: string
}

const navItems: QuickNavItem[] = [
  {
    id: 'manage',
    labelKey: 'admin.dashboard.quickNavManage',
    path: '/admin/manage',
    icon: 'grid_view',
  },
  { id: 'staff', labelKey: 'admin.dashboard.quickNavStaff', path: '/admin/staff', icon: 'badge' },
  {
    id: 'shifts',
    labelKey: 'admin.dashboard.quickNavShifts',
    path: '/admin/shifts',
    icon: 'receipt_long',
  },
  {
    id: 'pricing',
    labelKey: 'admin.dashboard.quickNavPricing',
    path: '/admin/pricing',
    icon: 'price_change',
  },
  {
    id: 'venues',
    labelKey: 'admin.dashboard.quickNavVenues',
    path: '/admin/venues',
    icon: 'location_on',
  },
  {
    id: 'payouts',
    labelKey: 'admin.dashboard.quickNavPayouts',
    path: '/admin/payouts',
    icon: 'account_balance_wallet',
  },
  {
    id: 'schedule',
    labelKey: 'admin.dashboard.quickNavSchedule',
    path: '/admin/schedule',
    icon: 'schedule',
  },
  {
    id: 'tournaments',
    labelKey: 'admin.dashboard.quickNavTournaments',
    path: '/admin/tournaments',
    icon: 'account_tree',
  },
]
// Payouts are a legacy finance page, shown only while marketplace_payouts is on.
const shownItems = computed(() =>
  navItems.filter((item) => item.id !== 'payouts' || isPayoutsEnabled.value),
)
</script>

<template>
  <div class="admin-quick-nav-card">
    <div class="title-with-icon">
      <span class="material-symbols-outlined card-title-icon">speed</span>
      <h3 class="title">{{ t('admin.dashboard.quickNavTitle') }}</h3>
    </div>
    <div class="nav-grid">
      <RouterLink v-for="item in shownItems" :key="item.id" :to="item.path" class="nav-card-item">
        <span class="material-symbols-outlined n-icon">{{ item.icon }}</span>
        <strong class="n-name">{{ t(item.labelKey) }}</strong>
        <span class="material-symbols-outlined n-arrow">arrow_forward</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.admin-quick-nav-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 20px;
  color: var(--pink-dark, #db2777);
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.nav-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.nav-card-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  text-decoration: none;
  transition: all 0.15s;
}

.nav-card-item:hover {
  border-color: var(--pink-dark, #db2777);
  transform: translateY(-2px);
  background: var(--surface);
  color: var(--pink-dark, #db2777);
}

.n-icon {
  font-size: 20px;
  color: var(--muted);
  transition: color 0.15s;
}

.nav-card-item:hover .n-icon {
  color: var(--pink-dark, #db2777);
}

.n-name {
  font-size: 13px;
  flex: 1;
  color: var(--ink);
}

.n-arrow {
  font-size: 16px;
  color: var(--muted);
}
</style>
