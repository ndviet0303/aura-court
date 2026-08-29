<script setup lang="ts">
import { useAdminStaff } from '../composables/useAdminStaff'

import StaffDirectoryTable from '../components/staff/StaffDirectoryTable.vue'
import StaffRoleMatrix from '../components/staff/StaffRoleMatrix.vue'
import StaffAuditLogs from '../components/staff/StaffAuditLogs.vue'
import StaffInviteModal from '../components/staff/StaffInviteModal.vue'

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
        <h1 class="page-title">👥 Nhân sự & Phân quyền</h1>
        <p class="page-subtitle">Quản trị danh sách nhân sự, phân quyền vai trò và theo dõi nhật ký hoạt động hệ thống.</p>
      </div>

      <div class="header-feedback">
        <div v-if="notice" class="alert-pill">{{ notice }}</div>
        <div v-if="error" class="alert-pill-error">{{ error }}</div>
      </div>
    </header>

    <!-- 4 KPI Stat Cards -->
    <div class="staff-kpi-grid">
      <div class="stat-card">
        <span class="stat-icon">👥</span>
        <span class="stat-label">Tổng Nhân Sự</span>
        <strong class="stat-value">{{ stats.totalStaff }} Nhân viên</strong>
        <span class="stat-trend text-green">100% tài khoản bảo mật</span>
      </div>

      <div class="stat-card">
        <span class="stat-icon">🛡️</span>
        <span class="stat-label">Quản Lý Cơ Sở</span>
        <strong class="stat-value">{{ stats.activeManagers }} Quản lý</strong>
        <span class="stat-trend text-blue">2 cơ sở vận hành</span>
      </div>

      <div class="stat-card">
        <span class="stat-icon">💼</span>
        <span class="stat-label">Lễ Tân / Thu Ngân</span>
        <strong class="stat-value">{{ stats.receptionistsOnline }} Đang online</strong>
        <span class="stat-trend text-green">Đầy đủ ca trực</span>
      </div>

      <div class="stat-card">
        <span class="stat-icon">👑</span>
        <span class="stat-label">Vai Trò Hệ Thống</span>
        <strong class="stat-value">{{ stats.systemRoles }} Nhóm quyền</strong>
        <span class="stat-trend">Phân quyền đa cấp (RBAC)</span>
      </div>
    </div>

    <!-- 3 View Navigation Buttons -->
    <div class="view-navigation-bar">
      <button
        class="view-nav-btn"
        :class="{ active: activeView === 'directory' }"
        @click="activeView = 'directory'"
      >
        📋 Danh Sách Nhân Sự
      </button>

      <button
        class="view-nav-btn"
        :class="{ active: activeView === 'roles' }"
        @click="activeView = 'roles'"
      >
        🛡️ Ma Trận Phân Quyền
      </button>

      <button
        class="view-nav-btn"
        :class="{ active: activeView === 'audit' }"
        @click="activeView = 'audit'"
      >
        🔒 Nhật Ký Bảo Mật
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

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-subtitle {
  margin: 0;
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

.stat-icon {
  font-size: 22px;
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

.view-navigation-bar {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 8px;
}

.view-nav-btn {
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

@media (max-width: 900px) {
  .staff-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
