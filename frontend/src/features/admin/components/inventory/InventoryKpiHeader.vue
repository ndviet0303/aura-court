<script setup lang="ts">
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

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="inventory-kpi-header-card">
    <div class="header-top">
      <div>
        <h1 class="page-title">📦 Quản Lý Kho Hàng & Tồn Kho (Inventory)</h1>
        <p class="page-sub">Kiểm soát danh mục sản phẩm đồ uống, phụ kiện, lịch sử nhập xuất và kiểm kê định kỳ.</p>
      </div>

      <div class="header-actions">
        <button class="btn-action-primary" @click="emit('addProduct')">
          ➕ Thêm Hàng Hóa
        </button>
        <button class="btn-action-secondary" @click="emit('stockIn')">
          📥 Nhập Kho
        </button>
        <button class="btn-action-secondary" @click="emit('audit')">
          📋 Kiểm Kê Kho
        </button>
      </div>
    </div>

    <!-- KPI Metrics Grid -->
    <div class="kpis-grid">
      <div class="kpi-card">
        <span class="lbl">TỔNG MẶT HÀNG</span>
        <strong class="val">{{ kpis.totalItems }} đơn vị</strong>
      </div>
      <div class="kpi-card">
        <span class="lbl">GIÁ TRỊ TỒN KHO</span>
        <strong class="val text-pink">{{ formatMoney(kpis.totalValue) }}</strong>
      </div>
      <div class="kpi-card">
        <span class="lbl">CẢNH BÁO SẮP HẾT HÀNG</span>
        <strong class="val text-yellow">{{ kpis.lowStockCount }} mặt hàng</strong>
      </div>
      <div class="kpi-card">
        <span class="lbl">ĐÃ HẾT HÀNG TRONG KHO</span>
        <strong class="val text-red">{{ kpis.outOfStockCount }} mặt hàng</strong>
      </div>
    </div>

    <!-- Tabs switcher -->
    <div class="tabs-row">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'catalog' }"
        @click="emit('update:activeTab', 'catalog')"
      >
        📦 Danh Mục Hàng Hóa & Tồn Kho
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'movements' }"
        @click="emit('update:activeTab', 'movements')"
      >
        📜 Lịch Sử Nhập / Xuất Kho
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

.page-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.header-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-action-primary {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-action-secondary {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.kpis-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
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

@media (max-width: 900px) {
  .kpis-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
