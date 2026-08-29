<script setup lang="ts">
import type { InventoryProduct } from '../../types/inventory'

defineProps<{
  products: InventoryProduct[]
  categories: string[]
  search: string
  categoryFilter: string
  stockFilter: 'all' | 'low' | 'out'
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:categoryFilter', val: string): void
  (e: 'update:stockFilter', val: 'all' | 'low' | 'out'): void
  (e: 'edit', product: InventoryProduct): void
  (e: 'stockIn', product: InventoryProduct): void
  (e: 'stockOut', product: InventoryProduct): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="catalog-table-card">
    <div class="table-toolbar">
      <input
        :value="search"
        type="text"
        class="search-input"
        placeholder="Tìm theo tên sản phẩm, mã SKU..."
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      />

      <div class="filters-group">
        <select
          :value="categoryFilter"
          class="filter-select"
          @change="emit('update:categoryFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="all">Tất cả danh mục</option>
          <option v-for="c in categories.filter(x => x !== 'all')" :key="c" :value="c">
            {{ c }}
          </option>
        </select>

        <select
          :value="stockFilter"
          class="filter-select"
          @change="emit('update:stockFilter', ($event.target as HTMLSelectElement).value as any)"
        >
          <option value="all">Tất cả trạng thái kho</option>
          <option value="low">Cảnh báo sắp hết</option>
          <option value="out">Đã hết hàng</option>
        </select>
      </div>
    </div>

    <div class="inventory-table">
      <div class="table-hdr">
        <span>Sản Phẩm & SKU</span>
        <span>Danh Mục</span>
        <span>Giá Vốn / Bán</span>
        <span>Tồn Kho Hiện Tại</span>
        <span>Hành Động</span>
      </div>

      <div class="table-body">
        <div v-for="p in products" :key="p.id" class="product-row">
          <div class="p-name-sku">
            <strong class="p-name">{{ p.name }}</strong>
            <span class="p-sku font-mono">SKU: {{ p.sku }}</span>
          </div>

          <span class="p-cat-tag">{{ p.category }}</span>

          <div class="p-prices">
            <span class="cost-sub">Vốn: {{ formatMoney(p.cost) }}</span>
            <strong class="price-val text-pink">{{ formatMoney(p.price) }}</strong>
          </div>

          <div class="p-stock-info">
            <strong class="stock-qty font-mono" :class="p.stock_quantity <= p.min_stock ? 'text-red' : 'text-green'">
              {{ p.stock_quantity }} {{ p.unit }}
            </strong>
            <span v-if="p.stock_quantity <= p.min_stock" class="min-alert">
              ⚠️ Dưới mức tối thiểu ({{ p.min_stock }})
            </span>
          </div>

          <div class="row-actions">
            <button class="btn-sm" title="Nhập kho" @click="emit('stockIn', p)">+ Nhập</button>
            <button class="btn-sm" title="Xuất kho" @click="emit('stockOut', p)">- Xuất</button>
            <button class="btn-sm" title="Sửa" @click="emit('edit', p)">✏️</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.catalog-table-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 240px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 13px;
}

.filters-group {
  display: flex;
  gap: 10px;
}

.filter-select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 13px;
}

.inventory-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.5fr 1.5fr 1.5fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.product-row {
  display: grid;
  grid-template-columns: 2fr 1.2fr 1.5fr 1.5fr 1.5fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.product-row:last-child {
  border-bottom: 0;
}

.p-name-sku {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.p-name { font-size: 13px; }
.p-sku { font-size: 11px; color: var(--muted); }

.p-cat-tag {
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  width: fit-content;
}

.p-prices {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cost-sub { font-size: 11px; color: var(--muted); }
.price-val { font-size: 13px; }
.text-pink { color: var(--pink-dark, #db2777); }

.p-stock-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stock-qty { font-size: 14px; }
.text-green { color: #10b981; }
.text-red { color: #ef4444; }

.min-alert {
  font-size: 10px;
  color: #ef4444;
}

.row-actions {
  display: flex;
  gap: 6px;
}

.btn-sm {
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
  cursor: pointer;
}
</style>
