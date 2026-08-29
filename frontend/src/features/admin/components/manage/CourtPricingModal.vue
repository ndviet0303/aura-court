<script setup lang="ts">
import type { Court } from '@/features/court/types/court'

defineProps<{
  show: boolean
  court: Court | null
  selectedWeekdayTab: number
  bulkScope: 'current' | 'weekdays' | 'weekends' | 'all'
  bulkStartHour: number
  bulkEndHour: number
  bulkPrice: number
  bulkSaving?: boolean
  priceNotice?: string
  priceError?: string
  weekdayList: Array<{ id: number; label: string; short: string; isWeekend?: boolean }>
  morningSlots: any[]
  afternoonSlots: any[]
  peakSlots: any[]
  nightSlots: any[]
  pricingStats: { count: number; min: number; max: number; avg: number }
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:selectedWeekdayTab', day: number): void
  (e: 'update:bulkScope', scope: 'current' | 'weekdays' | 'weekends' | 'all'): void
  (e: 'update:bulkStartHour', h: number): void
  (e: 'update:bulkEndHour', h: number): void
  (e: 'update:bulkPrice', p: number): void
  (e: 'applyBulk'): void
  (e: 'deleteSlot', slotId: number): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && court" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-xl">
      <div class="modal-header">
        <h3 class="modal-title">
          💰 Thiết Lập Bảng Giá: <strong>{{ court.name }}</strong>
        </h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body pricing-modal-body">
        <div v-if="priceNotice" class="alert-banner-success">{{ priceNotice }}</div>
        <div v-if="priceError" class="alert-banner-error">{{ priceError }}</div>

        <!-- Weekday Navigation Tabs -->
        <div class="weekday-tabs-row">
          <button
            class="weekday-tab-btn"
            :class="{ active: selectedWeekdayTab === 0 }"
            @click="emit('update:selectedWeekdayTab', 0)"
          >
            Tất Cả Các Ngày
          </button>

          <button
            v-for="d in weekdayList"
            :key="d.id"
            class="weekday-tab-btn"
            :class="{ active: selectedWeekdayTab === d.id, weekend: d.isWeekend }"
            @click="emit('update:selectedWeekdayTab', d.id)"
          >
            {{ d.label }}
          </button>
        </div>

        <!-- Bulk Price Setup Section -->
        <div class="bulk-pricing-section">
          <h4 class="section-title">⚡ Cài Đặt Giá Hàng Loạt Cho Khung Giờ</h4>
          <div class="bulk-inputs-grid">
            <div class="form-group">
              <label class="form-label">Phạm vi áp dụng:</label>
              <select
                :value="bulkScope"
                class="form-input"
                @change="emit('update:bulkScope', ($event.target as HTMLSelectElement).value as any)"
              >
                <option value="current">Ngày đang chọn</option>
                <option value="weekdays">Thứ 2 đến Thứ 6 (Trong tuần)</option>
                <option value="weekends">Thứ 7 & Chủ Nhật (Cuối tuần)</option>
                <option value="all">Tất cả 7 ngày trong tuần</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Từ giờ:</label>
              <select
                :value="bulkStartHour"
                class="form-input"
                @change="emit('update:bulkStartHour', Number(($event.target as HTMLSelectElement).value))"
              >
                <option v-for="h in 18" :key="h" :value="h + 4">
                  {{ String(h + 4).padStart(2, '0') }}:00
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Đến giờ:</label>
              <select
                :value="bulkEndHour"
                class="form-input"
                @change="emit('update:bulkEndHour', Number(($event.target as HTMLSelectElement).value))"
              >
                <option v-for="h in 18" :key="h" :value="h + 5">
                  {{ String(h + 5).padStart(2, '0') }}:00
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Đơn giá (VND/giờ):</label>
              <input
                :value="bulkPrice"
                type="number"
                step="10000"
                class="form-input"
                @input="emit('update:bulkPrice', Number(($event.target as HTMLInputElement).value) || 0)"
              />
            </div>

            <button
              class="btn-apply-bulk"
              :disabled="bulkSaving"
              @click="emit('applyBulk')"
            >
              <span v-if="bulkSaving">Đang áp dụng...</span>
              <span v-else>Áp Dụng Giá</span>
            </button>
          </div>
        </div>

        <!-- Slots Matrices by Period -->
        <div class="slots-matrices-container">
          <div class="period-group">
            <h5 class="period-heading">🌅 Sáng (06:00 - 12:00) · {{ morningSlots.length }} Khung</h5>
            <div class="period-slots-grid">
              <div v-for="slot in morningSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price">{{ formatMoney(slot.price) }}</span>
                <button class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>

          <div class="period-group">
            <h5 class="period-heading">☀️ Chiều (12:00 - 17:00) · {{ afternoonSlots.length }} Khung</h5>
            <div class="period-slots-grid">
              <div v-for="slot in afternoonSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price">{{ formatMoney(slot.price) }}</span>
                <button class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>

          <div class="period-group highlight-peak">
            <h5 class="period-heading">🔥 Giờ Vàng Cao Điểm (17:00 - 22:00) · {{ peakSlots.length }} Khung</h5>
            <div class="period-slots-grid">
              <div v-for="slot in peakSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price text-pink">{{ formatMoney(slot.price) }}</span>
                <button class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>

          <div v-if="nightSlots.length" class="period-group">
            <h5 class="period-heading">🌙 Đêm muộn (22:00 - 06:00) · {{ nightSlots.length }} Khung</h5>
            <div class="period-slots-grid">
              <div v-for="slot in nightSlots" :key="slot.id" class="slot-pill">
                <span class="slot-time">{{ slot.starts_at }} - {{ slot.ends_at }}</span>
                <span class="slot-price">{{ formatMoney(slot.price) }}</span>
                <button class="btn-del-slot" @click="emit('deleteSlot', slot.id)">✕</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer pricing-modal-footer">
        <div class="pricing-stats-summary">
          <span>Tổng: <strong>{{ pricingStats.count }}</strong> khung</span> · 
          <span>Thấp nhất: <strong>{{ formatMoney(pricingStats.min) }}</strong></span> · 
          <span>Cao nhất: <strong>{{ formatMoney(pricingStats.max) }}</strong></span> · 
          <span>Trung bình: <strong>{{ formatMoney(pricingStats.avg) }}</strong></span>
        </div>
        <button class="btn-primary" @click="emit('close')">Xong (Đóng)</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pricing-modal-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 75vh;
  overflow-y: auto;
}

.weekday-tabs-row {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.weekday-tab-btn {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.weekday-tab-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.weekday-tab-btn.weekend {
  border-color: rgba(245, 158, 11, 0.4);
}

.bulk-pricing-section {
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.section-title {
  margin: 0 0 12px;
  font-size: 13px;
  font-weight: 700;
}

.bulk-inputs-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr) auto;
  gap: 12px;
  align-items: end;
}

.btn-apply-bulk {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

.slots-matrices-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.period-group {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.period-group.highlight-peak {
  border-color: rgba(236, 72, 153, 0.4);
  background: rgba(236, 72, 153, 0.02);
}

.period-heading {
  margin: 0 0 10px;
  font-size: 13px;
  font-weight: 700;
}

.period-slots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 8px;
}

.slot-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.slot-time {
  font-weight: 600;
}

.slot-price {
  font-weight: 700;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.btn-del-slot {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  padding: 2px 4px;
  font-size: 11px;
}

.btn-del-slot:hover {
  color: #ef4444;
}

.pricing-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pricing-stats-summary {
  font-size: 12px;
  color: var(--muted);
}

.alert-banner-success {
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  font-size: 13px;
}

.alert-banner-error {
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  font-size: 13px;
}

.btn-primary {
  padding: 10px 24px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
