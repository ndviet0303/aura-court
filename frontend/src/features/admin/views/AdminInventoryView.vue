<script setup lang="ts">
import { useAdminInventory } from '../composables/useAdminInventory'

import InventoryKpiHeader from '../components/inventory/InventoryKpiHeader.vue'
import InventoryCatalogTable from '../components/inventory/InventoryCatalogTable.vue'
import InventoryMovementsTable from '../components/inventory/InventoryMovementsTable.vue'
import InventoryProductModal from '../components/inventory/InventoryProductModal.vue'
import InventoryStockActionModal from '../components/inventory/InventoryStockActionModal.vue'
import InventoryDeleteModal from '../components/inventory/InventoryDeleteModal.vue'

const {
  loading,
  saving,
  notice,
  errorMsg,
  activeTab,
  searchQuery,
  categoryFilter,
  stockFilter,
  movementTypeFilter,
  movementProductFilter,
  movementDateFrom,
  movementDateTo,
  movementSearch,
  categories,
  products,
  filteredProducts,
  filteredMovements,
  kpis,
  showProductModal,
  editingProduct,
  showStockActionModal,
  stockActionType,
  selectedProductForAction,
  showDeleteModal,
  productToDelete,
  openAddProduct,
  openEditProduct,
  openStockAction,
  openDeleteModal,
  handleSaveProduct,
  handleStockAction,
  handleDeleteProduct,
  resetCatalogFilters,
  resetMovementFilters,
  loadInventoryData,
} = useAdminInventory()
</script>

<template>
  <div class="admin-inventory-view">
    <!-- Feedback Alerts -->
    <transition name="fade">
      <div v-if="notice" class="alert-pill">
        <span class="material-symbols-outlined icon-18">check_circle</span>
        <span>{{ notice }}</span>
        <button type="button" class="alert-close-btn" @click="notice = ''">✕</button>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="errorMsg" class="alert-pill-error">
        <span class="material-symbols-outlined icon-18">error</span>
        <span>{{ errorMsg }}</span>
        <button type="button" class="alert-close-btn" @click="errorMsg = ''">✕</button>
      </div>
    </transition>

    <!-- Header & KPIs Grid -->
    <InventoryKpiHeader
      :kpis="kpis"
      :active-tab="activeTab"
      :loading="loading"
      @update:active-tab="activeTab = $event"
      @add-product="openAddProduct"
      @stock-in="openStockAction('in')"
      @audit="openStockAction('adjust')"
      @refresh="loadInventoryData"
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
        :loading="loading"
        @update:search="searchQuery = $event"
        @update:category-filter="categoryFilter = $event"
        @update:stock-filter="stockFilter = $event"
        @edit="openEditProduct"
        @stock-in="openStockAction('in', $event)"
        @stock-out="openStockAction('out', $event)"
        @delete="openDeleteModal"
        @reset-filters="resetCatalogFilters"
        @add-product="openAddProduct"
      />

      <InventoryMovementsTable
        v-else-if="activeTab === 'movements'"
        :movements="filteredMovements"
        :products="products"
        :type-filter="movementTypeFilter"
        :product-filter="movementProductFilter"
        :date-from="movementDateFrom"
        :date-to="movementDateTo"
        :search="movementSearch"
        :loading="loading"
        @update:type-filter="movementTypeFilter = $event"
        @update:product-filter="movementProductFilter = $event"
        @update:date-from="movementDateFrom = $event"
        @update:date-to="movementDateTo = $event"
        @update:search="movementSearch = $event"
        @reset-filters="resetMovementFilters"
      />
    </main>

    <!-- Modals -->
    <InventoryProductModal
      :show="showProductModal"
      :product="editingProduct"
      :saving="saving"
      @close="showProductModal = false"
      @save="handleSaveProduct"
    />

    <InventoryStockActionModal
      :show="showStockActionModal"
      :type="stockActionType"
      :product="selectedProductForAction"
      :saving="saving"
      @close="showStockActionModal = false"
      @submit="handleStockAction"
    />

    <InventoryDeleteModal
      :show="showDeleteModal"
      :product="productToDelete"
      :saving="saving"
      @close="showDeleteModal = false"
      @confirm="handleDeleteProduct"
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
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  border-radius: 12px;
  background: #10b981;
  color: #fff;
  font-size: 13.5px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
}

.alert-pill-error {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  border-radius: 12px;
  background: #ef4444;
  color: #fff;
  font-size: 13.5px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);
}

.alert-close-btn {
  margin-left: auto;
  background: transparent;
  border: none;
  color: #fff;
  font-size: 14px;
  cursor: pointer;
  opacity: 0.8;
  padding: 0 4px;
}

.alert-close-btn:hover {
  opacity: 1;
}

.inventory-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.icon-18 {
  font-size: 18px;
}

.fade-enter-active,
.fade-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
