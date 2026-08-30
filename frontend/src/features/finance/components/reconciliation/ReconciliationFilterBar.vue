<script setup lang="ts">
defineProps<{
  activeTab: string
  selectedGateway: string
  selectedStatus: string
  selectedRisk: string
  searchQuery: string
  syncing: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', val: any): void
  (e: 'update:selectedGateway', val: string): void
  (e: 'update:selectedStatus', val: string): void
  (e: 'update:selectedRisk', val: string): void
  (e: 'update:searchQuery', val: string): void
  (e: 'sync'): void
  (e: 'export', format: 'pdf' | 'excel' | 'csv'): void
}>()
</script>

<template>
  <div class="filter-wrapper">
    <!-- Top Row: Cycle Tabs & Action Buttons -->
    <div class="cycle-tabs-row">
      <div class="tabs-group">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'today' }"
          @click="emit('update:activeTab', 'today')"
        >
          <span class="pulse-indicator"></span>
          Hôm nay (T+0 Realtime)
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'yesterday' }"
          @click="emit('update:activeTab', 'yesterday')"
        >
          Hôm qua (T+1)
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === '7days' }"
          @click="emit('update:activeTab', '7days')"
        >
          7 Ngày qua
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'month' }"
          @click="emit('update:activeTab', 'month')"
        >
          Tháng 08/2026
        </button>
      </div>

      <div class="quick-actions-group">
        <button
          class="btn-sync"
          type="button"
          :disabled="syncing"
          @click="emit('sync')"
        >
          <span class="material-symbols-outlined" :class="{ 'spin-anim': syncing }">sync</span>
          {{ syncing ? 'Đang đối soát...' : 'Đối Soát Tức Thì' }}
        </button>
        <div class="export-dropdown">
          <button class="btn-export" type="button" @click="emit('export', 'pdf')">
            <span class="material-symbols-outlined">download</span>
            Xuất Báo Cáo
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom Row: Filter Controls -->
    <div class="filters-row">
      <!-- Search Input -->
      <div class="search-field">
        <span class="material-symbols-outlined search-icon">search</span>
        <input
          :value="searchQuery"
          type="text"
          placeholder="Tìm theo Mã REC, TXN, Cổng thanh toán, Khách hàng..."
          class="search-input"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
      </div>

      <!-- Gateway Filter -->
      <div class="filter-select-wrap">
        <label class="filter-label">Cổng TT:</label>
        <select
          :value="selectedGateway"
          class="filter-select"
          @change="emit('update:selectedGateway', ($event.target as HTMLSelectElement).value)"
        >
          <option value="all">Tất cả cổng</option>
          <option value="VietQR">VietQR (MBBank)</option>
          <option value="MoMo">MoMo Business</option>
          <option value="VNPay">VNPay QR</option>
          <option value="ZaloPay">ZaloPay</option>
          <option value="Stripe">Stripe Quốc tế</option>
        </select>
      </div>

      <!-- Status Filter -->
      <div class="filter-select-wrap">
        <label class="filter-label">Trạng thái:</label>
        <select
          :value="selectedStatus"
          class="filter-select"
          @change="emit('update:selectedStatus', ($event.target as HTMLSelectElement).value)"
        >
          <option value="all">Tất cả trạng thái</option>
          <option value="matched">Đã khớp 100%</option>
          <option value="mismatch">Phát hiện sai lệch</option>
          <option value="running">Đang xử lý</option>
        </select>
      </div>

      <!-- Risk Level Filter -->
      <div class="filter-select-wrap">
        <label class="filter-label">Rủi ro:</label>
        <select
          :value="selectedRisk"
          class="filter-select"
          @change="emit('update:selectedRisk', ($event.target as HTMLSelectElement).value)"
        >
          <option value="all">Tất cả rủi ro</option>
          <option value="high">Rủi ro cao (Đỏ)</option>
          <option value="medium">Cần chú ý (Vàng)</option>
          <option value="low">An toàn (Xanh)</option>
        </select>
      </div>
    </div>
  </div>
</template>

<style scoped>
.filter-wrapper {
  background: #ffffff;
  border-radius: 16px;
  padding: 16px 20px;
  border: 1px solid #e1e3e4;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.cycle-tabs-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.tabs-group {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f8f9fa;
  padding: 4px;
  border-radius: 999px;
  border: 1px solid #e1e3e4;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
  color: #4f4446;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #191c1d;
}

.tab-btn.active {
  background: #ffd1dc;
  color: #7a5761;
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(120, 85, 94, 0.1);
}

.pulse-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #059669;
}

.quick-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-sync {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffd1dc;
  color: #7a5761;
  border: none;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sync:hover:not(:disabled) {
  background: #f8bbc9;
}

.btn-sync:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: #191c1d;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-export:hover {
  background: #edeeef;
}

.filters-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding-top: 10px;
  border-top: 1px solid #f3f4f5;
}

.search-field {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 999px;
  padding: 6px 14px;
  flex: 1;
  min-width: 280px;
}

.search-icon {
  font-size: 18px;
  color: #817476;
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  width: 100%;
  color: #191c1d;
}

.filter-select-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 999px;
  padding: 4px 12px;
}

.filter-label {
  font-size: 12px;
  font-weight: 600;
  color: #5d5f5f;
}

.filter-select {
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  color: #191c1d;
  font-weight: 500;
  cursor: pointer;
}
</style>
