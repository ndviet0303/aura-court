<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Court, AvailabilitySlot } from '../../types/court'

const props = defineProps<{
  court: Court
  date: string
  selectedSlots: AvailabilitySlot[]
  totalPrice: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'checkout'): void
  (e: 'removeSlot', slot: AvailabilitySlot): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

const formattedDate = computed(() => {
  try {
    const d = new Date(props.date)
    return d.toLocaleDateString('vi-VN', {
      weekday: 'long',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return props.date
  }
})

const durationHours = computed(() => {
  return props.selectedSlots.length
})
</script>

<template>
  <div class="schedule-summary-card">
    <!-- Header -->
    <div class="summary-hdr">
      <div class="title-row">
        <h3 class="title">
          <span class="material-symbols-outlined title-icon">receipt_long</span>
          <span>Tóm Tắt Đặt Sân</span>
        </h3>
        <span v-if="selectedSlots.length > 0" class="badge-count">
          {{ selectedSlots.length }} khung giờ
        </span>
      </div>

      <div class="court-meta">
        <strong class="court-name">{{ court.name }}</strong>
        <RouterLink
          :to="`/courts/${court.slug || court.id}/directions`"
          class="venue-info-link"
          title="Xem chỉ đường đến cơ sở"
        >
          <span class="material-symbols-outlined icon-14">location_on</span>
          <span>{{ court.venue?.name || 'Aura Court' }}</span>
          <span class="material-symbols-outlined icon-10">open_in_new</span>
        </RouterLink>
        <span class="date-info">
          <span class="material-symbols-outlined icon-14">event</span>
          <span>{{ formattedDate }}</span>
        </span>
      </div>
    </div>

    <!-- Selected Slots Section -->
    <div class="selected-slots-section">
      <div class="section-label-row">
        <span class="section-sub-label">Khung giờ đã chọn:</span>
        <span v-if="durationHours > 0" class="duration-badge">
          Tổng: {{ durationHours }} giờ thi đấu
        </span>
      </div>

      <div v-if="selectedSlots.length === 0" class="no-slots-hint">
        <span class="material-symbols-outlined hint-icon">touch_app</span>
        <p class="hint-text">Vui lòng bấm chọn một hoặc nhiều khung giờ trên bảng lịch để tiếp tục.</p>
      </div>

      <div v-else class="slots-list-wrap">
        <div
          v-for="s in selectedSlots"
          :key="s.startsAt"
          class="slot-summary-item"
        >
          <div class="slot-time-badge">
            <span class="material-symbols-outlined icon-14">schedule</span>
            <span class="font-mono time-txt">
              {{ s.startsAt.slice(11, 16) }} - {{ s.endsAt.slice(11, 16) }}
            </span>
          </div>

          <div class="slot-price-wrap">
            <span class="font-mono item-price">{{ formatMoney(s.price) }}</span>
            <button
              type="button"
              class="btn-remove-slot"
              @click="emit('removeSlot', s)"
              title="Bỏ chọn khung giờ này"
            >
              <span class="material-symbols-outlined icon-14">close</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Guarantees & Perks -->
    <div class="trust-perks-box">
      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">bolt</span>
        <span>Giữ chỗ tức thì 100% thời gian thực</span>
      </div>
      <div class="perk-item">
        <span class="material-symbols-outlined perk-icon">verified_user</span>
        <span>Hỗ trợ đổi lịch linh hoạt trước giờ chơi 2h</span>
      </div>
    </div>

    <!-- Summary Total & Checkout -->
    <div class="summary-footer">
      <div class="price-breakdown">
        <div class="breakdown-row">
          <span>Thời lượng thi đấu:</span>
          <strong>{{ durationHours }} giờ</strong>
        </div>
        <div class="breakdown-row total-row">
          <span>Tổng thanh toán:</span>
          <strong class="total-num font-mono">{{ formatMoney(totalPrice) }}</strong>
        </div>
      </div>

      <button
        type="button"
        class="btn-checkout"
        :disabled="selectedSlots.length === 0 || loading"
        @click="emit('checkout')"
      >
        <span v-if="loading" class="material-symbols-outlined spinner-icon">progress_activity</span>
        <span v-else class="material-symbols-outlined btn-icon">payments</span>
        <span>{{ loading ? 'Đang khởi tạo đơn...' : 'Xác Nhận Giữ Chỗ & Thanh Toán' }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.schedule-summary-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .schedule-summary-card {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.summary-hdr {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}

:global(html.dark) .summary-hdr {
  border-color: #2e242a;
}

.title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.title-icon {
  font-size: 20px;
  color: var(--primary);
}

:global(html.dark) .title-icon {
  color: #e58e9f;
}

.badge-count {
  font-size: 11px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--primary-container);
  color: var(--primary);
}

:global(html.dark) .badge-count {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
  border: 1px solid rgba(229, 142, 159, 0.25);
}

.court-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.court-name {
  font-size: 14.5px;
  color: var(--primary);
  font-weight: 800;
}

:global(html.dark) .court-name {
  color: #f7a8b8;
}

.venue-info,
.venue-info-link,
.date-info {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--muted);
  text-decoration: none;
  width: fit-content;
  transition: color 0.15s ease;
}

.venue-info-link:hover {
  color: var(--primary);
  text-decoration: underline;
}

:global(html.dark) .venue-info-link:hover {
  color: #e58e9f;
}

.icon-10 {
  font-size: 10px;
  opacity: 0.7;
}

.icon-14 {
  font-size: 14px;
  color: var(--muted);
}

.selected-slots-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-sub-label {
  font-size: 11.5px;
  font-weight: 800;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.duration-badge {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--accent);
}

:global(html.dark) .duration-badge {
  color: #d8b46e;
}

.no-slots-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 20px 14px;
  border-radius: 14px;
  border: 1px dashed var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  text-align: center;
}

:global(html.dark) .no-slots-hint {
  background: rgba(255, 255, 255, 0.02);
  border-color: #382d33;
}

.hint-icon {
  font-size: 24px;
  color: var(--muted);
  opacity: 0.7;
}

.hint-text {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.4;
}

.slots-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 200px;
  overflow-y: auto;
  scrollbar-width: thin;
}

.slot-summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
}

:global(html.dark) .slot-summary-item {
  background: #231c20;
  border-color: #382d33;
}

.slot-time-badge {
  display: flex;
  align-items: center;
  gap: 6px;
}

.time-txt {
  font-size: 12.5px;
  font-weight: 800;
  color: var(--ink);
}

.slot-price-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.item-price {
  font-size: 12px;
  font-weight: 800;
  color: var(--ink);
}

.btn-remove-slot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-remove-slot:hover {
  background: var(--error-bg, #ffebee);
  color: var(--error-text, #c62828);
}

:global(html.dark) .btn-remove-slot:hover {
  background: rgba(248, 113, 113, 0.2);
  color: #f87171;
}

.trust-perks-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

:global(html.dark) .trust-perks-box {
  background: #151113;
  border-color: #2b2227;
}

.perk-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--muted);
}

.perk-icon {
  font-size: 14px;
  color: var(--accent);
}

:global(html.dark) .perk-icon {
  color: #d8b46e;
}

.summary-footer {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}

:global(html.dark) .summary-footer {
  border-color: #2e242a;
}

.price-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  color: var(--muted);
}

.total-row {
  padding-top: 4px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink);
}

.total-num {
  font-size: 20px;
  font-weight: 800;
  color: var(--primary);
}

:global(html.dark) .total-num {
  color: #f7a8b8;
}

.btn-checkout {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px;
  border-radius: 14px;
  border: 0;
  background: linear-gradient(145deg, var(--primary) 0%, var(--primary-hover, #562332) 100%);
  color: var(--on-primary);
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(107, 45, 63, 0.25);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-checkout:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 22px rgba(107, 45, 63, 0.35);
}

.btn-checkout:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

:global(html.dark) .btn-checkout {
  background: linear-gradient(145deg, #e58e9f 0%, #c46d80 100%);
  color: #1c0a10;
  box-shadow: 0 4px 20px rgba(229, 142, 159, 0.35);
}

:global(html.dark) .btn-checkout:hover:not(:disabled) {
  background: linear-gradient(145deg, #f0a3b2 0%, #d87d90 100%);
  box-shadow: 0 6px 26px rgba(229, 142, 159, 0.5);
}

.spinner-icon {
  font-size: 18px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
