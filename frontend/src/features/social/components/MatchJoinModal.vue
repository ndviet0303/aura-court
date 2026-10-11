<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { SocialMatch } from '../types/social'

defineProps<{
  show: boolean
  match: SocialMatch | null
  processing: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', match: SocialMatch): void
}>()

function formatMoney(amount?: number): string {
  if (!amount) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Header -->
      <div class="modal-header">
        <div class="header-title-row">
          <span class="material-symbols-outlined header-ico">group_add</span>
          <h3 class="modal-title">Tham Gia Kèo Giao Lưu</h3>
        </div>
        <button type="button" class="modal-close-btn" aria-label="Đóng" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <div class="match-summary-card">
          <div class="summary-top">
            <span class="sport-tag">
              <span class="material-symbols-outlined tag-ico">{{ match.sport_icon }}</span>
              {{ match.sport_name }}
            </span>
            <span class="slots-pill">
              {{ match.joined_count }} / {{ match.required_participants }} người
            </span>
          </div>

          <h4 class="summary-title">{{ match.title }}</h4>

          <div class="summary-details">
            <div class="detail-row">
              <span class="material-symbols-outlined detail-ico">location_on</span>
              <span
                >{{ match.venue_name }} {{ match.court_name ? `(${match.court_name})` : '' }} -
                {{ match.city }}</span
              >
            </div>

            <div class="detail-row">
              <span class="material-symbols-outlined detail-ico">schedule</span>
              <span class="font-mono">{{ match.starts_at }}</span>
            </div>

            <div class="detail-row">
              <span class="material-symbols-outlined detail-ico">person</span>
              <span
                >Chủ phòng: <strong>{{ match.host_name }}</strong> (Uy tín:
                {{ match.host_reputation }}%)</span
              >
            </div>
          </div>
        </div>

        <!-- Cost & Bill Sharing -->
        <div class="fee-split-box">
          <div class="fee-info">
            <span class="fee-lbl">Dự kiến chi phí phần bạn:</span>
            <span class="fee-val font-mono">{{ formatMoney(match.cost_per_person) }}</span>
          </div>
          <span class="fee-mode">{{
            match.split_bill_mode || 'Chia đều tiền sân & chia bill trực tiếp sau trận'
          }}</span>
        </div>

        <!-- Community Guidelines Note -->
        <div class="rules-note">
          <span class="material-symbols-outlined note-ico">info</span>
          <div class="note-text">
            <strong>Quy định văn hóa giao lưu Aura Court:</strong>
            <p>
              Vui lòng đến trước 10 phút để khởi động. Trong trường hợp có việc đột xuất không thể
              tham gia, hãy báo cho chủ phòng trước tối thiểu 2 tiếng để nhường chỗ cho vận động
              viên khác.
            </p>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="modal-footer">
        <AuraButton variant="ghost" size="md" :disabled="processing" @click="emit('close')">
          Huỷ bỏ
        </AuraButton>

        <AuraButton
          variant="primary"
          size="md"
          icon="check_circle"
          :loading="processing"
          @click="emit('confirm', match)"
        >
          Xác Nhận Tham Gia
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 12px 36px rgba(0, 0, 0, 0.25);
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

.header-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-ico {
  color: var(--pink-dark, #db2777);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
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
  border-radius: 50%;
  transition: color 0.15s ease;
}

.modal-close-btn:hover {
  color: var(--ink);
}

.modal-body {
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.match-summary-card {
  background: var(--surface-container, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.summary-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sport-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  font-size: 11.5px;
  font-weight: 750;
}

.tag-ico {
  font-size: 14px;
}

.slots-pill {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
}

.summary-title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--ink);
}

.summary-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 6px;
  border-top: 1px dashed var(--outline);
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--muted);
}

.detail-ico {
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
}

.fee-split-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.05);
  border: 1px solid rgba(219, 39, 119, 0.2);
}

.fee-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.fee-lbl {
  font-size: 11.5px;
  color: var(--muted);
  font-weight: 600;
}

.fee-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
}

.fee-mode {
  font-size: 11px;
  color: var(--muted);
  max-width: 180px;
  text-align: right;
}

.rules-note {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.note-ico {
  font-size: 18px;
  color: #3b82f6;
  flex-shrink: 0;
  margin-top: 2px;
}

.note-text {
  font-size: 12px;
  line-height: 1.45;
  color: var(--muted);
}

.note-text strong {
  color: var(--ink);
  display: block;
  margin-bottom: 2px;
}

.note-text p {
  margin: 0;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 16px 22px;
  border-top: 1px solid var(--outline);
  background: var(--surface-container, rgba(0, 0, 0, 0.01));
}
</style>
