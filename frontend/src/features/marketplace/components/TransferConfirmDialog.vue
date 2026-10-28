<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency, formatSlotRange } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { BookingTransfer } from '../types/transfer'

// UC-9.9 (Bước 6): repeat the buyer, the bookings and their prices before the seller confirms.
defineProps<{ transfer: BookingTransfer; busy: boolean }>()
const emit = defineEmits<{ confirm: []; close: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div
      class="modal-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="confirm-transfer-title"
    >
      <div class="modal-header">
        <h3 id="confirm-transfer-title" class="modal-title">
          {{ t('resale.transfer.confirmTitle') }}
        </h3>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="modal-body confirm-body">
        <p>{{ t('resale.transfer.confirmIntro', { name: transfer.buyer.name }) }}</p>
        <ul class="confirm-items">
          <li v-for="item in transfer.items" :key="item.reservation_id">
            <span>
              {{ item.court.name }} · {{ item.venue.name }} ·
              {{ formatSlotRange(item.starts_at, item.ends_at) }}
            </span>
            <strong>{{ formatCurrency(item.price) }}</strong>
          </li>
        </ul>
        <p class="confirm-total">
          {{ t('resale.transfer.total') }}: <strong>{{ formatCurrency(transfer.total) }}</strong>
        </p>
        <p class="muted">{{ t('resale.transfer.confirmWarning') }}</p>
      </div>
      <div class="modal-footer">
        <AuraButton variant="outline" :disabled="busy" @click="emit('close')">
          {{ t('common.cancel') }}
        </AuraButton>
        <AuraButton :loading="busy" data-testid="transfer-confirm-submit" @click="emit('confirm')">
          {{ t('resale.transfer.confirm') }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.confirm-body {
  display: grid;
  gap: 10px;
}
.confirm-body p {
  margin: 0;
}
.confirm-items {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
}
.confirm-items li {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  justify-content: space-between;
  padding: 8px 10px;
  border: 1px solid var(--outline);
  border-radius: 10px;
}
.confirm-total {
  text-align: right;
}
.muted {
  color: var(--muted);
  font-size: 12px;
}
</style>
