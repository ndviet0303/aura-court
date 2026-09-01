<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'

defineProps<{
  kpis: {
    totalItems: number
    totalValue: number
    lowStockCount: number
    outOfStockCount: number
  }
  activeTab: 'catalog' | 'movements'
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'catalog' | 'movements'): void
  (e: 'addProduct'): void
  (e: 'stockIn'): void
  (e: 'audit'): void
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
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
        <strong class="val">{{ t('admin.inventory.totalItemsCount', { count: kpis.totalItems }) }}</strong>
      </div>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-pink">payments</span>
          <span class="lbl">{{ t('admin.inventory.totalValue') }}</span>
        </div>
        <strong class="val text-pink">{{ formatMoney(kpis.totalValue) }}</strong>
      </div>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-yellow">warning</span>
          <span class="lbl">{{ t('admin.inventory.lowStock') }}</span>
        </div>
        <strong class="val text-yellow">{{ t('admin.inventory.lowStockCount', { count: kpis.lowStockCount }) }}</strong>
      </div>
      <div class="kpi-card">
        <div class="kpi-top-row">
          <span class="material-symbols-outlined kpi-icon text-red">error</span>
          <span class="lbl">{{ t('admin.inventory.outOfStock') }}</span>
        </div>
        <strong class="val text-red">{{ t('admin.inventory.outOfStockCount', { count: kpis.outOfStockCount }) }}</strong>
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
  gap: 6px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
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
  font-size: 10px;
  font-weight: 700;
  color: var(--muted);
}

.val {
  font-size: 18px;
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
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.tab-btn.active {
  background: rgba(236, 72, 153, 0.1);
  color: var(--pink-dark, #db2777);
  border-color: rgba(236, 72, 153, 0.2);
}

.icon-18 {
  font-size: 18px;
}

@media (max-width: 900px) {
  .kpis-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
