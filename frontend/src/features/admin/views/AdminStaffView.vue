<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAdminStaff } from '../composables/useAdminStaff'

import StaffDirectoryTable from '../components/staff/StaffDirectoryTable.vue'
import StaffRoleMatrix from '../components/staff/StaffRoleMatrix.vue'
import StaffAuditLogs from '../components/staff/StaffAuditLogs.vue'
import StaffInviteModal from '../components/staff/StaffInviteModal.vue'

const { t } = useI18n()

const {
  activeView,
  searchQuery,
  selectedRoleFilter,
  showInviteModal,
  notice,
  error,
  auditLogs,
  stats,
  filteredStaff,
  inviteStaff,
} = useAdminStaff()
</script>

<template>
  <div class="admin-staff-view">
    <!-- Top Header -->
    <header class="staff-header-section">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">badge</span>
          <h1 class="page-title">{{ t('admin.staff.title') }}</h1>
        </div>
        <p class="page-subtitle">{{ t('admin.staff.subtitle') }}</p>
      </div>

      <div class="header-feedback">
        <div v-if="notice" class="alert-pill">{{ notice }}</div>
        <div v-if="error" class="alert-pill-error">{{ error }}</div>
      </div>
    </header>

    <!-- 4 KPI Stat Cards -->
    <div class="staff-kpi-grid">
      <div class="stat-card">
        <div class="stat-top-row">
          <span class="material-symbols-outlined stat-icon">group</span>
          <span class="stat-label">{{ t('admin.staff.totalStaff') }}</span>
        </div>
        <strong class="stat-value">{{ t('admin.staff.totalStaffCount', { count: stats.totalStaff }) }}</strong>
        <span class="stat-trend text-green">{{ t('admin.staff.securityGuaranteed') }}</span>
      </div>

      <div class="stat-card">
        <div class="stat-top-row">
          <span class="material-symbols-outlined stat-icon text-blue">shield</span>
          <span class="stat-label">{{ t('admin.staff.activeManagers') }}</span>
        </div>
        <strong class="stat-value">{{ t('admin.staff.activeManagersCount', { count: stats.activeManagers }) }}</strong>
        <span class="stat-trend text-blue">{{ t('admin.staff.venuesOperational') }}</span>
      </div>

      <div class="stat-card">
        <div class="stat-top-row">
          <span class="material-symbols-outlined stat-icon text-green">support_agent</span>
          <span class="stat-label">{{ t('admin.staff.receptionistsOnline') }}</span>
        </div>
        <strong class="stat-value">{{ t('admin.staff.receptionistsOnlineCount', { count: stats.receptionistsOnline }) }}</strong>
        <span class="stat-trend text-green">{{ t('admin.staff.shiftsCovered') }}</span>
      </div>

      <div class="stat-card">
        <div class="stat-top-row">
          <span class="material-symbols-outlined stat-icon text-pink">admin_panel_settings</span>
          <span class="stat-label">{{ t('admin.staff.systemRoles') }}</span>
        </div>
        <strong class="stat-value">{{ t('admin.staff.systemRolesCount', { count: stats.systemRoles }) }}</strong>
        <span class="stat-trend">{{ t('admin.staff.rbacDesc') }}</span>
      </div>
    </div>

    <!-- 3 View Navigation Buttons -->
    <div class="view-navigation-bar">
      <button
        type="button"
        class="view-nav-btn"
        :class="{ active: activeView === 'directory' }"
        @click="activeView = 'directory'"
      >
        <span class="material-symbols-outlined icon-18">badge</span>
        <span>{{ t('admin.staff.tabDirectory') }}</span>
      </button>

      <button
        type="button"
        class="view-nav-btn"
        :class="{ active: activeView === 'roles' }"
        @click="activeView = 'roles'"
      >
        <span class="material-symbols-outlined icon-18">shield</span>
        <span>{{ t('admin.staff.tabRoles') }}</span>
      </button>

      <button
        type="button"
        class="view-nav-btn"
        :class="{ active: activeView === 'audit' }"
        @click="activeView = 'audit'"
      >
        <span class="material-symbols-outlined icon-18">security</span>
        <span>{{ t('admin.staff.tabAudit') }}</span>
      </button>
    </div>

    <!-- View 1: Directory Table -->
    <section v-if="activeView === 'directory'" class="tab-pane">
      <StaffDirectoryTable
        :staff="filteredStaff"
        :search-query="searchQuery"
        :selected-role-filter="selectedRoleFilter"
        @update:search-query="searchQuery = $event"
        @update:selected-role-filter="selectedRoleFilter = $event"
        @open-invite-modal="showInviteModal = true"
      />
    </section>

    <!-- View 2: Role Matrix -->
    <section v-else-if="activeView === 'roles'" class="tab-pane">
      <StaffRoleMatrix />
    </section>

    <!-- View 3: Audit Logs -->
    <section v-else-if="activeView === 'audit'" class="tab-pane">
      <StaffAuditLogs :logs="auditLogs" />
    </section>

    <!-- Invite Modal -->
    <StaffInviteModal
      :show="showInviteModal"
      @close="showInviteModal = false"
      @invite="inviteStaff"
    />
  </div>
</template>

<style scoped>
.admin-staff-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.staff-header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-subtitle {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.staff-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.stat-top-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat-icon {
  font-size: 20px;
  color: var(--muted);
}

.stat-label {
  font-size: 12px;
  color: var(--muted);
}

.stat-value {
  font-size: 18px;
}

.stat-trend {
  font-size: 11px;
  color: var(--muted);
}

.text-green { color: #10b981; }
.text-blue { color: #3b82f6; }
.text-pink { color: var(--pink-dark, #db2777); }

.view-navigation-bar {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 8px;
}

.view-nav-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.view-nav-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.view-nav-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.alert-pill {
  padding: 6px 14px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 6px 14px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}

.tab-pane {
  display: flex;
  flex-direction: column;
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 900px) {
  .staff-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
