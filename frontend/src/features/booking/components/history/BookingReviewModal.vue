<script setup lang="ts">
import type { Booking } from '../../types/booking'

defineProps<{
  show: boolean
  booking: Booking | null
  rating: number
  body: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:rating', val: number): void
  (e: 'update:body', val: string): void
  (e: 'submit'): void
}>()
</script>

<template>
  <div v-if="show && booking" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <h3 class="modal-title">⭐ Đánh Giá Trải Nghiệm Sân Đấu</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="booking-summary-box">
          <strong>{{ booking.court?.name || 'Sân Cầu Lông' }}</strong>
          <span>📍 {{ booking.court?.venue?.name }}</span>
        </div>

        <div class="rating-picker-box">
          <label class="form-label">Chất lượng mặt sân & cơ sở vật chất:</label>
          <div class="stars-row">
            <button
              v-for="star in [1, 2, 3, 4, 5]"
              :key="star"
              type="button"
              class="star-btn"
              :class="{ active: rating >= star }"
              @click="emit('update:rating', star)"
            >
              ★
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Cảm nhận chi tiết của bạn:</label>
          <textarea
            :value="body"
            rows="3"
            class="form-input"
            placeholder="Mặt thảm êm, ánh sáng tốt, nhân viên thân thiện..."
            @input="emit('update:body', ($event.target as HTMLTextAreaElement).value)"
          ></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" @click="emit('submit')">
          Gửi Đánh Giá
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.booking-summary-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 14px;
  font-size: 13px;
}

.rating-picker-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 14px;
}

.stars-row {
  display: flex;
  gap: 6px;
}

.star-btn {
  font-size: 24px;
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
}

.star-btn.active {
  color: #f59e0b;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 24px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
