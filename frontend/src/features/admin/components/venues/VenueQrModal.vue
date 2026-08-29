<script setup lang="ts">
import type { AdminVenueItem } from '../../composables/useAdminVenues'

defineProps<{
  show: boolean
  venue: AdminVenueItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div v-if="show && venue" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <h3 class="modal-title">📱 Mã QR Cơ Sở: {{ venue.name }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body qr-body">
        <p class="qr-sub">Khách hàng có thể quét mã QR này tại quầy hoặc cửa sân để mở trang đặt sân / chỉ đường:</p>
        <div class="qr-box">
          <img
            :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(venue.share_url || 'https://auracourt.vn')}`"
            alt="Venue QR"
          />
        </div>
        <span class="venue-link font-mono">{{ venue.share_url || `https://auracourt.vn/venues/${venue.slug}` }}</span>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.qr-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
}

.qr-sub {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.qr-box {
  width: 180px;
  height: 180px;
  padding: 8px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: #fff;
}

.qr-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.venue-link {
  font-size: 11px;
  color: var(--pink-dark, #db2777);
  word-break: break-all;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
