<script setup lang="ts">
import { ref } from 'vue'
import type { Reservation } from '../../types/reservation'
import { displayCode } from '../../utils/reservation'

defineProps<{
  show: boolean
  booking: Reservation | null
  rating: number
  body: string
  submitting?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:rating', val: number): void
  (e: 'update:body', val: string): void
  (e: 'submit'): void
}>()

const hoveredRating = ref<number | null>(null)
</script>

<template>
  <div v-if="show && booking" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined header-icon-gold">rate_review</span>
          <h3 class="modal-title">Đánh Giá Trải Nghiệm Sân Đấu</h3>
        </div>
        <button class="modal-close-btn" type="button" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="court-summary-box">
          <strong class="court-name">{{ booking.court?.name || 'Sân Aura Court' }}</strong>
          <span class="venue-name">
            <span class="material-symbols-outlined loc-icon">location_on</span>
            {{ booking.court?.venue?.name || 'Aura Badminton Center' }}
          </span>
          <span class="code-badge font-mono">Đơn: #{{ displayCode(booking) }}</span>
        </div>

        <div class="rating-section">
          <label class="section-label">Chất lượng mặt sân & cơ sở vật chất:</label>
          <div class="stars-picker" @mouseleave="hoveredRating = null">
            <button
              v-for="star in [1, 2, 3, 4, 5]"
              :key="star"
              type="button"
              class="star-btn"
              :class="{
                active: (hoveredRating ?? rating) >= star,
              }"
              @mouseenter="hoveredRating = star"
              @click="emit('update:rating', star)"
            >
              <span class="material-symbols-outlined star-icon">
                {{ (hoveredRating ?? rating) >= star ? 'star' : 'star_border' }}
              </span>
            </button>
            <span class="rating-text-hint font-mono">
              {{
                (hoveredRating ?? rating) === 5
                  ? 'Tuyệt vời (5/5)'
                  : (hoveredRating ?? rating) === 4
                    ? 'Rất tốt (4/5)'
                    : (hoveredRating ?? rating) === 3
                      ? 'Bình thường (3/5)'
                      : (hoveredRating ?? rating) === 2
                        ? 'Chưa hài lòng (2/5)'
                        : 'Kém (1/5)'
              }}
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="section-label">Cảm nhận chi tiết của bạn:</label>
          <textarea
            :value="body"
            rows="3"
            class="form-textarea"
            placeholder="Mặt thảm êm ái, hệ thống chiếu sáng chuẩn thi đấu, nhân viên nhiệt tình..."
            @input="emit('update:body', ($event.target as HTMLTextAreaElement).value)"
          ></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" type="button" :disabled="submitting" @click="emit('close')">
          Huỷ
        </button>
        <button class="btn-submit" type="button" :disabled="submitting" @click="emit('submit')">
          <span v-if="submitting" class="material-symbols-outlined spin-icon"
            >progress_activity</span
          >
          <span v-else>Gửi Đánh Giá</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: var(--surface);
  color: var(--ink);
  border-radius: 20px;
  width: 100%;
  max-width: 480px;
  border: 1px solid var(--outline);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--outline);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon-gold {
  font-size: 22px;
  color: var(--accent, #c9a15a);
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
}

.modal-close-btn:hover {
  color: var(--ink);
}

.modal-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.court-summary-box {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--border);
}

.court-name {
  font-size: 14px;
}

.venue-name {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--muted);
}

.loc-icon {
  font-size: 14px;
}

.code-badge {
  font-size: 11px;
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

.rating-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.stars-picker {
  display: flex;
  align-items: center;
  gap: 4px;
}

.star-btn {
  background: transparent;
  border: 0;
  padding: 4px;
  cursor: pointer;
  color: var(--muted);
  transition:
    transform 0.15s ease,
    color 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.star-btn:hover {
  transform: scale(1.15);
}

.star-icon {
  font-size: 28px;
}

.star-btn.active {
  color: #f59e0b;
}

.rating-text-hint {
  font-size: 12px;
  color: var(--muted);
  margin-left: 8px;
  font-weight: 600;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  color: var(--ink);
  font-size: 13px;
  line-height: 1.4;
  outline: none;
  resize: vertical;
}

.form-textarea:focus {
  border-color: var(--primary, #6b2d3f);
}

.modal-footer {
  padding: 14px 22px;
  border-top: 1px solid var(--outline);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-cancel {
  padding: 9px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-submit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 22px;
  border-radius: 10px;
  border: 0;
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spin-icon {
  animation: spin 1s linear infinite;
  font-size: 16px;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
