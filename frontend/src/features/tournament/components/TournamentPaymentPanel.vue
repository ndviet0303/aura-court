<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import OwnerPaymentInstructionsPanel from '@/features/booking/components/OwnerPaymentInstructionsPanel.vue'
import type { ReservationPaymentAttempt } from '@/features/booking/api/bookingApi'
import { tournamentApi } from '../api/tournamentApi'

const props = defineProps<{
  tournamentId: number | string
  payment: ReservationPaymentAttempt
}>()

const emit = defineEmits<{ (e: 'paid'): void }>()

const { t } = useI18n()
const attempt = ref<ReservationPaymentAttempt | null>(props.payment)
const registered = ref(false)
const refreshing = ref(false)
const loading = ref(false)
const error = ref('')
let pollInterval: ReturnType<typeof setInterval> | undefined

function stopPolling() {
  if (pollInterval) clearInterval(pollInterval)
  pollInterval = undefined
}

function startPolling() {
  stopPolling()
  if (attempt.value?.status === 'pending') pollInterval = setInterval(refresh, 5000)
}

// No realtime channel in production: the page polls the registration until the owner receipt lands.
async function refresh() {
  if (refreshing.value) return
  refreshing.value = true
  error.value = ''
  try {
    const res = await tournamentApi.getRegistration(props.tournamentId)
    if (res.registered) {
      registered.value = true
      stopPolling()
      emit('paid')
      return
    }
    attempt.value = res.payment
    if (attempt.value?.status !== 'pending') stopPolling()
  } catch (cause) {
    error.value = errorMessage(cause, t('tournament.paymentRefreshFailed'))
  } finally {
    refreshing.value = false
  }
}

// An expired hold is replaced by a fresh one when a place is still free.
async function retry() {
  loading.value = true
  error.value = ''
  try {
    const res = await tournamentApi.register(props.tournamentId)
    if (res.registered) {
      registered.value = true
      emit('paid')
      return
    }
    attempt.value = res.payment
    startPolling()
  } catch (cause) {
    error.value = errorMessage(cause, t('tournament.paymentRefreshFailed'))
  } finally {
    loading.value = false
  }
}

watch(
  () => props.payment,
  (payment) => {
    attempt.value = payment
    startPolling()
  },
  { immediate: true },
)

onUnmounted(stopPolling)
</script>

<template>
  <div class="tournament-payment" data-testid="tournament-payment">
    <p class="payment-hint">{{ t('tournament.paymentHint') }}</p>
    <OwnerPaymentInstructionsPanel
      :attempt="attempt"
      :reservation-status="registered ? 'confirmed' : 'held'"
      :loading="loading"
      :refreshing="refreshing"
      :error="error"
      @refresh="refresh"
      @retry="retry"
    />
  </div>
</template>

<style scoped>
.tournament-payment {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payment-hint {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
}
</style>
