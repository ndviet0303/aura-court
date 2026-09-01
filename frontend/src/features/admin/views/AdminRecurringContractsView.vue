<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminContracts } from '../composables/useAdminContracts'

import ContractsKpiBar from '../components/contracts/ContractsKpiBar.vue'
import ContractsTable from '../components/contracts/ContractsTable.vue'
import ContractDetailModal from '../components/contracts/ContractDetailModal.vue'
import ContractCreateModal from '../components/contracts/ContractCreateModal.vue'
import ContractInvoicePayModal from '../components/contracts/ContractInvoicePayModal.vue'

const { t } = useI18n()

const {
  loading,
  saving,
  actionLoading,
  activeStatusTab,
  searchQuery,
  selectedVenueId,
  selectedContract,
  selectedInvoiceForPay,
  showDetailModal,
  showCreateModal,
  showPayModal,
  notice,
  error,
  venues,
  courts,
  stats,
  filteredContracts,
  loadData,
  createContract,
  cancelContract,
  payInvoice,
  exportExcel,
  openDetail,
  openPayModal,
} = useAdminContracts()
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
        <AuraButton variant="secondary" :disabled="loading" @click="loadData">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-icon': loading }">refresh</span>
          <span>{{ t('admin.contracts.refreshBtn') }}</span>
        </AuraButton>
        <AuraButton variant="outline" @click="exportExcel">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>{{ t('admin.contracts.exportExcel') }}</span>
        </AuraButton>
        <AuraButton variant="primary" class="btn-primary-pink" @click="showCreateModal = true">
          <span class="material-symbols-outlined icon-18">post_add</span>
          <span>{{ t('admin.contracts.createContract') }}</span>
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

    <!-- 4 KPI Stat Cards with Skeleton Loading -->
    <ContractsKpiBar :stats="stats" :loading="loading" />

    <!-- Contracts Table with Tabs, Search, Venue Filter & Skeletons -->
    <ContractsTable
      :contracts="filteredContracts"
      :venues="venues"
      :active-status-tab="activeStatusTab"
      :search-query="searchQuery"
      :selected-venue-id="selectedVenueId"
      :loading="loading"
      @update:active-status-tab="activeStatusTab = $event"
      @update:search-query="searchQuery = $event"
      @update:selected-venue-id="selectedVenueId = $event"
      @view-detail="openDetail"
      @open-create="showCreateModal = true"
    />

    <!-- Contract Detail Modal -->
    <ContractDetailModal
      :show="showDetailModal"
      :contract="selectedContract"
      :action-loading="actionLoading"
      @close="showDetailModal = false"
      @pay-invoice="(inv) => selectedContract && openPayModal(selectedContract, inv)"
      @cancel-contract="(id) => cancelContract(id)"
    />

    <!-- Create Contract Modal -->
    <ContractCreateModal
      :show="showCreateModal"
      :venues="venues"
      :courts="courts"
      :saving="saving"
      @close="showCreateModal = false"
      @create="createContract"
    />

    <!-- Pay Invoice Confirmation Modal -->
    <ContractInvoicePayModal
      :show="showPayModal"
      :target="selectedInvoiceForPay"
      :loading="actionLoading"
      @close="showPayModal = false"
      @confirm="({ seriesId, invoiceId, reference }) => payInvoice(seriesId, invoiceId, reference)"
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
  gap: 16px;
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
}

.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.btn-primary-pink {
  background: var(--pink-dark, #db2777) !important;
  color: #fff !important;
}

.alert-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
  font-size: 13px;
  font-weight: 600;
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
  font-size: 13px;
  font-weight: 600;
}

.icon-16 { font-size: 16px; }
.icon-18 { font-size: 18px; }

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
