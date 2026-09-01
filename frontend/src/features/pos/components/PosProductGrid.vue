<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Product } from '../types/pos'

defineProps<{
  products: Product[]
  categories: Array<{ key: string; label: string; icon: string; labelKey?: string }>
  selectedCategory: string
  searchQuery: string
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedCategory', val: string): void
  (e: 'update:searchQuery', val: string): void
  (e: 'selectProduct', prod: Product): void
  (e: 'openCustomItemModal'): void
}>()

const { t } = useI18n()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="pos-catalog-area">
    <!-- Header & Search Toolbar -->
    <div class="pos-catalog-toolbar">
      <div class="search-box-wrap">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          class="catalog-search-input"
          :placeholder="t('admin.pos.searchPlaceholder')"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <button
          v-if="searchQuery"
          type="button"
          class="clear-search-btn"
          title="Clear"
          @click="emit('update:searchQuery', '')"
        >
          <span class="material-symbols-outlined icon-16">close</span>
        </button>
      </div>

      <button
        type="button"
        class="custom-item-btn"
        :title="t('admin.pos.customItemBtn')"
        @click="emit('openCustomItemModal')"
      >
        <span class="material-symbols-outlined icon-18">add</span>
        <span>{{ t('admin.pos.customItemBtn') }}</span>
      </button>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs-scroll">
      <button
        v-for="cat in categories"
        :key="cat.key"
        type="button"
        class="cat-tab-btn"
        :class="{ active: selectedCategory === cat.key }"
        @click="emit('update:selectedCategory', cat.key)"
      >
        <span class="material-symbols-outlined cat-icon">{{ cat.icon }}</span>
        <span class="cat-label">{{ cat.labelKey ? t(cat.labelKey) : cat.label }}</span>
      </button>
    </div>

    <!-- Products Grid -->
    <div class="products-grid-container">
      <div v-if="loading" class="catalog-loading-state">
        <div class="spinner"></div>
        <p>{{ t('admin.pos.loadingState') }}</p>
      </div>

      <div v-else-if="products.length === 0" class="catalog-empty-state">
        <span class="material-symbols-outlined empty-icon">inventory_2</span>
        <h3>{{ t('admin.pos.noProductsFound') }}</h3>
        <p>{{ t('admin.pos.noProductsSub') }}</p>
      </div>

      <div v-else class="products-grid">
        <div
          v-for="prod in products"
          :key="prod.id"
          class="product-card"
          :class="{ 'out-of-stock': !prod.is_service && prod.stock_quantity <= 0 }"
          @click="emit('selectProduct', prod)"
        >
          <div class="prod-img-wrap">
            <img
              v-if="prod.image_url"
              :src="prod.image_url"
              :alt="prod.name"
              loading="lazy"
            />
            <div v-else class="no-img-placeholder">
              <span class="material-symbols-outlined fallback-icon">sports_tennis</span>
            </div>
            <span v-if="prod.is_rental" class="badge-rental">{{ t('admin.pos.badgeRental') }}</span>
            <span v-else-if="prod.is_service" class="badge-service">{{ t('admin.pos.badgeService') }}</span>
            <span
              v-else-if="prod.stock_quantity <= 0"
              class="badge-out-of-stock"
            >
              {{ t('admin.pos.badgeOutOfStock') }}
            </span>
          </div>

          <div class="prod-details">
            <h4 class="prod-name" :title="prod.name">{{ prod.name }}</h4>
            <div class="prod-meta">
              <strong class="prod-price">{{ formatMoney(prod.price) }}</strong>
              <span v-if="!prod.is_service" class="prod-stock">
                SL: {{ prod.stock_quantity }} {{ prod.unit || 'món' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pos-catalog-area {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.pos-catalog-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid var(--outline);
}

.search-box-wrap {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  font-size: 18px;
  color: var(--muted);
  pointer-events: none;
}

.catalog-search-input {
  width: 100%;
  height: 44px;
  padding: 0 40px 0 42px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  color: var(--ink);
  font-size: 13px;
  font-family: inherit;
  transition: all 0.2s;
}

.catalog-search-input:focus {
  outline: 0;
  border-color: var(--pink-dark, #db2777);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
}

.custom-item-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 44px;
  padding: 0 16px;
  border-radius: 12px;
  border: 1px dashed var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.08);
  color: var(--pink-dark, #db2777);
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.custom-item-btn:hover {
  background: var(--pink-dark, #db2777);
  color: #fff;
}

.category-tabs-scroll {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  overflow-x: auto;
  border-bottom: 1px solid var(--outline);
  background: var(--surface);
}

.cat-tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}

.cat-tab-btn:hover {
  border-color: var(--pink-dark, #db2777);
  color: var(--pink-dark, #db2777);
}

.cat-tab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.cat-icon {
  font-size: 18px;
}

.products-grid-container {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 14px;
}

.product-card {
  display: flex;
  flex-direction: column;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}

.product-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
  border-color: var(--pink-dark, #db2777);
}

.product-card.out-of-stock {
  opacity: 0.6;
  cursor: not-allowed;
}

.prod-img-wrap {
  position: relative;
  width: 100%;
  height: 120px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  overflow: hidden;
}

.prod-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.no-img-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.fallback-icon {
  font-size: 36px;
  color: var(--muted);
  opacity: 0.4;
}

.badge-rental,
.badge-service,
.badge-out-of-stock {
  position: absolute;
  top: 8px;
  left: 8px;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.badge-rental {
  background: #3b82f6;
  color: #fff;
}

.badge-service {
  background: #8b5cf6;
  color: #fff;
}

.badge-out-of-stock {
  background: #ef4444;
  color: #fff;
}

.prod-details {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.prod-name {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.prod-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  font-size: 12px;
}

.prod-price {
  font-weight: 700;
  color: var(--pink-dark, #db2777);
  font-size: 13px;
}

.prod-stock {
  color: var(--muted);
  font-size: 11px;
}

.catalog-loading-state,
.catalog-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--muted);
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
  color: var(--muted);
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}
</style>
