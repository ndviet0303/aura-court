<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PackagePricesTab from '../components/PackagePricesTab.vue'

const { t } = useI18n()

// D15: payment requests, package status and the fee report join as tabs in Pha 6.
const tabs = [{ id: 'prices', labelKey: 'admin.platformFees.tabs.prices' }] as const
const activeTab = ref<(typeof tabs)[number]['id']>('prices')
</script>

<template>
  <div class="platform-fees-view">
    <header class="title-with-icon">
      <span class="material-symbols-outlined header-icon" aria-hidden="true">sell</span>
      <div>
        <h1 class="page-title">{{ t('admin.platformFees.title') }}</h1>
        <p class="subtitle">{{ t('admin.platformFees.subtitle') }}</p>
      </div>
    </header>

    <nav class="admin-tab-nav" role="tablist" :aria-label="t('admin.platformFees.title')">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        role="tab"
        class="admin-nav-item"
        :class="{ active: activeTab === tab.id }"
        :aria-selected="activeTab === tab.id"
        :data-testid="`platform-fees-tab-${tab.id}`"
        @click="activeTab = tab.id"
      >
        {{ t(tab.labelKey) }}
      </button>
    </nav>

    <PackagePricesTab v-if="activeTab === 'prices'" role="tabpanel" />
  </div>
</template>

<style scoped>
.platform-fees-view {
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

.admin-tab-nav {
  margin-bottom: 0;
}

.admin-nav-item {
  border: none;
  cursor: pointer;
  font-family: inherit;
}
</style>
