<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, QrImage } from '@/shared/components'
import type { VenueCreditAccount } from '@/core/types/venueCredit'
import { useDeadlineCountdown } from '@/features/booking/composables/useDeadlineCountdown'
import { useVenueCreditCashOut } from '../composables/useVenueCredits'

const props = defineProps<{ account: VenueCreditAccount }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const { t } = useI18n()
const { code, creating, error, create, reset } = useVenueCreditCashOut()
const amount = ref<number>(props.account.balance)
const valid = computed(
  () =>
    Number.isInteger(amount.value) && amount.value >= 1 && amount.value <= props.account.balance,
)
const countdown = useDeadlineCountdown(() => code.value?.expires_at)
const expired = computed(() => countdown.value === '00:00')

function submit() {
  if (valid.value) void create(props.account.organization.id, amount.value)
}
</script>

<template>
  <section class="cash-out" data-testid="venue-credit-cash-out">
    <header class="cash-out-head">
      <strong>{{ t('profile.credit.cashOutTitle') }}</strong>
      <button type="button" class="link-btn" @click="emit('close')">
        {{ t('profile.credit.close') }}
      </button>
    </header>

    <template v-if="code && !expired">
      <p class="hint">
        {{ t('profile.credit.codeHint', { amount: formatCurrency(code.amount) }) }}
      </p>
      <div class="code-box">
        <QrImage :value="code.code" :alt="t('profile.credit.codeQrAlt')" :size="180" />
        <strong class="code font-mono" data-testid="venue-credit-code">{{ code.code }}</strong>
        <span class="countdown" data-testid="venue-credit-countdown">
          {{ t('profile.credit.codeExpiresIn', { time: countdown }) }}
        </span>
      </div>
    </template>

    <form v-else class="cash-out-form" @submit.prevent="submit">
      <p v-if="code" class="hint">{{ t('profile.credit.codeExpired') }}</p>
      <label class="field">
        <span>{{ t('profile.credit.amountLabel', { max: formatCurrency(account.balance) }) }}</span>
        <input
          v-model.number="amount"
          type="number"
          min="1"
          :max="account.balance"
          step="1000"
          class="input font-mono"
          data-testid="venue-credit-amount"
          @input="reset()"
        />
      </label>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <AuraButton
        type="submit"
        variant="primary"
        size="sm"
        icon="qr_code_2"
        :loading="creating"
        :disabled="!valid"
        data-testid="venue-credit-create-code"
      >
        {{ t('profile.credit.createCode') }}
      </AuraButton>
    </form>
  </section>
</template>

<style scoped>
.cash-out {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 14px;
  border: 1px dashed var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
}

.cash-out-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.link-btn {
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 13px;
}

.hint {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.code-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.code {
  font-size: 30px;
  letter-spacing: 0.2em;
  color: var(--ink);
}

.countdown {
  font-size: 13px;
  color: #9a5a00;
}

.cash-out-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-start;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  color: var(--ink);
}

.input {
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  width: 200px;
}

.error {
  margin: 0;
  color: #c62828;
  font-size: 13px;
}
</style>
