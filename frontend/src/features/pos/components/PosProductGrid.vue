<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Product, ProductCategory } from '../types/pos'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'

defineProps<{
  products: Product[]
  categories: Array<{ key: string; label: string; icon: string; labelKey?: string }>
  selectedCategory: ProductCategory
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
const categoryScrollRef = ref<HTMLElement | null>(null)

function onCategoryWheel(e: WheelEvent) {
  if (!categoryScrollRef.value) return
  if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
    categoryScrollRef.value.scrollLeft += e.deltaY
  }
}

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
        <span class="material-symbols-outlined icon-18">add_circle</span>
        <span>{{ t('admin.pos.customItemBtn') }}</span>
      </button>
    </div>

    <!-- Category Tabs -->
    <div
      ref="categoryScrollRef"
      class="category-tabs-scroll"
      @wheel.passive="onCategoryWheel"
    >
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
      <!-- Skeleton Loading State -->
      <div v-if="loading" class="catalog-skeleton-grid">
        <div v-for="i in 8" :key="i" class="product-skeleton-card">
          <SkeletonLoader type="rect" height="110px" rounded="10px" />
          <div class="skeleton-meta">
            <SkeletonLoader type="title" width="85%" />
            <SkeletonLoader type="text" width="55%" />
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="products.length === 0" class="catalog-empty-state">
        <span class="material-symbols-outlined empty-icon">inventory_2</span>
        <h3>{{ t('admin.pos.noProductsFound') }}</h3>
        <p>{{ t('admin.pos.noProductsSub') }}</p>
      </div>

      <!-- Real Products List -->
      <div v-else class="products-grid">
        <div
          v-for="prod in products"
          :key="prod.id"
          class="product-card"
          :class="{ 'out-of-stock': !prod.is_service && prod.stock_quantity <= 0 }"
          role="button"
          tabindex="0"
          @click="emit('selectProduct', prod)"
          @keydown.enter="emit('selectProduct', prod)"
        >
          <div class="prod-img-wrap">
            <img
              v-if="prod.image_url"
              :src="prod.image_url"
              :alt="prod.name"
              loading="lazy"
            />
            <div v-else class="no-img-placeholder">
              <span class="material-symbols-outlined fallback-icon">
                {{ prod.is_rental ? 'schedule' : prod.is_service ? 'build' : prod.category === 'drinks' ? 'local_cafe' : 'sports_tennis' }}
              </span>
            </div>
            <span v-if="prod.is_rental" class="badge-tag badge-rental">{{ t('admin.pos.badgeRental') }}</span>
            <span v-else-if="prod.is_service" class="badge-tag badge-service">{{ t('admin.pos.badgeService') }}</span>
            <span
              v-else-if="prod.stock_quantity <= 0"
              class="badge-tag badge-out-of-stock"
            >
              {{ t('admin.pos.badgeOutOfStock') }}
            </span>
          </div>

          <div class="prod-details">
            <h4 class="prod-name" :title="prod.name">{{ prod.name }}</h4>
            <div class="prod-meta">
              <strong class="prod-price">{{ formatMoney(prod.price) }}</strong>
              <div class="prod-sub-meta">
                <span v-if="!prod.is_service" class="prod-stock">
                  SL: {{ prod.stock_quantity }}
                </span>
                <span v-else class="prod-stock service-tag">
                  {{ prod.unit || 'dịch vụ' }}
                </span>
                <span class="prod-vat-tag" :class="`vat-${Math.round((prod.vat_rate ?? 0.08) * 100)}`">
                  {{ Math.round((prod.vat_rate ?? 0.08) * 100) }}%
                </span>
              </div>
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
  min-height: 0;
  overflow: hidden;
  background: var(--surface, #1e293b);
  border-radius: 16px;
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
}

.pos-catalog-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  flex-shrink: 0;
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  background: var(--surface, #1e293b);
}

.search-box-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  position: relative;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 10px;
  padding: 0 12px;
  transition: border-color 0.2s ease;
}

.search-box-wrap:focus-within {
  border-color: var(--primary, #10b981);
}

.search-icon {
  color: var(--muted, #94a3b8);
  font-size: 20px;
}

.catalog-search-input {
  width: 100%;
  padding: 10px 8px;
  background: transparent;
  border: none;
  color: var(--ink, #ffffff);
  font-size: 14px;
  outline: none;
}

.catalog-search-input::placeholder {
  color: var(--muted, #94a3b8);
}

.clear-search-btn {
  background: transparent;
  border: none;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.clear-search-btn:hover {
  color: var(--ink, #ffffff);
}

.custom-item-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  border-radius: 10px;
  color: var(--primary, #10b981);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.custom-item-btn:hover {
  background: rgba(16, 185, 129, 0.1);
  border-color: var(--primary, #10b981);
}

.category-tabs-scroll {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  flex-shrink: 0;
  overflow-x: auto;
  overflow-y: hidden;
  border-bottom: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE 10+ */
  -webkit-overflow-scrolling: touch;
  scroll-behavior: smooth;
}

.category-tabs-scroll::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Edge */
  width: 0;
  height: 0;
}

.cat-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.05));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.1));
  color: var(--muted, #94a3b8);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.cat-tab-btn:hover {
  color: var(--ink, #ffffff);
  border-color: var(--outline-strong, rgba(255, 255, 255, 0.2));
}

.cat-tab-btn.active {
  background: var(--primary, #10b981);
  border-color: var(--primary, #10b981);
  color: #ffffff;
}

.cat-icon {
  font-size: 18px;
}

.products-grid-container {
  flex: 1;
  padding: 16px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}

.products-grid-container::-webkit-scrollbar {
  width: 6px;
}

.products-grid-container::-webkit-scrollbar-track {
  background: transparent;
}

.products-grid-container::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
}

.products-grid-container::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}

.catalog-skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 14px;
}

.product-skeleton-card {
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-hover, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.05));
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.skeleton-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.catalog-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--muted, #94a3b8);
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
  opacity: 0.6;
}

.catalog-empty-state h3 {
  color: var(--ink, #ffffff);
  margin: 0 0 6px;
  font-size: 16px;
}

.catalog-empty-state p {
  margin: 0;
  font-size: 13px;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 14px;
}

.product-card {
  display: flex;
  flex-direction: column;
  background: var(--surface-hover, rgba(255, 255, 255, 0.03));
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.08));
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}

.product-card:hover {
  transform: translateY(-2px);
  border-color: var(--primary, #10b981);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
  background: var(--surface-active, rgba(255, 255, 255, 0.06));
}

.product-card.out-of-stock {
  opacity: 0.55;
  cursor: not-allowed;
}

.product-card.out-of-stock:hover {
  transform: none;
  border-color: rgba(239, 68, 68, 0.4);
}

.prod-img-wrap {
  height: 115px;
  position: relative;
  background: rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.prod-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.product-card:hover .prod-img-wrap img {
  transform: scale(1.05);
}

.no-img-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--muted, #94a3b8);
}

.fallback-icon {
  font-size: 36px;
  opacity: 0.5;
}

.badge-tag {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  color: #ffffff;
  letter-spacing: 0.3px;
}

.badge-rental {
  background: #f59e0b;
}

.badge-service {
  background: #3b82f6;
}

.badge-out-of-stock {
  background: #ef4444;
}

.prod-details {
  padding: 12px;
  display: flex;
  flex-direction: column;
  flex: 1;
  justify-content: space-between;
  gap: 8px;
}

.prod-name {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink, #ffffff);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.prod-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.prod-price {
  color: var(--primary, #10b981);
  font-size: 14px;
  font-weight: 700;
}

.prod-stock {
  font-size: 11px;
  color: var(--muted, #94a3b8);
}

.prod-sub-meta {
  display: flex;
  align-items: center;
  gap: 5px;
}

.prod-vat-tag {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.05);
  color: var(--muted, #94a3b8);
  border: 1px solid rgba(255, 255, 255, 0.08);
  line-height: 1.2;
}

.prod-vat-tag.vat-10 {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
  border-color: rgba(245, 158, 11, 0.25);
}

.prod-vat-tag.vat-8 {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border-color: rgba(16, 185, 129, 0.25);
}

.service-tag {
  color: #3b82f6;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
}
</style>
