<script setup lang="ts">
import { useAdminInventory } from '../composables/useAdminInventory'

import InventoryKpiHeader from '../components/inventory/InventoryKpiHeader.vue'
import InventoryCatalogTable from '../components/inventory/InventoryCatalogTable.vue'
import InventoryMovementsTable from '../components/inventory/InventoryMovementsTable.vue'
import InventoryProductModal from '../components/inventory/InventoryProductModal.vue'
import InventoryStockActionModal from '../components/inventory/InventoryStockActionModal.vue'

const {
  notice,
  errorMsg,
  activeTab,
  searchQuery,
  categoryFilter,
  stockFilter,
  movementTypeFilter,
  categories,
  filteredProducts,
  filteredMovements,
  kpis,
  showProductModal,
  editingProduct,
  showStockActionModal,
  stockActionType,
  selectedProductForAction,
  openStockAction,
  handleSaveProduct,
  handleStockAction,
} = useAdminInventory()

function openAddProduct() {
  editingProduct.value = null
  showProductModal.value = true
}

function openEditProduct(p: any) {
  editingProduct.value = p
  showProductModal.value = true
}
</script>

<template>
  <div class="admin-inventory-view">
    <!-- Feedback Alerts -->
    <div v-if="notice" class="alert-pill">{{ notice }}</div>
    <div v-if="errorMsg" class="alert-pill-error">{{ errorMsg }}</div>

    <!-- Header & KPIs Grid -->
    <InventoryKpiHeader
      :kpis="kpis"
      :active-tab="activeTab"
      @update:active-tab="activeTab = $event"
      @add-product="openAddProduct"
      @stock-in="openStockAction('in')"
      @audit="openStockAction('adjust')"
    />

    <!-- Main Content Area -->
    <main class="inventory-main-content">
      <InventoryCatalogTable
        v-if="activeTab === 'catalog'"
        :products="filteredProducts"
        :categories="categories"
        :search="searchQuery"
        :category-filter="categoryFilter"
        :stock-filter="stockFilter"
        @update:search="searchQuery = $event"
        @update:category-filter="categoryFilter = $event"
        @update:stock-filter="stockFilter = $event"
        @edit="openEditProduct"
        @stock-in="openStockAction('in', $event)"
        @stock-out="openStockAction('out', $event)"
      />

      <InventoryMovementsTable
        v-else-if="activeTab === 'movements'"
        :movements="filteredMovements"
        :type-filter="movementTypeFilter"
        @update:type-filter="movementTypeFilter = $event"
      />
    </main>

    <!-- Modals -->
    <InventoryProductModal
      :show="showProductModal"
      :product="editingProduct"
      @close="showProductModal = false"
      @save="handleSaveProduct"
    />

    <InventoryStockActionModal
      :show="showStockActionModal"
      :type="stockActionType"
      :product="selectedProductForAction"
      @close="showStockActionModal = false"
      @submit="handleStockAction"
    />
  </div>
</template>

<style scoped>
.admin-inventory-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

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

.inventory-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
</style>
