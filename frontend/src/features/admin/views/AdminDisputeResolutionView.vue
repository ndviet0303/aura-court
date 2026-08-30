<script setup lang="ts">
import { useAdminDisputes } from '../composables/useAdminDisputes'
import DisputeKpiCards from '../components/disputes/DisputeKpiCards.vue'
import DisputeTicketList from '../components/disputes/DisputeTicketList.vue'
import DisputeDetailWorkbench from '../components/disputes/DisputeDetailWorkbench.vue'
import DisputeActionToolbar from '../components/disputes/DisputeActionToolbar.vue'
import ArbitrationDrawer from '../components/disputes/ArbitrationDrawer.vue'

const {
  activeTab,
  searchQuery,
  loading,
  isSubmitting,
  notice,
  isDrawerOpen,
  activeEvidenceTab,
  stats,
  selectedDispute,
  filteredDisputes,
  selectDispute,
  openDrawer,
  closeDrawer,
  submitArbitration,
  handleDismiss,
  handleRelocateCourt,
} = useAdminDisputes()
</script>

<template>
  <div class="dispute-resolution-view">
    <!-- View Header -->
    <header class="view-header">
      <div class="header-titles">
        <div class="breadcrumb">Quản trị vận hành / Phán quyết &amp; Bồi thường</div>
        <div class="title-row">
          <h1 class="page-title">⚖️ Trung Tâm Giải Quyết Tranh Chấp</h1>
          <span class="badge-live">PORTAL PHÁN QUYẾT</span>
        </div>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm theo #DSP, #BK, Tên khách hàng, Tên sân..."
            class="search-input"
          />
        </div>
        <button class="btn-export" type="button" title="Xuất hồ sơ vụ việc PDF">
          <span class="material-symbols-outlined">picture_as_pdf</span>
          Xuất Hồ Sơ Vụ Việc (PDF)
        </button>
      </div>
    </header>

    <!-- Notice alert -->
    <div v-if="notice" class="alert-notice">
      <span class="material-symbols-outlined">verified</span>
      <span>{{ notice }}</span>
    </div>

    <!-- KPI Summary Row -->
    <DisputeKpiCards :stats="stats" />

    <!-- Two-Column Master-Detail Layout -->
    <div class="workbench-layout">
      <!-- Left Column: Dispute Ticket List (35%) -->
      <DisputeTicketList
        :items="filteredDisputes"
        :active-tab="activeTab"
        :selected-id="selectedDispute?.id"
        :loading="loading"
        @update:active-tab="activeTab = $event"
        @select="selectDispute"
      />

      <!-- Right Column: Detail Workbench + Action Toolbar (65%) -->
      <div v-if="selectedDispute" class="detail-column">
        <DisputeDetailWorkbench
          :item="selectedDispute"
          :active-evidence-tab="activeEvidenceTab"
          @update:active-evidence-tab="activeEvidenceTab = $event"
        />

        <DisputeActionToolbar
          :item="selectedDispute"
          :is-submitting="isSubmitting"
          @open-arbitration="openDrawer"
          @dismiss="handleDismiss"
          @relocate="handleRelocateCourt"
        />
      </div>

      <!-- Empty State when no ticket is selected -->
      <div v-else class="detail-empty">
        <span class="material-symbols-outlined icon-48">folder_open</span>
        <p>Vui lòng chọn một vụ việc trong danh sách để điều tra và phán quyết.</p>
      </div>
    </div>

    <!-- Slide-over Drawer for Arbitration & Refund -->
    <ArbitrationDrawer
      :show="isDrawerOpen"
      :item="selectedDispute"
      :is-submitting="isSubmitting"
      @close="closeDrawer"
      @submit="submitArbitration"
    />
  </div>
</template>

<style scoped>
.dispute-resolution-view {
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

.breadcrumb {
  font-size: 12px;
  font-weight: 500;
  color: #5d5f5f;
  margin-bottom: 2px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
  color: #191c1d;
  letter-spacing: -0.01em;
}

.badge-live {
  font-size: 11px;
  font-weight: 700;
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.04em;
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
  font-size: 13px;
  width: 100%;
}

.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #e1e3e4;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: #191c1d;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-export:hover {
  background: #f8f9fa;
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #d1fae5;
  color: #065f46;
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.workbench-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 1080px) {
  .workbench-layout {
    grid-template-columns: 1fr;
  }
}

.detail-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-empty {
  background: #ffffff;
  border: 1px dashed #d3c3c5;
  border-radius: 20px;
  padding: 60px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #817476;
  gap: 12px;
}

.icon-48 {
  font-size: 48px;
  color: #d3c3c5;
}
</style>
