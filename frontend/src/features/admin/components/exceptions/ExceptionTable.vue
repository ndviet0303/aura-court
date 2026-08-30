<script setup lang="ts">
import type { FinancialException, ExceptionFilterTab } from '../../types/exceptions'

defineProps<{
  items: FinancialException[]
  activeTab: ExceptionFilterTab
  totalCount: number
  criticalCount: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: ExceptionFilterTab): void
  (e: 'override', item: FinancialException): void
}>()
</script>

<template>
  <section class="table-section">
    <!-- Filter Tabs -->
    <div class="tab-filter-bar">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'all' }"
        @click="emit('update:activeTab', 'all')"
      >
        Tất cả ngoại lệ ({{ totalCount }})
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'critical' }"
        @click="emit('update:activeTab', 'critical')"
      >
        🔴 Cảnh báo Đỏ ({{ criticalCount }})
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'refund' }"
        @click="emit('update:activeTab', 'refund')"
      >
        Hoàn tiền trễ hạn
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'deposit' }"
        @click="emit('update:activeTab', 'deposit')"
      >
        Bất thường Nạp/Rút
      </button>
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'deposit_hold' }"
        @click="emit('update:activeTab', 'deposit_hold')"
      >
        Lệch cọc giữ chỗ
      </button>
    </div>

    <!-- Table Container -->
    <div class="exceptions-table-wrapper">
      <table class="aura-data-table">
        <thead>
          <tr>
            <th>Mã TXN</th>
            <th>Thời gian & Ca trực</th>
            <th>Khách hàng / Tài khoản</th>
            <th class="text-right">Số tiền</th>
            <th>Loại ngoại lệ</th>
            <th>Mức rủi ro</th>
            <th class="text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" :class="{ 'row-resolved': item.status === 'resolved' }">
            <td class="code-cell font-mono">{{ item.reference }}</td>
            <td>
              <div class="time-main">{{ item.detected_at }}</div>
              <div class="time-sub">{{ (item.metadata?.shift || item.type) }}</div>
            </td>
            <td>
              <div class="user-cell">
                <div class="user-avatar-initial">{{ (item.resolver?.name || 'Hệ thống').charAt(0) }}</div>
                <div>
                  <div class="user-name">{{ (item.resolver?.name || 'Hệ thống') }}</div>
                  <div class="user-sub">{{ (item.resolver?.id || '') }} · {{ (item.metadata?.tier || '') }}</div>
                </div>
              </div>
            </td>
            <td class="text-right font-mono font-bold amount-cell">
              {{ item.amount.toLocaleString('vi-VN') }} ₫
            </td>
            <td>
              <span class="reason-pill">{{ item.description }}</span>
            </td>
            <td>
              <span
                class="risk-badge"
                :class="{
                  'risk-critical': item.severity === 'critical',
                  'risk-medium': item.severity === 'medium',
                  'risk-low': item.severity === 'low',
                }"
              >
                {{ item.severity === 'critical' ? 'Cao' : item.severity === 'medium' ? 'Trung bình' : 'Thấp' }}
              </span>
            </td>
            <td class="text-right">
              <div class="action-buttons">
                <span v-if="item.status === 'resolved'" class="status-done">
                  <span class="material-symbols-outlined">done_all</span> Đã xử lý
                </span>
                <button
                  v-else
                  class="btn-override"
                  @click="emit('override', item)"
                >
                  Can thiệp
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="7" class="empty-cell">Không có giao dịch ngoại lệ nào khớp bộ lọc.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.table-section {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  overflow: hidden;
}

.tab-filter-bar {
  display: flex;
  gap: 8px;
  padding: 16px;
  border-bottom: 1px solid #e1e3e4;
  overflow-x: auto;
}

.tab-btn {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #5d5f5f;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.tab-btn.active {
  background: #ffd1dc;
  color: #78555e;
  border-color: #e7bbc6;
}

.exceptions-table-wrapper {
  overflow-x: auto;
}

.aura-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.aura-data-table th {
  padding: 14px 16px;
  font-weight: 600;
  color: #5d5f5f;
  border-bottom: 1px solid #e1e3e4;
  background: #f8f9fa;
  text-align: left;
}

.aura-data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f3f4f5;
  vertical-align: middle;
}

.row-resolved {
  opacity: 0.6;
  background: #fafafa;
}

.code-cell {
  font-weight: 600;
  color: #191c1d;
}

.time-main {
  font-weight: 500;
  color: #191c1d;
}
.time-sub {
  font-size: 11px;
  color: #817476;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-avatar-initial {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #dfe0e0;
  color: #191c1d;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.user-name {
  font-weight: 600;
  color: #191c1d;
}
.user-sub {
  font-size: 11px;
  color: #817476;
}

.amount-cell {
  color: #191c1d;
}

.reason-pill {
  display: inline-block;
  background: #f3f4f5;
  border: 1px solid #e1e3e4;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  color: #4f4446;
}

.risk-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.risk-critical {
  background: #ffdad6;
  color: #ba1a1a;
}
.risk-medium {
  background: #fef3c7;
  color: #b45309;
}
.risk-low {
  background: #e0e7ff;
  color: #3730a3;
}

.btn-override {
  background: #ffd1dc;
  color: #78555e;
  border: none;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
}

.btn-override:hover {
  opacity: 0.85;
}

.status-done {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #10b981;
  font-weight: 600;
}

.empty-cell {
  text-align: center;
  padding: 32px;
  color: #817476;
}
</style>
