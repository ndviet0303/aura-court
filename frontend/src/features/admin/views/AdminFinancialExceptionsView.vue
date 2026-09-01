<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { useAdminFinancialExceptions } from '../composables/useAdminFinancialExceptions'
import ExceptionKpiCards from '../components/exceptions/ExceptionKpiCards.vue'
import ExceptionTable from '../components/exceptions/ExceptionTable.vue'
import ImmutableAuditSidebar from '../components/exceptions/ImmutableAuditSidebar.vue'
import ManualOverrideDrawer from '../components/exceptions/ManualOverrideDrawer.vue'

const { t } = useI18n()

const {
  activeTab,
  searchQuery,
  notice,
  stats,
  exceptions,
  auditLogs,
  selectedItem,
  isDrawerOpen,
  filteredExceptions,
  openOverrideDrawer,
  closeDrawer,
  submitOverride,
} = useAdminFinancialExceptions()
</script>

<template>
  <div class="financial-exceptions-view">
    <!-- Header -->
    <header class="view-header">
      <div class="header-titles">
        <div class="title-with-badge">
          <div class="title-with-icon">
            <span class="material-symbols-outlined header-icon">gavel</span>
            <h1 class="page-title">{{ t('admin.exceptions.title') }}</h1>
          </div>
          <span class="badge-live">{{ t('admin.exceptions.liveMonitoring') }}</span>
        </div>
        <p class="page-sub">{{ t('admin.exceptions.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('admin.exceptions.searchPlaceholder')"
            class="search-input"
          />
        </div>
        <AuraButton variant="outline" type="button" :title="t('admin.exceptions.exportBtn')">
          <span class="material-symbols-outlined icon-18">download</span>
          <span>{{ t('admin.exceptions.exportBtn') }}</span>
        </AuraButton>
      </div>
    </header>

    <!-- Notice alert -->
    <div v-if="notice" class="alert-notice">
      <span class="material-symbols-outlined">check_circle</span>
      <span>{{ notice }}</span>
    </div>

    <!-- Summary KPI Cards -->
    <ExceptionKpiCards :stats="stats" />

    <!-- Main Workspace Split 70 / 30 -->
    <div class="workspace-layout">
      <!-- Left Column: Exceptions Table (70%) -->
      <ExceptionTable
        :items="filteredExceptions"
        :active-tab="activeTab"
        :total-count="exceptions.length"
        :critical-count="exceptions.filter((e) => e.severity === 'critical').length"
        @update:active-tab="activeTab = $event"
        @override="openOverrideDrawer"
      />

      <!-- Right Column: Immutable Audit Trail (30%) -->
      <ImmutableAuditSidebar :logs="auditLogs" />
    </div>

    <!-- Slide-over Drawer for Manual Override -->
    <ManualOverrideDrawer
      :show="isDrawerOpen"
      :item="selectedItem"
      @close="closeDrawer"
      @submit="submitOverride"
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
  font-size: 26px;
  color: var(--pink-dark, #db2777);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
  color: var(--ink);
}

.badge-live {
  font-size: 11px;
  font-weight: 700;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  padding: 3px 10px;
  border-radius: 999px;
  letter-spacing: 0.04em;
}

.page-sub {
  font-size: 13px;
  color: var(--muted);
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

.icon-18 {
  font-size: 18px;
}

@media (max-width: 1080px) {
  .workspace-layout {
    grid-template-columns: 1fr;
  }
}
</style>
