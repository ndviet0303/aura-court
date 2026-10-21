<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import type { PriceChange } from '../types/reservation'
import { validBookerPhone } from '../utils/reservation'

// What the customer agrees to before submitting: the booker contact the booking will record (once
// signed in), the amounts (payable is the expected_total sent) and a PRICE_CHANGED awaiting confirmation.
defineProps<{
  rental: number
  couponCode?: string | null
  payable: number
  priceChange?: PriceChange | null
  busy?: boolean
}>()

const emit = defineEmits<{ (e: 'confirmPrice'): void }>()

const { t } = useI18n()
const auth = useAuthStore()
const phoneMissing = computed(() => !validBookerPhone(auth.user?.phone))
</script>

<template>
  <section class="booking-agreement" data-testid="booking-agreement">
    <div v-if="auth.user" class="agreement-booker">
      <span class="agreement-label">{{ t('reservations.agreement.booker') }}</span>
      <strong data-testid="booker-name">{{ auth.user?.name }}</strong>
      <span data-testid="booker-contact">
        {{ auth.user?.phone || t('reservations.agreement.noPhone') }} · {{ auth.user?.email }}
      </span>
      <RouterLink
        v-if="phoneMissing"
        :to="{ name: 'profile' }"
        class="agreement-link"
        data-testid="update-phone-link"
      >
        {{ t('reservations.agreement.updatePhone') }}
      </RouterLink>
    </div>

    <dl class="agreement-amounts">
      <div>
        <dt>{{ t('reservations.agreement.rental') }}</dt>
        <dd>{{ formatCurrency(rental) }}</dd>
      </div>
      <div v-if="couponCode">
        <dt>{{ t('reservations.agreement.voucher') }}</dt>
        <dd>{{ couponCode }}</dd>
      </div>
      <div v-if="rental > payable">
        <dt>{{ t('reservations.agreement.discount') }}</dt>
        <dd>-{{ formatCurrency(rental - payable) }}</dd>
      </div>
      <div class="agreement-total">
        <dt>{{ t('reservations.agreement.payable') }}</dt>
        <dd data-testid="agreement-payable">{{ formatCurrency(payable) }}</dd>
      </div>
    </dl>

    <div v-if="priceChange" class="agreement-price-change" role="alert" data-testid="price-changed">
      <strong>{{ t('reservations.agreement.priceChanged') }}</strong>
      <dl class="agreement-amounts">
        <div>
          <dt>{{ t('reservations.agreement.rental') }}</dt>
          <dd>{{ formatCurrency(priceChange.rental) }}</dd>
        </div>
        <div v-if="priceChange.discount > 0">
          <dt>{{ t('reservations.agreement.discount') }}</dt>
          <dd>-{{ formatCurrency(priceChange.discount) }}</dd>
        </div>
        <div class="agreement-total">
          <dt>{{ t('reservations.agreement.payable') }}</dt>
          <dd data-testid="price-changed-total">{{ formatCurrency(priceChange.total) }}</dd>
        </div>
      </dl>
      <button
        type="button"
        class="agreement-confirm"
        :disabled="busy || phoneMissing"
        data-testid="confirm-new-price"
        @click="emit('confirmPrice')"
      >
        {{ t('reservations.agreement.confirmNewPrice') }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.booking-agreement {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background: var(--surface-subtle);
  color: var(--ink);
  font-size: 13px;
}
.agreement-booker {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
}
.agreement-label {
  color: var(--muted);
}
.agreement-link {
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}
.agreement-amounts {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
}
.agreement-amounts div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.agreement-amounts dt {
  color: var(--muted);
}
.agreement-amounts dd {
  margin: 0;
  font-family: var(--font-mono, monospace);
}
.agreement-total dt,
.agreement-total dd {
  color: var(--ink);
  font-weight: 800;
}
.agreement-price-change {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid rgba(198, 40, 40, 0.25);
  border-radius: 10px;
  background: var(--error-bg, rgba(198, 40, 40, 0.08));
}
.agreement-confirm {
  align-self: flex-end;
  padding: 8px 14px;
  border: none;
  border-radius: 10px;
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-weight: 700;
  cursor: pointer;
}
.agreement-confirm:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
