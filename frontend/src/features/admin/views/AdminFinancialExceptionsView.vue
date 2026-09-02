<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminFinancialExceptions } from '../composables/useAdminFinancialExceptions'
import ExceptionKpiCards from '../components/exceptions/ExceptionKpiCards.vue'
import ExceptionTable from '../components/exceptions/ExceptionTable.vue'
import ImmutableAuditSidebar from '../components/exceptions/ImmutableAuditSidebar.vue'
import ManualOverrideDrawer from '../components/exceptions/ManualOverrideDrawer.vue'
import ExceptionDetailModal from '../components/exceptions/ExceptionDetailModal.vue'

const { t } = useI18n()

const {
  activeTab,
  searchQuery,
  filterDate,
  filterSeverity,
  filterStatus,
  showAdvancedFilters,
  notice,
  error,
  loading,
  isExporting,
  stats,
  exceptions,
  auditLogs,
  selectedOverrideItem,
  isOverrideDrawerOpen,
  selectedDetailItem,
  isDetailModalOpen,
  filteredExceptions,
  toggleAdvancedFilters,
  resetFilters,
  openDetailModal,
  closeDetailModal,
  openOverrideDrawer,
  closeOverrideDrawer,
  submitOverride,
  exportAuditReport,
  downloadCaSignedLedger,
  loadData,
} = useAdminFinancialExceptions()
</script>

<template>
  <div class="financial-exceptions-view">
    <!-- Header -->
    <header class="view-header">
      <div class="header-titles">
        <div class="title-with-badge">
          <div class="title-with-icon">
            <span class="material-symbols-outlined header-icon">account_balance_wallet</span>
            <h1 class="page-title">{{ t('admin.exceptions.title') }}</h1>
          </div>
          <span class="badge-live">
            <span class="live-dot"></span>
            <span>{{ t('admin.exceptions.liveMonitoring') }}</span>
          </span>
        </div>
        <p class="page-sub">{{ t('admin.exceptions.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <!-- Search Box -->
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.exceptions.searchPlaceholder')"
            class="search-input"
          />
          <button v-if="searchQuery" type="button" class="btn-clear-search" @click="searchQuery = ''">
            <span class="material-symbols-outlined icon-14">close</span>
          </button>
        </div>

        <!-- Refresh Button -->
        <AuraButton
          variant="secondary"
          :disabled="loading"
          :title="t('common.refresh')"
          @click="loadData"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">refresh</span>
        </AuraButton>

        <!-- Export Button -->
        <AuraButton
          variant="outline"
          type="button"
          :disabled="isExporting"
          :title="t('admin.exceptions.exportBtn')"
          @click="exportAuditReport"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': isExporting }">
            {{ isExporting ? 'sync' : 'download' }}
          </span>
          <span>{{ isExporting ? t('admin.exceptions.exporting') : t('admin.exceptions.exportBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Notice Alert -->
    <transition name="fade">
      <div v-if="notice" class="alert-notice">
        <span class="material-symbols-outlined icon-18">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>

    <!-- Error Alert -->
    <transition name="fade">
      <div v-if="error" class="alert-error">
        <span class="material-symbols-outlined icon-18">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Summary KPI Cards -->
    <ExceptionKpiCards :stats="stats" :loading="loading" />

    <!-- Main Workspace Split 70% / 30% -->
    <div class="workspace-layout">
      <!-- Left Column: Exceptions Table (70%) -->
      <ExceptionTable
        :items="filteredExceptions"
        :active-tab="activeTab"
        :total-count="exceptions.length"
        :critical-count="exceptions.filter((e) => e.severity === 'critical' && e.status !== 'resolved').length"
        :loading="loading"
        :show-advanced-filters="showAdvancedFilters"
        :filter-date="filterDate"
        :filter-severity="filterSeverity"
        :filter-status="filterStatus"
        @update:active-tab="activeTab = $event"
        @update:filter-date="filterDate = $event"
        @update:filter-severity="filterSeverity = $event"
        @update:filter-status="filterStatus = $event"
        @toggle-filters="toggleAdvancedFilters"
        @reset-filters="resetFilters"
        @view-detail="openDetailModal"
        @override="openOverrideDrawer"
      />

      <!-- Right Column: Immutable Audit Trail (30%) -->
      <ImmutableAuditSidebar
        :logs="auditLogs"
        :loading="loading"
        @download-ca="downloadCaSignedLedger"
      />
    </div>

    <!-- Slide-over Drawer for Manual Override -->
    <ManualOverrideDrawer
      :show="isOverrideDrawerOpen"
      :item="selectedOverrideItem"
      :loading="loading"
      @close="closeOverrideDrawer"
      @submit="submitOverride"
    />

    <!-- Modal for Exception Dossier Details -->
    <ExceptionDetailModal
      :show="isDetailModalOpen"
      :item="selectedDetailItem"
      @close="closeDetailModal"
      @proceed-override="openOverrideDrawer"
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

.title-with-badge {
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
  font-size: 28px;
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
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.04em;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse-dot 1.5s infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.8); }
}

.page-sub {
  font-size: 13px;
  color: var(--muted, #817476);
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
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e1e3e4);
  border-radius: 999px;
  padding: 7px 16px;
  width: 320px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
  transition: border-color 0.2s;
}

.search-box:focus-within {
  border-color: var(--primary, #78555e);
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

.btn-clear-search {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #817476);
  padding: 2px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #047857;
  padding: 12px 16px;
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
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.workspace-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 20px;
  align-items: start;
}

.icon-14 { font-size: 14px; }
.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 1080px) {
  .workspace-layout {
    grid-template-columns: 1fr;
  }
}
</style>

