<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton, SelectDropdown, DatePicker } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
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
  filterSeverity,
  filterDisputeType,
  filterDate,
  showAdvancedFilters,
  loading,
  isSubmitting,
  isExporting,
  notice,
  error,
  isDrawerOpen,
  activeEvidenceTab,
  stats,
  selectedDispute,
  filteredDisputes,
  selectDispute,
  openDrawer,
  closeDrawer,
  toggleAdvancedFilters,
  resetFilters,
  submitArbitration,
  handleDismiss,
  handleRelocateCourt,
  exportPdfReport,
  handlePrint,
  loadData,
} = useAdminDisputes()

const severityOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.disputes.filterAll') },
  { value: 'critical', label: t('admin.disputes.severityCritical'), icon: 'warning' },
  { value: 'high', label: t('admin.disputes.severityHigh'), icon: 'error' },
  { value: 'medium', label: t('admin.disputes.severityMedium'), icon: 'info' },
  { value: 'low', label: t('admin.disputes.severityLow'), icon: 'check_circle' },
])

const disputeTypeOptions = computed<DropdownOption[]>(() => [
  { value: 'all', label: t('admin.disputes.filterAll') },
  { value: 'double_booking', label: t('admin.disputes.typeDoubleBooking'), icon: 'event_busy' },
  { value: 'match_stake', label: t('admin.disputes.typeMatchStake'), icon: 'sports_kabaddi' },
  { value: 'resale_fraud', label: t('admin.disputes.typeResaleFraud'), icon: 'gavel' },
  {
    value: 'equipment_damage',
    label: t('admin.disputes.typeEquipmentDamage'),
    icon: 'sports_tennis',
  },
  { value: 'tournament_score', label: t('admin.disputes.typeTournamentScore'), icon: 'scoreboard' },
  { value: 'other', label: t('admin.disputes.typeOther'), icon: 'help' },
])
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
        <p class="header-sub">{{ t('admin.disputes.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <!-- Search Box -->
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.disputes.searchPlaceholder')"
            class="search-input"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="btn-clear-search"
            @click="searchQuery = ''"
          >
            <span class="material-symbols-outlined icon-14">close</span>
          </button>
        </div>

        <!-- Filter Toggle -->
        <AuraButton
          :variant="showAdvancedFilters ? 'primary' : 'secondary'"
          type="button"
          :title="t('admin.disputes.filterStatus')"
          @click="toggleAdvancedFilters"
        >
          <span class="material-symbols-outlined icon-18">filter_list</span>
          <span>{{ t('admin.disputes.filterStatus') }}</span>
        </AuraButton>

        <!-- Refresh Button -->
        <AuraButton
          variant="secondary"
          type="button"
          :disabled="loading"
          :title="t('admin.disputes.refreshBtn')"
          @click="loadData"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }"
            >refresh</span
          >
        </AuraButton>

        <!-- Export PDF Button -->
        <AuraButton
          variant="outline"
          type="button"
          :disabled="isExporting"
          :title="t('admin.disputes.exportPdfBtn')"
          @click="exportPdfReport"
        >
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': isExporting }">
            {{ isExporting ? 'sync' : 'picture_as_pdf' }}
          </span>
          <span>{{
            isExporting ? t('admin.disputes.exportingPdf') : t('admin.disputes.exportPdfBtn')
          }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Advanced Filter Bar (Collapsible) -->
    <transition name="fade">
      <div v-if="showAdvancedFilters" class="advanced-filter-panel">
        <div class="filter-col">
          <label class="filter-label">{{ t('admin.disputes.filterSeverity') }}</label>
          <SelectDropdown v-model="filterSeverity" :options="severityOptions" variant="bordered" />
        </div>

        <div class="filter-col">
          <label class="filter-label">{{ t('admin.disputes.filterType') }}</label>
          <SelectDropdown
            v-model="filterDisputeType"
            :options="disputeTypeOptions"
            variant="bordered"
          />
        </div>

        <div class="filter-col">
          <label class="filter-label">{{ t('admin.disputes.filterDate') }}</label>
          <DatePicker v-model="filterDate" :placeholder="t('admin.disputes.filterDate')" />
        </div>

        <div class="filter-col filter-actions-col">
          <button type="button" class="btn-reset-filters" @click="resetFilters">
            <span class="material-symbols-outlined icon-16">restart_alt</span>
            <span>{{ t('admin.disputes.resetFilters') }}</span>
          </button>
        </div>
      </div>
    </transition>

    <!-- Feedback Notice Alert -->
    <transition name="fade">
      <div v-if="notice" class="alert-notice">
        <span class="material-symbols-outlined icon-18">verified</span>
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

    <!-- KPI Summary Row -->
    <DisputeKpiCards :stats="stats" :loading="loading" />

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
          @print="handlePrint"
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

    <!-- Slide-over Drawer for Arbitration -->
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
  padding: 24px 20px 48px;
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
  font-size: 28px;
  color: var(--primary);
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
  background: var(--primary-container);
  color: var(--primary);
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.04em;
  border: 1px solid var(--primary);
}

.header-sub {
  font-size: 13px;
  color: var(--muted);
  margin: 4px 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 999px;
  padding: 7px 16px;
  width: 320px;
  box-shadow: var(--shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.04));
  transition: border-color 0.2s;
}

.search-box:focus-within {
  border-color: var(--primary);
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

.btn-clear-search {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted);
  padding: 2px;
  display: flex;
  align-items: center;
}

.advanced-filter-panel {
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 16px 20px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  align-items: end;
  box-shadow: var(--shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
}

.filter-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.filter-actions-col {
  display: flex;
  justify-content: flex-end;
}

.btn-reset-filters {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  border-radius: 10px;
  padding: 9px 14px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-reset-filters:hover {
  background: var(--soft);
  color: var(--ink);
}

.alert-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--success-bg, rgba(46, 125, 50, 0.25));
  border: 1px solid rgba(74, 222, 128, 0.3);
  color: var(--success-text, #4ade80);
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.alert-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--error-bg, rgba(198, 40, 40, 0.2));
  border: 1px solid rgba(248, 113, 113, 0.3);
  color: var(--error-text, #f87171);
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.workbench-layout {
  display: grid;
  grid-template-columns: 380px 1fr;
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
  border-radius: 16px;
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
  color: var(--outline);
}

.icon-14 {
  font-size: 14px;
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
  100% {
    transform: rotate(360deg);
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
