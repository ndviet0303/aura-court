<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import type { PriceChange } from '../types/reservation'
import { validBookerPhone } from '../utils/reservation'
import BookingVenueCreditPicker from './BookingVenueCreditPicker.vue'

// What the customer agrees to before submitting: the booker contact the booking will record (once
// signed in), the amounts (payable is the expected_total sent) and a PRICE_CHANGED awaiting confirmation.
// A null payable is a server quote still pending or failed (quoteError): no amount is shown then.
// organizationId offers the customer's venue credit there (venue-credit spec).
defineProps<{
  rental: number | null
  payable: number | null
  quoteError?: string
  priceChange?: PriceChange | null
  busy?: boolean
  organizationId?: number | null
}>()

const emit = defineEmits<{ (e: 'confirmPrice'): void; (e: 'retryQuote'): void }>()

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

    <dl v-if="payable !== null" class="agreement-amounts">
      <div>
        <dt>{{ t('reservations.agreement.rental') }}</dt>
        <dd>{{ formatCurrency(rental ?? payable) }}</dd>
      </div>
      <div v-if="rental !== null && rental > payable">
        <dt>{{ t('reservations.agreement.discount') }}</dt>
        <dd>-{{ formatCurrency(rental - payable) }}</dd>
      </div>
      <div class="agreement-total">
        <dt>{{ t('reservations.agreement.payable') }}</dt>
        <dd data-testid="agreement-payable">{{ formatCurrency(payable) }}</dd>
      </div>
    </dl>
    <div
      v-else-if="quoteError"
      class="agreement-quote-error"
      role="alert"
      data-testid="quote-error"
    >
      <span>{{ quoteError }}</span>
      <button
        type="button"
        class="agreement-confirm"
        data-testid="retry-quote"
        @click="emit('retryQuote')"
      >
        {{ t('reservations.agreement.retryQuote') }}
      </button>
    </div>
    <p v-else class="agreement-quoting" data-testid="quote-pending">
      {{ t('reservations.agreement.quoting') }}
    </p>
    <BookingVenueCreditPicker
      v-if="auth.user && organizationId"
      :organization-id="organizationId"
      :payable="payable"
    />

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
.agreement-quoting {
  margin: 0;
  color: var(--muted);
}
.agreement-quote-error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: var(--error-text, #c62828);
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
