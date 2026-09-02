<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminKyc } from '../composables/useAdminKyc'
import KycKpiCards from '../components/kyc/KycKpiCards.vue'
import KycQueueList from '../components/kyc/KycQueueList.vue'
import KycDetailWorkbench from '../components/kyc/KycDetailWorkbench.vue'
import KycActionFooter from '../components/kyc/KycActionFooter.vue'
import KycDocumentInspectModal from '../components/kyc/KycDocumentInspectModal.vue'
import KycAuditHistoryModal from '../components/kyc/KycAuditHistoryModal.vue'

const { t } = useI18n()

const {
  activeTab,
  selectedBusinessType,
  selectedDate,
  searchQuery,
  loading,
  isSubmitting,
  isExporting,
  notice,
  error,
  stats,
  selectedKyc,
  filteredQueue,
  selectedRejectReason,
  customRejectReason,
  reviewer_notes,
  checklist,
  isInspectModalOpen,
  inspectData,
  isHistoryModalOpen,
  auditLogs,
  selectKyc,
  fetchQueue,
  openInspectModal,
  closeInspectModal,
  openHistoryModal,
  closeHistoryModal,
  callHotline,
  exportKycReport,
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
        <!-- Search Box -->
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.kyc.searchPlaceholder')"
            class="search-input"
          />
          <button v-if="searchQuery" type="button" class="btn-clear" @click="searchQuery = ''">
            <span class="material-symbols-outlined icon-14">close</span>
          </button>
        </div>

        <!-- Refresh Button -->
        <AuraButton
          variant="secondary"
          :disabled="loading"
          :title="t('admin.kyc.refresh')"
          @click="fetchQueue"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">refresh</span>
        </AuraButton>

        <!-- Export Report Button -->
        <AuraButton
          variant="outline"
          type="button"
          :disabled="isExporting"
          :title="t('admin.kyc.exportBtn')"
          @click="exportKycReport"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': isExporting }">
            {{ isExporting ? 'sync' : 'download' }}
          </span>
          <span>{{ isExporting ? t('admin.kyc.exporting') : t('admin.kyc.exportBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Notice alert -->
    <transition name="fade">
      <div v-if="notice" class="alert-notice">
        <span class="material-symbols-outlined icon-18">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <!-- Error alert -->
    <transition name="fade">
      <div v-if="error" class="alert-error">
        <span class="material-symbols-outlined icon-18">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- KPI Stats Row -->
    <KycKpiCards :stats="stats" :loading="loading" />

    <!-- Two-Column Workbench Layout (35% Queue / 65% Deep Workbench) -->
    <div class="workbench-layout">
      <!-- Left Column: KYC Queue (35%) -->
      <KycQueueList
        :items="filteredQueue"
        :active-tab="activeTab"
        :selected-business-type="selectedBusinessType"
        :selected-date="selectedDate"
        :selected-id="selectedKyc?.id"
        :loading="loading"
        :stats="stats"
        @update:active-tab="activeTab = $event"
        @update:selected-business-type="selectedBusinessType = $event"
        @update:selected-date="selectedDate = $event"
        @select="selectKyc"
      />

      <!-- Right Column: Verification Detail Workbench (65%) -->
      <div v-if="selectedKyc" class="detail-column">
        <KycDetailWorkbench
          :item="selectedKyc"
          :checklist="checklist"
          :reviewer_notes="reviewer_notes"
          :loading="loading"
          @update:checklist="checklist = $event"
          @update:reviewer_notes="reviewer_notes = $event"
          @inspect-document="openInspectModal"
          @open-history="openHistoryModal"
          @call-hotline="callHotline"
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

    <!-- Document Image Inspect Modal -->
    <KycDocumentInspectModal
      :show="isInspectModalOpen"
      :inspect-data="inspectData"
      @close="closeInspectModal"
    />

    <!-- KYC Review Audit History Modal -->
    <KycAuditHistoryModal
      :show="isHistoryModalOpen"
      :item="selectedKyc"
      :logs="auditLogs"
      @close="closeHistoryModal"
    />
  </div>
</template>

<style scoped>
.kyc-verification-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink, #191c1d);
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
  color: var(--muted, #817476);
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
  color: var(--primary, #78555e);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
  color: var(--ink, #191c1d);
  letter-spacing: -0.01em;
}

.badge-live {
  font-size: 11px;
  font-weight: 700;
  background: var(--primary-container, #ffd1dc);
  color: var(--primary, #78555e);
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
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 999px;
  padding: 8px 16px;
  width: 320px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.search-icon {
  font-size: 18px;
  color: var(--muted, #817476);
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: var(--ink, #191c1d);
}

.btn-clear {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #817476);
  display: flex;
  align-items: center;
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #047857;
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.alert-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #b91c1c;
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
  background: var(--surface, #ffffff);
  border: 1px dashed var(--outline, #e1e3e4);
  border-radius: 20px;
  padding: 60px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--muted, #817476);
  gap: 12px;
}

.icon-48 { font-size: 48px; color: var(--muted, #817476); }
.icon-18 { font-size: 18px; }
.icon-14 { font-size: 14px; }

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

