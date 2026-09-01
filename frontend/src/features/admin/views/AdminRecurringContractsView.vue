<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminContracts } from '../composables/useAdminContracts'

import ContractsTable from '../components/contracts/ContractsTable.vue'
import ContractDetailModal from '../components/contracts/ContractDetailModal.vue'
import ContractCreateModal from '../components/contracts/ContractCreateModal.vue'

const { t } = useI18n()

const {
  activeStatusTab,
  searchQuery,
  selectedContract,
  showDetailModal,
  showCreateModal,
  notice,
  error,
  contracts,
  stats,
  filteredContracts,
  showNotice,
  openDetail,
} = useAdminContracts()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function handleCreateContract(payload: any) {
  contracts.value.unshift({
    id: Date.now(),
    contract_code: `CTR-2026-00${contracts.value.length + 1}`,
    customer_name: payload.customer_name,
    phone: payload.phone,
    court_name: payload.court_name,
    venue_name: 'Downtown Arena (Quận 1)',
    time_slot: payload.time_slot,
    weekday: payload.weekday,
    start_date: '2026-04-01',
    end_date: '2027-04-01',
    total_monthly_amount: payload.total_monthly_amount,
    deposit_amount: 5000000,
    status: 'active',
    payment_cycles: [],
  })
  showCreateModal.value = false
  showNotice(t('admin.contracts.createSuccess', { customer: payload.customer_name }))
}
</script>

<template>
  <div class="admin-contracts-view">
    <!-- Header Section -->
    <header class="contracts-header">
      <div class="header-titles">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">contract</span>
          <h1 class="page-title">{{ t('admin.contracts.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.contracts.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="outline" @click="showNotice(t('admin.contracts.exportNotice'))">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>{{ t('admin.contracts.exportExcel') }}</span>
        </AuraButton>
        <AuraButton variant="primary" class="btn-primary-pink" @click="showCreateModal = true">
          <span class="material-symbols-outlined icon-18">post_add</span>
          <span>{{ t('admin.contracts.createContract') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- 4 KPI Stat Cards -->
    <div class="contracts-kpi-grid">
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon">contract</span>
          <span class="kpi-label">{{ t('admin.contracts.activeContracts') }}</span>
        </div>
        <strong class="kpi-value">{{ t('admin.contracts.activeContractsCount', { count: stats.activeContracts }) }}</strong>
        <span class="kpi-trend text-green">{{ t('admin.contracts.activeTrend') }}</span>
      </div>

      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">payments</span>
          <span class="kpi-label">{{ t('admin.contracts.mrrLabel') }}</span>
        </div>
        <strong class="kpi-value text-pink">{{ formatMoney(stats.mrrAmount) }}</strong>
        <span class="kpi-trend">{{ t('admin.contracts.mrrContribution') }}</span>
      </div>

      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-yellow">warning</span>
          <span class="kpi-label">{{ t('admin.contracts.overdueCycles') }}</span>
        </div>
        <strong class="kpi-value text-yellow">{{ t('admin.contracts.overdueCount', { count: stats.overdueCycles }) }}</strong>
        <span class="kpi-trend text-red">{{ t('admin.contracts.overdueWarning') }}</span>
      </div>

      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-green">bolt</span>
          <span class="kpi-label">{{ t('admin.contracts.primeTimeUtilization') }}</span>
        </div>
        <strong class="kpi-value text-green">{{ stats.primeTimeUtilization }}%</strong>
        <span class="kpi-trend">{{ t('admin.contracts.primeTimeHours') }}</span>
      </div>
    </div>

    <!-- Alert Notices -->
    <div v-if="notice" class="alert-pill toast-floating-banner">{{ notice }}</div>
    <div v-if="error" class="alert-pill-error">{{ error }}</div>

    <!-- Main Table -->
    <ContractsTable
      :contracts="filteredContracts"
      :active-status-tab="activeStatusTab"
      :search-query="searchQuery"
      @update:active-status-tab="activeStatusTab = $event"
      @update:search-query="searchQuery = $event"
      @view-detail="openDetail"
      @open-create="showCreateModal = true"
    />

    <!-- Modals -->
    <ContractDetailModal
      :show="showDetailModal"
      :contract="selectedContract"
      @close="showDetailModal = false"
    />

    <ContractCreateModal
      :show="showCreateModal"
      @close="showCreateModal = false"
      @create="handleCreateContract"
    />
  </div>
</template>

<style scoped>
.admin-contracts-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.contracts-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
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
}

.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.contracts-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.kpi-top-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.kpi-icon {
  font-size: 20px;
  color: var(--muted);
}

.kpi-label {
  font-size: 12px;
  color: var(--muted);
}

.kpi-value {
  font-size: 18px;
}

.kpi-trend {
  font-size: 11px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-green { color: #10b981; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

.alert-pill {
  padding: 8px 16px;
  border-radius: 8px;
  background: #10b981;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  padding: 8px 16px;
  border-radius: 8px;
  background: #ef4444;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 900px) {
  .contracts-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
