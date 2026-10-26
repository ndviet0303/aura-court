<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatTime } from '@/i18n'
import type { MyResaleListing } from '../types/marketplace'
import { AuraButton } from '@/shared/components'
import { usePolling } from '../composables/usePolling'

const props = defineProps<{
  listings: MyResaleListing[]
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'withdraw', listingId: number): void
  (e: 'create-new'): void
  (e: 'refresh'): void
}>()

const { t } = useI18n()
// A held hour turns sold once the owner's bank receipt arrives; refresh to show the credit.
usePolling(
  () => props.listings.some((l) => l.status === 'checkout'),
  10000,
  () => emit('refresh'),
)

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="my-listings-container">
    <div class="section-header">
      <div class="header-titles">
        <h2 class="section-title">Danh Sách Tin Đã Đăng Nhượng Của Tôi</h2>
        <p class="section-sub">
          Theo dõi trạng thái các giờ sân bạn đang niêm yết chuyển nhượng trên chợ Aura.
        </p>
      </div>

      <AuraButton variant="primary" size="sm" icon="add" @click="emit('create-new')">
        Pass Thêm Giờ Chơi
      </AuraButton>
    </div>

    <!-- Empty state -->
    <div v-if="listings.length === 0" class="empty-state-card">
      <span class="material-symbols-outlined empty-icon">receipt_long</span>
      <h3>Bạn chưa có tin đăng pass sân nào</h3>
      <p>
        Khi bạn bận và pass lại giờ đã đặt, các tin niêm yết sẽ hiển thị tại đây để bạn quản lý.
      </p>
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
                {{
                  item.status === 'listed'
                    ? 'schedule'
                    : item.status === 'checkout'
                      ? 'lock_clock'
                      : item.status === 'sold'
                        ? 'check_circle'
                        : 'remove_circle_outline'
                }}
              </span>
              <span>{{
                item.status === 'listed'
                  ? 'Đang rao bán'
                  : item.status === 'checkout'
                    ? t('resale.p2p.statusCheckout')
                    : item.status === 'sold'
                      ? item.askingPrice > 0
                        ? t('resale.p2p.soldWithCredit', { amount: formatMoney(item.askingPrice) })
                        : 'Đã bán'
                      : 'Đã rút tin'
              }}</span>
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

          <div v-if="item.status === 'checkout'" class="lock-box" data-testid="resale-seller-lock">
            <span v-if="item.lockedUntil">
              {{ t('resale.p2p.sellerHolding', { time: formatTime(item.lockedUntil) }) }}
            </span>
            <span>{{ t('resale.p2p.sellerAwaitReceipt') }}</span>
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
          <span v-else-if="item.status !== 'checkout'" class="text-muted-status">
            Không còn khả dụng
          </span>
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
  border-color: var(--accent, #c9a15a);
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

.status-checkout {
  background: rgba(239, 108, 0, 0.12);
  color: #9a5a00;
}

.lock-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  border-radius: 10px;
  background: rgba(239, 108, 0, 0.08);
  font-size: 12px;
  color: var(--ink);
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
  color: var(--accent, #c9a15a);
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
  color: var(--primary, #6b2d3f);
}

html.dark .price-val {
  color: var(--accent, #c9a15a);
}

.price-orig {
  font-size: 11px;
  color: var(--muted);
}

.actions-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.text-muted-status {
  font-size: 12px;
  color: var(--muted);
  font-style: italic;
}
</style>
