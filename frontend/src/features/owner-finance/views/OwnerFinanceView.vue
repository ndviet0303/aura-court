<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOwnerFinanceFilters } from '../composables/useOwnerFinanceFilters'
import OwnerFinanceFilterBar from '../components/OwnerFinanceFilterBar.vue'
import OwnerFinanceTransactionsTab from '../components/OwnerFinanceTransactionsTab.vue'
import OwnerFinanceReportTab from '../components/OwnerFinanceReportTab.vue'

const { t } = useI18n()
const { organizationId, venues, courts, filters } = useOwnerFinanceFilters()

const tabs = [
  { id: 'transactions', labelKey: 'admin.ownerFinance.tabs.transactions' },
  { id: 'report', labelKey: 'admin.ownerFinance.tabs.report' },
] as const
const activeTab = ref<(typeof tabs)[number]['id']>('transactions')
</script>

<template>
  <div class="owner-finance-view">
    <header class="title-with-icon">
      <span class="material-symbols-outlined header-icon" aria-hidden="true">monitoring</span>
      <div>
        <h1 class="page-title">{{ t('admin.ownerFinance.title') }}</h1>
        <p class="subtitle">{{ t('admin.ownerFinance.subtitle') }}</p>
      </div>
    </header>

    <p v-if="organizationId === undefined" class="error" role="alert">
      {{ t('admin.ownerFinance.noOrganization') }}
    </p>
    <template v-else>
      <nav class="admin-tab-nav" role="tablist" :aria-label="t('admin.ownerFinance.title')">
        <button
          v-for="tab in tabs"
          :id="`owner-finance-tab-${tab.id}`"
          :key="tab.id"
          type="button"
          role="tab"
          class="admin-nav-item"
          :class="{ active: activeTab === tab.id }"
          :aria-selected="activeTab === tab.id"
          aria-controls="owner-finance-panel"
          :data-testid="`owner-finance-tab-${tab.id}`"
          @click="activeTab = tab.id"
        >
          {{ t(tab.labelKey) }}
        </button>
      </nav>

      <OwnerFinanceFilterBar
        v-model="filters"
        :venues="venues"
        :courts="courts"
        :show-court="activeTab === 'report'"
      />

      <div
        id="owner-finance-panel"
        role="tabpanel"
        :aria-labelledby="`owner-finance-tab-${activeTab}`"
      >
        <OwnerFinanceTransactionsTab
          v-if="activeTab === 'transactions'"
          :organization-id="organizationId"
          :filters="filters"
        />
        <OwnerFinanceReportTab v-else :organization-id="organizationId" :filters="filters" />
      </div>
    </template>
  </div>
</template>

<style scoped>
.owner-finance-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-icon {
  font-size: 26px;
  color: var(--primary);
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
}

.subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--muted);
}

.error {
  margin: 0;
  color: var(--error-text);
}

.admin-tab-nav {
  margin-bottom: 0;
}

.admin-nav-item {
  border: none;
  cursor: pointer;
  font-family: inherit;
}
</style>
