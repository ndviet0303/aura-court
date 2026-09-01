<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { SplitItemDetail, SplitBillBookingInfo } from '../../types/split'

defineProps<{
  booking: SplitBillBookingInfo
  items: SplitItemDetail[]
  totalBill: number
  totalPaid: number
  remainingAmount: number
  progressPercent: number
  isFullyPaid: boolean
}>()

const emit = defineEmits<{
  (e: 'addService'): void
  (e: 'removeItem', itemId: string): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function getCategoryBadge(cat: string) {
  switch (cat) {
    case 'court':
      return { label: 'Sân đấu', class: 'cat-court', icon: 'sports_tennis' }
    case 'drink':
      return { label: 'Nước uống', class: 'cat-drink', icon: 'local_drink' }
    case 'gear':
      return { label: 'Dụng cụ', class: 'cat-gear', icon: 'sports' }
    default:
      return { label: 'Dịch vụ', class: 'cat-service', icon: 'room_service' }
  }
}
</script>

<template>
  <div class="split-summary-card">
    <div class="booking-top-info">
      <div class="booking-details">
        <div class="code-and-status">
          <span class="code-pill font-mono">MÃ ĐƠN: {{ booking.bookingCode }}</span>
          <span v-if="isFullyPaid" class="status-pill status-pill--success">
            <span class="material-symbols-outlined status-icon">check_circle</span>
            Đã Quyết Toán Xong (100%)
          </span>
          <span v-else class="status-pill status-pill--pending">
            <span class="material-symbols-outlined status-icon">hourglass_top</span>
            Đang Thu Tiền Nhóm
          </span>
        </div>

        <h2 class="court-title">{{ booking.courtName }}</h2>
        <p class="venue-sub">
          <span class="material-symbols-outlined sub-icon">location_on</span>
          <span>{{ booking.venueName }}</span>
          <span class="separator">·</span>
          <span class="material-symbols-outlined sub-icon">calendar_today</span>
          <span>{{ booking.bookingDate }} ({{ booking.bookingTime }})</span>
        </p>
      </div>

      <div class="total-badge-box">
        <span class="lbl">Tổng Hóa Đơn Chung</span>
        <strong class="total-num">{{ formatMoney(totalBill) }}</strong>
        <span class="host-info">Chủ bill: {{ booking.hostName }}</span>
      </div>
    </div>

    <!-- Progress Bar Section -->
    <div class="progress-section">
      <div class="progress-labels">
        <div class="p-left">
          <span class="progress-title">Tiến độ thanh toán:</span>
          <span class="progress-val">{{ progressPercent }}%</span>
        </div>
        <div class="p-right">
          <span>Đã thu: <strong class="text-success">{{ formatMoney(totalPaid) }}</strong></span>
          <span class="sep">/</span>
          <span>Còn thiếu: <strong class="text-danger">{{ formatMoney(remainingAmount) }}</strong></span>
        </div>
      </div>

      <div class="progress-track">
        <div
          class="progress-fill"
          :class="{ 'progress-fill--complete': isFullyPaid }"
          :style="{ width: `${progressPercent}%` }"
        ></div>
      </div>
    </div>

    <!-- Order Items Breakdown -->
    <div class="items-list-box">
      <div class="items-header">
        <div class="items-title-wrap">
          <span class="material-symbols-outlined items-icon">receipt_long</span>
          <h4 class="items-title">Chi Tiết Hóa Đơn Sân & Dịch Vụ ({{ items.length }} mục)</h4>
        </div>
        <AuraButton
          variant="ghost"
          size="sm"
          icon="add_circle"
          @click="emit('addService')"
        >
          <span>Thêm Nước / Dịch Vụ</span>
        </AuraButton>
      </div>

      <div class="items-rows">
        <div v-for="item in items" :key="item.id" class="item-row">
          <div class="item-info">
            <span class="category-tag" :class="getCategoryBadge(item.category).class">
              <span class="material-symbols-outlined tag-icon">{{ getCategoryBadge(item.category).icon }}</span>
              {{ getCategoryBadge(item.category).label }}
            </span>
            <span class="item-name">{{ item.name }}</span>
            <span class="item-qty">x{{ item.qty }}</span>
          </div>

          <div class="item-pricing">
            <span class="unit-price font-mono">{{ formatMoney(item.unitPrice) }} / cái</span>
            <strong class="item-total font-mono">{{ formatMoney(item.total) }}</strong>
            <button
              v-if="item.category !== 'court'"
              class="btn-del-item"
              title="Xóa mục này"
              @click="emit('removeItem', item.id)"
            >
              <span class="material-symbols-outlined del-icon">close</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.split-summary-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 22px;
  border-radius: 18px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface, #1e191c);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.booking-top-info {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.booking-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.code-and-status {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.code-pill {
  font-size: 11px;
  font-weight: 700;
  color: var(--primary, #E58E9F);
  background: rgba(229, 142, 159, 0.12);
  border: 1px solid rgba(229, 142, 159, 0.25);
  padding: 3px 9px;
  border-radius: 6px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.status-pill--pending {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-pill--success {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-icon {
  font-size: 14px;
}

.court-title {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  color: var(--on-surface, #FAF7F3);
}

.venue-sub {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted, #9e939a);
  flex-wrap: wrap;
}

.sub-icon {
  font-size: 15px;
  color: var(--primary, #E58E9F);
}

.separator {
  color: var(--outline, #554850);
}

.total-badge-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  background: var(--surface-container, rgba(255, 255, 255, 0.03));
  padding: 12px 18px;
  border-radius: 14px;
  border: 1px solid var(--border, var(--outline, #382f35));
}

.lbl {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #9e939a);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.total-num {
  font-size: 24px;
  font-weight: 850;
  color: var(--primary, #E58E9F);
  line-height: 1.2;
}

.host-info {
  font-size: 11px;
  color: var(--muted, #9e939a);
  margin-top: 2px;
}

.progress-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}

.p-left {
  display: flex;
  align-items: center;
  gap: 6px;
}

.progress-title {
  color: var(--muted, #9e939a);
}

.progress-val {
  font-weight: 800;
  color: var(--on-surface, #FAF7F3);
}

.p-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sep {
  color: var(--outline, #554850);
}

.text-success {
  color: #10b981;
}

.text-danger {
  color: #ef4444;
}

.progress-track {
  width: 100%;
  height: 10px;
  border-radius: 6px;
  background: var(--surface-container, rgba(255, 255, 255, 0.06));
  overflow: hidden;
  border: 1px solid var(--border, transparent);
}

.progress-fill {
  height: 100%;
  border-radius: 6px;
  background: linear-gradient(90deg, #f59e0b 0%, #10b981 100%);
  transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.progress-fill--complete {
  background: #10b981;
}

.items-list-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--surface-container, rgba(0, 0, 0, 0.15));
  border: 1px solid var(--border, var(--outline, #382f35));
}

.items-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.05));
}

.items-title-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.items-icon {
  font-size: 18px;
  color: var(--primary, #E58E9F);
}

.items-title {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--on-surface, #FAF7F3);
}

.items-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.02);
  transition: background 0.15s;
}

.item-row:hover {
  background: rgba(255, 255, 255, 0.05);
}

.item-info {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.category-tag {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 6px;
}

.tag-icon {
  font-size: 13px;
}

.cat-court {
  background: rgba(229, 142, 159, 0.15);
  color: var(--primary, #E58E9F);
}

.cat-drink {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
}

.cat-gear {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
}

.cat-service {
  background: rgba(168, 85, 247, 0.15);
  color: #c084fc;
}

.item-name {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--on-surface, #FAF7F3);
}

.item-qty {
  font-size: 12px;
  color: var(--muted, #9e939a);
  font-weight: 700;
}

.item-pricing {
  display: flex;
  align-items: center;
  gap: 12px;
}

.unit-price {
  font-size: 11px;
  color: var(--muted, #9e939a);
}

.item-total {
  font-size: 14px;
  font-weight: 750;
  color: var(--on-surface, #FAF7F3);
}

.btn-del-item {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-del-item:hover {
  background: #ef4444;
  color: #fff;
}

.del-icon {
  font-size: 14px;
}
</style>
