<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { usePricingCalendarV2 } from '../composables/usePricingCalendarV2'
import type { PriceRuleItem, PriceRuleFormPayload } from '../types/pricing'

import PricingKpiBar from '../components/pricing/PricingKpiBar.vue'
import PricingRulePriorityStack from '../components/pricing/PricingRulePriorityStack.vue'
import PricingCalendarGrid from '../components/pricing/PricingCalendarGrid.vue'
import PricingSimulatorModal from '../components/pricing/PricingSimulatorModal.vue'
import PricingRuleModal from '../components/pricing/PricingRuleModal.vue'
import PricingPresetsModal from '../components/pricing/PricingPresetsModal.vue'

const { t } = useI18n()

const {
  loading,
  saving,
  simulating,
  applyingPreset,
  notice,
  error,
  activeTab,
  showSimulatorModal,
  showRuleModal,
  showPresetsModal,
  editingRule,
  rules,
  venues,
  sportZones,
  courts,
  searchQuery,
  selectedVenueId,
  selectedCourtId,
  selectedTypeFilter,
  selectedStatusFilter,
  quoteResult,
  quoteError,
  filteredRules,
  totalRulesCount,
  activeRulesCount,
  peakRulesCount,
  holidayRulesCount,
  averagePrice,
  loadData,
  saveRule,
  toggleRuleActive,
  deleteRule,
  simulateQuote,
  applyPresetRules,
} = usePricingCalendarV2()

function handleStartCreate() {
  editingRule.value = null
  showRuleModal.value = true
}

function handleStartEdit(rule: PriceRuleItem) {
  editingRule.value = rule
  showRuleModal.value = true
}

function handleCreateForSlot(payload: { weekday: number; starts_at: string; ends_at: string; court_id?: number }) {
  editingRule.value = {
    id: 0,
    name: '',
    type: 'peak',
    weekday: payload.weekday,
    starts_at: payload.starts_at,
    ends_at: payload.ends_at,
    court_id: payload.court_id || null,
    price: 150000,
    priority: 2,
    is_active: true,
    version: 1,
  }
  showRuleModal.value = true
}

async function handleSaveRule(payload: PriceRuleFormPayload) {
  await saveRule(payload)
}

function handleClearFilters() {
  searchQuery.value = ''
  selectedVenueId.value = 'all'
  selectedCourtId.value = 'all'
  selectedTypeFilter.value = 'all'
  selectedStatusFilter.value = 'all'
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="admin-pricing-view">
    <!-- Header Section -->
    <header class="pricing-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">price_change</span>
          <h1 class="page-title">{{ t('admin.pricing.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.pricing.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="secondary" :disabled="loading" @click="loadData">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">refresh</span>
          <span>{{ t('admin.pricing.refreshBtn') }}</span>
        </AuraButton>
        <AuraButton variant="secondary" @click="showPresetsModal = true">
          <span class="material-symbols-outlined icon-18">dashboard_customize</span>
          <span>{{ t('admin.pricing.presetsBtn') }}</span>
        </AuraButton>
        <AuraButton variant="outline" @click="showSimulatorModal = true">
          <span class="material-symbols-outlined icon-18">calculate</span>
          <span>{{ t('admin.pricing.simulatorBtn') }}</span>
        </AuraButton>
        <AuraButton variant="primary" @click="handleStartCreate">
          <span class="material-symbols-outlined icon-18">add_circle</span>
          <span>{{ t('admin.pricing.addNewRuleBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">
      <span class="material-symbols-outlined icon-16">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div v-if="error" class="alert-pill-error">
      <span class="material-symbols-outlined icon-16">error</span>
      <span>{{ error }}</span>
    </div>

    <!-- KPI Summary Bar -->
    <PricingKpiBar
      :total-rules="totalRulesCount"
      :active-rules="activeRulesCount"
      :peak-rules="peakRulesCount"
      :holiday-rules="holidayRulesCount"
      :average-price="averagePrice"
      :loading="loading"
    />

    <!-- Navigation Tab Switcher -->
    <div class="pricing-tabs-bar">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'rules' }"
        @click="activeTab = 'rules'"
      >
        <span class="material-symbols-outlined icon-18">tune</span>
        <span>{{ t('admin.pricing.tabRulesList') }}</span>
        <span class="tab-count-badge font-mono">{{ filteredRules.length }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'matrix' }"
        @click="activeTab = 'matrix'"
      >
        <span class="material-symbols-outlined icon-18">calendar_view_week</span>
        <span>{{ t('admin.pricing.tabCalendarMatrix') }}</span>
      </button>
    </div>

    <!-- Tab 1: Rules Priority Stack -->
    <PricingRulePriorityStack
      v-if="activeTab === 'rules'"
      :rules="filteredRules"
      :venues="venues"
      :courts="courts"
      :loading="loading"
      :search-query="searchQuery"
      :selected-venue-id="selectedVenueId"
      :selected-court-id="selectedCourtId"
      :selected-type-filter="selectedTypeFilter"
      :selected-status-filter="selectedStatusFilter"
      @update:search-query="searchQuery = $event"
      @update:selected-venue-id="selectedVenueId = $event"
      @update:selected-court-id="selectedCourtId = $event"
      @update:selected-type-filter="selectedTypeFilter = $event"
      @update:selected-status-filter="selectedStatusFilter = $event"
      @create-rule="handleStartCreate"
      @edit-rule="handleStartEdit"
      @toggle-active="toggleRuleActive"
      @delete-rule="deleteRule"
      @clear-filters="handleClearFilters"
    />

    <!-- Tab 2: Interactive Calendar Grid Matrix -->
    <PricingCalendarGrid
      v-else-if="activeTab === 'matrix'"
      :rules="rules"
      :courts="courts"
      :venues="venues"
      :loading="loading"
      @create-for-slot="handleCreateForSlot"
    />

    <!-- Modals -->
    <PricingRuleModal
      :show="showRuleModal"
      :rule="editingRule"
      :venues="venues"
      :sport-zones="sportZones"
      :courts="courts"
      :saving="saving"
      @close="showRuleModal = false"
      @save="handleSaveRule"
    />

    <PricingSimulatorModal
      :show="showSimulatorModal"
      :courts="courts"
      :rules="rules"
      :simulating="simulating"
      :quote-result="quoteResult"
      :quote-error="quoteError"
      @close="showSimulatorModal = false"
      @simulate="simulateQuote($event.courtId, $event.dateStr, $event.startTime, $event.durationHours)"
    />

    <PricingPresetsModal
      :show="showPresetsModal"
      :venues="venues"
      :courts="courts"
      :applying="applyingPreset"
      @close="showPresetsModal = false"
      @apply-preset="applyPresetRules($event.presetId, $event.venueId, $event.courtId)"
    />
  </div>
</template>

<style scoped>
.admin-pricing-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.pricing-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.header-titles {
  display: flex;
  flex-direction: column;
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
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.header-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.alert-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.alert-pill-error {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}

.pricing-tabs-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid var(--outline);
  padding-bottom: 2px;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: var(--ink);
}

.tab-btn.active {
  color: var(--pink-dark, #db2777);
  border-bottom-color: var(--pink-dark, #db2777);
}

.tab-count-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  color: var(--muted);
}

.tab-btn.active .tab-count-badge {
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }
</style>
