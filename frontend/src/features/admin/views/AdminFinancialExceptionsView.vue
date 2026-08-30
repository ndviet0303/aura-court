<script setup lang="ts">
import { useAdminFinancialExceptions } from '../composables/useAdminFinancialExceptions'
import ExceptionKpiCards from '../components/exceptions/ExceptionKpiCards.vue'
import ExceptionTable from '../components/exceptions/ExceptionTable.vue'
import ImmutableAuditSidebar from '../components/exceptions/ImmutableAuditSidebar.vue'
import ManualOverrideDrawer from '../components/exceptions/ManualOverrideDrawer.vue'

const {
  activeTab,
  searchQuery,
  notice,
  stats,
  exceptions,
  auditLogs,
  selectedItem,
  isDrawerOpen,
  filteredExceptions,
  openOverrideDrawer,
  closeDrawer,
  submitOverride,
} = useAdminFinancialExceptions()
</script>

<template>
  <div class="financial-exceptions-view">
    <!-- Header -->
    <header class="view-header">
      <div class="header-titles">
        <div class="title-with-badge">
          <h1 class="page-title">🛡️ Ngoại Lệ Tài Chính & Sổ Cái Kiểm Toán</h1>
          <span class="badge-live">LIVE MONITORING</span>
        </div>
        <p class="page-sub">
          Theo dõi, xử lý can thiệp các giao dịch bất thường, sai lệch đối soát cổng thanh toán và lưu vết kiểm toán bất biến SHA-256.
        </p>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm theo Mã TXN, UID, Tên khách hàng..."
            class="search-input"
          />
        </div>
        <button class="btn-secondary" type="button" title="Xuất báo cáo kiểm toán">
          <span class="material-symbols-outlined">download</span>
          Xuất Báo Cáo
        </button>
      </div>
    </header>

    <!-- Notice alert -->
    <div v-if="notice" class="alert-notice">
      <span class="material-symbols-outlined">check_circle</span>
      <span>{{ notice }}</span>
    </div>

    <!-- Summary KPI Cards -->
    <ExceptionKpiCards :stats="stats" />

    <!-- Main Workspace Split 70 / 30 -->
    <div class="workspace-layout">
      <!-- Left Column: Exceptions Table (70%) -->
      <ExceptionTable
        :items="filteredExceptions"
        :active-tab="activeTab"
        :total-count="exceptions.length"
        :critical-count="exceptions.filter((e) => e.riskLevel === 'critical').length"
        @update:active-tab="activeTab = $event"
        @override="openOverrideDrawer"
      />

      <!-- Right Column: Immutable Audit Trail (30%) -->
      <ImmutableAuditSidebar :logs="auditLogs" />
    </div>

    <!-- Slide-over Drawer for Manual Override -->
    <ManualOverrideDrawer
      :show="isDrawerOpen"
      :item="selectedItem"
      @close="closeDrawer"
      @submit="submitOverride"
    />
  </div>
</template>

<style scoped>
.financial-exceptions-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px 20px;
  color: #191c1d;
  font-family: 'Inter', sans-serif;
}

.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
  color: #191c1d;
}

.badge-live {
  font-size: 11px;
  font-weight: 700;
  background: #ffdad6;
  color: #ba1a1a;
  padding: 3px 8px;
  border-radius: 999px;
  letter-spacing: 0.04em;
}

.page-sub {
  font-size: 14px;
  color: #5d5f5f;
  margin: 4px 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 999px;
  padding: 8px 16px;
  width: 320px;
}

.search-icon {
  font-size: 18px;
  color: #817476;
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  width: 100%;
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: #191c1d;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-secondary:hover {
  background: #edeeef;
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #d1fae5;
  color: #065f46;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
}

.workspace-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 20px;
  align-items: start;
}

@media (max-width: 1080px) {
  .workspace-layout {
    grid-template-columns: 1fr;
  }
}
</style>
