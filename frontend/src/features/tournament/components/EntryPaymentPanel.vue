<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import OwnerPaymentInstructionsPanel from '@/features/booking/components/OwnerPaymentInstructionsPanel.vue'
import { useDeadlineCountdown } from '@/features/booking/composables/useDeadlineCountdown'
import type { PaymentInstructions } from '../types/tournament'

// UC-7.25: the same bank transfer and QR as a booking; it ends at the tournament's shared payment deadline.
const props = defineProps<{ payment: PaymentInstructions; refreshing: boolean }>()
const emit = defineEmits<{ refresh: [] }>()
const { t } = useI18n()
const countdown = useDeadlineCountdown(() => props.payment.expires_at)
</script>

<template>
  <div class="entry-payment">
    <p v-if="payment.status === 'pending' && countdown" class="admin-detail-muted">
      {{ t('tournament.me.paymentCountdown', { time: countdown }) }}
    </p>
    <OwnerPaymentInstructionsPanel
      :attempt="{ ...payment, provider: 'sepay' }"
      reservation-status="held"
      :loading="false"
      :refreshing="refreshing"
      @refresh="emit('refresh')"
    />
  </div>
</template>

<style scoped>
.entry-payment {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
</style>
