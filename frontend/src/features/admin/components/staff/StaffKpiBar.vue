<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'

defineProps<{
  totalStaff: number
  activeManagers: number
  receptionistsOnline: number
  systemRoles: number
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="staff-kpi-grid">
    <!-- Card 1: Total Staff -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-pink">badge</span>
        <span class="stat-label">{{ t('admin.staff.totalStaff') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="120px" />
      </div>
      <strong v-else class="stat-value">
        {{ t('admin.staff.totalStaffCount', { count: totalStaff }) }}
      </strong>
      <span class="stat-trend text-green">
        <span class="material-symbols-outlined icon-14">verified_user</span>
        <span>{{ t('admin.staff.securityGuaranteed') }}</span>
      </span>
    </div>

    <!-- Card 2: Active Managers -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-blue">shield_person</span>
        <span class="stat-label">{{ t('admin.staff.activeManagers') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="110px" />
      </div>
      <strong v-else class="stat-value">
        {{ t('admin.staff.activeManagersCount', { count: activeManagers }) }}
      </strong>
      <span class="stat-trend text-blue">
        <span class="material-symbols-outlined icon-14">domain</span>
        <span>{{ t('admin.staff.venuesOperational') }}</span>
      </span>
    </div>

    <!-- Card 3: Receptionists & POS -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-green">point_of_sale</span>
        <span class="stat-label">{{ t('admin.staff.receptionistsOnline') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="110px" />
      </div>
      <strong v-else class="stat-value">
        {{ t('admin.staff.receptionistsOnlineCount', { count: receptionistsOnline }) }}
      </strong>
      <span class="stat-trend text-green">
        <span class="material-symbols-outlined icon-14">check_circle</span>
        <span>{{ t('admin.staff.shiftsCovered') }}</span>
      </span>
    </div>

    <!-- Card 4: System Roles -->
    <div class="stat-card">
      <div class="stat-top-row">
        <span class="material-symbols-outlined stat-icon text-purple">admin_panel_settings</span>
        <span class="stat-label">{{ t('admin.staff.systemRoles') }}</span>
      </div>
      <div v-if="loading" class="stat-skeleton-wrap">
        <SkeletonLoader type="rect" height="24px" width="100px" />
      </div>
      <strong v-else class="stat-value">
        {{ t('admin.staff.systemRolesCount', { count: systemRoles }) }}
      </strong>
      <span class="stat-trend text-muted-sub">
        <span class="material-symbols-outlined icon-14">tune</span>
        <span>{{ t('admin.staff.rbacDesc') }}</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.staff-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.stat-top-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-icon {
  font-size: 22px;
}

.stat-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted);
}

.stat-value {
  font-size: 19px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
}

.stat-skeleton-wrap {
  padding: 2px 0;
}

.stat-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  font-weight: 600;
}

.icon-14 {
  font-size: 14px;
}

.text-green {
  color: #10b981;
}
.text-blue {
  color: #3b82f6;
}
.text-pink {
  color: var(--pink-dark, #db2777);
}
.text-purple {
  color: #8b5cf6;
}
.text-muted-sub {
  color: var(--muted);
}

:global(html.dark) .stat-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

@media (max-width: 992px) {
  .staff-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 540px) {
  .staff-kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>
