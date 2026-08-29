<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Court } from '../../types/court'

defineProps<{
  court: Court
  basePrice: number
  selectedDuration: number
  estimatedTotal: number
  durationOptions: Array<{ value: number; label: string }>
}>()

const emit = defineEmits<{
  (e: 'update:selectedDuration', val: number): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="sticky-booking-card">
    <div class="card-price-header">
      <span class="price-lbl">Giá thuê từ:</span>
      <div class="price-val">
        <strong class="price-num text-pink font-mono">{{ formatMoney(basePrice) }}</strong>
        <span class="unit">/ giờ</span>
      </div>
    </div>

    <!-- Duration Picker -->
    <div class="duration-picker-box">
      <label class="d-lbl">Chọn thời lượng thuê:</label>
      <div class="durations-grid">
        <button
          v-for="opt in durationOptions"
          :key="opt.value"
          type="button"
          class="duration-btn"
          :class="{ active: selectedDuration === opt.value }"
          @click="emit('update:selectedDuration', opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- Estimate Summary -->
    <div class="estimate-box">
      <span>Tạm tính ước tính:</span>
      <strong class="estimate-amount text-pink font-mono">{{ formatMoney(estimatedTotal) }}</strong>
    </div>

    <!-- Book CTA -->
    <RouterLink
      :to="`/booking?court=${court.id}&duration=${selectedDuration}`"
      class="btn-book-now"
    >
      🏸 Chọn Giờ & Đặt Sân Ngay
    </RouterLink>
  </div>
</template>

<style scoped>
.sticky-booking-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.card-price-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.price-lbl {
  font-size: 13px;
  color: var(--muted);
}

.price-val {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.price-num {
  font-size: 22px;
  font-weight: 800;
}

.unit {
  font-size: 12px;
  color: var(--muted);
}

.text-pink { color: var(--pink-dark, #db2777); }

.duration-picker-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.d-lbl {
  font-size: 12px;
  font-weight: 600;
}

.durations-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.duration-btn {
  padding: 8px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.duration-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.estimate-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 10px;
  background: rgba(236, 72, 153, 0.04);
  border: 1px solid rgba(236, 72, 153, 0.15);
  font-size: 13px;
}

.estimate-amount {
  font-size: 16px;
}

.btn-book-now {
  padding: 14px;
  border-radius: 12px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 800;
  font-size: 14px;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(219, 39, 119, 0.25);
}
</style>
