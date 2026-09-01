<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminDisputes } from '../composables/useAdminDisputes'
import DisputeKpiCards from '../components/disputes/DisputeKpiCards.vue'
import DisputeTicketList from '../components/disputes/DisputeTicketList.vue'
import DisputeDetailWorkbench from '../components/disputes/DisputeDetailWorkbench.vue'
import DisputeActionToolbar from '../components/disputes/DisputeActionToolbar.vue'
import ArbitrationDrawer from '../components/disputes/ArbitrationDrawer.vue'

const { t } = useI18n()

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
        <div class="breadcrumb">{{ t('admin.disputes.breadcrumb') }}</div>
        <div class="title-row">
          <div class="title-with-icon">
            <span class="material-symbols-outlined header-icon">gavel</span>
            <h1 class="page-title">{{ t('admin.disputes.title') }}</h1>
          </div>
          <span class="badge-live">{{ t('admin.disputes.badge') }}</span>
        </div>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.disputes.searchPlaceholder')"
            class="search-input"
          />
        </div>
        <AuraButton variant="outline" type="button" :title="t('admin.disputes.exportPdfBtn')">
          <span class="material-symbols-outlined icon-18">picture_as_pdf</span>
          <span>{{ t('admin.disputes.exportPdfBtn') }}</span>
        </AuraButton>
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
        <p>{{ t('admin.disputes.selectDisputePrompt') }}</p>
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
  color: var(--ink);
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
  color: var(--muted);
  margin-bottom: 2px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
  color: var(--ink);
  letter-spacing: -0.01em;
}

.badge-live {
  font-size: 11px;
  font-weight: 700;
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
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
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 999px;
  padding: 8px 16px;
  width: 300px;
}

.search-icon {
  font-size: 18px;
  color: var(--muted);
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink);
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
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
  background: var(--surface);
  border: 1px dashed var(--outline);
  border-radius: 20px;
  padding: 60px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  gap: 12px;
}

.icon-48 {
  font-size: 48px;
  color: var(--muted);
}

.icon-18 {
  font-size: 18px;
}
</style>
