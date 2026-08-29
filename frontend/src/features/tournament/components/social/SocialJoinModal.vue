<script setup lang="ts">
import type { SocialMatch } from '../../composables/useSocialTournament'

defineProps<{
  show: boolean
  match: SocialMatch | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', match: SocialMatch): void
}>()
</script>

<template>
  <div v-if="show && match" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <h3 class="modal-title">⚡ Tham Gia Kèo Giao Lưu</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <p>Bạn sắp tham gia kèo: <strong>{{ match.title }}</strong></p>
        <div class="match-mini-info">
          <span>📍 {{ match.venue_name }}</span>
          <span>⏰ {{ match.starts_at }}</span>
          <span>👤 Chủ phòng: {{ match.host_name }}</span>
        </div>
        <p class="terms-note">Vui lòng có mặt đúng giờ và thanh toán phần tiền sân của mình trực tiếp tại quầy hoặc qua chia bill.</p>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Huỷ</button>
        <button class="btn-primary" @click="emit('confirm', match)">
          Xác Nhận Tham Gia
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.match-mini-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  font-size: 12px;
  margin: 10px 0;
}

.terms-note {
  font-size: 11px;
  color: var(--muted);
  font-style: italic;
}

.btn-cancel {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.btn-primary {
  padding: 8px 20px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
</style>
