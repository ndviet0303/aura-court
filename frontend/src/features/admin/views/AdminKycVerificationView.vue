<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminKyc } from '../composables/useAdminKyc'
import KycKpiCards from '../components/kyc/KycKpiCards.vue'
import KycQueueList from '../components/kyc/KycQueueList.vue'
import KycDetailWorkbench from '../components/kyc/KycDetailWorkbench.vue'
import KycActionFooter from '../components/kyc/KycActionFooter.vue'

const { t } = useI18n()

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
        <div class="breadcrumb">{{ t('admin.kyc.breadcrumb') }}</div>
        <div class="title-row">
          <div class="title-with-icon">
            <span class="material-symbols-outlined header-icon">verified_user</span>
            <h1 class="page-title">{{ t('admin.kyc.title') }}</h1>
          </div>
          <span class="badge-live">{{ t('admin.kyc.badge') }}</span>
        </div>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.kyc.searchPlaceholder')"
            class="search-input"
          />
        </div>
        <AuraButton variant="outline" type="button" :title="t('admin.kyc.exportBtn')">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>{{ t('admin.kyc.exportBtn') }}</span>
        </AuraButton>
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
        <p>{{ t('admin.kyc.selectQueuePrompt') }}</p>
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
