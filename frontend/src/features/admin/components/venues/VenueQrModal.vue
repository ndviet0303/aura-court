<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import type { AdminVenueItem } from '../../composables/useAdminVenues'

defineProps<{
  show: boolean
  venue: AdminVenueItem | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div v-if="show && venue" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="title-with-icon">
          <span class="material-symbols-outlined card-title-icon">qr_code_2</span>
          <h3 class="modal-title">{{ t('admin.venues.qrModalTitle', { name: resolveLocalizedText(venue.name) }) }}</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body qr-body">
        <p class="qr-sub">{{ t('admin.venues.qrModalSub') }}</p>
        <div class="qr-box">
          <img
            :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(venue.share_url || 'https://auracourt.vn')}`"
            alt="Venue QR"
          />
        </div>
        <span class="venue-link font-mono">{{ venue.share_url || `https://auracourt.vn/venues/${venue.slug}` }}</span>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" @click="emit('close')">{{ t('common.close') }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.title-with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title-icon {
  font-size: 22px;
  color: var(--pink-dark, #db2777);
}

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
