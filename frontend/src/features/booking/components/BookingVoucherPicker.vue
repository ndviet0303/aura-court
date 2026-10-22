<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useBookingStore } from '../stores/bookingStore'

// The booking form's voucher (module 3 D10, D20): public vouchers that apply, a private code, the
// voucher dropped when the court or time changes, and a VOUCHER_INVALID at checkout. The amounts
// with it (T, D, P) are shown by BookingAgreement through useSelectionQuote.
const { t } = useI18n()
const store = useBookingStore()
const code = ref('')

function load() {
  const court = store.courts.find((c) => c.id === store.selectedCourt)
  const selection = store.selection
  store.loadVouchers(
    court?.venue?.id ?? court?.venue_id ?? null,
    court && selection
      ? { court_id: court.id, starts_at: selection.startsAt, ends_at: selection.endsAt }
      : null,
  )
}

const interval = () => `${store.selection?.startsAt}/${store.selection?.endsAt}`
watch([() => store.selectedCourt, interval], load, { immediate: true })
// Only this form sends a voucher; leaving it drops the choice.
onUnmounted(store.clearVouchers)

async function applyCode() {
  await store.enterVoucherCode(code.value)
  if (!store.voucherCodeError) code.value = ''
}
</script>

<template>
  <section class="voucher-picker" data-testid="voucher-picker">
    <span class="voucher-label">{{ t('reservations.voucher.title') }}</span>

    <p
      v-if="store.voucherDropped"
      class="voucher-dropped"
      role="status"
      data-testid="voucher-dropped"
    >
      {{ t('reservations.voucher.dropped', { voucher: store.voucherDropped }) }}
    </p>

    <div
      v-if="store.voucher?.code"
      class="voucher-option is-active"
      data-testid="voucher-code-applied"
    >
      <span>{{ store.voucher.code }} · {{ store.voucher.name }}</span>
      <strong>-{{ formatCurrency(store.voucher.discount) }}</strong>
      <button type="button" class="voucher-link" @click="store.chooseVoucher(null)">
        {{ t('reservations.voucher.remove') }}
      </button>
    </div>

    <template v-else-if="store.vouchers.length">
      <label v-for="v in store.vouchers" :key="v.id" class="voucher-option">
        <input
          type="radio"
          name="booking-voucher"
          :checked="store.voucher?.id === v.id"
          @change="store.chooseVoucher(v)"
        />
        <span>{{ v.name }}</span>
        <strong>-{{ formatCurrency(v.discount) }}</strong>
      </label>
      <label class="voucher-option">
        <input
          type="radio"
          name="booking-voucher"
          :checked="!store.voucher"
          @change="store.chooseVoucher(null)"
        />
        <span>{{ t('reservations.voucher.none') }}</span>
      </label>
    </template>

    <form class="voucher-code" @submit.prevent="applyCode">
      <input
        v-model="code"
        class="voucher-input"
        maxlength="32"
        :placeholder="t('reservations.voucher.codePlaceholder')"
        :aria-label="t('reservations.voucher.codePlaceholder')"
        data-testid="voucher-code-input"
      />
      <button type="submit" class="voucher-apply" :disabled="!code.trim()">
        {{ t('reservations.voucher.apply') }}
      </button>
    </form>
    <p v-if="store.voucherCodeError" class="voucher-error" role="alert">
      {{ store.voucherCodeError }}
    </p>

    <div
      v-if="store.voucherInvalid"
      class="voucher-invalid"
      role="alertdialog"
      aria-labelledby="voucher-invalid-title"
      data-testid="voucher-invalid"
    >
      <strong id="voucher-invalid-title">{{ t('reservations.voucher.invalidTitle') }}</strong>
      <p>{{ store.voucherInvalid.message }}</p>
      <p>
        {{
          t('reservations.voucher.withoutTotal', {
            total: formatCurrency(store.voucherInvalid.total),
          })
        }}
      </p>
      <div class="voucher-actions">
        <button type="button" class="voucher-apply" @click="store.continueWithoutVoucher">
          {{ t('reservations.voucher.continueWithout') }}
        </button>
        <button type="button" class="voucher-link" @click="store.chooseOtherVoucher">
          {{ t('reservations.voucher.chooseOther') }}
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.voucher-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.voucher-label {
  font-size: 11.5px;
  font-weight: 800;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.voucher-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  font-size: 12.5px;
  color: var(--ink);
  cursor: pointer;
}

.voucher-option span {
  flex: 1;
}

.voucher-option.is-active {
  border-color: var(--primary);
}

.voucher-code,
.voucher-actions {
  display: flex;
  gap: 8px;
}

.voucher-input {
  flex: 1;
  min-width: 0;
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  text-transform: uppercase;
}

.voucher-apply,
.voucher-link {
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.voucher-apply {
  border: 0;
  background: var(--primary);
  color: var(--on-primary);
}

.voucher-apply:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.voucher-link {
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
}

.voucher-dropped,
.voucher-error {
  margin: 0;
  font-size: 12px;
}

.voucher-dropped {
  color: var(--muted);
}

.voucher-error {
  color: #b91c1c;
}

.voucher-invalid {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  font-size: 12.5px;
}

.voucher-invalid p {
  margin: 0;
}

:global(html.dark) .voucher-error {
  color: #fca5a5;
}
</style>
