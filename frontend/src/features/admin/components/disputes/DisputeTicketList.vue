<script setup lang="ts">
import type { DisputeCase, DisputeStatus } from '../../types/dispute'

defineProps<{
  items: DisputeCase[]
  activeTab: DisputeStatus | 'all'
  selectedId?: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: DisputeStatus | 'all'): void
  (e: 'select', item: DisputeCase): void
}>()

function getStatusBadge(item: DisputeCase) {
  if (item.severity === 'critical') {
    return { label: 'KHẨN CẤP - SLA 15P', class: 'badge-critical', icon: 'warning' }
  }
  if (item.disputeType === 'resale_fraud') {
    return { label: 'NGHI VẤN GIAN LẬN', class: 'badge-fraud', icon: 'gavel' }
  }
  if (item.severity === 'high') {
    return { label: 'ƯU TIÊN CAO', class: 'badge-high', icon: 'error' }
  }
  if (item.status === 'resolved') {
    return { label: 'ĐÃ PHÁN QUYẾT', class: 'badge-resolved', icon: 'check_circle' }
  }
  return { label: 'TRUNG BÌNH', class: 'badge-normal', icon: 'info' }
}
</script>

<template>
  <div class="dispute-ticket-list">
    <!-- Header & Tabs -->
    <div class="list-header">
      <div class="list-title-row">
        <h2 class="list-title">Vụ Việc Tranh Chấp</h2>
        <span class="total-badge">{{ items.length }} vụ</span>
      </div>

      <!-- Filter Tabs -->
      <div class="filter-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'all' }"
          @click="emit('update:activeTab', 'all')"
        >
          Tất cả
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'pending' }"
          @click="emit('update:activeTab', 'pending')"
        >
          Chờ xử lý
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'investigating' }"
          @click="emit('update:activeTab', 'investigating')"
        >
          Điều tra
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === 'resolved' }"
          @click="emit('update:activeTab', 'resolved')"
        >
          Đã xong
        </button>
      </div>
    </div>

    <!-- Items Scroll list -->
    <div class="items-scroll" style="scrollbar-width: none;">
      <div v-if="loading" class="loading-state">
        <span class="material-symbols-outlined spin">refresh</span>
        <span>Đang tải danh sách vụ việc...</span>
      </div>

      <div v-else-if="items.length === 0" class="empty-state">
        <span class="material-symbols-outlined icon-empty">inbox</span>
        <p>Không tìm thấy vụ việc tranh chấp nào.</p>
      </div>

      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="ticket-card"
        :class="{ active: item.id === selectedId }"
        @click="emit('select', item)"
      >
        <div v-if="item.id === selectedId" class="active-indicator"></div>

        <div class="card-top">
          <span class="ticket-code">#{{ item.disputeCode }}</span>
          <span class="ticket-time">10 phút trước</span>
        </div>

        <h3 class="ticket-title">{{ item.title }}</h3>
        <p class="ticket-parties">
          {{ item.complainant.name }} vs {{ item.respondent.name }}
        </p>

        <div class="card-footer">
          <span class="status-pill" :class="getStatusBadge(item).class">
            <span class="material-symbols-outlined status-icon">{{ getStatusBadge(item).icon }}</span>
            {{ getStatusBadge(item).label }}
          </span>

          <span class="amount-tag" v-if="item.amountDisputed > 0">
            {{ (item.amountDisputed).toLocaleString('vi-VN') }} ₫
          </span>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.dispute-ticket-list {
  background: #ffffff;
  border: 1px solid #e1e3e4;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  height: 820px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.list-header {
  padding: 16px 20px 12px;
  border-bottom: 1px solid #edeeef;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.list-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.list-title {
  font-size: 16px;
  font-weight: 700;
  margin: 0;
  color: #191c1d;
}

.total-badge {
  font-size: 11px;
  font-weight: 700;
  background: #edeeef;
  color: #5d5f5f;
  padding: 2px 8px;
  border-radius: 999px;
}

.filter-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
}

.tab-btn {
  background: #f8f9fa;
  border: 1px solid #e1e3e4;
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #5d5f5f;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: #edeeef;
  color: #191c1d;
}

.tab-btn.active {
  background: #ffd1dc;
  color: #78555e;
  border-color: #ffd1dc;
}

.items-scroll {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.ticket-card {
  width: 100%;
  text-align: left;
  padding: 16px 20px;
  border: none;
  border-bottom: 1px solid #edeeef;
  background: transparent;
  cursor: pointer;
  position: relative;
  transition: background 0.15s;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ticket-card:hover {
  background: #f8f9fa;
}

.ticket-card.active {
  background: rgba(255, 209, 220, 0.25);
}

.active-indicator {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: #78555e;
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ticket-code {
  font-size: 12px;
  font-weight: 700;
  color: #78555e;
  letter-spacing: 0.03em;
}

.ticket-time {
  font-size: 11px;
  color: #817476;
}

.ticket-title {
  font-size: 14px;
  font-weight: 700;
  color: #191c1d;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ticket-parties {
  font-size: 12px;
  color: #5d5f5f;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status-icon {
  font-size: 12px;
}

.badge-critical {
  background: #ffdad6;
  color: #ba1a1a;
}

.badge-fraud {
  background: #fee2e2;
  color: #991b1b;
}

.badge-high {
  background: #fff8e1;
  color: #b45309;
}

.badge-resolved {
  background: #dcfce7;
  color: #15803d;
}

.badge-normal {
  background: #edeeef;
  color: #5d5f5f;
}

.amount-tag {
  font-size: 12px;
  font-weight: 700;
  color: #191c1d;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #817476;
  gap: 10px;
  font-size: 13px;
}

.icon-empty {
  font-size: 40px;
  color: #d3c3c5;
}

.spin {
  animation: spin 1s infinite linear;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
