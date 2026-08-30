<script setup lang="ts">
import { useAdminKyc } from '../composables/useAdminKyc'
import KycKpiCards from '../components/kyc/KycKpiCards.vue'
import KycQueueList from '../components/kyc/KycQueueList.vue'
import KycDetailWorkbench from '../components/kyc/KycDetailWorkbench.vue'
import KycActionFooter from '../components/kyc/KycActionFooter.vue'

const {
  activeTab,
  searchQuery,
  loading,
  isSubmitting,
  notice,
  stats,
  selectedKyc,
  filteredQueue,
  selectedRejectReason,
  customRejectReason,
  reviewer_notes,
  checklist,
  selectKyc,
  handleApprove,
  handleReject,
  handleRequestSupplement,
} = useAdminKyc()
</script>

<template>
  <div class="kyc-verification-view">
    <!-- View Header -->
    <header class="view-header">
      <div class="header-titles">
        <div class="breadcrumb">Quản trị nền tảng / Hồ sơ pháp lý chủ sân</div>
        <div class="title-row">
          <h1 class="page-title">🛡️ Phê Duyệt KYC & Thẩm Định Chủ Sân</h1>
          <span class="badge-live">PORTAL THẨM ĐỊNH</span>
        </div>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm theo Mã KYC, Tên sân, CCCD, MST..."
            class="search-input"
          />
        </div>
        <button class="btn-export" type="button" title="Xuất báo cáo thẩm định">
          <span class="material-symbols-outlined">download</span>
          Xuất Báo Cáo
        </button>
      </div>
    </header>

    <!-- Notice alert -->
    <div v-if="notice" class="alert-notice">
      <span class="material-symbols-outlined">verified</span>
      <span>{{ notice }}</span>
    </div>

    <!-- KPI Stats Row -->
    <KycKpiCards :stats="stats" />

    <!-- Two-Column Workbench Layout (35% Queue / 65% Deep Workbench) -->
    <div class="workbench-layout">
      <!-- Left Column: KYC Queue (35%) -->
      <KycQueueList
        :items="filteredQueue"
        :active-tab="activeTab"
        :selected-id="selectedKyc?.id"
        :loading="loading"
        @update:active-tab="activeTab = $event"
        @select="selectKyc"
      />

      <!-- Right Column: Verification Detail Workbench (65%) -->
      <div v-if="selectedKyc" class="detail-column">
        <KycDetailWorkbench
          :item="selectedKyc"
          :checklist="checklist"
          :reviewer_notes="reviewer_notes"
          @update:checklist="checklist = $event"
          @update:reviewer_notes="reviewer_notes = $event"
        />

        <!-- Action Footer -->
        <KycActionFooter
          :selected-reason="selectedRejectReason"
          :custom-reason="customRejectReason"
          :is-submitting="isSubmitting"
          :is-approved="selectedKyc.status === 'approved'"
          @update:selected-reason="selectedRejectReason = $event"
          @update:custom-reason="customRejectReason = $event"
          @approve="handleApprove"
          @reject="handleReject"
          @request-supplement="handleRequestSupplement"
        />
      </div>

      <div v-else class="detail-empty">
        <span class="material-symbols-outlined icon-48">folder_open</span>
        <p>Vui lòng chọn một hồ sơ trong hàng đợi để thẩm định.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kyc-verification-view {
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
