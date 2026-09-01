<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'

defineProps<{
  loading?: boolean
}>()

const { t } = useI18n()

interface PermissionRow {
  moduleKey: string
  actionKey: string
  owner: boolean
  manager: boolean
  receptionist: boolean
  tech: boolean
  staff: boolean
}

const permissionsMatrix: PermissionRow[] = [
  { moduleKey: 'courtManagement', actionKey: 'courtManagementDesc', owner: true, manager: true, receptionist: false, tech: true, staff: false },
  { moduleKey: 'pricingRules', actionKey: 'pricingRulesDesc', owner: true, manager: true, receptionist: false, tech: false, staff: false },
  { moduleKey: 'posAndCashier', actionKey: 'posAndCashierDesc', owner: true, manager: true, receptionist: true, tech: false, staff: true },
  { moduleKey: 'refundProcessing', actionKey: 'refundProcessingDesc', owner: true, manager: true, receptionist: false, tech: false, staff: false },
  { moduleKey: 'maintenanceSchedule', actionKey: 'maintenanceScheduleDesc', owner: true, manager: true, receptionist: true, tech: true, staff: false },
  { moduleKey: 'tournamentManagement', actionKey: 'tournamentManagementDesc', owner: true, manager: true, receptionist: true, tech: false, staff: true },
  { moduleKey: 'financialReports', actionKey: 'financialReportsDesc', owner: true, manager: true, receptionist: false, tech: false, staff: false },
  { moduleKey: 'staffAndRoles', actionKey: 'staffAndRolesDesc', owner: true, manager: false, receptionist: false, tech: false, staff: false },
]
</script>

<template>
  <div class="role-matrix-container">
    <!-- Matrix Header -->
    <div class="matrix-header">
      <div class="title-with-icon">
        <span class="material-symbols-outlined card-title-icon">shield</span>
        <div>
          <h3 class="matrix-heading">{{ t('admin.staff.roleMatrixHeading') }}</h3>
          <p class="matrix-sub">{{ t('admin.staff.roleMatrixSub') }}</p>
        </div>
      </div>
    </div>

    <!-- Skeletons State -->
    <div v-if="loading" class="matrix-table-wrap">
      <div v-for="i in 8" :key="i" class="matrix-row skeleton-matrix-row">
        <SkeletonLoader type="rect" height="40px" />
      </div>
    </div>

    <!-- Matrix Table -->
    <div v-else class="matrix-table-wrap">
      <div class="matrix-header-row">
        <span>{{ t('admin.staff.hdrModuleAndAction') }}</span>
        <span class="role-col-hdr">{{ t('admin.staff.roleOwner') }}</span>
        <span class="role-col-hdr">{{ t('admin.staff.roleManager') }}</span>
        <span class="role-col-hdr">{{ t('admin.staff.roleReceptionist') }}</span>
        <span class="role-col-hdr">{{ t('admin.staff.roleTechnician') }}</span>
        <span class="role-col-hdr">{{ t('admin.staff.roleStaff') }}</span>
      </div>

      <div class="matrix-body-rows">
        <div
          v-for="(row, idx) in permissionsMatrix"
          :key="idx"
          class="matrix-row"
        >
          <div class="module-action-info">
            <strong class="module-title">{{ t(`admin.staff.matrix.${row.moduleKey}`) }}</strong>
            <span class="action-desc">{{ t(`admin.staff.matrix.${row.actionKey}`) }}</span>
          </div>

          <div class="check-col">
            <span v-if="row.owner" class="material-symbols-outlined perm-yes">check_circle</span>
            <span v-else class="perm-no">—</span>
          </div>
          <div class="check-col">
            <span v-if="row.manager" class="material-symbols-outlined perm-yes">check_circle</span>
            <span v-else class="perm-no">—</span>
          </div>
          <div class="check-col">
            <span v-if="row.receptionist" class="material-symbols-outlined perm-yes">check_circle</span>
            <span v-else class="perm-no">—</span>
          </div>
          <div class="check-col">
            <span v-if="row.tech" class="material-symbols-outlined perm-yes">check_circle</span>
            <span v-else class="perm-no">—</span>
          </div>
          <div class="check-col">
            <span v-if="row.staff" class="material-symbols-outlined perm-yes">check_circle</span>
            <span v-else class="perm-no">—</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.role-matrix-container {
  display: flex;
  flex-direction: column;
  gap: 18px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  padding: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-title-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.matrix-heading {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.matrix-sub {
  margin: 2px 0 0 0;
  font-size: 12.5px;
  color: var(--muted);
}

.matrix-table-wrap {
  display: flex;
  flex-direction: column;
  border-radius: 14px;
  border: 1px solid var(--outline);
  overflow: hidden;
  margin-top: 4px;
  background: var(--surface);
}

.matrix-header-row {
  display: grid;
  grid-template-columns: 2.2fr repeat(5, 110px);
  padding: 14px 20px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.025));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.role-col-hdr {
  text-align: center;
}

.matrix-body-rows {
  display: flex;
  flex-direction: column;
}

.matrix-row {
  display: grid;
  grid-template-columns: 2.2fr repeat(5, 110px);
  padding: 14px 20px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13.5px;
  transition: background 0.12s;
}

.matrix-row:last-child {
  border-bottom: 0;
}

.matrix-row:hover {
  background: var(--surface-secondary, rgba(0, 0, 0, 0.015));
}

.skeleton-matrix-row {
  padding: 10px 20px;
}

.module-action-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.module-title {
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 700;
}

.action-desc {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.4;
}

.check-col {
  display: flex;
  align-items: center;
  justify-content: center;
}

.perm-yes {
  color: #10b981;
  font-size: 20px;
}

.perm-no {
  color: var(--muted);
  opacity: 0.35;
  font-weight: 700;
}

:global(html.dark) .role-matrix-container {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .matrix-table-wrap {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .matrix-header-row {
  background: var(--surface-subtle, #262024);
  color: var(--muted, #a89fa4);
  border-bottom-color: var(--outline, #4d4049);
}

:global(html.dark) .matrix-row {
  border-bottom-color: var(--outline, #4d4049);
}

:global(html.dark) .matrix-row:hover {
  background: var(--soft, #30292e);
}

:global(html.dark) .module-title {
  color: var(--ink, #FAF7F3);
}

@media (max-width: 900px) {
  .matrix-header-row,
  .matrix-row {
    grid-template-columns: 2fr repeat(5, 80px);
    padding: 10px 12px;
  }
}
</style>
