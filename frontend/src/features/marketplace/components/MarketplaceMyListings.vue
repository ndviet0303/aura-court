<script setup lang="ts">
import type { MyResaleListing } from '../types/marketplace'
import { AuraButton } from '@/shared/components'

defineProps<{
  listings: MyResaleListing[]
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'withdraw', listingId: number): void
  (e: 'create-new'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="my-listings-container">
    <div class="section-header">
      <div class="header-titles">
        <h2 class="section-title">Danh Sách Tin Đã Đăng Nhượng Của Tôi</h2>
        <p class="section-sub">Theo dõi trạng thái các slot sân bạn đang niêm yết chuyển nhượng trên chợ Aura.</p>
      </div>

      <AuraButton variant="primary" size="sm" icon="add" @click="emit('create-new')">
        Pass Thêm Slot Mới
      </AuraButton>
    </div>

    <!-- Empty state -->
    <div v-if="listings.length === 0" class="empty-state-card">
      <span class="material-symbols-outlined empty-icon">receipt_long</span>
      <h3>Bạn chưa có tin đăng pass sân nào</h3>
      <p>Khi bạn bận và pass lại slot đã đặt, các tin niêm yết sẽ hiển thị tại đây để bạn quản lý.</p>
      <AuraButton variant="primary" size="md" icon="sell" @click="emit('create-new')">
        Xem Các Lịch Có Thể Pass
      </AuraButton>
    </div>

    <!-- Listings Table / Grid -->
    <div v-else class="my-listings-list">
      <div v-for="item in listings" :key="item.id" class="my-listing-row">
        <div class="slot-info">
          <div class="court-line">
            <strong class="court-name">{{ item.courtName }}</strong>
            <span class="status-pill" :class="`status-${item.status}`">
              <span class="material-symbols-outlined status-icon">
                {{ item.status === 'listed' ? 'schedule' : item.status === 'sold' ? 'check_circle' : 'remove_circle_outline' }}
              </span>
              <span>{{ item.status === 'listed' ? 'Đang rao bán' : item.status === 'sold' ? 'Đã bán' : 'Đã rút tin' }}</span>
            </span>
          </div>

          <div class="meta-line">
            <span class="meta-item">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ item.venueName }}</span>
            </span>
            <span class="meta-item">
              <span class="material-symbols-outlined icon-14">calendar_today</span>
              <span>{{ item.dateStr }} ({{ item.timeSlot }})</span>
            </span>
          </div>
        </div>

        <div class="pricing-info">
          <span class="price-label">Giá pass lại:</span>
          <strong class="price-val font-mono">{{ formatMoney(item.askingPrice) }}</strong>
          <span class="price-orig">(Gốc: {{ formatMoney(item.originalPrice) }})</span>
        </div>

        <div class="actions-cell">
          <AuraButton
            v-if="item.status === 'listed'"
            variant="danger"
            size="sm"
            icon="cancel"
            :loading="processing"
            @click="emit('withdraw', item.id)"
          >
            Rút tin
          </AuraButton>
          <span v-else class="text-muted-status">Không còn khả dụng</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.my-listings-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 14px;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.section-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 56px 24px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  gap: 12px;
}

.empty-icon {
  font-size: 48px;
  color: var(--muted);
}

.empty-state-card h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
}

.empty-state-card p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  max-width: 440px;
}

.my-listings-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.my-listing-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 16px 20px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: var(--shadow-sm);
  transition: border-color 0.2s ease;
}

.my-listing-row:hover {
  border-color: var(--accent, #C9A15A);
}

.slot-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  min-width: 240px;
}

.court-line {
  display: flex;
  align-items: center;
  gap: 10px;
}

.court-name {
  font-size: 15px;
  color: var(--ink);
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

.status-icon {
  font-size: 13px;
}

.status-listed {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-sold {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.status-withdrawn {
  background: rgba(107, 114, 128, 0.1);
  color: #6b7280;
}

.meta-line {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--muted);
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.icon-14 {
  font-size: 14px;
  color: var(--accent, #C9A15A);
}

.pricing-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: right;
  min-width: 140px;
}

.price-label {
  font-size: 11px;
  color: var(--muted);
}

.price-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
}

html.dark .price-val {
  color: var(--accent, #C9A15A);
}

.price-orig {
  font-size: 11px;
  color: var(--muted);
}

.actions-cell {
  display: flex;
  align-items: center;
}

.btn-withdraw {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: transparent;
  color: #ef4444;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-withdraw:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.08);
  border-color: #ef4444;
}

.btn-withdraw .material-symbols-outlined {
  font-size: 16px;
}

.text-muted-status {
  font-size: 12px;
  color: var(--muted);
  font-style: italic;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--primary, #6B2D3F);
  color: #ffffff;
  border: 0;
}

.btn-primary:hover {
  background: var(--primary-hover, #562332);
}

.btn-outline {
  background: transparent;
  border: 1px solid var(--outline);
  color: var(--ink);
}

.btn-outline:hover {
  border-color: var(--primary, #6B2D3F);
  color: var(--primary, #6B2D3F);
}
</style>
