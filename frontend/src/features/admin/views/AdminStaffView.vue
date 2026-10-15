<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminStaff, type StaffMember } from '../composables/useAdminStaff'

import StaffKpiBar from '../components/staff/StaffKpiBar.vue'
import StaffDirectoryTable from '../components/staff/StaffDirectoryTable.vue'
import StaffRoleMatrix from '../components/staff/StaffRoleMatrix.vue'
import StaffAuditLogs from '../components/staff/StaffAuditLogs.vue'
import StaffInviteModal from '../components/staff/StaffInviteModal.vue'
import StaffEditRoleModal from '../components/staff/StaffEditRoleModal.vue'
import StaffRemoveModal from '../components/staff/StaffRemoveModal.vue'

const { t } = useI18n()

const {
  loading,
  saving,
  activeView,
  searchQuery,
  selectedRoleFilter,
  selectedStatusFilter,
  selectedVenueFilter,
  auditSearchQuery,
  auditActionFilter,
  auditDateFilter,
  showInviteModal,
  showEditModal,
  showRemoveModal,
  selectedMemberForEdit,
  selectedMemberForRemove,
  notice,
  error,
  venuesList,
  stats,
  filteredStaff,
  filteredAuditLogs,
  loadStaffData,
  inviteStaff,
  openEditModal,
  updateMemberRole,
  openRemoveModal,
  removeMember,
  toggleMemberStatus,
} = useAdminStaff()

async function handleInviteMember(member: {
  name: string
  email: string
  phone: string
  role: StaffMember['role']
  venueIds: number[]
}) {
  await inviteStaff(member)
}

async function handleSaveEditRole(memberId: number, payload: { role: string; status: string }) {
  await updateMemberRole(memberId, payload)
}

async function handleConfirmRemove(memberId: number) {
  await removeMember(memberId)
}
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

      <div class="header-actions">
        <AuraButton variant="secondary" :disabled="loading" @click="loadStaffData">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">
            refresh
          </span>
          <span>{{ t('admin.staff.refreshBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill alert-success" data-testid="staff-notice">
      <span class="material-symbols-outlined icon-16">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill alert-error" data-testid="staff-error">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- 4 KPI Stat Cards with Skeletons -->
    <StaffKpiBar
      :total-staff="stats.totalStaff"
      :active-managers="stats.activeManagers"
      :receptionists="stats.receptionists"
      :system-roles="stats.systemRoles"
      :loading="loading"
    />

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
        <span class="material-symbols-outlined icon-18">history</span>
        <span>{{ t('admin.staff.tabAudit') }}</span>
      </button>
    </div>

    <!-- View 1: Directory Table -->
    <section v-if="activeView === 'directory'" class="tab-pane">
      <StaffDirectoryTable
        :staff="filteredStaff"
        :venues="venuesList"
        :search-query="searchQuery"
        :selected-role-filter="selectedRoleFilter"
        :selected-status-filter="selectedStatusFilter"
        :selected-venue-filter="selectedVenueFilter"
        :loading="loading"
        @update:search-query="searchQuery = $event"
        @update:selected-role-filter="selectedRoleFilter = $event"
        @update:selected-status-filter="selectedStatusFilter = $event"
        @update:selected-venue-filter="selectedVenueFilter = $event"
        @open-invite-modal="showInviteModal = true"
        @edit-member="openEditModal"
        @toggle-status="toggleMemberStatus"
        @remove-member="openRemoveModal"
      />
    </section>

    <!-- View 2: Role Matrix -->
    <section v-else-if="activeView === 'roles'" class="tab-pane">
      <StaffRoleMatrix :loading="loading" />
    </section>

    <!-- View 3: Audit Logs -->
    <section v-else-if="activeView === 'audit'" class="tab-pane">
      <StaffAuditLogs
        :logs="filteredAuditLogs"
        :search-query="auditSearchQuery"
        :action-filter="auditActionFilter"
        :date-filter="auditDateFilter"
        :loading="loading"
        @update:search-query="auditSearchQuery = $event"
        @update:action-filter="auditActionFilter = $event"
        @update:date-filter="auditDateFilter = $event"
        @refresh="loadStaffData"
      />
    </section>

    <!-- Invite Modal -->
    <StaffInviteModal
      :show="showInviteModal"
      :saving="saving"
      :venues="venuesList"
      @close="showInviteModal = false"
      @invite="handleInviteMember"
    />

    <!-- Edit Role & Status Modal -->
    <StaffEditRoleModal
      :show="showEditModal"
      :member="selectedMemberForEdit"
      :saving="saving"
      @close="showEditModal = false"
      @save="handleSaveEditRole"
    />

    <!-- Remove Member Modal -->
    <StaffRemoveModal
      :show="showRemoveModal"
      :member="selectedMemberForRemove"
      :saving="saving"
      @close="showRemoveModal = false"
      @confirm="handleConfirmRemove"
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
  gap: 16px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 28px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.page-subtitle {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

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
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
}

.alert-success {
  background: #10b981;
  color: #fff;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.alert-error {
  background: #ef4444;
  color: #fff;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}

.tab-pane {
  display: flex;
  flex-direction: column;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

:global(html.dark) .admin-staff-view {
  color: var(--ink, #faf7f3);
}

:global(html.dark) .view-nav-btn {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
  color: var(--ink, #faf7f3);
}

:global(html.dark) .view-nav-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

:global(html.dark) .view-nav-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}
</style>
