<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, SkeletonLoader } from '@/shared/components'

defineProps<{
  kpis: {
    totalItems: number
    totalValue: number
    lowStockCount: number
    outOfStockCount: number
  }
  activeTab: 'catalog' | 'movements'
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'catalog' | 'movements'): void
  (e: 'addProduct'): void
  (e: 'stockIn'): void
  (e: 'audit'): void
  (e: 'refresh'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="inventory-kpi-header-card">
    <div class="header-top">
      <div>
        <div class="header-title-row">
          <span class="material-symbols-outlined header-icon">inventory_2</span>
          <h1 class="page-title">{{ t('admin.inventory.title') }}</h1>
        </div>
        <p class="page-sub">{{ t('admin.inventory.subtitle') }}</p>
      </div>

      <div class="header-actions">
        <AuraButton variant="outline" :disabled="loading" @click="emit('refresh')">
          <span class="material-symbols-outlined icon-18" :class="{ 'spin-anim': loading }">refresh</span>
          <span>{{ t('common.refresh') }}</span>
        </AuraButton>

        <AuraButton variant="primary" @click="emit('addProduct')">
          <span class="material-symbols-outlined icon-18">add_box</span>
          <span>{{ t('admin.inventory.addProduct') }}</span>
        </AuraButton>

        <AuraButton variant="secondary" @click="emit('stockIn')">
          <span class="material-symbols-outlined icon-18">move_to_inbox</span>
          <span>{{ t('admin.inventory.stockIn') }}</span>
        </AuraButton>

        <AuraButton variant="outline" @click="emit('audit')">
          <span class="material-symbols-outlined icon-18">fact_check</span>
          <span>{{ t('admin.inventory.auditStock') }}</span>
        </AuraButton>
      </div>
    </div>

    <!-- KPI Metrics Grid -->
    <div class="kpis-grid">
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon">inventory</span>
          <span class="lbl">{{ t('admin.inventory.totalItems') }}</span>
        </div>
        <SkeletonLoader v-if="loading" type="text" width="90px" height="22px" />
        <strong v-else class="val font-mono">{{ t('admin.inventory.totalItemsCount', { count: kpis.totalItems }) }}</strong>
      </div>

      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">payments</span>
          <span class="lbl">{{ t('admin.inventory.totalValue') }}</span>
        </div>
        <SkeletonLoader v-if="loading" type="text" width="120px" height="22px" />
        <strong v-else class="val text-pink font-mono">{{ formatCurrency(kpis.totalValue) }}</strong>
      </div>

      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-yellow">warning</span>
          <span class="lbl">{{ t('admin.inventory.lowStock') }}</span>
        </div>
        <SkeletonLoader v-if="loading" type="text" width="90px" height="22px" />
        <strong v-else class="val text-yellow font-mono">{{ t('admin.inventory.lowStockCount', { count: kpis.lowStockCount }) }}</strong>
      </div>

      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-red">error</span>
          <span class="lbl">{{ t('admin.inventory.outOfStock') }}</span>
        </div>
        <SkeletonLoader v-if="loading" type="text" width="90px" height="22px" />
        <strong v-else class="val text-red font-mono">{{ t('admin.inventory.outOfStockCount', { count: kpis.outOfStockCount }) }}</strong>
      </div>
    </div>

    <!-- Tabs switcher -->
    <div class="tabs-row">
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'catalog' }"
        @click="emit('update:activeTab', 'catalog')"
      >
        <span class="material-symbols-outlined icon-18">inventory_2</span>
        <span>{{ t('admin.inventory.tabCatalog') }}</span>
      </button>
      <button
        type="button"
        class="tab-btn"
        :class="{ active: activeTab === 'movements' }"
        @click="emit('update:activeTab', 'movements')"
      >
        <span class="material-symbols-outlined icon-18">history</span>
        <span>{{ t('admin.inventory.tabMovements') }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.inventory-kpi-header-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.header-top {
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
  letter-spacing: -0.02em;
}

.page-sub {
  margin: 6px 0 0;
  font-size: 13.5px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.kpis-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  transition: transform 0.2s ease, border-color 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  border-color: rgba(219, 39, 119, 0.3);
}

.kpi-top-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.kpi-icon {
  font-size: 18px;
  color: var(--muted);
}

.lbl {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.val {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
}

.text-pink { color: var(--pink-dark, #db2777); }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }

.tabs-row {
  display: flex;
  gap: 10px;
  border-top: 1px solid var(--outline);
  padding-top: 16px;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-btn:hover {
  color: var(--ink);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
}

.tab-btn.active {
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(219, 39, 119, 0.25);
  font-weight: 700;
}

.icon-18 {
  font-size: 18px;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 900px) {
  .kpis-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 500px) {
  .kpis-grid {
    grid-template-columns: 1fr;
  }
}
</style>
