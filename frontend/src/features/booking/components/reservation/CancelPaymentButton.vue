<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { bookingApi } from '../../api/bookingApi'
import { bookingErrorMessage } from '../../utils/reservation'

// Cancels a booking still pending payment, after an explicit confirmation step (report FR-76).
const props = defineProps<{ code: string }>()
const emit = defineEmits<{ (e: 'cancelled'): void }>()

const { t } = useI18n()
const confirming = ref(false)
const submitting = ref(false)
const error = ref('')

async function cancel() {
  submitting.value = true
  error.value = ''
  try {
    await bookingApi.cancelReservationPayment(props.code)
    confirming.value = false
    emit('cancelled')
  } catch (cause) {
    error.value = bookingErrorMessage(cause, t('reservations.cancelPayment.failed'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="cancel-payment">
    <button
      v-if="!confirming"
      type="button"
      class="cancel-btn"
      data-testid="cancel-payment"
      @click="confirming = true"
    >
      {{ t('reservations.cancelPayment.button') }}
    </button>
    <div v-else class="cancel-confirm" role="group" data-testid="cancel-payment-confirm">
      <span class="cancel-question">{{ t('reservations.cancelPayment.question') }}</span>
      <button
        type="button"
        class="cancel-btn danger"
        :disabled="submitting"
        data-testid="confirm-cancel-payment"
        @click="cancel"
      >
        {{
          submitting
            ? t('reservations.cancelPayment.cancelling')
            : t('reservations.cancelPayment.confirm')
        }}
      </button>
      <button type="button" class="cancel-btn" :disabled="submitting" @click="confirming = false">
        {{ t('reservations.cancelPayment.keep') }}
      </button>
    </div>
    <p v-if="error" class="cancel-error" role="alert" data-testid="cancel-payment-error">
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.cancel-payment {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.cancel-confirm {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.cancel-question {
  font-size: 12.5px;
  color: var(--ink);
}

.cancel-btn {
  padding: 8px 14px;
  border-radius: 9999px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.cancel-btn.danger,
.cancel-btn:hover:not(:disabled) {
  background: var(--error-bg, rgba(198, 40, 40, 0.1));
  color: var(--error-text, #c62828);
  border-color: rgba(198, 40, 40, 0.3);
}

.cancel-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.cancel-error {
  margin: 0;
  font-size: 12px;
  color: var(--error-text, #c62828);
}
</style>
