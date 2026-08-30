<script setup lang="ts">
import type { Court } from '@/features/court/types/court'

defineProps<{
  courts: Court[]
  selectedCourtId: number
  frequency: 'weekly' | 'biweekly' | 'monthly'
  selectedWeekdays: number[]
  startTime: string
  endTime: string
  startDate: string
  endDate: string
  weekdayButtons: Array<{ id: number; label: string; name: string }>
  submitting?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedCourtId', id: number): void
  (e: 'update:frequency', freq: 'weekly' | 'biweekly' | 'monthly'): void
  (e: 'update:startTime', val: string): void
  (e: 'update:endTime', val: string): void
  (e: 'update:startDate', val: string): void
  (e: 'update:endDate', val: string): void
  (e: 'toggleWeekday', dayId: number): void
  (e: 'submit'): void
}>()
</script>

<template>
  <div class="recurring-wizard-card">
    <div class="wizard-header">
      <h3 class="wizard-title">✨ Tạo lịch định kỳ mới (Recurring Booking)</h3>
      <p class="wizard-sub">Giữ sân cố định hàng tuần hoặc hàng tháng, tự động tạo hóa đơn và thanh toán định kỳ.</p>
    </div>

    <form class="wizard-form" @submit.prevent="emit('submit')">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Chọn sân thể thao:</label>
          <select
            :value="selectedCourtId"
            class="form-input"
            @change="emit('update:selectedCourtId', Number(($event.target as HTMLSelectElement).value))"
          >
            <option v-for="c in courts" :key="c.id" :value="c.id">
              {{ c.name }} {{ c.venue ? `(${c.venue.name})` : '' }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Tần suất lặp:</label>
          <select
            :value="frequency"
            class="form-input"
            @change="emit('update:frequency', ($event.target as HTMLSelectElement).value as any)"
          >
            <option value="weekly">Hàng tuần (Weekly)</option>
            <option value="biweekly">2 tuần một lần (Bi-weekly)</option>
            <option value="monthly">Hàng tháng (Monthly)</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label class="form-label">Chọn các ngày trong tuần:</label>
          <div class="weekdays-row">
            <button
              v-for="btn in weekdayButtons"
              :key="btn.id"
              type="button"
              class="weekday-circle-btn"
              :class="{ active: selectedWeekdays.includes(btn.id) }"
              @click="emit('toggleWeekday', btn.id)"
            >
              {{ btn.label }}
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Giờ bắt đầu:</label>
          <input
            :value="startTime"
            type="time"
            class="form-input"
            @input="emit('update:startTime', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Giờ kết thúc:</label>
          <input
            :value="endTime"
            type="time"
            class="form-input"
            @input="emit('update:endTime', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Ngày bắt đầu chuỗi:</label>
          <input
            :value="startDate"
            type="date"
            class="form-input"
            @input="emit('update:startDate', ($event.target as HTMLInputElement).value)"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Ngày kết thúc chuỗi:</label>
          <input
            :value="endDate"
            type="date"
            class="form-input"
            @input="emit('update:endDate', ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>

      <button type="submit" class="btn-submit-series" :disabled="submitting">
        <span v-if="submitting">Đang xử lý...</span>
        <span v-else>🚀 Đặt Lịch Cố Định Ngay</span>
      </button>
    </form>
  </div>
</template>

<style scoped>
.recurring-wizard-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.wizard-title {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
}

.wizard-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.wizard-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.full-width {
  grid-column: 1 / -1;
}

.weekdays-row {
  display: flex;
  gap: 10px;
  margin-top: 6px;
}

.weekday-circle-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.weekday-circle-btn.active {
  background: var(--pink-dark, #db2777);
  color: #fff;
  border-color: var(--pink-dark, #db2777);
}

.btn-submit-series {
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
}
</style>
